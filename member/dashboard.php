<?php
session_start();

require_once '../includes/db.php';

$is_logged_in = isset($_SESSION['member_id']);

$member_id = $is_logged_in ? (int) $_SESSION['member_id'] : 0;
$member_name = $is_logged_in && isset($_SESSION['member_name']) ? $_SESSION['member_name'] : 'Guest';
$member_email = $is_logged_in && isset($_SESSION['member_email']) ? $_SESSION['member_email'] : '';

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$recent_type = isset($_GET['type']) ? $_GET['type'] : 'used';

if ($tab !== 'dashboard' && $tab !== 'recent') {
    $tab = 'dashboard';
}

if ($recent_type !== 'used' && $recent_type !== 'new') {
    $recent_type = 'used';
}

$recentViews = [];

function dashboardImagePath($imageUrl)
{
    $imageUrl = trim((string) $imageUrl);

    if ($imageUrl === '') {
        return '../images/no-image.jpg';
    }

    if (preg_match('/^https?:\/\//i', $imageUrl)) {
        return $imageUrl;
    }

    if (strpos($imageUrl, '../') === 0) {
        return $imageUrl;
    }

    if (strpos($imageUrl, 'images/') === 0) {
        return '../' . $imageUrl;
    }

    return '../images/' . $imageUrl;
}

function listingTypeText($listingType)
{
    if ($listingType === 'sale') {
        return 'For Sale';
    }

    if ($listingType === 'rent') {
        return 'For Rent';
    }

    if ($listingType === 'sale_rent' || $listingType === 'both') {
        return 'For Sale & Rent';
    }

    return ucfirst((string) $listingType);
}

if ($tab === 'recent' && $is_logged_in) {
    try {
        $recentStmt = $pdo->prepare("
            SELECT 
                rv.viewed_at,
                b.id,
                b.brand,
                b.model,
                b.name,
                b.seats,
                b.listing_type,
                b.buggy_condition,
                b.selling_price,
                b.rent_price_1_day,
                b.image_url,
                b.status
            FROM member_recent_views rv
            INNER JOIN buggies b ON rv.buggy_id = b.id
            WHERE rv.member_id = :member_id
            AND b.status = 'active'
            AND b.buggy_condition = :buggy_condition
            ORDER BY rv.viewed_at DESC
            LIMIT 12
        ");

        $recentStmt->execute([
            ':member_id' => $member_id,
            ':buggy_condition' => $recent_type
        ]);

        $recentViews = $recentStmt->fetchAll();
    } catch (PDOException $e) {
        $recentViews = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Dashboard | YHI Buggy Mart</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', Arial, Helvetica, sans-serif;
            background: #ffffff;
            color: #222222;
        }

        .member-dashboard-page {
            background: #ffffff;
            padding: 38px 15px 70px;
            min-height: 70vh;
        }

        .member-dashboard-wrap {
            max-width: 1180px;
            margin: 0 auto;
        }

        .dashboard-breadcrumb {
            font-size: 13px;
            color: #999999;
            margin-bottom: 18px;
        }

        .dashboard-breadcrumb a {
            color: #777777;
            text-decoration: none;
        }

        .dashboard-breadcrumb span {
            margin: 0 8px;
            color: #bbbbbb;
        }

        .dashboard-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 30px;
            align-items: start;
        }

        .dashboard-sidebar {
            background: #ffffff;
            border: 1px solid #dddddd;
            border-radius: 14px;
            padding: 26px 18px;
        }

        .dashboard-sidebar h2 {
            margin: 0 0 8px;
            font-size: 18px;
            color: #222222;
        }

        .dashboard-sidebar p {
            margin: 0 0 24px;
            font-size: 14px;
            color: #888888;
            line-height: 1.5;
        }

        .sidebar-menu {
            display: grid;
            gap: 8px;
        }

        .sidebar-menu a,
        .sidebar-menu button {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 12px 14px;
            border-radius: 999px;
            color: #888888;
            text-decoration: none;
            font-size: 15px;
            transition: 0.2s ease;
            border: none;
            background: transparent;
            cursor: pointer;
            font-family: inherit;
            text-align: left;
        }

        .sidebar-menu a.active,
        .sidebar-menu a:hover,
        .sidebar-menu button.active,
        .sidebar-menu button:hover {
            background: #f7f7f7;
            color: #ef3f4d;
            font-weight: bold;
        }

        .menu-icon {
            width: 18px;
            text-align: center;
            font-size: 15px;
        }

        .notice-badge {
            margin-left: auto;
            min-width: 28px;
            height: 20px;
            border-radius: 999px;
            background: #ef3f4d;
            color: #ffffff;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .dashboard-main {
            min-width: 0;
        }

        .dashboard-tabs {
            display: flex;
            align-items: center;
            gap: 28px;
            border-bottom: 1px solid #dddddd;
            margin-bottom: 22px;
        }

        .dashboard-tabs button {
            position: relative;
            padding: 0 0 16px;
            border: none;
            background: transparent;
            color: #999999;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            font-family: inherit;
        }

        .dashboard-tabs button.active {
            color: #ef3f4d;
        }

        .dashboard-tabs button.active::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -1px;
            width: 100%;
            height: 3px;
            background: #ef3f4d;
        }

        .recent-filter-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .recent-filter-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recent-filter-btn {
            min-width: 115px;
            height: 36px;
            border-radius: 999px;
            border: 1px solid #dddddd;
            background: #ffffff;
            color: #666666;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            font-family: inherit;
            transition: 0.2s ease;
        }

        .recent-filter-btn.active {
            border: 2px solid #111111;
            color: #ef3f4d;
            background: #ffffff;
        }

        .recent-filter-btn:hover {
            border-color: #ef3f4d;
            color: #ef3f4d;
        }

        .clear-all-btn {
            border: none;
            background: transparent;
            color: #0066ff;
            font-size: 15px;
            cursor: pointer;
            font-family: inherit;
        }

        .clear-all-btn:hover {
            text-decoration: underline;
        }

        .welcome-card {
            max-width: 520px;
            background: #ffffff;
            border: 1px solid #eeeeee;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            display: flex;
            gap: 18px;
            align-items: center;
        }

        .welcome-icon {
            width: 68px;
            height: 68px;
            border-radius: 12px;
            background: #fff0f2;
            color: #ef3f4d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            flex-shrink: 0;
        }

        .welcome-content {
            flex: 1;
        }

        .welcome-content h3 {
            margin: 0 0 8px;
            font-size: 20px;
            color: #222222;
        }

        .welcome-content p {
            margin: 0 0 14px;
            color: #444444;
            font-size: 15px;
            line-height: 1.4;
        }

        .dashboard-login-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 220px;
            height: 38px;
            border-radius: 5px;
            background: #ef3f4d;
            color: #ffffff;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            transition: 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .dashboard-login-btn:hover {
            background: #d92e3d;
        }

        .member-info-box {
            max-width: 520px;
            background: #f9f9f9;
            border: 1px solid #eeeeee;
            border-radius: 8px;
            padding: 18px 20px;
            margin-bottom: 24px;
        }

        .member-info-box h3 {
            margin: 0 0 8px;
            font-size: 18px;
            color: #222222;
        }

        .member-info-box p {
            margin: 5px 0;
            color: #666666;
            font-size: 14px;
        }

        .section-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 0 18px;
        }

        .section-row h3 {
            margin: 0;
            font-size: 18px;
            color: #111111;
        }

        .section-row a {
            color: #0066ff;
            text-decoration: none;
            font-size: 14px;
        }

        .reward-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .reward-card {
            border: 1px solid #dddddd;
            border-radius: 6px;
            background: #ffffff;
            overflow: hidden;
        }

        .reward-img {
            height: 88px;
            background: linear-gradient(135deg, #ef3f4d, #222222);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            padding: 12px;
        }

        .reward-card p {
            margin: 0;
            padding: 12px;
            min-height: 58px;
            font-size: 13px;
            font-weight: bold;
            line-height: 1.35;
            color: #222222;
        }

        .locked-note {
            color: #999999;
            font-size: 13px;
            margin-top: 15px;
        }

        .recent-empty-box {
            max-width: 520px;
            background: #ffffff;
            border: 1px solid #eeeeee;
            border-radius: 8px;
            padding: 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .recent-empty-box h3 {
            margin: 0 0 8px;
            font-size: 20px;
            color: #222222;
        }

        .recent-empty-box p {
            margin: 0 0 18px;
            color: #666666;
            font-size: 15px;
            line-height: 1.5;
        }

        .recent-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .recent-item {
            display: grid;
            grid-template-columns: 145px 1fr;
            gap: 16px;
            border: 1px solid #dddddd;
            border-radius: 6px;
            padding: 16px;
            background: #ffffff;
            align-items: center;
        }

        .recent-thumb {
            height: 105px;
            border-radius: 6px;
            background: #f3f3f3;
            overflow: hidden;
            display: block;
        }

        .recent-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: 0.2s ease;
        }

        .recent-thumb:hover img {
            transform: scale(1.04);
        }

        .recent-info h4 {
            margin: 0 0 8px;
            font-size: 17px;
            color: #222222;
            line-height: 1.35;
        }

        .recent-info p {
            margin: 0;
            color: #666666;
            font-size: 14px;
            line-height: 1.5;
        }

        .recent-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .recent-pill {
            display: inline-flex;
            align-items: center;
            min-height: 23px;
            padding: 3px 9px;
            border-radius: 999px;
            background: #f7f7f7;
            color: #666666;
            font-size: 12px;
            font-weight: bold;
        }

        .recent-price {
            display: block;
            color: #ef3f4d;
            font-weight: bold;
            font-size: 20px;
            margin-bottom: 6px;
        }

        .recent-guest-list {
            display: none;
        }

        .logout-loading-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(255, 255, 255, 0.72);
            display: none;
            align-items: center;
            justify-content: center;
        }

        .logout-loading-overlay.show {
            display: flex;
        }

        .bubble-loader {
            position: relative;
            width: 78px;
            height: 78px;
            animation: bubbleRotate 1s linear infinite;
        }

        .bubble-loader span {
            position: absolute;
            width: 15px;
            height: 15px;
            background: #999999;
            border-radius: 50%;
            opacity: 0.25;
        }

        .bubble-loader span:nth-child(1) {
            top: 0;
            left: 31px;
            opacity: 1;
            background: #444444;
        }

        .bubble-loader span:nth-child(2) {
            top: 9px;
            right: 9px;
            opacity: 0.8;
        }

        .bubble-loader span:nth-child(3) {
            top: 31px;
            right: 0;
            opacity: 0.65;
        }

        .bubble-loader span:nth-child(4) {
            right: 9px;
            bottom: 9px;
            opacity: 0.5;
        }

        .bubble-loader span:nth-child(5) {
            bottom: 0;
            left: 31px;
            opacity: 0.4;
        }

        .bubble-loader span:nth-child(6) {
            left: 9px;
            bottom: 9px;
            opacity: 0.55;
        }

        .bubble-loader span:nth-child(7) {
            top: 31px;
            left: 0;
            opacity: 0.7;
        }

        .bubble-loader span:nth-child(8) {
            top: 9px;
            left: 9px;
            opacity: 0.85;
        }

        @keyframes bubbleRotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 1000px) {
            .recent-list {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }

            .dashboard-sidebar {
                order: 2;
            }

            .dashboard-main {
                order: 1;
            }

            .reward-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 560px) {
            .member-dashboard-page {
                padding: 25px 12px 50px;
            }

            .welcome-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .dashboard-login-btn {
                width: 100%;
            }

            .reward-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-tabs {
                gap: 20px;
            }

            .recent-filter-row {
                align-items: flex-start;
                flex-direction: column;
            }

            .recent-item {
                grid-template-columns: 1fr;
            }

            .recent-thumb {
                height: 170px;
            }

            .section-row {
                align-items: flex-start;
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>

<body>

<?php include '../header.php'; ?>

<section class="member-dashboard-page">
    <div class="member-dashboard-wrap">

        <div class="dashboard-breadcrumb">
            <a href="../index.php">Home</a>
            <span>›</span>
            Dashboard
        </div>

        <div class="dashboard-layout">

            <aside class="dashboard-sidebar">
                <h2>Member Dashboard</h2>

                <?php if ($is_logged_in): ?>
                    <p>
                        Welcome back,<br>
                        <strong><?php echo htmlspecialchars($member_name); ?></strong>
                    </p>
                <?php else: ?>
                    <p>
                        Become a member to unlock rewards and manage your buggy enquiries.
                    </p>
                <?php endif; ?>

                <nav class="sidebar-menu">
                    <button type="button" class="<?php echo ($tab == 'dashboard') ? 'active' : ''; ?>" onclick="goDashboardTab('dashboard')">
                        <span class="menu-icon">▦</span>
                        Dashboard
                    </button>

                    <a href="<?php echo $is_logged_in ? '#' : 'login.php'; ?>">
                        <span class="menu-icon">○</span>
                        My Profile
                    </a>

                    <a href="<?php echo $is_logged_in ? '#' : 'login.php'; ?>">
                        <span class="menu-icon">🔔</span>
                        Notifications
                        <span class="notice-badge">0</span>
                    </a>

                    <a href="<?php echo $is_logged_in ? '#' : 'login.php'; ?>">
                        <span class="menu-icon">♡</span>
                        Favourite Buggy
                    </a>

                    <a href="<?php echo $is_logged_in ? '#' : 'login.php'; ?>">
                        <span class="menu-icon">☷</span>
                        Rental Enquiry
                    </a>

                    <a href="<?php echo $is_logged_in ? '#' : 'login.php'; ?>">
                        <span class="menu-icon">□</span>
                        My Orders
                    </a>

                    <a href="<?php echo $is_logged_in ? '#' : 'login.php'; ?>">
                        <span class="menu-icon">★</span>
                        My Rewards
                    </a>

                    <?php if ($is_logged_in): ?>
                        <a href="/member/logout.php" class="js-logout-link">
                            <span class="menu-icon">↪</span>
                            Logout
                        </a>
                    <?php endif; ?>
                </nav>
            </aside>

            <main class="dashboard-main">

                <div class="dashboard-tabs">
                    <button
                        type="button"
                        class="<?php echo ($tab == 'dashboard') ? 'active' : ''; ?>"
                        onclick="goDashboardTab('dashboard')"
                    >
                        Dashboard
                    </button>

                    <button
                        type="button"
                        class="<?php echo ($tab == 'recent') ? 'active' : ''; ?>"
                        onclick="goDashboardTab('recent')"
                    >
                        Recent Views
                    </button>
                </div>

                <?php if ($tab == 'recent'): ?>

                    <div class="recent-filter-row">
                        <div class="recent-filter-buttons">
                            <button
                                type="button"
                                class="recent-filter-btn <?php echo ($recent_type == 'used') ? 'active' : ''; ?>"
                                onclick="goRecentType('used')"
                            >
                                Used Buggy
                            </button>

                            <button
                                type="button"
                                class="recent-filter-btn <?php echo ($recent_type == 'new') ? 'active' : ''; ?>"
                                onclick="goRecentType('new')"
                            >
                                New Buggy
                            </button>
                        </div>

                        <button type="button" class="clear-all-btn" onclick="clearAllRecentViews()">
                            Clear All
                        </button>
                    </div>

                    <?php if ($is_logged_in): ?>

                        <?php if (count($recentViews) > 0): ?>

                            <div class="recent-list">
                                <?php foreach ($recentViews as $recent): ?>
                                    <?php
                                        $recentTitle = !empty($recent['name'])
                                            ? $recent['name']
                                            : trim(($recent['brand'] ?? '') . ' ' . ($recent['model'] ?? ''));

                                        if ($recentTitle === '') {
                                            $recentTitle = 'Buggy Detail';
                                        }

                                        $recentImage = dashboardImagePath($recent['image_url'] ?? '');
                                        $recentListingText = listingTypeText($recent['listing_type'] ?? '');
                                        $recentCondition = ucfirst($recent['buggy_condition'] ?? '');
                                        $recentPrice = (float) ($recent['selling_price'] ?? 0);
                                        $recentRentalDay = $recent['rent_price_1_day'] ?? '';
                                    ?>

                                    <div class="recent-item">
                                        <a class="recent-thumb" href="../buggy-detail.php?id=<?php echo (int) $recent['id']; ?>">
                                            <img
                                                src="<?php echo htmlspecialchars($recentImage); ?>"
                                                alt="<?php echo htmlspecialchars($recentTitle); ?>"
                                                onerror="this.src='../images/no-image.jpg';"
                                            >
                                        </a>

                                        <div class="recent-info">
                                            <h4><?php echo htmlspecialchars($recentTitle); ?></h4>

                                            <span class="recent-price">
                                                <?php if ($recentPrice > 0): ?>
                                                    RM <?php echo number_format($recentPrice, 0); ?>
                                                <?php elseif (!empty($recentRentalDay)): ?>
                                                    Rental from RM <?php echo htmlspecialchars($recentRentalDay); ?> / day
                                                <?php else: ?>
                                                    Price on request
                                                <?php endif; ?>
                                            </span>

                                            <div class="recent-meta">
                                                <?php if (!empty($recent['brand'])): ?>
                                                    <span class="recent-pill"><?php echo htmlspecialchars($recent['brand']); ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($recent['seats'])): ?>
                                                    <span class="recent-pill"><?php echo htmlspecialchars($recent['seats']); ?> Seater</span>
                                                <?php endif; ?>

                                                <?php if (!empty($recentCondition)): ?>
                                                    <span class="recent-pill"><?php echo htmlspecialchars($recentCondition); ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($recentListingText)): ?>
                                                    <span class="recent-pill"><?php echo htmlspecialchars($recentListingText); ?></span>
                                                <?php endif; ?>
                                            </div>

                                            <p>
                                                Viewed on <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($recent['viewed_at']))); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php else: ?>

                            <div class="recent-empty-box">
                                <h3>No Recent Views Yet</h3>
                                <p>
                                    Your recently viewed <?php echo $recent_type === 'used' ? 'used buggy' : 'new buggy'; ?> listings will appear here after you view product details.
                                </p>
                                <a href="<?php echo $recent_type === 'used' ? '../usedbuggy.php' : '../newbuggy.php'; ?>" class="dashboard-login-btn">
                                    Browse <?php echo $recent_type === 'used' ? 'Used Buggy' : 'New Buggy'; ?>
                                </a>
                            </div>

                        <?php endif; ?>

                    <?php else: ?>

                        <div class="recent-list recent-guest-list" id="guestRecentList"></div>

                        <div class="recent-empty-box" id="guestRecentEmpty">
                            <h3>No Recent Views Yet</h3>
                            <p>
                                Your recently viewed <?php echo $recent_type === 'used' ? 'used buggy' : 'new buggy'; ?> listings on this browser will appear here.
                                Login or sign up to save recent views across devices.
                            </p>
                            <a href="login.php" class="dashboard-login-btn">Login / Sign Up</a>
                        </div>

                    <?php endif; ?>

                <?php else: ?>

                    <?php if (!$is_logged_in): ?>

                        <div class="welcome-card">
                            <div class="welcome-icon">🎁</div>

                            <div class="welcome-content">
                                <h3>Unlock Rewards</h3>
                                <p>
                                    Become a member to enjoy exclusive benefits and manage your buggy enquiries.
                                </p>

                                <a href="login.php" class="dashboard-login-btn">
                                    Login / Sign Up
                                </a>
                            </div>
                        </div>

                    <?php else: ?>

                        <div class="member-info-box">
                            <h3>Hello, <?php echo htmlspecialchars($member_name); ?></h3>
                            <p>Email: <?php echo htmlspecialchars($member_email); ?></p>
                            <p>You are signed in to your YHI Buggy Mart member account.</p>
                        </div>

                    <?php endif; ?>

                    <div class="section-row">
                        <h3>My Rewards</h3>
                        <a href="<?php echo $is_logged_in ? '#' : 'login.php'; ?>">View All</a>
                    </div>

                    <div class="reward-grid">

                        <div class="reward-card">
                            <div class="reward-img">YHI MEMBER</div>
                            <p>WIN FREE BUGGY CHECKING SERVICE</p>
                        </div>

                        <div class="reward-card">
                            <div class="reward-img">PREMIUM</div>
                            <p>FREE BUGGY CLEANING FOR MEMBERS</p>
                        </div>

                        <div class="reward-card">
                            <div class="reward-img">RENTAL</div>
                            <p>GET RENTAL DISCOUNT VOUCHERS</p>
                        </div>

                        <div class="reward-card">
                            <div class="reward-img">SERVICE</div>
                            <p>EARN POINTS ON YOUR BOOKINGS</p>
                        </div>

                        <div class="reward-card">
                            <div class="reward-img">ACCESSORY</div>
                            <p>15% OFF SELECTED BUGGY PARTS</p>
                        </div>

                        <div class="reward-card">
                            <div class="reward-img">EVENT</div>
                            <p>SPECIAL EVENT RENTAL PACKAGE</p>
                        </div>

                        <div class="reward-card">
                            <div class="reward-img">INSPECTION</div>
                            <p>FREE BASIC BUGGY INSPECTION</p>
                        </div>

                        <div class="reward-card">
                            <div class="reward-img">LISTING</div>
                            <p>SELLER LISTING PROMOTION</p>
                        </div>

                    </div>

                    <?php if (!$is_logged_in): ?>
                        <div class="locked-note">
                            Please login to use profile, favourite buggy, enquiry and order features.
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </main>

        </div>

    </div>
</section>

<div class="logout-loading-overlay" id="logoutLoadingOverlay">
    <div class="bubble-loader">
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
    </div>
</div>

<script>
    const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    const currentTab = <?php echo json_encode($tab); ?>;
    const currentRecentType = <?php echo json_encode($recent_type); ?>;

    function goDashboardTab(tabName) {
        if (tabName === 'recent') {
            window.location.href = 'dashboard.php?tab=recent&type=used';
            return;
        }

        window.location.href = 'dashboard.php?tab=dashboard';
    }

    function goRecentType(type) {
        if (type === 'new') {
            window.location.href = 'dashboard.php?tab=recent&type=new';
            return;
        }

        window.location.href = 'dashboard.php?tab=recent&type=used';
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatPrice(item) {
        const price = parseFloat(item.price || 0);

        if (price > 0) {
            return 'RM ' + price.toLocaleString('en-MY', {
                maximumFractionDigits: 0
            });
        }

        if (item.rental_day && parseFloat(item.rental_day) > 0) {
            return 'Rental from RM ' + escapeHtml(item.rental_day) + ' / day';
        }

        return 'Price on request';
    }

    function formatViewedDate(dateString) {
        if (!dateString) {
            return '';
        }

        const date = new Date(dateString);

        if (isNaN(date.getTime())) {
            return '';
        }

        return date.toLocaleString('en-MY', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function itemMatchesCurrentRecentType(item) {
        const condition = String(item.condition || '').toLowerCase();

        if (currentRecentType === 'used') {
            return condition === 'used';
        }

        if (currentRecentType === 'new') {
            return condition === 'new';
        }

        return false;
    }

    function loadGuestRecentViews() {
        if (isLoggedIn) {
            return;
        }

        if (currentTab !== 'recent') {
            return;
        }

        const list = document.getElementById('guestRecentList');
        const empty = document.getElementById('guestRecentEmpty');

        if (!list || !empty) {
            return;
        }

        let recentViews = [];

        try {
            const stored = localStorage.getItem('yhi_recent_buggies');

            if (stored) {
                recentViews = JSON.parse(stored);
            }

            if (!Array.isArray(recentViews)) {
                recentViews = [];
            }
        } catch (error) {
            recentViews = [];
        }

        recentViews = recentViews.filter(itemMatchesCurrentRecentType);

        if (recentViews.length === 0) {
            list.style.display = 'none';
            empty.style.display = 'block';
            list.innerHTML = '';
            return;
        }

        empty.style.display = 'none';
        list.style.display = 'grid';

        list.innerHTML = recentViews.map(function (item) {
            const id = parseInt(item.id || 0);
            const title = item.title || 'Buggy Detail';
            const brand = item.brand || '';
            const condition = item.condition || '';
            const listingType = item.listing_type || '';
            const image = item.image || 'images/no-image.jpg';
            const url = '../buggy-detail.php?id=' + id;
            const viewedDate = formatViewedDate(item.viewed_at);

            let imagePath = image;

            if (!/^https?:\/\//i.test(imagePath)) {
                if (imagePath.indexOf('../') !== 0) {
                    imagePath = '../' + imagePath;
                }
            }

            return `
                <div class="recent-item">
                    <a class="recent-thumb" href="${url}">
                        <img
                            src="${escapeHtml(imagePath)}"
                            alt="${escapeHtml(title)}"
                            onerror="this.src='../images/no-image.jpg';"
                        >
                    </a>

                    <div class="recent-info">
                        <h4>${escapeHtml(title)}</h4>

                        <span class="recent-price">${formatPrice(item)}</span>

                        <div class="recent-meta">
                            ${brand ? `<span class="recent-pill">${escapeHtml(brand)}</span>` : ''}
                            ${condition ? `<span class="recent-pill">${escapeHtml(condition)}</span>` : ''}
                            ${listingType ? `<span class="recent-pill">${escapeHtml(listingType)}</span>` : ''}
                        </div>

                        <p>
                            ${viewedDate ? `Viewed on ${escapeHtml(viewedDate)}` : ''}
                        </p>
                    </div>
                </div>
            `;
        }).join('');
    }

    function clearAllRecentViews() {
        if (!confirm('Clear all recent views?')) {
            return;
        }

        if (!isLoggedIn) {
            localStorage.removeItem('yhi_recent_buggies');
            loadGuestRecentViews();
            return;
        }

        fetch('clear-recent-view.php', {
            method: 'POST'
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Unable to clear recent views.');
            }
        })
        .catch(function () {
            alert('Unable to clear recent views.');
        });
    }

    function mergeGuestRecentViewsToMember() {
        if (!isLoggedIn) {
            return;
        }

        let recentViews = [];

        try {
            const stored = localStorage.getItem('yhi_recent_buggies');

            if (stored) {
                recentViews = JSON.parse(stored);
            }

            if (!Array.isArray(recentViews) || recentViews.length === 0) {
                return;
            }
        } catch (error) {
            return;
        }

        fetch('save-guest-recent.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                items: recentViews
            })
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (data.success) {
                localStorage.removeItem('yhi_recent_buggies');

                if (currentTab === 'recent') {
                    window.location.reload();
                }
            }
        })
        .catch(function () {
            console.log('Unable to merge guest recent views.');
        });
    }

    function setupLogoutLoading() {
        const logoutLink = document.querySelector('.js-logout-link');
        const logoutOverlay = document.getElementById('logoutLoadingOverlay');

        if (!logoutLink || !logoutOverlay) {
            return;
        }

        logoutLink.addEventListener('click', function (e) {
            e.preventDefault();

            logoutOverlay.classList.add('show');

            setTimeout(function () {
                window.location.href = logoutLink.href;
            }, 1000);
        });
    }

    loadGuestRecentViews();
    mergeGuestRecentViewsToMember();
    setupLogoutLoading();
</script>

<?php include '../footer.php'; ?>

</body>
</html>