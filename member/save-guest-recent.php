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

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid data'
    ]);
    exit;
}

$items = array_slice($data['items'], 0, 12);

if (count($items) === 0) {
    echo json_encode([
        'success' => true,
        'message' => 'No recent views to merge'
    ]);
    exit;
}

try {
    $checkStmt = $pdo->prepare("
        SELECT id
        FROM buggies
        WHERE id = :id
        AND status = 'active'
        LIMIT 1
    ");

    $insertStmt = $pdo->prepare("
        INSERT INTO member_recent_views (member_id, buggy_id, viewed_at)
        VALUES (:member_id, :buggy_id, :viewed_at)
        ON DUPLICATE KEY UPDATE viewed_at = VALUES(viewed_at)
    ");

    $savedCount = 0;

    foreach ($items as $index => $item) {
        $buggyId = isset($item['id']) ? (int) $item['id'] : 0;

        if ($buggyId <= 0) {
            continue;
        }

        $checkStmt->execute([
            ':id' => $buggyId
        ]);

        $buggyExists = $checkStmt->fetch();

        if (!$buggyExists) {
            continue;
        }

        /*
            Keep the localStorage order.
            First item is newest.
        */
        $viewedAt = date('Y-m-d H:i:s', time() - $index);

        $insertStmt->execute([
            ':member_id' => $memberId,
            ':buggy_id' => $buggyId,
            ':viewed_at' => $viewedAt
        ]);

        $savedCount++;
    }

    echo json_encode([
        'success' => true,
        'saved' => $savedCount
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
    exit;
}