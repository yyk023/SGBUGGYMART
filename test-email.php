<?php
/*
|--------------------------------------------------------------------------
| TEMPORARY EMAIL DIAGNOSTIC — DELETE AFTER USE
|--------------------------------------------------------------------------
| Visit: https://sgbuggymart.com/test-email.php?to=your@email.com
|--------------------------------------------------------------------------
*/

// Turn on ALL error output so we see what's broken
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre style='font-family:monospace;background:#111;color:#0f0;padding:20px;'>";

// ---------- Step 1: check the PHPMailer files exist ----------
echo "STEP 1 — Checking PHPMailer files exist:\n";

$files = [
    __DIR__ . '/includes/PHPMailer/PHPMailer.php',
    __DIR__ . '/includes/PHPMailer/SMTP.php',
    __DIR__ . '/includes/PHPMailer/Exception.php',
    __DIR__ . '/includes/mailer.php',
];

$allExist = true;
foreach ($files as $f) {
    if (file_exists($f)) {
        echo "  [OK]     $f\n";
    } else {
        echo "  [MISSING] $f\n";
        $allExist = false;
    }
}

if (!$allExist) {
    echo "\nFIX: Upload the missing file(s) above, then reload this page.\n</pre>";
    exit;
}

echo "\nAll files present.\n\n";

// ---------- Step 2: load the mailer ----------
echo "STEP 2 — Loading includes/mailer.php ...\n";
require_once __DIR__ . '/includes/mailer.php';
echo "  Loaded OK.\n\n";

// ---------- Step 3: send a test email ----------
$to = $_GET['to'] ?? '';
if ($to === '') {
    echo "STEP 3 — Add ?to=your@email.com to the URL to send a test.\n</pre>";
    exit;
}

echo "STEP 3 — Sending test email to: $to\n";
echo "  SMTP Host:     " . SGBM_SMTP_HOST . "\n";
echo "  SMTP Port:     " . SGBM_SMTP_PORT . "\n";
echo "  SMTP Secure:   " . SGBM_SMTP_SECURE . "\n";
echo "  SMTP Username: " . SGBM_SMTP_USERNAME . "\n\n";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
$mail->SMTPDebug   = 3;
$mail->Debugoutput = function($str, $level) {
    echo "  [DEBUG L$level] " . htmlspecialchars(trim($str)) . "\n";
};

try {
    $mail->isSMTP();
    $mail->Host       = SGBM_SMTP_HOST;
    $mail->Port       = SGBM_SMTP_PORT;
    $mail->SMTPAuth   = true;
    $mail->Username   = SGBM_SMTP_USERNAME;
    $mail->Password   = SGBM_SMTP_PASSWORD;
    $mail->SMTPSecure = SGBM_SMTP_SECURE;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SGBM_SMTP_FROM, SGBM_SMTP_FROMNAME);
    $mail->addAddress($to);
    $mail->Subject = 'SGBUGGYMART Test Email';
    $mail->Body    = "If you can read this, SMTP works!";
    $mail->isHTML(false);

    $ok = $mail->send();
    echo "\n" . ($ok ? "SUCCESS: Email accepted by SMTP server. Check inbox." : "FAILED: " . $mail->ErrorInfo) . "\n";
} catch (Exception $e) {
    echo "\nEXCEPTION: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "ErrorInfo: " . htmlspecialchars($mail->ErrorInfo) . "\n";
}

echo "</pre>";
