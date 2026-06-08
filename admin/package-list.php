<?php
require_once '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error   = '';

if (isset($_GET['updated']) && $_GET['updated'] === '1') $message = 'Package updated successfully.';
if (isset($_GET['added'])   && $_GET['added']   === '1') $message = 'Package added successfully.';
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') $message = 'Package deleted successfully.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id     = (int)$_POST['id'];
    $action = $_POST['action'];

    if ($id > 0 && in_array($action, ['active', 'inactive'], true)) {
        try {
            $stmt = $pdo->prepare("UPDATE seller_packages SET status = ? WHERE id = ?");
            $stmt->execute([$action, $id]);
            header('Location: package-list.php?updated=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to update package status: ' . $e->getMessage();
        }
    }

    if ($id > 0 && $action === 'delete') {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sellers WHERE package_id = ?");
            $stmt->execute([$id]);
            $usedCount = (int)$stmt->fetchColumn();

            if ($usedCount > 0) {
                $error = "Cannot delete: $usedCount seller(s) are using this package. Reassign them first.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM seller_packages WHERE id = ?");
                $stmt->execute([$id]);
                header('Location: package-list.php?deleted=1');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Failed to delete package: ' . $e->getMessage();
        }
    }
}

try {
    $stmt = $pdo->query("
        SELECT p.*,
               (SELECT COUNT(*) FROM sellers WHERE package_id = p.id) AS sellers_using
        FROM seller_packages p
        ORDER BY sort_order ASC, id ASC
    ");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $packages = [];
    $error = 'Failed to load packages: ' . $e->getMessage();
}

include 'header.php';
?>

<style>
    .pkg-header {
        display: flex; align-items: center; justify-content: space-between;
        gap: 16px; margin-bottom: 22px;
    }
    .pkg-header h1 { margin: 0; font-size: 28px; }
    .pkg-header p  { margin: 6px 0 0; color: #777; font-size: 14px; }

    .pkg-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .pkg-card {
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 14px;
        padding: 22px;
        position: relative;
        transition: 0.2s ease;
    }
    .pkg-card:hover {
        border-color: #ef3f4d;
        box-shadow: 0 8px 24px rgba(0,0,0,0.06);
        transform: translateY(-2px);
    }

    .pkg-card.highlighted { border-color: #f59e0b; }
    .pkg-card.highlighted::before {
        content: '⭐ Most Popular';
        position: absolute;
        top: -10px; left: 18px;
        background: #f59e0b; color: #fff;
        padding: 4px 10px; border-radius: 999px;
        font-size: 11px; font-weight: 700;
    }

    .pkg-name { margin: 0 0 4px; font-size: 18px; font-weight: 800; color: #111; }
    .pkg-tagline { color: #777; font-size: 13px; margin: 0 0 14px; min-height: 18px; }

    .pkg-price {
        display: flex; align-items: baseline; gap: 6px;
        font-weight: 900; color: #ef3f4d;
        margin: 0 0 6px;
    }
    .pkg-price-amount { font-size: 30px; }
    .pkg-price-note { font-size: 12px; color: #777; font-weight: 500; }

    .pkg-limit {
        background: #eef6ff; color: #0066cc;
        padding: 8px 12px; border-radius: 8px;
        font-size: 13px; font-weight: 700;
        margin: 12px 0 16px;
    }

    .pkg-benefits {
        list-style: none; padding: 0; margin: 0 0 18px;
        font-size: 13px; color: #444;
    }
    .pkg-benefits li {
        padding: 5px 0 5px 22px;
        position: relative;
        line-height: 1.5;
    }
    .pkg-benefits li::before {
        content: '✓';
        position: absolute; left: 0;
        color: #16a34a; font-weight: 900;
    }

    .pkg-card-footer {
        border-top: 1px solid #f0f0f0;
        padding-top: 12px;
        display: flex; flex-direction: column; gap: 8px;
    }

    .pkg-status-row {
        display: flex; align-items: center; justify-content: space-between;
        font-size: 12px;
    }
    .pkg-status-row .badge {
        padding: 4px 10px; border-radius: 999px;
        font-size: 11px; font-weight: 700;
    }
    .badge-active   { background: #dcfce7; color: #166534; }
    .badge-inactive { background: #fee2e2; color: #991b1b; }

    .pkg-actions {
        display: flex; gap: 6px;
    }
    .pkg-actions form, .pkg-actions a {
        flex: 1;
    }
    .pkg-actions .btn-small {
        width: 100%; height: 34px; border-radius: 7px;
        font-size: 13px; font-weight: 700; border: 0;
        display: flex; align-items: center; justify-content: center;
        text-decoration: none; cursor: pointer; transition: 0.2s ease;
    }
    .btn-edit   { background: #2563eb; color: #fff; }
    .btn-edit:hover { background: #1d4ed8; }
    .btn-hide   { background: #fee2e2; color: #991b1b; }
    .btn-show   { background: #dcfce7; color: #166534; }
    .btn-delete { background: #ef3f4d; color: #fff; }
    .btn-delete:hover { background: #d92e3d; }

    .pkg-usage {
        color: #777; font-size: 12px; margin-top: 4px;
    }

    .empty {
        background: #fafafa;
        border: 1px dashed #ddd;
        border-radius: 12px;
        padding: 50px 20px;
        text-align: center;
        color: #777;
    }

    .message-success { background: #e8fff0; color: #167a3c; border: 1px solid #b8e8c8; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
    .message-error   { background: #fff0f2; color: #ef3f4d; border: 1px solid #ffc4cc; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
</style>

<div class="pkg-header">
    <div>
        <h1>Seller Packages</h1>
        <p>Manage seller subscription packages, pricing, and listing limits.</p>
    </div>
    <a href="package-form.php" class="btn">+ Add Package</a>
</div>

<?php if ($message !== ''): ?>
    <div class="message-success"><?php echo e($message); ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="message-error"><?php echo e($error); ?></div>
<?php endif; ?>

<?php if (count($packages) > 0): ?>
    <div class="pkg-grid">
        <?php foreach ($packages as $pkg):
            $benefits = !empty($pkg['benefits']) ? json_decode($pkg['benefits'], true) : [];
            if (!is_array($benefits)) $benefits = [];
            $statusVal = strtolower((string)$pkg['status']);
        ?>
            <div class="pkg-card <?php echo (int)$pkg['is_highlighted'] === 1 ? 'highlighted' : ''; ?>">
                <h3 class="pkg-name"><?php echo e($pkg['name']); ?></h3>
                <p class="pkg-tagline"><?php echo e($pkg['tagline'] ?? ''); ?></p>

                <div class="pkg-price">
                    <span class="pkg-price-amount">$<?php echo number_format((float)$pkg['amount'], 0); ?></span>
                    <span class="pkg-price-note">one-time</span>
                </div>

                <div class="pkg-limit">
                    Up to <?php echo (int)$pkg['listing_limit']; ?> buggy listing<?php echo (int)$pkg['listing_limit'] === 1 ? '' : 's'; ?>
                </div>

                <?php if (count($benefits) > 0): ?>
                    <ul class="pkg-benefits">
                        <?php foreach ($benefits as $b): ?>
                            <li><?php echo e($b); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="pkg-card-footer">
                    <div class="pkg-status-row">
                        <span class="badge <?php echo $statusVal === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                            <?php echo e(ucfirst($statusVal)); ?>
                        </span>
                        <span class="pkg-usage"><?php echo (int)$pkg['sellers_using']; ?> seller(s) using</span>
                    </div>

                    <div class="pkg-actions">
                        <a href="package-form.php?id=<?php echo (int)$pkg['id']; ?>" class="btn-small btn-edit">Edit</a>

                        <?php if ($statusVal === 'active'): ?>
                            <form method="post">
                                <input type="hidden" name="id" value="<?php echo (int)$pkg['id']; ?>">
                                <input type="hidden" name="action" value="inactive">
                                <button type="submit" class="btn-small btn-hide">Hide</button>
                            </form>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="id" value="<?php echo (int)$pkg['id']; ?>">
                                <input type="hidden" name="action" value="active">
                                <button type="submit" class="btn-small btn-show">Show</button>
                            </form>
                        <?php endif; ?>

                        <form method="post" onsubmit="return confirm('Delete this package? This cannot be undone.');">
                            <input type="hidden" name="id" value="<?php echo (int)$pkg['id']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn-small btn-delete">Del</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty">
        <p>No packages yet. Click <strong>+ Add Package</strong> to create your first package.</p>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
