<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);

$productPages = [
    'product-list.php',
    'product-form.php',
    'product-edit.php',
    'product-view.php',
    'product-brand.php'
];

$sellerPages = [
    'seller-list.php',
    'seller-buggy-list.php'
];

$accessoryPages = [
    'accessory-list.php',
    'accessory-form.php',
];

$isProductPage   = in_array($currentPage, $productPages, true);
$isSellerPage    = in_array($currentPage, $sellerPages, true);
$isAccessoryPage = in_array($currentPage, $accessoryPages, true);
$bannerPages = ['banner-list.php', 'banner-form.php'];

$isProductListPage = in_array($currentPage, [
    'product-list.php',
    'product-form.php',
    'product-edit.php',
    'product-view.php'
], true);

$isProductBrandPage = $currentPage === 'product-brand.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGBUGGYMART Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #ef3f4d;
            --primary-dark: #d92e3d;
            --buggy-blue: #007bff;
            --dark: #20242a;
            --bg: #f5f6f8;
            --white: #ffffff;
            --border: #e5e5e5;
            --muted: #777777;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: 'Poppins', Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: #222;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 270px;
            min-height: 100vh;
            background: var(--dark);
            color: #fff;
            padding: 24px 20px;
            flex-shrink: 0;
            transition: width 0.25s ease, padding 0.25s ease;
        }

        .sidebar-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 36px;
        }

        .admin-logo {
            margin: 0;
            line-height: 1.15;
            white-space: normal;
            max-width: 175px;
        }

        .admin-logo .brand-line {
            display: block;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.2px;
            color: #ffffff;
            word-break: keep-all;
        }

        .admin-logo .buggy-blue {
            color: #1ca0e6;
        }

        .admin-logo .admin-line {
            display: block;
            margin-top: 4px;
            font-size: 14px;
            font-weight: 700;
            color: var(--primary);
        }

        .sidebar-toggle {
            width: 38px;
            min-width: 38px;
            height: 38px;
            border: 0;
            border-radius: 9px;
            background: var(--primary);
            color: #ffffff;
            font-size: 22px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle:hover {
            background: var(--primary-dark);
        }

        .admin-menu {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .admin-menu a,
        .menu-parent {
            width: 100%;
            min-height: 54px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 16px;
            border-radius: 10px;
            color: #ffffff;
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            background: transparent;
            border: 0;
            cursor: pointer;
            text-align: left;
            font-family: inherit;
            transition: 0.2s ease;
            appearance: none;
            -webkit-appearance: none;
        }

        .admin-menu a:hover,
        .admin-menu a.active,
        .menu-parent:hover,
        .menu-group.open > .menu-parent {
            background: var(--primary);
            color: #ffffff;
        }

        .menu-label {
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }

        .menu-icon {
            width: 22px;
            min-width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
        }

        .menu-arrow {
            font-size: 13px;
            margin-left: auto;
            transition: transform 0.2s ease;
        }

        .menu-group.open .menu-arrow {
            transform: rotate(180deg);
        }

        .submenu {
            display: none;
            flex-direction: column;
            gap: 6px;
            margin-top: 6px;
            margin-left: 28px;
        }

        .menu-group.open .submenu {
            display: flex;
        }

        .submenu a {
            min-height: 44px;
            padding: 12px 16px;
            font-size: 15px;
            border-radius: 9px;
            background: transparent;
        }

        .submenu a:hover,
        .submenu a.active {
            background: var(--primary);
            color: #ffffff;
        }

        .admin-layout.sidebar-collapsed .admin-sidebar {
            width: 82px;
            padding-left: 14px;
            padding-right: 14px;
        }

        .admin-layout.sidebar-collapsed .sidebar-top {
            justify-content: center;
            margin-bottom: 36px;
        }

        .admin-layout.sidebar-collapsed .admin-logo {
            display: none;
        }

        .admin-layout.sidebar-collapsed .sidebar-toggle {
            background: var(--primary);
        }

        .admin-layout.sidebar-collapsed .sidebar-toggle:hover {
            background: var(--primary-dark);
        }

        .admin-layout.sidebar-collapsed .admin-menu a,
        .admin-layout.sidebar-collapsed .menu-parent {
            justify-content: center;
            padding: 15px 10px;
            position: relative;
        }

        .admin-layout.sidebar-collapsed .menu-label {
            justify-content: center;
            gap: 0;
        }

        .admin-layout.sidebar-collapsed .menu-label span:not(.menu-icon) {
            display: none;
        }

        .admin-layout.sidebar-collapsed .menu-arrow {
            display: none;
        }

        .admin-layout.sidebar-collapsed .menu-icon {
            font-size: 20px;
        }

        /* Expand button on collapsed menu-parent (top right corner) */
        .collapsed-expand-btn {
            display: none;
            position: absolute;
            top: 2px;
            right: 2px;
            width: 20px;
            height: 20px;
            background: var(--primary);
            color: #fff;
            border: 0;
            border-radius: 50%;
            font-size: 13px;
            font-weight: bold;
            line-height: 20px;
            text-align: center;
            cursor: pointer;
            z-index: 5;
            box-shadow: 0 2px 6px rgba(0,0,0,0.4);
            transition: background 0.2s ease, transform 0.2s ease;
            padding: 0;
            user-select: none;
        }

        .collapsed-expand-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.2);
        }

        .admin-layout.sidebar-collapsed .menu-group .menu-parent .collapsed-expand-btn {
            display: block;
        }

        /* Floating popout submenu when collapsed */
        .admin-layout.sidebar-collapsed .submenu {
            display: none;
        }

        .admin-layout.sidebar-collapsed .menu-group.flyout-open .submenu {
            display: flex !important;
            position: absolute;
            left: 78px;
            top: 0;
            min-width: 200px;
            background: var(--dark);
            border-radius: 10px;
            padding: 8px;
            margin: 0;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            z-index: 1000;
            flex-direction: column;
            gap: 4px;
        }

        .admin-layout.sidebar-collapsed .menu-group {
            position: relative;
        }

        /* Override: collapsed sidebar rules should NOT affect flyout submenu items */
        .admin-layout.sidebar-collapsed .menu-group.flyout-open .submenu a {
            justify-content: flex-start !important;
            padding: 11px 14px !important;
            min-height: 40px;
            font-size: 14px;
            text-align: left;
            width: 100%;
            border-radius: 8px;
            display: flex;
            align-items: center;
        }

        .admin-layout.sidebar-collapsed .menu-group.flyout-open > .menu-parent .collapsed-expand-btn {
            transform: rotate(180deg);
            background: var(--primary-dark);
        }

        .admin-main {
            flex: 1;
            padding: 28px;
            overflow-x: auto;
            position: relative;
        }

        .admin-page-title {
            margin-bottom: 22px;
        }

        .admin-page-title h1 {
            margin: 0;
            font-size: 28px;
        }

        .admin-page-title p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .card {
            background: var(--white);
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 8px 22px rgba(0,0,0,0.04);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: #fff;
            border: 0;
            border-radius: 9px;
            padding: 11px 18px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .btn:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: #333;
        }

        .btn-secondary:hover {
            background: #111;
        }

        input,
        select,
        textarea {
            width: 100%;
            min-height: 42px;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0 12px;
            font-size: 14px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 110px;
            padding-top: 12px;
            resize: vertical;
        }

        label {
            font-weight: bold;
            font-size: 14px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .full {
            grid-column: 1 / -1;
        }

        .section-title {
            grid-column: 1 / -1;
            margin-top: 12px;
            padding-top: 18px;
            border-top: 1px solid #eee;
            font-size: 18px;
            font-weight: bold;
        }

        .message-success {
            background: #e8fff0;
            color: #167a3c;
            border: 1px solid #b8e8c8;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .message-error {
            background: #fff0f2;
            color: var(--primary);
            border: 1px solid #ffc4cc;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .help {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.4;
        }

        .back-to-top {
            position: fixed;
            right: 24px;
            bottom: 24px;
            width: 46px;
            height: 46px;
            border: 0;
            border-radius: 50%;
            background: var(--primary);
            color: #ffffff;
            font-size: 26px;
            font-weight: bold;
            cursor: pointer;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transform: translateY(12px);
            transition: 0.25s ease;
            box-shadow: 0 8px 22px rgba(0,0,0,0.18);
        }

        .back-to-top.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .back-to-top:hover {
            background: var(--primary-dark);
        }

        @media (max-width: 800px) {
            .admin-layout {
                display: block;
            }

            .admin-sidebar {
                width: 100%;
                min-height: auto;
            }

            .admin-logo {
                max-width: none;
            }

            .admin-logo .brand-line {
                font-size: 20px;
            }

            .admin-logo .admin-line {
                font-size: 14px;
            }

            .admin-layout.sidebar-collapsed .admin-sidebar {
                width: 100%;
            }

            .admin-layout.sidebar-collapsed .sidebar-top {
                justify-content: space-between;
            }

            .admin-layout.sidebar-collapsed .admin-logo {
                display: block;
            }

            .admin-layout.sidebar-collapsed .menu-label {
                justify-content: flex-start;
                gap: 10px;
            }

            .admin-layout.sidebar-collapsed .menu-label span:not(.menu-icon) {
                display: inline;
            }

            .admin-layout.sidebar-collapsed .menu-arrow {
                display: inline;
            }

            .admin-main {
                padding: 18px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full,
            .section-title {
                grid-column: auto;
            }

            .submenu {
                margin-left: 18px;
            }

            .back-to-top {
                right: 16px;
                bottom: 16px;
                width: 42px;
                height: 42px;
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<div class="admin-layout">

    <aside class="admin-sidebar">
        <div class="sidebar-top">
            <div class="admin-logo">
                <span class="brand-line">
                    SG<span class="buggy-blue">BUGGY</span>MART
                </span>
                <span class="admin-line">Admin</span>
            </div>

            <button type="button" class="sidebar-toggle" id="sidebarToggle" title="Toggle sidebar">
                ☰
            </button>
        </div>

        <nav class="admin-menu">

            <a href="dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="menu-label">
                    <span class="menu-icon">🏠</span>
                    <span>Dashboard</span>
                </span>
            </a>

            <div class="menu-group <?php echo $isProductPage ? 'open' : ''; ?>">
                <button type="button" class="menu-parent">
                    <span class="menu-label">
                        <span class="menu-icon">🛒</span>
                        <span>Product</span>
                    </span>
                    <span class="menu-arrow">▾</span>
                    <span role="button" class="collapsed-expand-btn" title="Open menu">›</span>
                </button>

                <div class="submenu">
                    <a href="product-list.php" class="<?php echo $isProductListPage ? 'active' : ''; ?>">
                        Product List
                    </a>

                    <a href="product-brand.php" class="<?php echo $isProductBrandPage ? 'active' : ''; ?>">
                        Product Brand
                    </a>
                </div>
            </div>

            <div class="menu-group <?php echo $isSellerPage ? 'open' : ''; ?>">
                <button type="button" class="menu-parent">
                    <span class="menu-label">
                        <span class="menu-icon">👤</span>
                        <span>Seller</span>
                    </span>
                    <span class="menu-arrow">▾</span>
                    <span role="button" class="collapsed-expand-btn" title="Open menu">›</span>
                </button>

                <div class="submenu">
                    <a href="seller-list.php" class="<?php echo $currentPage === 'seller-list.php' ? 'active' : ''; ?>">
                        Seller Approval
                    </a>

                    <a href="seller-buggy-list.php" class="<?php echo $currentPage === 'seller-buggy-list.php' ? 'active' : ''; ?>">
                        Seller Buggy Approval
                    </a>
                </div>
            </div>
            
            <a href="package-list.php" class="<?php echo in_array($currentPage, ['package-list.php','package-form.php']) ? 'active' : ''; ?>">
                <span class="menu-label">
                    <span class="menu-icon">📦</span>
                    <span>Packages</span>
                </span>
            </a>

               <a href="banner-list.php" class="<?php echo in_array($currentPage, ['banner-list.php','banner-form.php']) ? 'active' : ''; ?>">
                <span class="menu-label">
                    <span class="menu-icon">🖼️</span>
                    <span>Banners</span>
                </span>
            </a>
            
            <div class="menu-group <?php echo $isAccessoryPage ? 'open' : ''; ?>">
                <button type="button" class="menu-parent">
                    <span class="menu-label">
                        <span class="menu-icon">🔧</span>
                        <span>Accessories</span>
                    </span>
                    <span class="menu-arrow">▾</span>
                    <span role="button" class="collapsed-expand-btn" title="Open menu">›</span>
                </button>

                <div class="submenu">
                    <a href="accessory-list.php" class="<?php echo $currentPage === 'accessory-list.php' ? 'active' : ''; ?>">
                        Accessory List
                    </a>
                </div>
            </div>

            <a href="../index.php" target="_blank">
                <span class="menu-label">
                    <span class="menu-icon">🌐</span>
                    <span>View Website</span>
                </span>
            </a>

        </nav>
    </aside>

    <main class="admin-main">

        <button type="button" class="back-to-top" id="backToTop" title="Back to top">
            ↑
        </button>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const adminLayout = document.querySelector('.admin-layout');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const backToTop = document.getElementById('backToTop');

    if (localStorage.getItem('adminSidebarCollapsed') === 'yes') {
        adminLayout.classList.add('sidebar-collapsed');
    }

    document.querySelectorAll('.menu-parent').forEach(function (button) {
        button.addEventListener('click', function (e) {
            // If click was on the small expand button — let its own handler take over
            if (e.target.closest('.collapsed-expand-btn')) {
                return;
            }

            if (adminLayout.classList.contains('sidebar-collapsed')) {
                return;
            }

            const group = this.closest('.menu-group');
            group.classList.toggle('open');
        });
    });

    // Small expand button (visible only in collapsed mode) — toggles floating popout
    document.querySelectorAll('.collapsed-expand-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            e.preventDefault();

            const group = this.closest('.menu-group');
            if (!group) return;

            // Close other open flyouts
            document.querySelectorAll('.menu-group.flyout-open').forEach(function (g) {
                if (g !== group) g.classList.remove('flyout-open');
            });

            group.classList.toggle('flyout-open');
        });
    });

    // Click outside closes any open flyout
    document.addEventListener('click', function (e) {
        if (e.target.closest('.menu-group')) return;
        document.querySelectorAll('.menu-group.flyout-open').forEach(function (g) {
            g.classList.remove('flyout-open');
        });
    });

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            adminLayout.classList.toggle('sidebar-collapsed');

            if (adminLayout.classList.contains('sidebar-collapsed')) {
                localStorage.setItem('adminSidebarCollapsed', 'yes');
            } else {
                localStorage.setItem('adminSidebarCollapsed', 'no');
            }
        });
    }

    function toggleBackToTopButton() {
        if (!backToTop) {
            return;
        }

        if (window.scrollY > 250) {
            backToTop.classList.add('show');
        } else {
            backToTop.classList.remove('show');
        }
    }

    window.addEventListener('scroll', toggleBackToTopButton);
    toggleBackToTopButton();

    if (backToTop) {
        backToTop.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
});
</script>