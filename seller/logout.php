<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['seller_id']);
unset($_SESSION['seller_name']);
unset($_SESSION['seller_email']);
unset($_SESSION['seller_status']);
unset($_SESSION['payment_status']);

session_regenerate_id(true);

header('Location: login.php?mode=login&message=' . urlencode('You have logged out successfully.'));
exit;