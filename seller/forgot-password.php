<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../includes/db.php';
require_once '../includes/mailer.php';

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Always show the same friendly message whether the email exists or not
        // (prevents attackers from probing which emails are registered).
        $genericOk = 'If that email is registered, a password reset link has been sent. Please check your inbox and spam folder.';

        try {
            $stmt = $pdo->prepare("SELECT id, full_name, email FROM sellers WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $seller = $stmt->fetch();

            if ($seller) {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $upd = $pdo->prepare("UPDATE sellers SET password_reset_token=?, password_reset_expires=? WHERE id=?");
                $upd->execute([$token, $expires, $seller['id']]);

                $resetUrl = 'https://sgbuggymart.com/seller/reset-password.php?token=' . urlencode($token);

                $subject = 'SGBUGGYMART — Reset Your Seller Password';
                $body =
                    "Dear " . $seller['full_name'] . ",\n\n" .
                    "We received a request to reset the password for your SGBUGGYMART seller account.\n\n" .
                    "Click the link below to set a new password:\n\n" .
                    $resetUrl . "\n\n" .
                    "This link will expire in 1 hour.\n\n" .
                    "If you did not request a password reset, please ignore this email — your password will not change.\n\n" .
                    "Best regards,\n" .
                    "SGBUGGYMART Team";

                sgbm_send_mail($seller['email'], $seller['full_name'], $subject, $body);
            }

            $message = $genericOk;
        } catch (PDOException $e) {
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | SGBUGGYMART Seller</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/images/sgbuggymart_logo.png">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <style>
        .fp-wrap { max-width: 500px; margin: 60px auto; padding: 0 20px; }
        .fp-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:34px 30px; box-shadow:0 6px 20px rgba(0,0,0,0.05); }
        .fp-card h1 { margin:0 0 8px; font-size:22px; color:#111; }
        .fp-card p.lead { margin:0 0 20px; color:#666; font-size:14px; line-height:1.55; }
        .fp-group { margin-bottom:14px; }
        .fp-group label { display:block; font-weight:600; font-size:13px; margin-bottom:6px; color:#374151; }
        .fp-group input { width:100%; height:44px; padding:0 14px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box; }
        .fp-group input:focus { border-color:#007bff; box-shadow:0 0 0 3px rgba(0,123,255,0.12); }
        .fp-btn { width:100%; height:46px; border:0; border-radius:8px; background:#007bff; color:#fff; font-weight:700; font-size:15px; cursor:pointer; margin-top:6px; }
        .fp-btn:hover { background:#0062cc; }
        .fp-back { display:block; text-align:center; margin-top:14px; color:#007bff; text-decoration:none; font-size:14px; }
        .fp-back:hover { text-decoration:underline; }
        .fp-alert { padding:12px 14px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .fp-ok    { background:#dcfce7; border:1px solid #86efac; color:#166534; }
        .fp-err   { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="fp-wrap">
        <div class="fp-card">
            <h1>Forgot Password?</h1>
            <p class="lead">Enter the email address on your seller account. We'll send you a link to reset your password.</p>

            <?php if ($message): ?>
                <div class="fp-alert fp-ok"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="fp-alert fp-err"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="fp-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <button type="submit" class="fp-btn">Send Reset Link</button>
            </form>

            <a class="fp-back" href="login.php">← Back to Sign In</a>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
