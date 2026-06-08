<?php
session_start();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email)) {
        $message = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } else {
        $message = 'If this email exists in our system, password reset instructions will be sent later.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | YHI Mart</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', Arial, Helvetica, sans-serif;
            background: #f5f5f5;
            color: #222;
        }

        .forgot-page {
            min-height: 70vh;
            padding: 50px 15px;
            background: #f5f5f5;
        }

        .forgot-box {
            max-width: 520px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 35px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        .forgot-box h1 {
            margin: 0 0 12px;
            font-size: 26px;
            color: #222;
        }

        .forgot-box p {
            margin: 0 0 25px;
            color: #666;
            font-size: 15px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: #333;
            font-size: 14px;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            height: 43px;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 0 12px;
            font-size: 14px;
            outline: none;
            background: #fff;
        }

        .form-control:focus {
            border-color: #d71920;
        }

        .forgot-btn {
            width: 100%;
            height: 45px;
            border: none;
            border-radius: 4px;
            background: #d71920;
            color: #fff;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .forgot-btn:hover {
            background: #b9151b;
        }

        .forgot-alert {
            padding: 12px 14px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            background: #f7f7f7;
            color: #444;
            border: 1px solid #ddd;
        }

        .back-login {
            margin-top: 18px;
            text-align: center;
            font-size: 14px;
        }

        .back-login a {
            color: #d71920;
            font-weight: bold;
            text-decoration: none;
        }

        .back-login a:hover {
            text-decoration: underline;
        }

        @media (max-width: 560px) {
            .forgot-box {
                padding: 28px 22px;
            }

            .forgot-box h1 {
                font-size: 23px;
            }
        }
    </style>
</head>

<body>

<?php include '../header.php'; ?>

<section class="forgot-page">
    <div class="forgot-box">

        <h1>Forgot Password</h1>

        <p>
            Enter your registered email address. We will prepare the reset password function later.
        </p>

        <?php if (!empty($message)): ?>
            <div class="forgot-alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="forgot-password.php" method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <button type="submit" class="forgot-btn">SUBMIT</button>
        </form>

        <div class="back-login">
            <a href="login.php">Back to Sign In</a>
        </div>

    </div>
</section>

<?php include '../footer.php'; ?>

</body>
</html>