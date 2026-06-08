<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$reqUri = $_SERVER['REQUEST_URI'] ?? '';

try {
    $stmt = $pdo->prepare("
        INSERT INTO visitor_logs
        (session_id, ip_address, user_agent, page_url)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        session_id(),
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $reqUri
    ]);
} catch (PDOException $e) {
    // Keep website working even if tracking fails
}

/*
    Auto-prune visitor logs older than 90 days.
    Runs roughly once every 100 visits to keep the table small.
    Fast because the visited_at column is indexed.
*/
if (mt_rand(1, 100) === 1) {
    try {
        $pdo->exec("DELETE FROM visitor_logs WHERE visited_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    } catch (PDOException $e) {
        // Don't break the page if cleanup fails
    }
}

/* Increment per-product view counter (fast — used by admin dashboard Top Views) */
try {
    if (strpos($reqUri, 'buggy-detail.php') !== false) {
        $buggyId = (int)($_GET['id'] ?? 0);
        if ($buggyId > 0) {
            $viewStmt = $pdo->prepare("UPDATE buggies SET total_views = total_views + 1 WHERE id = ?");
            $viewStmt->execute([$buggyId]);
        }
    } elseif (strpos($reqUri, 'accessory-detail.php') !== false) {
        $accId = (int)($_GET['id'] ?? 0);
        if ($accId > 0) {
            $viewStmt = $pdo->prepare("UPDATE accessories SET total_views = total_views + 1 WHERE id = ?");
            $viewStmt->execute([$accId]);
        }
    }
} catch (PDOException $e) {
    // Don't break the page if the counter update fails
}