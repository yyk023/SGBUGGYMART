<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

function redirectWithError($mode, $error, $formData = [])
{
    $query = 'mode=' . urlencode($mode) . '&error=' . urlencode($error);
    if (!empty($formData)) {
        $query .= '&' . http_build_query($formData);
    }
    header('Location: login.php?' . $query);
    exit;
}

function redirectWithMessage($mode, $message)
{
    header('Location: login.php?mode=' . urlencode($mode) . '&message=' . urlencode($message));
    exit;
}

function sendMemberVerificationEmail($toEmail, $toName, $token)
{
    $verifyUrl = 'https://yhimart.lagenzssb.com/member/verify-email.php?token=' . urlencode($token);

    $subject = 'Verify Your SGBUGGYMART Member Account Email';

    $body = "Dear " . $toName . ",\n\n"
        . "Thank you for registering as a member on SGBUGGYMART!\n\n"
        . "Please verify your email address by clicking the link below:\n\n"
        . $verifyUrl . "\n\n"
        . "This link will expire in 24 hours.\n\n"
        . "If you did not register on SGBUGGYMART, please ignore this email.\n\n"
        . "Best regards,\n"
        . "SGBUGGYMART Team";

    $headers  = "From: noreply@yhimart.lagenzssb.com\r\n";
    $headers .= "Reply-To: noreply@yhimart.lagenzssb.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($toEmail, $subject, $body, $headers);
}

$action = $_POST['action'] ?? '';

/*
|--------------------------------------------------------------------------
| REGISTER MEMBER
|--------------------------------------------------------------------------
*/
if ($action === 'register') {

    $full_name        = trim($_POST['full_name']        ?? '');
    $gender           = trim($_POST['gender']           ?? '');
    $dob_day          = trim($_POST['dob_day']          ?? '');
    $dob_month        = trim($_POST['dob_month']        ?? '');
    $dob_year         = trim($_POST['dob_year']         ?? '');
    $contact_no       = trim($_POST['contact_no']       ?? '');
    $address          = trim($_POST['address']          ?? '');
    $email            = trim($_POST['email']            ?? '');
    $password         = $_POST['password']              ?? '';
    $confirm_password = $_POST['confirm_password']      ?? '';

    $stickyData = [
        'old_full_name'  => $full_name,
        'old_contact_no' => $contact_no,
        'old_email'      => $email,
        'old_address'    => $address,
        'old_gender'     => $gender,
        'old_dob_day'    => $dob_day,
        'old_dob_month'  => $dob_month,
        'old_dob_year'   => $dob_year,
    ];

    if (empty($full_name))        redirectWithError('register', 'Full name is required.', $stickyData);
    if (empty($gender))           redirectWithError('register', 'Gender is required.', $stickyData);
    if (empty($dob_day) || empty($dob_month) || empty($dob_year)) redirectWithError('register', 'Date of birth is required.', $stickyData);
    if (empty($contact_no))       redirectWithError('register', 'Contact number is required.', $stickyData);
    if (empty($address))          redirectWithError('register', 'Address is required.', $stickyData);
    if (empty($email))            redirectWithError('register', 'Email address is required.', $stickyData);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) redirectWithError('register', 'Please enter a valid email address.', $stickyData);
    if (empty($password))         redirectWithError('register', 'Password is required.', $stickyData);
    if (strlen($password) < 6)    redirectWithError('register', 'Password must be at least 6 characters.', $stickyData);
    if ($password !== $confirm_password) redirectWithError('register', 'Password and confirm password do not match.', $stickyData);

    try {
        $checkStmt = $pdo->prepare("SELECT id FROM members WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            redirectWithError('register', 'This email is already registered.', $stickyData);
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $verifyToken     = bin2hex(random_bytes(32));
        $tokenExpiresAt  = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare("
            INSERT INTO members
                (full_name, gender, dob_day, dob_month, dob_year, contact_no, address,
                 email, password, status, email_verified, email_verify_token, token_expires_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 0, ?, ?)
        ");

        $stmt->execute([
            $full_name, $gender, $dob_day, $dob_month, $dob_year,
            $contact_no, $address, $email, $hashed_password,
            $verifyToken, $tokenExpiresAt
        ]);

        sendMemberVerificationEmail($email, $full_name, $verifyToken);

        redirectWithMessage('login', 'Registration successful! Please check your email to verify your account before logging in.');

    } catch (PDOException $e) {
        redirectWithError('register', 'Registration failed. Please try again.', $stickyData);
    }
}

/*
|--------------------------------------------------------------------------
| LOGIN MEMBER
|--------------------------------------------------------------------------
*/
if ($action === 'login') {

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    if (empty($email))    redirectWithError('login', 'Email address is required.');
    if (empty($password)) redirectWithError('login', 'Password is required.');

    try {
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, password, status, email_verified
            FROM members
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $member = $stmt->fetch();

        if (!$member) {
            redirectWithError('login', 'Invalid email or password.');
        }

        if (!password_verify($password, $member['password'])) {
            redirectWithError('login', 'Invalid email or password.');
        }

        if ($member['status'] !== 'active') {
            redirectWithError('login', 'Your account is inactive. Please contact SGBUGGYMART.');
        }

        if ((int)$member['email_verified'] !== 1) {
            redirectWithError('login', 'Please verify your email address first. Check your inbox for the verification link.&resend_email=' . urlencode($email));
        }

        $_SESSION['member_id']    = (int)$member['id'];
        $_SESSION['member_name']  = $member['full_name'];
        $_SESSION['member_email'] = $member['email'];

        header('Location: dashboard.php');
        exit;

    } catch (PDOException $e) {
        redirectWithError('login', 'Login failed. Please try again.');
    }
}

/*
|--------------------------------------------------------------------------
| RESEND VERIFICATION EMAIL
|--------------------------------------------------------------------------
*/
if ($action === 'resend_verification') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        redirectWithError('login', 'Email address is required.');
    }

    try {
        $stmt = $pdo->prepare("SELECT id, full_name, email, email_verified FROM members WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $member = $stmt->fetch();

        if (!$member) {
            redirectWithError('login', 'Email not found.');
        }

        if ((int)$member['email_verified'] === 1) {
            redirectWithMessage('login', 'Your email is already verified. Please log in.');
        }

        $newToken       = bin2hex(random_bytes(32));
        $tokenExpiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare("
            UPDATE members SET
                email_verify_token = ?,
                token_expires_at   = ?
            WHERE id = ?
        ");
        $stmt->execute([$newToken, $tokenExpiresAt, $member['id']]);

        sendMemberVerificationEmail($member['email'], $member['full_name'], $newToken);

        redirectWithMessage('login', 'Verification email resent! Please check your inbox.');

    } catch (PDOException $e) {
        redirectWithError('login', 'Failed to resend verification. Please try again.');
    }
}

header('Location: login.php');
exit;
