<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['seller_id'])) {
    header("Location: dashboard.php");
    exit();
}

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'login';
if ($mode !== 'login' && $mode !== 'register') {
    $mode = 'login';
}

$message = isset($_GET['message']) ? $_GET['message'] : '';
$error   = isset($_GET['error'])   ? $_GET['error']   : '';

$oldFullName  = htmlspecialchars($_GET['old_full_name']  ?? '', ENT_QUOTES, 'UTF-8');
$oldContactNo = htmlspecialchars($_GET['old_contact_no'] ?? '', ENT_QUOTES, 'UTF-8');
$oldEmail     = htmlspecialchars($_GET['old_email']      ?? '', ENT_QUOTES, 'UTF-8');
$oldAddress   = htmlspecialchars($_GET['old_address']    ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Seller Login | SGBUGGYMART</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Poppins', Arial, Helvetica, sans-serif;
            background: #f5f5f5;
            color: #222;
        }

        .seller-page {
            min-height: 100vh;
            background: #f5f5f5;
            padding: 40px 15px;
        }

        .seller-container {
            max-width: 1050px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        .seller-left {
            background: #fafafa;
            padding: 40px;
            border-right: 1px solid #e5e5e5;
        }

        .seller-left h1 { margin: 0 0 15px; font-size: 30px; color: #222; }
        .seller-left p  { margin: 0 0 20px; color: #666; line-height: 1.6; font-size: 15px; }

        .seller-benefits { margin-top: 25px; padding: 0; list-style: none; }

        .seller-benefits li {
            padding: 10px 0;
            color: #444;
            font-size: 15px;
            border-bottom: 1px solid #eee;
        }

        .seller-benefits li:before {
            content: "✓ ";
            color: #0d6efd;
            font-weight: bold;
            margin-right: 8px;
        }

        .seller-right { padding: 40px; }

        .seller-tabs {
            display: flex;
            gap: 25px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
        }

        .seller-tabs a {
            text-decoration: none;
            color: #555;
            font-size: 16px;
            font-weight: bold;
            padding-bottom: 13px;
            position: relative;
        }

        .seller-tabs a.active { color: #0d6efd; }

        .seller-tabs a.active:after {
            content: "";
            position: absolute;
            left: 0; bottom: -1px;
            width: 100%; height: 3px;
            background: #0d6efd;
        }

        .seller-form-title { font-size: 22px; margin: 0 0 22px; color: #222; }

        .form-group { margin-bottom: 17px; }

        .form-group label {
            display: block; margin-bottom: 7px;
            color: #333; font-size: 14px; font-weight: bold;
        }

        .form-control {
            width: 100%; height: 43px;
            border: 1px solid #ccc; border-radius: 4px;
            padding: 0 12px; font-size: 14px;
            outline: none; background: #fff;
        }

        .form-control:focus { border-color: #0d6efd; }

        textarea.form-control { height: 95px; resize: vertical; padding-top: 10px; }

        .pdpa-consent {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin: 2px 0 22px;
            padding: 13px 14px;
            background: #f4f8ff;
            border: 1px solid #cfe2ff;
            border-radius: 6px;
            color: #444;
            font-size: 13px;
            line-height: 1.5;
        }

        .pdpa-consent input { margin-top: 3px; flex: 0 0 auto; }
        .pdpa-consent span  { display: block; }

        .form-extra {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 5px 0 20px;
            font-size: 14px;
        }

        .form-extra a       { color: #0d6efd; text-decoration: none; }
        .form-extra a:hover { text-decoration: underline; }

        .remember-me { display: flex; align-items: center; gap: 6px; color: #555; }

        .seller-btn {
            width: 100%; height: 45px; border: none; border-radius: 4px;
            background: #0d6efd; color: #fff;
            font-size: 15px; font-weight: bold;
            cursor: pointer; transition: 0.2s ease;
        }

        .seller-btn:hover { background: #0b5ed7; }

        .seller-alert {
            padding: 12px 14px; border-radius: 4px;
            margin-bottom: 20px; font-size: 14px;
        }

        .seller-alert.success { background: #eaf7ea; color: #237b23; border: 1px solid #bfe6bf; }
        .seller-alert.error   { background: #fdeaea; color: #b9151b; border: 1px solid #f1b8b8; }

        .switch-note {
            margin-top: 18px; text-align: center;
            font-size: 14px; color: #555;
        }

        .switch-note a       { color: #0d6efd; font-weight: bold; text-decoration: none; }
        .switch-note a:hover { text-decoration: underline; }

        .status-note {
            margin-top: 18px; padding: 14px; border-radius: 6px;
            background: #f4f8ff; border: 1px solid #cfe2ff;
            color: #555; font-size: 14px; line-height: 1.5;
        }

        @media (max-width: 768px) {
            .seller-container { grid-template-columns: 1fr; }
            .seller-left { border-right: none; border-bottom: 1px solid #e5e5e5; padding: 30px 25px; }
            .seller-right { padding: 30px 25px; }
            .form-extra { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>

<?php include '../header.php'; ?>

<section class="seller-page">
    <div class="seller-container">

        <div class="seller-left">
            <h1>Seller Portal</h1>
            <p>
                Register a separate seller account to post your used buggy on SGBUGGYMART.
                Seller accounts require payment verification before listing upload is unlocked.
            </p>
            <ul class="seller-benefits">
                <li>Separate account from normal member login</li>
                <li>Submit payment reference and receipt</li>
                <li>Upload up to 10 used buggy listings</li>
                <li>Listings go public after admin approval</li>
            </ul>
            <div class="status-note">
                New seller accounts start as <strong>Pending Payment</strong>.
                After payment receipt submission, SGBUGGYMART admin will verify your account.
            </div>
        </div>

        <div class="seller-right">
            <div class="seller-tabs">
                <a href="login.php?mode=login"    class="<?php echo ($mode == 'login')    ? 'active' : ''; ?>">SELLER SIGN IN</a>
                <a href="login.php?mode=register" class="<?php echo ($mode == 'register') ? 'active' : ''; ?>">SELLER REGISTER</a>
            </div>

            <?php if (!empty($message)): ?>
                <div class="seller-alert success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="seller-alert error"><?php echo htmlspecialchars($error); ?></div>
                <?php if (!empty($_GET['resend_email'])): ?>
                    <form method="post" action="seller-auth.php" style="margin-bottom:16px;">
                        <input type="hidden" name="action" value="resend_verification">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['resend_email']); ?>">
                        <button type="submit" style="width:100%;height:42px;border:1px solid #ef3f4d;background:#fff;color:#ef3f4d;border-radius:4px;font-size:14px;font-weight:bold;cursor:pointer;">
                            Resend Verification Email
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($mode == 'register'): ?>
                <h2 class="seller-form-title">Create Seller Account</h2>
                <form action="seller-auth.php" method="POST">
                    <input type="hidden" name="action" value="register">

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo $oldFullName; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Contact No.</label>
                        <input type="text" name="contact_no" class="form-control" value="<?php echo $oldContactNo; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $oldEmail; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" required><?php echo $oldAddress; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <label class="pdpa-consent">
                        <input type="checkbox" name="pdpa_acknowledgement" value="1" required>
                        <span>
                            I have read and understood that I am responsible for safeguarding my seller account login credentials.
                            SGBUGGYMART will take reasonable steps to protect personal data in accordance with applicable Singapore data
                            protection laws. I further acknowledge that, despite such measures, no internet-based system can be guaranteed
                            to be entirely free from security risks.
                        </span>
                    </label>

                    <button type="submit" class="seller-btn">CREATE SELLER ACCOUNT</button>

                    <div class="switch-note">
                        Already have a seller account?
                        <a href="login.php?mode=login">Sign in now</a>
                    </div>
                </form>

            <?php else: ?>
                <h2 class="seller-form-title">Sign In to Seller Account</h2>
                <form action="seller-auth.php" method="POST">
                    <input type="hidden" name="action" value="login">

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-extra">
                        <label class="remember-me">
                            <input type="checkbox" name="remember_me" value="1">
                            Remember me
                        </label>
                        <a href="/sell-buggy.php">Back to Sell Buggy</a>
                    </div>

                    <button type="submit" class="seller-btn">SELLER SIGN IN</button>

                    <div class="switch-note">
                        Not a seller yet?
                        <a href="login.php?mode=register">Register seller account</a>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </div>
</section>

<?php include '../footer.php'; ?>
</body>
</html>