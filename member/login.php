<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['member_id'])) {
    header('Location: dashboard.php');
    exit();
}

$mode    = isset($_GET['mode']) ? $_GET['mode'] : 'login';
if ($mode !== 'login' && $mode !== 'register') $mode = 'login';

$message = isset($_GET['message']) ? $_GET['message'] : '';
$error   = isset($_GET['error'])   ? $_GET['error']   : '';

$oldFullName  = htmlspecialchars($_GET['old_full_name']  ?? '', ENT_QUOTES, 'UTF-8');
$oldContactNo = htmlspecialchars($_GET['old_contact_no'] ?? '', ENT_QUOTES, 'UTF-8');
$oldEmail     = htmlspecialchars($_GET['old_email']      ?? '', ENT_QUOTES, 'UTF-8');
$oldAddress   = htmlspecialchars($_GET['old_address']    ?? '', ENT_QUOTES, 'UTF-8');
$oldGender    = htmlspecialchars($_GET['old_gender']     ?? '', ENT_QUOTES, 'UTF-8');
$oldDobDay    = htmlspecialchars($_GET['old_dob_day']    ?? '', ENT_QUOTES, 'UTF-8');
$oldDobMonth  = htmlspecialchars($_GET['old_dob_month']  ?? '', ENT_QUOTES, 'UTF-8');
$oldDobYear   = htmlspecialchars($_GET['old_dob_year']   ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Login | SGBUGGYMART</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }

        body { margin: 0; font-family: 'Poppins', Arial, Helvetica, sans-serif; background: #f5f5f5; color: #222; }

        .member-page { min-height: 100vh; background: #f5f5f5; padding: 40px 15px; }

        .member-container {
            max-width: 1050px; margin: 0 auto; background: #fff;
            border: 1px solid #ddd; border-radius: 4px;
            display: grid; grid-template-columns: 1fr 1.2fr;
            overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        .member-left { background: #fafafa; padding: 40px; border-right: 1px solid #e5e5e5; }
        .member-left h1 { margin: 0 0 15px; font-size: 30px; color: #222; }
        .member-left p  { margin: 0 0 20px; color: #666; line-height: 1.6; font-size: 15px; }

        .member-benefits { margin-top: 25px; padding: 0; list-style: none; }
        .member-benefits li { padding: 10px 0; color: #444; font-size: 15px; border-bottom: 1px solid #eee; }
        .member-benefits li:before { content: "✓ "; color: #0d6efd; font-weight: bold; margin-right: 8px; }

        .status-note { margin-top: 18px; padding: 14px; border-radius: 6px; background: #f4f8ff; border: 1px solid #cfe2ff; color: #555; font-size: 14px; line-height: 1.5; }

        .member-right { padding: 40px; }

        .member-tabs { display: flex; gap: 25px; border-bottom: 1px solid #ddd; margin-bottom: 30px; }
        .member-tabs a { text-decoration: none; color: #555; font-size: 16px; font-weight: bold; padding-bottom: 13px; position: relative; }
        .member-tabs a.active { color: #0d6efd; }
        .member-tabs a.active:after { content: ""; position: absolute; left: 0; bottom: -1px; width: 100%; height: 3px; background: #0d6efd; }

        .member-form-title { font-size: 22px; margin: 0 0 22px; color: #222; }

        .form-group { margin-bottom: 17px; }
        .form-group label { display: block; margin-bottom: 7px; color: #333; font-size: 14px; font-weight: bold; }

        .form-control { width: 100%; height: 43px; border: 1px solid #ccc; border-radius: 4px; padding: 0 12px; font-size: 14px; outline: none; background: #fff; }
        .form-control:focus { border-color: #0d6efd; }

        textarea.form-control { height: 85px; resize: vertical; padding-top: 10px; }

        .form-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }

        .form-extra { display: flex; justify-content: space-between; align-items: center; margin: 5px 0 20px; font-size: 14px; }
        .form-extra a { color: #0d6efd; text-decoration: none; }
        .form-extra a:hover { text-decoration: underline; }

        .remember-me { display: flex; align-items: center; gap: 6px; color: #555; }

        .member-btn { width: 100%; height: 45px; border: none; border-radius: 4px; background: #0d6efd; color: #fff; font-size: 15px; font-weight: bold; cursor: pointer; transition: 0.2s ease; }
        .member-btn:hover { background: #0b5ed7; }

        .member-alert { padding: 12px 14px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
        .member-alert.success { background: #eaf7ea; color: #237b23; border: 1px solid #bfe6bf; }
        .member-alert.error   { background: #fdeaea; color: #b9151b; border: 1px solid #f1b8b8; }

        .switch-note { margin-top: 18px; text-align: center; font-size: 14px; color: #555; }
        .switch-note a { color: #0d6efd; font-weight: bold; text-decoration: none; }
        .switch-note a:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            .member-container { grid-template-columns: 1fr; }
            .member-left { border-right: none; border-bottom: 1px solid #e5e5e5; padding: 30px 25px; }
            .member-right { padding: 30px 25px; }
            .form-row { grid-template-columns: 1fr; }
            .form-extra { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>

<?php include '../header.php'; ?>

<section class="member-page">
    <div class="member-container">

        <div class="member-left">
            <h1>Member Portal</h1>
            <p>
                Sign in to manage your profile, save your favourite buggy,
                and check your rental or purchase enquiry.
            </p>
            <ul class="member-benefits">
                <li>Manage your member profile</li>
                <li>Save favourite buggy listings</li>
                <li>Send rental or buying enquiries</li>
                <li>View your enquiry history</li>
            </ul>
            <div class="status-note">
                After registration, a verification email will be sent to your inbox. Please verify your email before logging in.
            </div>
        </div>

        <div class="member-right">
            <div class="member-tabs">
                <a href="login.php?mode=login"    class="<?php echo ($mode == 'login')    ? 'active' : ''; ?>">SIGN IN</a>
                <a href="login.php?mode=register" class="<?php echo ($mode == 'register') ? 'active' : ''; ?>">REGISTER</a>
            </div>

            <?php if (!empty($message)): ?>
                <div class="member-alert success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="member-alert error"><?php echo htmlspecialchars($error); ?></div>
                <?php if (!empty($_GET['resend_email'])): ?>
                    <form method="post" action="member-auth.php" style="margin-bottom:16px;">
                        <input type="hidden" name="action" value="resend_verification">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['resend_email']); ?>">
                        <button type="submit" style="width:100%;height:42px;border:1px solid #0d6efd;background:#fff;color:#0d6efd;border-radius:4px;font-size:14px;font-weight:bold;cursor:pointer;">
                            Resend Verification Email
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($mode == 'register'): ?>
                <h2 class="member-form-title">Create Member Account</h2>
                <form action="member-auth.php" method="POST">
                    <input type="hidden" name="action" value="register">

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo $oldFullName; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="Male"   <?php echo $oldGender === 'Male'   ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $oldGender === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Date of Birth</label>
                        <div class="form-row">
                            <select name="dob_day" class="form-control" required>
                                <option value="">Day</option>
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $oldDobDay == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="dob_month" class="form-control" required>
                                <option value="">Month</option>
                                <?php
                                $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                                foreach ($months as $month):
                                ?>
                                    <option value="<?php echo $month; ?>" <?php echo $oldDobMonth === $month ? 'selected' : ''; ?>><?php echo $month; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="dob_year" class="form-control" required>
                                <option value="">Year</option>
                                <?php for ($y = date('Y'); $y >= 1940; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $oldDobYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Contact No.</label>
                        <input type="text" name="contact_no" class="form-control" value="<?php echo $oldContactNo; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" required><?php echo $oldAddress; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $oldEmail; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <button type="submit" class="member-btn">SIGN UP</button>

                    <div class="switch-note">
                        Already have an account?
                        <a href="login.php?mode=login">Sign in now</a>
                    </div>
                </form>

            <?php else: ?>
                <h2 class="member-form-title">Sign In to Your Account</h2>
                <form action="member-auth.php" method="POST">
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
                        <a href="forgot-password.php">Forgot Password?</a>
                    </div>

                    <button type="submit" class="member-btn">SIGN IN</button>

                    <div class="switch-note">
                        Not a member?
                        <a href="login.php?mode=register">Register now</a>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </div>
</section>

<?php include '../footer.php'; ?>
</body>
</html>