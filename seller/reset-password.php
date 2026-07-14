<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../includes/db.php';

$token   = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error   = '';
$success = '';
$seller  = null;

// Validate the token
if ($token === '') {
    $error = 'Invalid or missing reset link.';
} else {
    $stmt = $pdo->prepare("SELECT id, full_name, email, password, password_reset_expires FROM sellers WHERE password_reset_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $seller = $stmt->fetch();

    if (!$seller) {
        $error = 'This reset link is invalid. Please request a new one.';
    } elseif (strtotime($seller['password_reset_expires']) < time()) {
        $error = 'This reset link has expired. Please request a new one.';
        $seller = null;
    }
}

// Handle the new password submission
if ($seller && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($password === '')                                        $error = 'Please enter a new password.';
    elseif (strlen($password) < 6)                               $error = 'Password must be at least 6 characters.';
    elseif ($password !== $confirm)                              $error = 'Passwords do not match.';
    elseif (password_verify($password, $seller['password']))     $error = 'Your new password cannot be the same as your current password. Please choose a different one.';
    else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd  = $pdo->prepare("UPDATE sellers SET password=?, password_reset_token=NULL, password_reset_expires=NULL, updated_at=NOW() WHERE id=?");
        $upd->execute([$hash, $seller['id']]);
        $success = 'Your password has been reset. You can now sign in with your new password.';
        $seller  = null; // prevent form re-showing
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | SGBUGGYMART Seller</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/images/sgbuggymart_logo.png">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <style>
        .rp-wrap { max-width: 500px; margin: 60px auto; padding: 0 20px; }
        .rp-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:34px 30px; box-shadow:0 6px 20px rgba(0,0,0,0.05); }
        .rp-card h1 { margin:0 0 8px; font-size:22px; color:#111; }
        .rp-card p.lead { margin:0 0 20px; color:#666; font-size:14px; line-height:1.55; }
        .rp-group { margin-bottom:14px; }
        .rp-group label { display:block; font-weight:600; font-size:13px; margin-bottom:6px; color:#374151; }
        .rp-group input { width:100%; height:44px; padding:0 14px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box; }
        .rp-group input:focus { border-color:#007bff; box-shadow:0 0 0 3px rgba(0,123,255,0.12); }
        .rp-btn { width:100%; height:46px; border:0; border-radius:8px; background:#007bff; color:#fff; font-weight:700; font-size:15px; cursor:pointer; margin-top:6px; }
        .rp-btn:hover { background:#0062cc; }
        .rp-back { display:block; text-align:center; margin-top:14px; color:#007bff; text-decoration:none; font-size:14px; }
        .rp-back:hover { text-decoration:underline; }
        .rp-alert { padding:12px 14px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .rp-ok  { background:#dcfce7; border:1px solid #86efac; color:#166534; }
        .rp-err { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="rp-wrap">
        <div class="rp-card">
            <h1>Reset Password</h1>

            <?php if ($success): ?>
                <div class="rp-alert rp-ok"><?php echo htmlspecialchars($success); ?></div>
                <a class="rp-back" href="login.php">← Go to Sign In</a>

            <?php elseif ($error && !$seller): ?>
                <div class="rp-alert rp-err"><?php echo htmlspecialchars($error); ?></div>
                <a class="rp-back" href="forgot-password.php">Request a new reset link</a>

            <?php elseif ($seller): ?>
                <p class="lead">Set a new password for <strong><?php echo htmlspecialchars($seller['email']); ?></strong>.</p>

                <?php if ($error): ?>
                    <div class="rp-alert rp-err"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="rp-group">
                        <label>New Password</label>
                        <input type="password" name="password" required minlength="6">
                    </div>

                    <div class="rp-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>

                    <button type="submit" class="rp-btn">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
