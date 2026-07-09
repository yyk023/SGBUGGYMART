<?php
/*
|--------------------------------------------------------------------------
| ONE-TIME HASH GENERATOR
|--------------------------------------------------------------------------
| USAGE:
|   1. Upload this file to /admin/
|   2. Visit: https://sgbuggymart.com/admin/hash-only.php
|   3. Copy the hash shown on the page
|   4. In phpMyAdmin, paste the hash into the admin's password column
|   5. DELETE this file from the server immediately.
|--------------------------------------------------------------------------
*/

$newPassword = '9ddN5~9g@O+';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Hash</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
        .box { max-width: 720px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        .warn { background: #fff3cd; border: 1px solid #ffe08a; color: #92400e; padding: 12px 14px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        code { background: #f3f4f6; padding: 12px; border-radius: 6px; font-size: 15px; display: block; word-break: break-all; margin: 10px 0; user-select: all; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🔒 Password Hash</h1>

        <div class="warn">⚠️ DELETE this file after use!</div>

        <p><strong>Plain password:</strong></p>
        <code><?php echo htmlspecialchars($newPassword); ?></code>

        <p style="margin-top:24px;"><strong>Bcrypt hash (paste this into phpMyAdmin password column):</strong></p>
        <code><?php echo htmlspecialchars($hash); ?></code>

        <p style="margin-top:24px;color:#6b7280;">Click the hash above to select all, then copy (Ctrl+C).</p>
    </div>
</body>
</html>
