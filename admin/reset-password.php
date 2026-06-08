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
$newPassword = 'admin123';
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    UPDATE admins 
    SET password = :password, status = 'active'
    WHERE email = :email
");

$stmt->execute([
    ':password' => $newHash,
    ':email' => $email
]);

echo "<pre>";
echo "Password reset successful.\n";
echo "Email: admin@yhimart.com\n";
echo "Password: admin123\n";
echo "New hash: " . $newHash . "\n";
echo "</pre>";