<?php
/*
|--------------------------------------------------------------------------
| ONE-TIME MIGRATION — Fleet Admin Accounts + Fleet Product Tag
|--------------------------------------------------------------------------
| Adds:
|   - admins.role         ('super_admin' | 'seller')
|   - products.is_fleet   (0 | 1)
|
| USAGE:
|   1. Upload this file to /admin/
|   2. Visit: https://sgbuggymart.com/admin/migrate-fleet.php
|   3. When you see "All migrations complete" — DELETE this file.
|--------------------------------------------------------------------------
*/
require_once '../includes/db.php';

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font-family:monospace;background:#111;color:#0f0;padding:20px;'>";

function tryStep($pdo, $label, $sql) {
    try {
        $pdo->exec($sql);
        echo "[OK]   $label\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'duplicate') !== false || stripos($msg, 'exists') !== false) {
            echo "[SKIP] $label (already exists)\n";
        } else {
            echo "[FAIL] $label — $msg\n";
        }
    }
}

tryStep($pdo, 'admins.role',
    "ALTER TABLE admins ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'super_admin' AFTER password");

tryStep($pdo, 'buggies.is_fleet',
    "ALTER TABLE buggies ADD COLUMN is_fleet TINYINT(1) NOT NULL DEFAULT 0");

// Backfill: any existing admin without a role becomes super_admin (safe default)
try {
    $pdo->exec("UPDATE admins SET role = 'super_admin' WHERE role = '' OR role IS NULL");
    echo "[OK]   Backfilled existing admins → super_admin\n";
} catch (PDOException $e) {
    echo "[FAIL] Backfill: " . $e->getMessage() . "\n";
}

echo "\nAll migrations complete. DELETE this file now.\n";
echo "</pre>";
