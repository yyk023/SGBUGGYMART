<?php
/*
|--------------------------------------------------------------------------
| SGBUGGYMART SMTP Mailer
|--------------------------------------------------------------------------
| Uses PHPMailer + SMTP (mail.sgbuggymart.com) to reliably send email.
| PHPMailer library is in /includes/PHPMailer/
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// -----------------------------------------------------------------------
// SMTP configuration — cPanel account: noreply@sgbuggymart.com
// -----------------------------------------------------------------------
define('SGBM_SMTP_HOST',     'mail.sgbuggymart.com');
define('SGBM_SMTP_PORT',     465);
define('SGBM_SMTP_SECURE',   'ssl');           // ssl (465) or tls (587)
define('SGBM_SMTP_USERNAME', 'noreply@sgbuggymart.com');
define('SGBM_SMTP_PASSWORD', 'p=MVk@3[{,cl');  // change here if password changes
define('SGBM_SMTP_FROM',     'noreply@sgbuggymart.com');
define('SGBM_SMTP_FROMNAME', 'SGBUGGYMART');

/**
 * Send a plain-text email via SMTP.
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $body       Plain-text body
 * @param string &$errorOut  (optional) receives the error message on failure
 * @return bool
 */
function sgbm_send_mail($toEmail, $toName, $subject, $body, &$errorOut = '')
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SGBM_SMTP_HOST;
        $mail->Port       = SGBM_SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SGBM_SMTP_USERNAME;
        $mail->Password   = SGBM_SMTP_PASSWORD;
        $mail->SMTPSecure = SGBM_SMTP_SECURE;
        $mail->CharSet    = 'UTF-8';

        // Uncomment for verbose debug output to error_log while troubleshooting
        // $mail->SMTPDebug = 2;
        // $mail->Debugoutput = 'error_log';

        $mail->setFrom(SGBM_SMTP_FROM, SGBM_SMTP_FROMNAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(SGBM_SMTP_FROM, SGBM_SMTP_FROMNAME);

        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);

        return $mail->send();
    } catch (Exception $e) {
        $errorOut = $mail->ErrorInfo ?: $e->getMessage();
        error_log('[SGBM MAIL ERROR] ' . $errorOut);
        return false;
    }
}
