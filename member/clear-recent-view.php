<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once '../includes/db.php';

if (!isset($_SESSION['member_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

$memberId = (int) $_SESSION['member_id'];

if ($memberId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid member'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        DELETE FROM member_recent_views
        WHERE member_id = :member_id
    ");

    $stmt->execute([
        ':member_id' => $memberId
    ]);

    echo json_encode([
        'success' => true
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
    exit;
}
?>