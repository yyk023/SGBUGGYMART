<?php
/*
|--------------------------------------------------------------------------
| ONE-TIME ADMIN PASSWORD UPDATE HELPER
|--------------------------------------------------------------------------
| USAGE:
|   1. Upload this file to /admin/ on the server.
|   2. Visit: https://sgbuggymart.com/admin/set-password.php
|   3. When you see "Password updated!" — DELETE this file immediately.
|
| SECURITY: DO NOT leave this file on the server. Delete after use.
|--------------------------------------------------------------------------
*/

require_once '../includes/db.php';

// New password to set
$newPassword = '9ddN5~9g@O+';

// Which admin to update (change this if your admin uses a different email/id)
$adminIdentifier = 'admin'; // set to admin email OR numeric id

$updated = false;
$foundAdmins = [];
$error = '';

try {
    // Load all admins to show the user
    $listStmt = $pdo->query("SELECT id, name, email FROM admins ORDER BY id ASC");
    $foundAdmins = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes' && !empty($_POST['admin_id'])) {
        $targetId = (int)$_POST['admin_id'];
        $newHash  = password_hash($newPassword, PASSWORD_DEFAULT);

        $updStmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $updStmt->execute([$newHash, $targetId]);

        if ($updStmt->rowCount() > 0) {
            $updated = true;
        } else {
            $error = 'No admin was updated — check the ID.';
        }
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Set Admin Password</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 40px; }
        .box { max-width: 640px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        h1 { color: #ef3f4d; margin: 0 0 10px; }
        .warn { background: #fff3cd; border: 1px solid #ffe08a; color: #92400e; padding: 12px 14px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .success { background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 14px; border-radius: 8px; font-weight: 700; margin-bottom: 18px; }
        .error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 14px; border-radius: 8px; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f9fafb; font-size: 13px; }
        .btn { background: #ef3f4d; color: #fff; padding: 12px 22px; border: 0; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #d92e3d; }
        code { background: #f3f4f6; padding: 3px 6px; border-radius: 4px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🔒 Set Admin Password</h1>

        <div class="warn">
            ⚠️ <strong>Delete this file (admin/set-password.php) immediately after use!</strong>
        </div>

        <?php if ($updated): ?>
            <div class="success">
                ✅ Admin password updated successfully!<br><br>
                New password: <code><?php echo htmlspecialchars($newPassword); ?></code><br><br>
                <strong>🚨 Delete this file from the server NOW.</strong>
            </div>
            <p><a href="login.php">Go to Admin Login →</a></p>
        <?php elseif (!empty($error)): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$updated): ?>
            <p>The new password will be set to:</p>
            <p><code style="font-size:16px;padding:8px 12px;"><?php echo htmlspecialchars($newPassword); ?></code></p>

            <p>Select which admin account to update:</p>

            <form method="post">
                <table>
                    <thead>
                        <tr><th>Select</th><th>ID</th><th>Name</th><th>Email</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($foundAdmins as $a): ?>
                            <tr>
                                <td><input type="radio" name="admin_id" value="<?php echo (int)$a['id']; ?>" required></td>
                                <td><?php echo (int)$a['id']; ?></td>
                                <td><?php echo htmlspecialchars($a['name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($a['email'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <input type="hidden" name="confirm" value="yes">
                <button type="submit" class="btn">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
