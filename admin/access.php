<?php
/*
|--------------------------------------------------------------------------
| Admin Access Helpers
|--------------------------------------------------------------------------
| Session keys used:
|   $_SESSION['admin_id']
|   $_SESSION['admin_name']
|   $_SESSION['admin_role']   'super_admin' | 'seller'
|--------------------------------------------------------------------------
*/

function admin_role() {
    return $_SESSION['admin_role'] ?? 'super_admin';
}

function is_super_admin() {
    return admin_role() === 'super_admin';
}

function is_seller_admin() {
    return admin_role() === 'seller';
}

// Blocks pages that only super_admin should reach (e.g. admin account management).
function require_super_admin() {
    if (!is_super_admin()) {
        header('Location: dashboard.php?error=' . urlencode('Only the main admin can access that page.'));
        exit;
    }
}
