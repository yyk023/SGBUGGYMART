<?php
require_once '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$email = 'admin@yhimart.com';
$testPassword = 'admin123';

$stmt = $pdo->prepare("SELECT * FROM admins WHERE email = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$admin = $stmt->fetch();

echo "<pre>";

if (!$admin) {
    echo "Admin not found.\n";
    exit;
}

echo "Admin found.\n";
echo "ID: " . $admin['id'] . "\n";
echo "Email: " . $admin['email'] . "\n";
echo "Status: " . $admin['status'] . "\n";
echo "Password from DB: " . $admin['password'] . "\n";
echo "Password length: " . strlen($admin['password']) . "\n";

if (password_verify($testPassword, $admin['password'])) {
    echo "Password verify: SUCCESS\n";
} else {
    echo "Password verify: FAILED\n";
}

echo "</pre>";