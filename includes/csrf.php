<?php
/*
|--------------------------------------------------------------------------
| CSRF Token Helper
|--------------------------------------------------------------------------
| Prevents Cross-Site Request Forgery on POST forms.
|
| Usage in a form:
|     <?php require_once 'includes/csrf.php'; ?>
|     <form method="post">
|         <?php echo csrfField(); ?>
|         ...
|     </form>
|
| Usage on the receiving page (BEFORE processing POST):
|     require_once 'includes/csrf.php';
|     if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfVerify()) {
|         http_response_code(403);
|         exit('Invalid CSRF token. Please reload the page and try again.');
|     }
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfToken()
{
    return $_SESSION['csrf_token'] ?? '';
}

function csrfField()
{
    return '<input type="hidden" name="_csrf" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function csrfVerify()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return true;
    }
    $sent     = $_POST['_csrf'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    return $sent !== '' && $expected !== '' && hash_equals($expected, $sent);
}
