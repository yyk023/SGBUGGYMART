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

function sendVerificationEmail($toEmail, $toName, $token)
{
    $verifyUrl = 'https://sgbuggymart.com/seller/verify-email.php?token=' . urlencode($token);

    $subject = 'Verify Your SGBUGGYMART Seller Account Email';

    $body = "Dear " . $toName . ",\n\n"
        . "Thank you for registering as a seller on SGBUGGYMART!\n\n"
        . "Please verify your email address by clicking the link below:\n\n"
        . $verifyUrl . "\n\n"
        . "This link will expire in 24 hours.\n\n"
        . "If you did not register on SGBUGGYMART, please ignore this email.\n\n"
        . "Best regards,\n"
        . "SGBUGGYMART Team";

    $headers  = "From: noreply@sgbuggymart.com\r\n";
    $headers .= "Reply-To: noreply@sgbuggymart.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($toEmail, $subject, $body, $headers);
}

$action = $_POST['action'] ?? '';

if ($action === 'register') {
    $fullName        = trim($_POST['full_name']       ?? '');
    $contactNo       = trim($_POST['contact_no']      ?? '');
    $email           = trim($_POST['email']           ?? '');
    $address         = trim($_POST['address']         ?? '');
    $password        = $_POST['password']             ?? '';
    $confirmPassword = $_POST['confirm_password']     ?? '';

    $stickyData = [
        'old_full_name'  => $fullName,
        'old_contact_no' => $contactNo,
        'old_email'      => $email,
        'old_address'    => $address,
    ];

    if ($fullName === '')        redirectWithError('register', 'Full name is required.', $stickyData);
    if ($contactNo === '')       redirectWithError('register', 'Contact number is required.', $stickyData);
    if ($email === '')           redirectWithError('register', 'Email address is required.', $stickyData);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) redirectWithError('register', 'Please enter a valid email address.', $stickyData);
    if ($address === '')         redirectWithError('register', 'Address is required.', $stickyData);
    if ($password === '')        redirectWithError('register', 'Password is required.', $stickyData);
    if (strlen($password) < 6)  redirectWithError('register', 'Password must be at least 6 characters.', $stickyData);
    if ($password !== $confirmPassword) redirectWithError('register', 'Password and confirm password do not match.', $stickyData);

    try {
        $checkStmt = $pdo->prepare("SELECT id FROM sellers WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            redirectWithError('register', 'This email is already registered as a seller.', $stickyData);
        }

        $hashedPassword  = password_hash($password, PASSWORD_DEFAULT);
        $verifyToken     = bin2hex(random_bytes(32));
        $tokenExpiresAt  = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare("
            INSERT INTO sellers (
                full_name, contact_no, email, password, address,
                seller_status, payment_status,
                email_verified, email_verify_token, token_expires_at,
                status, created_at, updated_at
            ) VALUES (
                :full_name, :contact_no, :email, :password, :address,
                'pending_payment', 'unpaid',
                0, :token, :token_expires,
                1, NOW(), NOW()
            )
        ");

        $stmt->execute([
            ':full_name'     => $fullName,
            ':contact_no'    => $contactNo,
            ':email'         => $email,
            ':password'      => $hashedPassword,
            ':address'       => $address,
            ':token'         => $verifyToken,
            ':token_expires' => $tokenExpiresAt,
        ]);

        $sellerId = (int)$pdo->lastInsertId();

        sendVerificationEmail($email, $fullName, $verifyToken);

        redirectWithMessage('login', 'Registration successful! Please check your email to verify your account before logging in.');

    } catch (PDOException $e) {
        redirectWithError('register', 'Registration failed: ' . $e->getMessage(), $stickyData);
    }
}

if ($action === 'login') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    if ($email === '')    redirectWithError('login', 'Email address is required.');
    if ($password === '') redirectWithError('login', 'Password is required.');

    try {
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, password,
                   seller_status, payment_status,
                   email_verified, status
            FROM sellers
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $seller = $stmt->fetch();

        if (!$seller) {
            redirectWithError('login', 'Invalid email or password.');
        }

        if (!password_verify($password, $seller['password'])) {
            redirectWithError('login', 'Invalid email or password.');
        }

        if ((int)$seller['status'] !== 1) {
            redirectWithError('login', 'Your seller account is disabled. Please contact SGBUGGYMART admin.');
        }

        if ((int)$seller['email_verified'] !== 1) {
            redirectWithError('login', 'Please verify your email address first. Check your inbox for the verification link.&resend_email=' . urlencode($email));
        }

        $_SESSION['seller_id']      = (int)$seller['id'];
        $_SESSION['seller_name']    = $seller['full_name'];
        $_SESSION['seller_email']   = $seller['email'];
        $_SESSION['seller_status']  = $seller['seller_status'];
        $_SESSION['payment_status'] = $seller['payment_status'];

        if ($seller['seller_status'] === 'pending_payment')      { header('Location: payment.php');   exit; }
        if ($seller['seller_status'] === 'pending_verification') { header('Location: dashboard.php'); exit; }
        if ($seller['seller_status'] === 'active')               { header('Location: dashboard.php'); exit; }
        if ($seller['seller_status'] === 'rejected')             { header('Location: dashboard.php'); exit; }

        if ($seller['seller_status'] === 'suspended') {
            redirectWithError('login', 'Your seller account is suspended. Please contact SGBUGGYMART admin.');
        }

        header('Location: dashboard.php');
        exit;

    } catch (PDOException $e) {
        redirectWithError('login', 'Login failed: ' . $e->getMessage());
    }
}

if ($action === 'resend_verification') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        redirectWithError('login', 'Email address is required.');
    }

    try {
        $stmt = $pdo->prepare("SELECT id, full_name, email, email_verified FROM sellers WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $seller = $stmt->fetch();

        if (!$seller) {
            redirectWithError('login', 'Email not found.');
        }

        if ((int)$seller['email_verified'] === 1) {
            redirectWithMessage('login', 'Your email is already verified. Please log in.');
        }

        $newToken       = bin2hex(random_bytes(32));
        $tokenExpiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare("
            UPDATE sellers SET
                email_verify_token = ?,
                token_expires_at   = ?,
                updated_at         = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newToken, $tokenExpiresAt, $seller['id']]);

        sendVerificationEmail($seller['email'], $seller['full_name'], $newToken);

        redirectWithMessage('login', 'Verification email resent! Please check your inbox.');

    } catch (PDOException $e) {
        redirectWithError('login', 'Failed to resend verification: ' . $e->getMessage());
    }
}

header('Location: login.php');
exit;