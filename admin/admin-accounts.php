<?php
require_once '../includes/db.php';
require_once 'access.php';
require_once 'header.php';   // includes session_start + login check

require_super_admin();

$flash = '';
$error = '';

/* ---------- Handle POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- CREATE ----
    if ($action === 'create') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = ($_POST['role'] ?? 'seller') === 'super_admin' ? 'super_admin' : 'seller';

        if ($name === '' || $email === '' || $password === '') {
            $error = 'Name, User ID and password are all required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $check = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'That User ID is already taken by another admin account.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins  = $pdo->prepare("
                    INSERT INTO admins (name, email, password, role, status, created_at)
                    VALUES (?, ?, ?, ?, 'active', NOW())
                ");
                $ins->execute([$name, $email, $hash, $role]);
                $flash = 'Admin account created.';
            }
        }
    }

    // ---- UPDATE ----
    elseif ($action === 'update') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = ($_POST['role'] ?? 'seller') === 'super_admin' ? 'super_admin' : 'seller';
        $status   = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($id <= 0 || $name === '' || $email === '') {
            $error = 'Name and User ID are required.';
        } else {
            // Cannot lock yourself out (demote self + inactivate self)
            if ($id === (int)$_SESSION['admin_id'] && ($role !== 'super_admin' || $status !== 'active')) {
                $error = 'You cannot demote or deactivate your own account.';
            } else {
                if ($password !== '') {
                    if (strlen($password) < 6) {
                        $error = 'Password must be at least 6 characters.';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $upd  = $pdo->prepare("UPDATE admins SET name=?, email=?, password=?, role=?, status=? WHERE id=?");
                        $upd->execute([$name, $email, $hash, $role, $status, $id]);
                        $flash = 'Admin account updated (password changed).';
                    }
                } else {
                    $upd = $pdo->prepare("UPDATE admins SET name=?, email=?, role=?, status=? WHERE id=?");
                    $upd->execute([$name, $email, $role, $status, $id]);
                    $flash = 'Admin account updated.';
                }
            }
        }
    }

    // ---- DELETE ----
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['admin_id']) {
            $error = 'You cannot delete your own account.';
        } else {
            $del = $pdo->prepare("DELETE FROM admins WHERE id = ?");
            $del->execute([$id]);
            $flash = 'Admin account deleted.';
        }
    }
}

/* ---------- Load admins ---------- */
$admins = $pdo->query("SELECT id, name, email, role, status, created_at FROM admins ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Optional edit target ---------- */
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = null;
if ($editId > 0) {
    foreach ($admins as $a) { if ((int)$a['id'] === $editId) { $editRow = $a; break; } }
}
?>
<style>
    .aa-wrap { padding: 24px; }
    .aa-title { font-size: 22px; font-weight: 700; margin: 0 0 6px; }
    .aa-sub   { color: #666; margin: 0 0 20px; font-size: 14px; }
    .aa-flash   { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-weight: 600; }
    .aa-success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
    .aa-error   { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
    .aa-grid { display: grid; grid-template-columns: 380px 1fr; gap: 22px; align-items: start; }
    @media (max-width: 980px) { .aa-grid { grid-template-columns: 1fr; } }

    .aa-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
    .aa-card h2 { margin: 0 0 14px; font-size: 16px; }

    .aa-field { margin-bottom: 12px; }
    .aa-field label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 5px; }
    .aa-field input, .aa-field select {
        width: 100%; height: 40px; padding: 0 12px; border: 1px solid #d1d5db; border-radius: 8px;
        font-size: 14px; outline: none; box-sizing: border-box;
    }
    .aa-field input:focus, .aa-field select:focus { border-color: #ef3f4d; box-shadow: 0 0 0 3px rgba(239,63,77,0.12); }
    .aa-help { font-size: 12px; color: #6b7280; margin-top: 4px; }

    .aa-btn { height: 40px; padding: 0 18px; border: 0; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px; }
    .aa-btn-primary { background: #ef3f4d; color: #fff; }
    .aa-btn-primary:hover { background: #d92e3d; }
    .aa-btn-secondary { background: #e5e7eb; color: #111; }
    .aa-btn-danger { background: #fff; color: #b91c1c; border: 1px solid #fca5a5; }
    .aa-btn-danger:hover { background: #fee2e2; }

    .aa-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .aa-table th, .aa-table td { padding: 12px 10px; border-bottom: 1px solid #f1f1f1; text-align: left; }
    .aa-table th { background: #f9fafb; font-size: 12px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.03em; }
    .aa-badge { padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 700; }
    .aa-badge-super { background: #fee2e2; color: #991b1b; }
    .aa-badge-seller { background: #dbeafe; color: #1e40af; }
    .aa-badge-active { background: #dcfce7; color: #166534; }
    .aa-badge-inactive { background: #f3f4f6; color: #6b7280; }
    .aa-actions a, .aa-actions button { margin-right: 6px; }

    .aa-pw-row { display: flex; gap: 6px; }
    .aa-pw-row input { flex: 1; font-family: 'Courier New', monospace; font-size: 13px; letter-spacing: 0.5px; }
    .aa-pw-row .aa-btn { height: 40px; padding: 0 12px; font-size: 12px; white-space: nowrap; }
    .aa-copied { background: #dcfce7 !important; color: #166534 !important; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const pwInput = document.getElementById('aaPassword');
        const genBtn  = document.getElementById('aaGenPw');
        const copyBtn = document.getElementById('aaCopyPw');

        // 16-char password using uppercase, lowercase, digits, symbols.
        // Guarantees at least one from each pool → passes any strength check.
        function generateStrongPassword(length) {
            length = length || 16;
            const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';   // no I, O for readability
            const lower = 'abcdefghijkmnpqrstuvwxyz';   // no l, o
            const digit = '23456789';                    // no 0, 1
            const sym   = '!@#$%^&*_-+=';

            const pools = [upper, lower, digit, sym];
            const all   = upper + lower + digit + sym;

            const rand = (n) => {
                const buf = new Uint32Array(1);
                window.crypto.getRandomValues(buf);
                return buf[0] % n;
            };

            let chars = pools.map(p => p[rand(p.length)]);
            for (let i = chars.length; i < length; i++) {
                chars.push(all[rand(all.length)]);
            }
            // Fisher–Yates shuffle
            for (let i = chars.length - 1; i > 0; i--) {
                const j = rand(i + 1);
                [chars[i], chars[j]] = [chars[j], chars[i]];
            }
            return chars.join('');
        }

        if (genBtn && pwInput) {
            genBtn.addEventListener('click', function () {
                pwInput.value = generateStrongPassword(8);
                pwInput.focus();
                pwInput.select();
            });
        }

        if (copyBtn && pwInput) {
            copyBtn.addEventListener('click', function () {
                if (!pwInput.value) return;
                navigator.clipboard.writeText(pwInput.value).then(function () {
                    const original = copyBtn.innerHTML;
                    copyBtn.classList.add('aa-copied');
                    copyBtn.innerHTML = '✓ Copied';
                    setTimeout(function () {
                        copyBtn.classList.remove('aa-copied');
                        copyBtn.innerHTML = original;
                    }, 1500);
                });
            });
        }
    });
</script>

<div class="aa-wrap">
    <h1 class="aa-title">Admin Accounts</h1>
    <p class="aa-sub">Create additional admin accounts. "Seller" role has admin panel access but cannot manage other admin accounts. Listings uploaded by Seller-role admins are automatically tagged as Fleet listings.</p>

    <?php if ($flash): ?><div class="aa-flash aa-success"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="aa-flash aa-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="aa-grid">
        <!-- Form: Create or Edit -->
        <div class="aa-card">
            <h2><?php echo $editRow ? 'Edit Admin Account' : 'Create Admin Account'; ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $editRow ? 'update' : 'create'; ?>">
                <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$editRow['id']; ?>">
                <?php endif; ?>

                <div class="aa-field">
                    <label>Name</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($editRow['name'] ?? ''); ?>">
                </div>

                <div class="aa-field">
                    <label>User ID</label>
                    <input type="text" name="email" required value="<?php echo htmlspecialchars($editRow['email'] ?? ''); ?>" placeholder="e.g. fleet_yhi">
                    <div class="aa-help">The login ID this admin will use. Letters, numbers, dot, underscore, dash.</div>
                </div>

                <div class="aa-field">
                    <label>Password <?php echo $editRow ? '(leave blank to keep current)' : ''; ?></label>
                    <div class="aa-pw-row">
                        <input type="text" name="password" id="aaPassword" <?php echo $editRow ? '' : 'required'; ?>>
                        <button type="button" class="aa-btn aa-btn-secondary" id="aaGenPw" title="Generate strong password">🎲 Generate</button>
                        <button type="button" class="aa-btn aa-btn-secondary" id="aaCopyPw" title="Copy to clipboard">📋 Copy</button>
                    </div>
                    <div class="aa-help">Minimum 6 characters. Click Generate for a strong 8-char password.</div>
                </div>

                <div class="aa-field">
                    <label>Role</label>
                    <select name="role">
                        <option value="seller"      <?php echo (($editRow['role'] ?? 'seller') === 'seller') ? 'selected' : ''; ?>>Seller (Fleet)</option>
                        <option value="super_admin" <?php echo (($editRow['role'] ?? '') === 'super_admin') ? 'selected' : ''; ?>>Super Admin (full access)</option>
                    </select>
                    <div class="aa-help">Seller = same panel access, but cannot manage admin accounts. Uploaded listings are auto-tagged as Fleet.</div>
                </div>

                <?php if ($editRow): ?>
                    <div class="aa-field">
                        <label>Status</label>
                        <select name="status">
                            <option value="active"   <?php echo ($editRow['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($editRow['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                <?php endif; ?>

                <button type="submit" class="aa-btn aa-btn-primary">
                    <?php echo $editRow ? 'Update Account' : 'Create Account'; ?>
                </button>
                <?php if ($editRow): ?>
                    <a href="admin-accounts.php" class="aa-btn aa-btn-secondary" style="text-decoration:none;display:inline-block;line-height:40px;">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- List -->
        <div class="aa-card">
            <h2>All Admin Accounts (<?php echo count($admins); ?>)</h2>
            <table class="aa-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>User ID</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowNum = 0; foreach ($admins as $a): $rowNum++; ?>
                        <?php $isMe = ((int)$a['id'] === (int)$_SESSION['admin_id']); ?>
                        <tr>
                            <td><?php echo $rowNum; ?></td>
                            <td><?php echo htmlspecialchars($a['name']); ?><?php echo $isMe ? ' <span style="font-size:11px;color:#6b7280;">(you)</span>' : ''; ?></td>
                            <td><?php echo htmlspecialchars($a['email']); ?></td>
                            <td>
                                <?php if (($a['role'] ?? '') === 'super_admin'): ?>
                                    <span class="aa-badge aa-badge-super">Super Admin</span>
                                <?php else: ?>
                                    <span class="aa-badge aa-badge-seller">Seller</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($a['status'] ?? 'active') === 'active'): ?>
                                    <span class="aa-badge aa-badge-active">Active</span>
                                <?php else: ?>
                                    <span class="aa-badge aa-badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="aa-actions">
                                <a class="aa-btn aa-btn-secondary" style="text-decoration:none;display:inline-block;line-height:40px;padding:0 12px;" href="admin-accounts.php?edit=<?php echo (int)$a['id']; ?>">Edit</a>
                                <?php if (!$isMe): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this admin account? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                                        <button class="aa-btn aa-btn-danger" type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
