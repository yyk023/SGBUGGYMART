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
    <title>Admin Login - YHI Buggy Mart</title>
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-box {
            width: 380px;
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }
        h2 {
            margin-bottom: 20px;
        }
        input {
            width: 100%;
            height: 42px;
            margin-bottom: 14px;
            padding: 0 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        button {
            width: 100%;
            height: 42px;
            border: 0;
            border-radius: 8px;
            background: #ef3f4d;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }
        .error {
            color: #ef3f4d;
            margin-bottom: 14px;
        }
    </style>
</head>
<body>
    <form class="login-box" method="post">
        <h2>Admin Login</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <input type="text" name="email" placeholder="Admin ID / Email" required>
        <input type="password" name="password" placeholder="Password" required>

        <button type="submit">Login</button>
    </form>
</body>
</html>