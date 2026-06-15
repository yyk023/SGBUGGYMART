<?php
// header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_seller_logged_in = isset($_SESSION['seller_id']);
$is_member_logged_in = isset($_SESSION['member_id']);

$account_display_name = '';
$account_dashboard_link = '';

if ($is_seller_logged_in) {
    $account_display_name = isset($_SESSION['seller_name']) ? $_SESSION['seller_name'] : 'Seller';
    $account_dashboard_link = '/seller/dashboard.php';
} elseif ($is_member_logged_in) {
    $account_display_name = isset($_SESSION['member_name']) ? $_SESSION['member_name'] : 'Member';
    $account_dashboard_link = '/member/dashboard.php';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGBUGGYMART</title>

    <style>
        :root {
            --primary: #0066cc;
            --primary-dark: #004f99;
            --price-red: #ef3f4d;
            --price-red-dark: #d92e3d;
            --dark: #222222;
            --muted: #757575;
            --line: #e6e6e6;
            --soft: #f6f6f6;
            --white: #ffffff;
            --shadow: 0 18px 45px rgba(0, 0, 0, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body,
        input,
        select,
        textarea,
        button,
        a,
        h1, h2, h3, h4, h5, h6,
        p, span, label, li, td, th {
            font-family: Graphik, system-ui, sans-serif;
        }

        body {
            font-family: Graphik, system-ui, sans-serif;
            font-size: 16px;
            color: var(--dark);
            background: #ffffff;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--white);
            border-bottom: 1px solid var(--line);
        }

        .header-inner {
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .header-top {
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }

        .brand-logo img {
            height: 38px;
            width: auto;
            display: block;
        }

        .header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            flex: 1;
        }

        .promo-pill {
            border: 1px solid var(--primary);
            color: var(--primary);
            border-radius: 5px;
            padding: 6px 10px;
            font-size: 11px;
            line-height: 1;
            font-weight: 800;
            white-space: nowrap;
        }

        .mini-search {
            width: 320px;
            height: 42px;
            display: flex;
            align-items: center;
            background: #f5f5f5;
            border-radius: 999px;
            overflow: hidden;
        }

        .mini-search input {
            width: 100%;
            height: 100%;
            border: 0;
            outline: 0;
            background: transparent;
            padding: 0 12px;
            font-size: 15px;
        }

        .mini-search button {
            width: 46px;
            height: 100%;
            border: 0;
            background: transparent;
            color: #333333;
            cursor: pointer;
            font-size: 18px;
        }


        .account-links {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #333333;
            font-size: 15px;
            white-space: nowrap;
            line-height: 1;
        }

        .account-links .account-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            font-size: 18px;
            line-height: 1;
            color: #4b2e83;
        }

        .account-links a {
            display: inline-flex;
            align-items: center;
            height: 20px;
            font-weight: 700;
            color: #111111;
            text-decoration: underline;
            text-decoration-color: transparent;
            text-decoration-thickness: 1px;
            text-underline-offset: 4px;
            line-height: 20px;
        }

        .account-links a:hover {
            color: inherit;
            text-decoration-color: currentColor;
        }

        .account-links .dashboard-link {
            color: #0066cc;
        }

        .account-links .dashboard-link:hover {
            color: #0066cc;
            text-decoration-color: #0066cc;
        }

        .mobile-header-actions {
            display: none;
            align-items: center;
            gap: 12px;
            margin-left: auto;
        }


        .mobile-login-pill {
            min-width: 90px;
            height: 46px;
            padding: 0 22px;
            border: 1px solid #dddddd;
            border-radius: 999px;
            background: #ffffff;
            color: #000000;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
        }

        .mobile-menu-btn {
            width: 34px;
            height: 34px;
            border: 0;
            background: transparent;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
        }

        .mobile-menu-btn span {
            width: 23px;
            height: 2px;
            background: #333333;
            border-radius: 999px;
            display: block;
        }

        .mobile-menu-panel {
            display: none;
            border-top: 1px solid #eeeeee;
            background: #ffffff;
            padding: 0;
        }

        .mobile-menu-panel.active {
            display: block;
        }

        .mobile-menu-account {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px 22px;
            border-bottom: 1px solid #dddddd;
            color: #333333;
            font-size: 15px;
            font-weight: 400;
        }

        .mobile-menu-account .user-icon {
            width: 26px;
            font-size: 22px;
            color: #333333;
        }

        .mobile-menu-account a {
            font-size: 15px;
            font-weight: 400;
        }

        .mobile-menu-account .dashboard-link {
            color: var(--price-red);
        }

        .mobile-menu-links {
            display: grid;
            gap: 0;
            padding: 8px 0 10px;
        }

        .mobile-menu-links a {
            display: block;
            padding: 16px 22px;
            color: #444444;
            font-size: 15px;
            font-weight: 400;
            border-bottom: 0;
        }

        .mobile-menu-links a:hover {
            color: var(--primary);
            background: #fafafa;
        }

        .main-nav {
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 26px;
            font-size: 16px;
            font-weight: 700;
            padding-top: 15px;
            padding-bottom: 22px;
        }

        .main-nav a:hover {
            color: var(--primary);
        }

        .header-contact-link {
            font-size: 15px;
            font-weight: 700;
            white-space: nowrap;
            margin-right: 15px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            height: 34px;
        }

        .header-contact-link:hover .rotating-text-wrap {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,102,204,0.15);
        }

        .rotating-text-wrap {
            display: inline-block;
            position: relative;
            height: 34px;
            width: 160px;
            overflow: hidden;
            background: #0066cc;
            border: 2px solid #0066cc;
            border-radius: 999px;
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
            animation: pulseRing 2.5s ease-in-out infinite;
        }

        @keyframes pulseRing {
            0%   { box-shadow: 0 0 0 0 rgba(0,102,204,0.35); }
            50%  { box-shadow: 0 0 0 6px rgba(0,102,204,0); }
            100% { box-shadow: 0 0 0 0 rgba(0,102,204,0); }
        }

        .rotating-text-item {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 34px;
            line-height: 34px;
            animation: none;
            white-space: nowrap;
            color: #ffffff;
            font-size: 13px;
            font-weight: 800;
            text-align: center;
        }

        .rotating-text-item.active {
            animation: slideUpIn 0.45s cubic-bezier(0.22,1,0.36,1) forwards;
        }

        .rotating-text-item.exit {
            animation: slideUpOut 0.45s cubic-bezier(0.22,1,0.36,1) forwards;
        }

        @keyframes slideUpIn {
            from { transform: translateY(110%); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }

        @keyframes slideUpOut {
            from { transform: translateY(0);      opacity: 1; }
            to   { transform: translateY(-110%);  opacity: 0; }
        }

        .main-nav .nav-sell-btn {
            background: #ef3f4d;
            color: #ffffff;
            padding: 7px 16px;
            border-radius: 999px;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .main-nav .nav-sell-btn:hover {
            background: #d92e3d;
            color: #ffffff;
            transform: translateY(-2px);
        }

        .hero {
            position: relative;
            min-height: 420px;
            background:
                linear-gradient(90deg, rgba(3, 14, 34, 0.88), rgba(3, 14, 34, 0.58), rgba(3, 14, 34, 0.1)),
                url('https://images.unsplash.com/photo-1596496181871-9681eacf9764?auto=format&fit=crop&w=1800&q=80') center/cover no-repeat;
        }

        .hero-inner {
            max-width: 1180px;
            margin: 0 auto;
            padding: 68px 24px 150px;
            color: var(--white);
        }

        .hero-kicker {
            display: inline-block;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .hero h1 {
            max-width: 720px;
            font-size: clamp(34px, 5vw, 62px);
            line-height: 0.98;
            letter-spacing: -2px;
            margin-bottom: 18px;
        }

        .hero p {
            max-width: 560px;
            color: rgba(255, 255, 255, 0.88);
            font-size: 17px;
            line-height: 1.6;
        }

        .search-panel {
            max-width: 920px;
            margin: -58px auto 44px;
            padding: 0 24px;
            position: relative;
            z-index: 10;
        }

        .search-card {
            background: var(--white);
            border-radius: 14px;
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
            overflow: visible;
            position: relative;
        }

        .tabs {
            width: 520px;
            min-height: 54px;
            margin: 0 auto;
            transform: translateY(-27px);
            background: #303030;
            border-radius: 999px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            padding: 7px;
            gap: 6px;
        }

        .tab {
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: #ffffff;
            padding: 9px 16px;
            font-weight: 800;
            cursor: pointer;
        }

        .tab.active {
            background: #ffffff;
            color: var(--primary);
        }

        .search-form {
            padding: 0 36px 30px;
            display: grid;
            gap: 16px;
        }

        .input-large {
            width: 100%;
            height: 42px;
            border: 1px solid #cfd3d8;
            border-radius: 999px;
            padding: 0 18px;
            font-size: 14px;
            outline: none;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
        }

        .select-control {
            height: 42px;
            border: 1px solid #cfd3d8;
            border-radius: 999px;
            padding: 0 16px;
            background: #ffffff;
            font-size: 14px;
            outline: none;
        }

        .btn-primary {
            height: 42px;
            border: 0;
            border-radius: 999px;
            background: var(--primary);
            color: #ffffff;
            padding: 0 30px;
            font-weight: 800;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .main {
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 24px 70px;
        }

        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 26px 0 20px;
        }

        .section-title {
            font-size: 22px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 22px;
            background: var(--primary);
            border-radius: 8px;
        }

        .view-all {
            color: var(--primary);
            font-size: 14px;
            font-weight: 700;
            border: 0;
            background: transparent;
            cursor: pointer;
        }

        .results-summary {
            margin-bottom: 18px;
            color: var(--muted);
            font-size: 14px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 22px;
        }

        .buggy-card {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #ffffff;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .buggy-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.1);
        }

        .card-image {
            position: relative;
            height: 165px;
            background: var(--soft);
            overflow: hidden;
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .tag {
            position: absolute;
            top: 12px;
            left: 12px;
            background: var(--primary);
            color: #ffffff;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 800;
        }

        .condition-tag {
            position: absolute;
            top: 12px;
            right: 12px;
            background: #222222;
            color: #ffffff;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .card-body {
            padding: 18px;
        }

        .card-body h3 {
            font-size: 16px;
            line-height: 1.35;
            margin-bottom: 9px;
        }

        .short-info {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
            margin-bottom: 12px;
            min-height: 36px;
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .meta span {
            background: var(--soft);
            border-radius: 999px;
            padding: 7px 9px;
            color: #5f6368;
            font-size: 12px;
            font-weight: 700;
        }

        .price-block {
            border-top: 1px solid var(--line);
            padding-top: 14px;
            display: grid;
            gap: 8px;
        }

        .sale-price {
            font-size: 20px;
            font-weight: 900;
            color: var(--price-red);
        }

        .card-actions {
            margin-top: 14px;
            display: flex;
            gap: 8px;
        }

        .outline-btn {
            flex: 1;
            text-align: center;
            border: 1px solid var(--primary);
            color: var(--primary);
            border-radius: 999px;
            padding: 9px 13px;
            font-size: 13px;
            font-weight: 800;
        }

        .whatsapp-btn {
            flex: 1;
            text-align: center;
            border: 1px solid #18a558;
            color: #18a558;
            border-radius: 999px;
            padding: 9px 13px;
            font-size: 13px;
            font-weight: 800;
        }

        .empty-state,
        .loading-state,
        .error-state {
            grid-column: 1 / -1;
            border: 1px dashed #d6d6d6;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            color: var(--muted);
            background: #fafafa;
        }

        .error-state {
            color: var(--primary);
            border-color: rgba(0, 102, 204, 0.35);
            background: #f5faff;
        }

        .footer {
            background: #151515;
            color: #ffffff;
            padding: 70px 24px 35px;
            font-size: 14px;
        }

        .footer-inner {
            max-width: 1320px;
            margin: 0 auto;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.1fr 1.1fr 1.2fr 1fr;
            gap: 70px;
        }

        .footer-col h3 {
            font-size: 17px;
            letter-spacing: 7px;
            font-weight: 800;
            margin-bottom: 26px;
            color: #ffffff;
            text-transform: uppercase;
        }

        .footer-col ul {
            list-style: none;
            display: grid;
            gap: 14px;
        }

        .footer-col a,
        .footer-col li,
        .footer-contact p {
            color: #f0f0f0;
            font-size: 14px;
            line-height: 1.3;
        }

        .footer-col a:hover {
            color: var(--primary);
        }

        .footer-contact {
            display: grid;
            gap: 12px;
        }

        .service-block {
            margin-bottom: 34px;
        }

        .service-block h4 {
            color: #ffffff;
            font-size: 16px;
            margin-bottom: 14px;
        }

        .footer-social a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ffffff;
        }

        .social-icon {
            width: 18px;
            display: inline-flex;
            justify-content: center;
            font-size: 17px;
        }

        .footer-bottom {
            max-width: 1320px;
            margin: 55px auto 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 22px;
            flex-wrap: wrap;
        }

        .footer-accept {
            letter-spacing: 4px;
            font-weight: 800;
            font-size: 14px;
        }

        .payment-icons {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .payment-badge {
            min-width: 48px;
            height: 22px;
            padding: 0 8px;
            border-radius: 3px;
            background: #ffffff;
            color: #222222;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 900;
        }

        .payment-badge.dark {
            background: #263b80;
            color: #ffffff;
        }

        .payment-badge.green {
            background: #1ba85d;
            color: #ffffff;
        }

        .payment-badge.orange {
            background: #e86f32;
            color: #ffffff;
        }

        .footer-copy {
            max-width: 1320px;
            margin: 28px auto 0;
            color: #bdbdbd;
            font-size: 13px;
            text-align: right;
        }

        @media (max-width: 1020px) {
            .main-nav {
                display: none;
            }

            .cards-grid {
                grid-template-columns: 1fr 1fr;
            }

            .filter-row {
                grid-template-columns: 1fr 1fr;
            }

            .btn-primary {
                grid-column: 1 / -1;
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 46px;
            }

            .footer-bottom,
            .footer-copy {
                justify-content: flex-start;
                text-align: left;
            }
        }

        @media (max-width: 680px) {
            .topbar {
                border-bottom: 1px solid #eeeeee;
            }

            .header-inner {
                padding: 0 20px;
            }

            .header-top {
                min-height: 68px;
                height: 68px;
                padding: 0;
                align-items: center;
                justify-content: space-between;
                flex-direction: row;
                gap: 10px;
            }

            .brand-logo {
                flex-shrink: 0;
            }

            .brand-logo img {
                height: 32px;
            }

            .header-actions {
                display: none;
            }

            .mobile-header-actions {
                display: inline-flex;
            }

            .hero-inner {
                padding: 48px 20px 132px;
            }

            .search-panel {
                margin-top: -86px;
                padding: 0 16px;
            }

            .search-form {
                padding: 0 18px 24px;
            }

            .tabs {
                width: calc(100% - 24px);
                grid-template-columns: 1fr;
                border-radius: 18px;
            }

            .filter-row,
            .cards-grid {
                grid-template-columns: 1fr;
            }

            .footer {
                padding: 46px 20px 28px;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 34px;
            }

            .footer-col h3 {
                font-size: 15px;
                letter-spacing: 5px;
                margin-bottom: 20px;
            }

            .footer-bottom {
                margin-top: 38px;
                align-items: flex-start;
                flex-direction: column;
                gap: 16px;
            }

            .payment-icons {
                gap: 10px;
            }
        }

        @media (max-width: 390px) {
            .header-inner {
                padding: 0 16px;
            }

            .brand-logo img {
                height: 26px;
            }

            .mobile-login-pill {
                min-width: 82px;
                height: 42px;
                padding: 0 17px;
                font-size: 15px;
            }

            .mobile-header-actions {
                gap: 8px;
            }

            .mobile-cart-btn {
                width: 38px;
                height: 38px;
                font-size: 21px;
            }
        }
    </style>
</head>

<body>
    <header class="topbar">
        <div class="header-inner">
            <div class="header-top">
                <a href="/index.php" class="brand-logo">
                    <img src="/images/sgbuggymart_logo.png" alt="SGBUGGYMART.COM" onerror="this.style.display='none';">
                </a>

                <div class="header-actions">
                    <a href="/contact.php" class="header-contact-link">
                        <span class="rotating-text-wrap">
                            <span class="rotating-text-item active" id="rotatingText">Contact Me</span>
                        </span>
                    </a>

                    <form class="mini-search" id="headerSearchForm" method="get" action="/allbuggy.php">
                        <input
                            type="search"
                            id="headerKeyword"
                            name="keyword"
                            placeholder="Search buggy"
                            value="<?php echo htmlspecialchars($_GET['keyword'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        >
                        <button type="submit">&#128269;</button>
                    </form>

                    <div class="account-links">
                        <span class="account-icon">&#128100;</span>

                        <?php if ($is_seller_logged_in || $is_member_logged_in): ?>
                            <a class="dashboard-link" href="<?php echo htmlspecialchars($account_dashboard_link); ?>">
                                <?php echo htmlspecialchars($account_display_name); ?>
                            </a>
                        <?php else: ?>
                            <a href="/seller/login.php">Login</a>
                            <span>|</span>
                            <a class="dashboard-link" href="/member/dashboard.php">Dashboard</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mobile-header-actions">
                    <?php if ($is_seller_logged_in || $is_member_logged_in): ?>
                        <a class="mobile-login-pill" href="<?php echo htmlspecialchars($account_dashboard_link); ?>">
                            Account
                        </a>
                    <?php else: ?>
                        <a class="mobile-login-pill" href="/seller/login.php">Login</a>
                    <?php endif; ?>

                    <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>
            </div>

            <nav class="main-nav">
                <a href="https://onstore.sgbuggymart.com/" target="_blank">Rent Buggy</a>
                <a href="/newbuggy.php" data-condition="new">New Buggy</a>
                <a href="/usedbuggy.php" data-condition="used">Used Buggy</a>
                <a href="/accessories.php">Accessories</a>
                <a href="/automotive.php">Automotive</a>
                <a href="/sell-buggy.php" class="nav-sell-btn">Sell Your Buggy</a>
            </nav>

            <div class="mobile-menu-panel" id="mobileMenuPanel">
                <div class="mobile-menu-account">
                    <span class="user-icon">&#128100;</span>

                    <?php if ($is_seller_logged_in || $is_member_logged_in): ?>
                        <a href="<?php echo htmlspecialchars($account_dashboard_link); ?>">
                            <?php echo htmlspecialchars($account_display_name); ?>
                        </a>
                    <?php else: ?>
                        <a href="/seller/login.php">Login</a>
                        <span>|</span>
                        <a class="dashboard-link" href="/member/dashboard.php">Dashboard</a>
                    <?php endif; ?>
                </div>

                <div class="mobile-menu-links">
                    <a href="https://onstore.sgbuggymart.com/" target="_blank">Rent Buggy</a>
                    <a href="/newbuggy.php">New Buggy</a>
                    <a href="/usedbuggy.php">Used Buggy</a>
                    <a href="/accessories.php">Accessories</a>
                    <a href="/automotive.php">Automotive</a>
                    <a href="/contact.php">Contact</a>
                    <a href="/sell-buggy.php">Sell Your Buggy</a>
                </div>
            </div>
        </div>
    </header>

    <script>
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenuPanel = document.getElementById('mobileMenuPanel');

        if (mobileMenuBtn && mobileMenuPanel) {
            mobileMenuBtn.addEventListener('click', function () {
                mobileMenuPanel.classList.toggle('active');
            });
        }

        // Rotating text animation
        const rotatingMessages = [
            'Contact Me',
            'For Better Price',
            'Get Best Deal!'
        ];

        let rotatingIndex = 0;
        const rotatingWrap = document.querySelector('.rotating-text-wrap');

        if (rotatingWrap) {
            // Clear any stale items on init (back/forward cache)
            rotatingWrap.innerHTML = '';
            const initItem = document.createElement('span');
            initItem.className = 'rotating-text-item active';
            initItem.textContent = rotatingMessages[0];
            rotatingWrap.appendChild(initItem);

            setInterval(function () {
                rotatingIndex = (rotatingIndex + 1) % rotatingMessages.length;

                // Slide out old item — remove after animation via setTimeout (reliable on all browsers)
                const oldItem = rotatingWrap.querySelector('.rotating-text-item');
                if (oldItem) {
                    oldItem.classList.remove('active');
                    oldItem.classList.add('exit');
                    setTimeout(function () {
                        if (oldItem.parentNode) oldItem.remove();
                    }, 500);
                }

                // Slide in new item
                const newItem = document.createElement('span');
                newItem.className = 'rotating-text-item active';
                newItem.textContent = rotatingMessages[rotatingIndex];
                rotatingWrap.appendChild(newItem);

            }, 2500);
        }
    </script>

    <script defer src="https://app.fastbots.ai/embed.js" data-bot-id="cmp59wyly01iqnl1pt48ofcms"></script>
