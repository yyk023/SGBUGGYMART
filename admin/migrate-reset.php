<?php
/*
|--------------------------------------------------------------------------
| ONE-TIME MIGRATION — Password Reset Columns for sellers
|--------------------------------------------------------------------------
| Adds:
|   - sellers.password_reset_token
|   - sellers.password_reset_expires
|
| USAGE:
|   1. Upload to /admin/
|   2. Visit: https://sgbuggymart.com/admin/migrate-reset.php
|   3. DELETE this file after "All migrations complete".
|--------------------------------------------------------------------------
*/
require_once '../includes/db.php';
header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font-family:monospace;background:#111;color:#0f0;padding:20px;'>";

function tryStep($pdo, $label, $sql) {
    try { $pdo->exec($sql); echo "[OK]   $label\n"; }
    catch (PDOException $e) {
        $m = $e->getMessage();
        if (stripos($m, 'duplicate') !== false || stripos($m, 'exists') !== false) echo "[SKIP] $label (already exists)\n";
        else echo "[FAIL] $label — $m\n";
    }
}

tryStep($pdo, 'sellers.password_reset_token',
    "ALTER TABLE sellers ADD COLUMN password_reset_token VARCHAR(64) NULL");
tryStep($pdo, 'sellers.password_reset_expires',
    "ALTER TABLE sellers ADD COLUMN password_reset_expires DATETIME NULL");

echo "\nAll migrations complete. DELETE this file now.\n</pre>";
