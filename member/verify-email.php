<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

$token   = trim($_GET['token'] ?? '');
$status  = '';
$message = '';

if ($token === '') {
    $status  = 'error';
    $message = 'Invalid verification link.';
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, email_verified, token_expires_at
            FROM members
            WHERE email_verify_token = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $member = $stmt->fetch();

        if (!$member) {
            $status  = 'error';
            $message = 'Invalid or expired verification link.';
        } elseif ((int)$member['email_verified'] === 1) {
            $status  = 'already';
            $message = 'Your email is already verified. You can log in now.';
        } elseif (strtotime($member['token_expires_at']) < time()) {
            $status  = 'expired';
            $message = 'This verification link has expired. Please request a new one.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE members SET
                    email_verified     = 1,
                    email_verify_token = NULL,
                    token_expires_at   = NULL
                WHERE id = ?
            ");
            $stmt->execute([$member['id']]);

            $status  = 'success';
            $message = 'Your email has been verified successfully! You can now log in.';
        }
    } catch (PDOException $e) {
        $status  = 'error';
        $message = 'Verification failed. Please try again.';
    }
}

include '../header.php';
?>

<style>
    .verify-page {
        min-height: 70vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f5f5f5;
        padding: 40px 20px;
    }

    .verify-box {
        background: #ffffff;
        border-radius: 16px;
        padding: 48px 42px;
        max-width: 480px;
        width: 100%;
        text-align: center;
        box-shadow: 0 10px 32px rgba(0,0,0,0.08);
    }

    .verify-icon { font-size: 64px; margin-bottom: 20px; display: block; }

    .verify-box h1 { margin: 0 0 14px; font-size: 26px; color: #222; }
    .verify-box p  { margin: 0 0 24px; color: #666; font-size: 15px; line-height: 1.6; }

    .verify-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 46px;
        padding: 0 28px;
        border-radius: 8px;
        background: #0d6efd;
        color: #ffffff;
        font-size: 15px;
        font-weight: 700;
        text-decoration: none;
        transition: 0.2s ease;
        border: none;
        cursor: pointer;
        width: 100%;
    }

    .verify-btn:hover { background: #0b5ed7; color: #fff; }

    .verify-btn-outline {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 46px;
        padding: 0 28px;
        border-radius: 8px;
        border: 1px solid #0d6efd;
        background: #fff;
        color: #0d6efd;
        font-size: 15px;
        font-weight: 700;
        text-decoration: none;
        margin-top: 12px;
        transition: 0.2s ease;
        width: 100%;
        box-sizing: border-box;
    }

    .verify-btn-outline:hover { background: #0d6efd; color: #fff; }
</style>

<div class="verify-page">
    <div class="verify-box">
        <?php if ($status === 'success'): ?>
            <span class="verify-icon">✅</span>
            <h1>Email Verified!</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="login.php?mode=login" class="verify-btn">Login Now</a>

        <?php elseif ($status === 'already'): ?>
            <span class="verify-icon">✅</span>
            <h1>Already Verified</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="login.php?mode=login" class="verify-btn">Login Now</a>

        <?php elseif ($status === 'expired'): ?>
            <span class="verify-icon">⏰</span>
            <h1>Link Expired</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <form method="post" action="member-auth.php" style="margin-top:8px;">
                <input type="hidden" name="action" value="resend_verification">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>">
                <button type="submit" class="verify-btn">Resend Verification Email</button>
            </form>
            <a href="login.php?mode=register" class="verify-btn-outline">Back to Register</a>

        <?php else: ?>
            <span class="verify-icon">❌</span>
            <h1>Verification Failed</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="login.php?mode=register" class="verify-btn">Back to Register</a>
        <?php endif; ?>
    </div>
</div>

<?php include '../footer.php'; ?>
