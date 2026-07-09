<?php
session_start();
require_once '../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = :email AND status = 'active' LIMIT 1");
    $stmt->execute([':email' => $email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - SGBUGGYMART</title>
    <link rel="icon" type="image/png" href="/images/sgbuggymart_logo.png">
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-box {
            width: 400px;
            background: white;
            padding: 36px 34px 32px;
            border-radius: 14px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            text-align: center;
        }
        .login-logo {
            display: block;
            margin: 0 auto 18px;
            height: 52px;
            width: auto;
        }
        h2 {
            margin: 0 0 6px;
            font-size: 22px;
            color: #111827;
        }
        .login-sub {
            margin: 0 0 24px;
            color: #6b7280;
            font-size: 13px;
        }
        input {
            width: 100%;
            height: 44px;
            margin-bottom: 14px;
            padding: 0 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            transition: 0.2s ease;
        }
        input:focus {
            border-color: #ef3f4d;
            box-shadow: 0 0 0 3px rgba(239, 63, 77, 0.12);
        }
        button {
            width: 100%;
            height: 44px;
            border: 0;
            border-radius: 8px;
            background: #ef3f4d;
            color: white;
            font-weight: bold;
            font-size: 15px;
            cursor: pointer;
            transition: 0.2s ease;
            margin-top: 4px;
        }
        button:hover {
            background: #d92e3d;
            transform: translateY(-1px);
        }
        .error {
            color: #ef3f4d;
            margin-bottom: 14px;
            font-size: 13px;
            background: #fff0f2;
            border: 1px solid #ffc4cc;
            border-radius: 6px;
            padding: 10px;
        }
    </style>
</head>
<body>
    <form class="login-box" method="post">
        <img src="/images/sgbuggymart_logo.png" alt="SGBUGGYMART" class="login-logo" onerror="this.style.display='none';">

        <h2>Admin Login</h2>
        <p class="login-sub">Sign in to access the admin panel</p>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <input type="text" name="email" placeholder="Admin ID / Email" required>
        <input type="password" name="password" placeholder="Password" required>

        <button type="submit">Login</button>
    </form>
</body>
</html>