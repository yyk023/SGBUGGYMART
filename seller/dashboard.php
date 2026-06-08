<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

if (!isset($_SESSION['seller_id'])) {
    header('Location: login.php?mode=login');
    exit;
}

$sellerId = (int)$_SESSION['seller_id'];

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sellerImagePath($imageUrl)
{
    $imageUrl = trim((string)$imageUrl);
    if ($imageUrl === '') return '../images/no-image.png';
    if (preg_match('/^https?:\/\//i', $imageUrl)) return $imageUrl;
    if (strpos($imageUrl, '../') === 0) return $imageUrl;
    if (strpos($imageUrl, 'images/') === 0) return '../' . $imageUrl;
    return '../images/' . $imageUrl;
}

function statusBadgeClass($status)
{
    $status = strtolower((string)$status);
    if ($status === 'active' || $status === 'verified') return 'badge-active';
    if ($status === 'rejected')  return 'badge-rejected';
    if ($status === 'suspended') return 'badge-suspended';
    return 'badge-pending';
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id, full_name, contact_no, email, address,
            seller_status, payment_status, payment_reference,
            payment_receipt, approved_at, rejected_reason,
            status, created_at, updated_at
        FROM sellers
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$sellerId]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seller) {
        session_destroy();
        header('Location: login.php?mode=login&error=' . urlencode('Seller account not found.'));
        exit;
    }

    $_SESSION['seller_name']    = $seller['full_name'];
    $_SESSION['seller_email']   = $seller['email'];
    $_SESSION['seller_status']  = $seller['seller_status'];
    $_SESSION['payment_status'] = $seller['payment_status'];

    if (
        $seller['seller_status'] !== 'active' ||
        $seller['payment_status'] !== 'verified'
    ) {
        header('Location: payment.php?notice=' . urlencode('Please wait for SGBUGGYMART admin payment verification before accessing seller dashboard.'));
        exit;
    }

    $countStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_listings,
            SUM(CASE WHEN status = 'pending'  THEN 1 ELSE 0 END) AS pending_listings,
            SUM(CASE WHEN status = 'active'   THEN 1 ELSE 0 END) AS active_listings,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_listings
        FROM buggies
        WHERE owner_type = 'seller'
        AND owner_id = ?
    ");
    $countStmt->execute([$sellerId]);
    $listingStats = $countStmt->fetch(PDO::FETCH_ASSOC);

    $recentStmt = $pdo->prepare("
        SELECT id, brand, model, name, selling_price, image_url, status, created_at
        FROM buggies
        WHERE owner_type = 'seller'
        AND owner_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 5
    ");
    $recentStmt->execute([$sellerId]);
    $recentListings = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('Failed to load seller dashboard: ' . $e->getMessage());
}

$totalListings    = (int)($listingStats['total_listings']    ?? 0);
$pendingListings  = (int)($listingStats['pending_listings']  ?? 0);
$activeListings   = (int)($listingStats['active_listings']   ?? 0);
$rejectedListings = (int)($listingStats['rejected_listings'] ?? 0);

$sellerStatus  = $seller['seller_status'];
$paymentStatus = $seller['payment_status'];

include '../header.php';
?>

<style>
    .seller-dashboard-page {
        background: #ffffff;
        padding: 38px 15px 76px;
        min-height: 75vh;
    }

    .seller-dashboard-wrap {
        max-width: 1320px;
        margin: 0 auto;
    }

    .seller-breadcrumb { font-size: 13px; color: #999999; margin-bottom: 18px; }
    .seller-breadcrumb a { color: #777777; text-decoration: none; }
    .seller-breadcrumb span { margin: 0 8px; color: #bbbbbb; }

    .seller-layout {
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 30px;
        align-items: start;
    }

    .seller-sidebar {
        background: #ffffff;
        border: 1px solid #dddddd;
        border-radius: 14px;
        padding: 26px 18px;
    }

    .seller-sidebar h2 { margin: 0 0 8px; font-size: 18px; color: #222222; }
    .seller-sidebar p  { margin: 0 0 24px; font-size: 14px; color: #888888; line-height: 1.5; }

    .seller-menu { display: grid; gap: 8px; }

    .seller-menu a {
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
    }

    .seller-menu a.active,
    .seller-menu a:hover {
        background: #f7f7f7;
        color: #ef3f4d;
        font-weight: bold;
    }

    .menu-icon { width: 18px; text-align: center; font-size: 15px; }

    .seller-main { min-width: 0; }

    .dashboard-hero {
        background: linear-gradient(135deg, #ef3f4d, #222222);
        color: #ffffff;
        border-radius: 18px;
        padding: 30px;
        margin-bottom: 24px;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 20px;
        align-items: center;
    }

    .dashboard-hero h1 { margin: 0 0 8px; font-size: 30px; line-height: 1.15; }
    .dashboard-hero p  { margin: 0; color: rgba(255,255,255,0.88); line-height: 1.6; font-size: 15px; }

    .hero-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }

    .hero-btn {
        min-width: 150px; height: 42px; border-radius: 999px;
        display: inline-flex; align-items: center; justify-content: center;
        text-decoration: none; font-size: 14px; font-weight: bold; transition: 0.2s ease;
    }

    .hero-btn-light         { background: #ffffff; color: #ef3f4d; }
    .hero-btn-light:hover   { background: #fff0f2; }
    .hero-btn-outline       { background: transparent; color: #ffffff; border: 1px solid rgba(255,255,255,0.55); }
    .hero-btn-outline:hover { background: rgba(255,255,255,0.12); }

    .notice-box {
        border-radius: 12px; padding: 18px 20px;
        margin-bottom: 24px; font-size: 15px; line-height: 1.6;
    }

    .notice-warning  { background: #fff7e6; color: #8a5a00; border: 1px solid #ffd999; }
    .notice-success  { background: #e8fff0; color: #167a3c; border: 1px solid #b8e8c8; }
    .notice-danger   { background: #fff0f2; color: #991b1b; border: 1px solid #ffc4cc; }
    .notice-muted    { background: #f5f5f5; color: #555555; border: 1px solid #dddddd; }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: #ffffff; border: 1px solid #eeeeee;
        border-radius: 14px; padding: 20px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    }

    .stat-card span   { display: block; color: #888888; font-size: 13px; margin-bottom: 8px; }
    .stat-card strong { display: block; color: #222222; font-size: 28px; line-height: 1; }

    .content-grid {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 24px;
        align-items: start;
    }

    .panel {
        background: #ffffff; border: 1px solid #eeeeee;
        border-radius: 14px; padding: 24px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    }

    .panel h2 { margin: 0 0 18px; color: #222222; font-size: 21px; }

    .seller-info-list { display: grid; gap: 12px; }

    .info-row { padding-bottom: 11px; border-bottom: 1px solid #eeeeee; }
    .info-row span   { display: block; color: #888888; font-size: 13px; margin-bottom: 4px; }
    .info-row strong { display: block; color: #222222; font-size: 15px; word-break: break-word; }

    .badge {
        display: inline-flex; align-items: center;
        border-radius: 999px; padding: 7px 11px;
        font-size: 12px; font-weight: bold; text-transform: capitalize;
    }

    .badge-pending   { background: #fff3cd; color: #856404; }
    .badge-active    { background: #dcfce7; color: #166534; }
    .badge-rejected  { background: #fee2e2; color: #991b1b; }
    .badge-suspended { background: #e5e7eb; color: #374151; }

    .recent-list { display: grid; gap: 14px; }

    .recent-item {
        display: grid;
        grid-template-columns: 96px 1fr;
        gap: 14px; align-items: center;
        border: 1px solid #eeeeee; border-radius: 12px; padding: 12px;
    }

    .recent-thumb {
        width: 96px; height: 72px; border-radius: 8px;
        object-fit: cover; background: #f3f3f3; border: 1px solid #eeeeee;
    }

    .recent-info h3 { margin: 0 0 6px; color: #222222; font-size: 16px; }
    .recent-info p  { margin: 0 0 7px; color: #777777; font-size: 13px; }
    .recent-info a  { color: #ef3f4d; font-weight: bold; text-decoration: none; font-size: 13px; }

    .empty-box {
        border: 1px dashed #dddddd; border-radius: 12px;
        padding: 28px; color: #777777; text-align: center; background: #fafafa;
    }

    .action-btn {
        width: 100%; height: 42px; border-radius: 8px;
        background: #ef3f4d; color: #ffffff;
        text-decoration: none;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 14px; font-weight: bold; margin-top: 14px;
    }

    .action-btn:hover        { background: #d92e3d; }
    .action-btn.dark         { background: #222222; }
    .action-btn.dark:hover   { background: #111111; }
    .action-btn.disabled     { background: #bbbbbb; cursor: not-allowed; pointer-events: none; }

    .logout-loading-overlay {
        position: fixed; inset: 0; z-index: 99999;
        background: rgba(255,255,255,0.72);
        display: none; align-items: center; justify-content: center;
    }

    .logout-loading-overlay.show { display: flex; }

    .bubble-loader { position: relative; width: 78px; height: 78px; animation: bubbleRotate 1s linear infinite; }
    .bubble-loader span { position: absolute; width: 15px; height: 15px; background: #999999; border-radius: 50%; opacity: 0.25; }
    .bubble-loader span:nth-child(1) { top: 0;    left: 31px; opacity: 1;    background: #444444; }
    .bubble-loader span:nth-child(2) { top: 9px;  right: 9px; opacity: 0.8; }
    .bubble-loader span:nth-child(3) { top: 31px; right: 0;   opacity: 0.65; }
    .bubble-loader span:nth-child(4) { right: 9px; bottom: 9px; opacity: 0.5; }
    .bubble-loader span:nth-child(5) { bottom: 0; left: 31px; opacity: 0.4; }
    .bubble-loader span:nth-child(6) { left: 9px; bottom: 9px; opacity: 0.55; }
    .bubble-loader span:nth-child(7) { top: 31px; left: 0;    opacity: 0.7; }
    .bubble-loader span:nth-child(8) { top: 9px;  left: 9px;  opacity: 0.85; }

    @keyframes bubbleRotate {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }

    @media (max-width: 1000px) {
        .seller-layout  { grid-template-columns: 1fr; }
        .seller-sidebar { order: 2; }
        .seller-main    { order: 1; }
        .dashboard-grid { grid-template-columns: repeat(2, 1fr); }
        .content-grid   { grid-template-columns: 1fr; }
        .dashboard-hero { grid-template-columns: 1fr; }
        .hero-actions   { justify-content: flex-start; }
    }

    @media (max-width: 560px) {
        .seller-dashboard-page { padding: 28px 12px 56px; }
        .dashboard-grid        { grid-template-columns: 1fr; }
        .recent-item           { grid-template-columns: 1fr; }
        .recent-thumb          { width: 100%; height: 170px; }
        .hero-actions          { flex-direction: column; align-items: stretch; }
        .hero-btn              { width: 100%; }
    }
</style>

<section class="seller-dashboard-page">
    <div class="seller-dashboard-wrap">

        <div class="seller-breadcrumb">
            <a href="../index.php">Home</a>
            <span>›</span>
            Seller Dashboard
        </div>

        <div class="seller-layout">

            <aside class="seller-sidebar">
                <h2>Seller Portal</h2>
                <p>
                    Welcome back,<br>
                    <strong><?php echo e($seller['full_name']); ?></strong>
                </p>

                <nav class="seller-menu">
                    <a href="dashboard.php" class="active">
                        <span class="menu-icon">▦</span>
                        Dashboard
                    </a>
                    <a href="my-plan.php">
                        <span class="menu-icon">📦</span>
                        My Plan
                    </a>
                    <a href="payment.php">
                        <span class="menu-icon">□</span>
                        Payment Verification
                    </a>
                    <a href="buggy-list.php">
                        <span class="menu-icon">☰</span>
                        My Buggy Listings
                    </a>
                    <a href="<?php echo $sellerStatus === 'active' ? 'buggy-form.php' : '#'; ?>">
                        <span class="menu-icon">+</span>
                        Add Listing
                    </a>
                    <a href="logout.php" class="js-logout-link">
                        <span class="menu-icon">⏻</span>
                        Logout
                    </a>
                </nav>
            </aside>

            <main class="seller-main">
                <div class="dashboard-hero">
                    <div>
                        <h1>Hello, <?php echo e($seller['full_name']); ?></h1>
                        <p>Manage your seller account, payment verification status and buggy listings from this dashboard.</p>
                    </div>
                    <div class="hero-actions">
                        <a href="payment.php" class="hero-btn hero-btn-outline">Payment Status</a>
                        <?php if ($sellerStatus === 'active'): ?>
                            <a href="buggy-form.php" class="hero-btn hero-btn-light">Add Listing</a>
                        <?php else: ?>
                            <a href="payment.php" class="hero-btn hero-btn-light">Complete Verification</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($sellerStatus === 'pending_payment'): ?>
                    <div class="notice-box notice-warning">
                        Your seller account is pending payment. Please submit your payment reference and receipt before admin can verify your account.
                    </div>
                <?php elseif ($sellerStatus === 'pending_verification'): ?>
                    <div class="notice-box notice-warning">
                        Your payment has been submitted. Please wait for SGBUGGYMART admin to verify your seller account.
                    </div>
                <?php elseif ($sellerStatus === 'active'): ?>
                    <div class="notice-box notice-success">
                        Your seller account is active. You can now upload buggy listings. Each listing still requires admin approval before going public.
                    </div>
                <?php elseif ($sellerStatus === 'rejected'): ?>
                    <div class="notice-box notice-danger">
                        Your seller account was rejected.
                        <?php if (!empty($seller['rejected_reason'])): ?>
                            <br><strong>Reason:</strong> <?php echo e($seller['rejected_reason']); ?>
                        <?php endif; ?>
                        <br>You may resubmit payment details from the payment page.
                    </div>
                <?php elseif ($sellerStatus === 'suspended'): ?>
                    <div class="notice-box notice-muted">
                        Your seller account is suspended. Please contact SGBUGGYMART admin for more information.
                    </div>
                <?php endif; ?>

                <div class="dashboard-grid">
                    <div class="stat-card">
                        <span>Total Listings</span>
                        <strong><?php echo $totalListings; ?></strong>
                    </div>
                    <div class="stat-card">
                        <span>Active Listings</span>
                        <strong><?php echo $activeListings; ?></strong>
                    </div>
                    <div class="stat-card">
                        <span>Pending Listings</span>
                        <strong><?php echo $pendingListings; ?></strong>
                    </div>
                    <div class="stat-card">
                        <span>Rejected Listings</span>
                        <strong><?php echo $rejectedListings; ?></strong>
                    </div>
                </div>

                <div class="content-grid">
                    <div class="panel">
                        <h2>Recent Buggy Listings</h2>
                        <?php if (count($recentListings) > 0): ?>
                            <div class="recent-list">
                                <?php foreach ($recentListings as $listing): ?>
                                    <?php
                                        $title = !empty($listing['name'])
                                            ? $listing['name']
                                            : trim(($listing['brand'] ?? '') . ' ' . ($listing['model'] ?? ''));
                                        if ($title === '') $title = 'Buggy Listing';
                                        $listingStatus      = strtolower((string)$listing['status']);
                                        $listingStatusLabel = ucwords(str_replace('_', ' ', $listingStatus));
                                    ?>
                                    <div class="recent-item">
                                        <img
                                            src="<?php echo e(sellerImagePath($listing['image_url'] ?? '')); ?>"
                                            alt="<?php echo e($title); ?>"
                                            class="recent-thumb"
                                            onerror="this.src='../images/no-image.png';"
                                        >
                                        <div class="recent-info">
                                            <h3><?php echo e($title); ?></h3>
                                            <p>
                                                RM <?php echo number_format((float)$listing['selling_price'], 2); ?>
                                                &nbsp;
                                                <span class="badge <?php echo e(statusBadgeClass($listingStatus)); ?>">
                                                    <?php echo e($listingStatusLabel); ?>
                                                </span>
                                            </p>
                                            <a href="buggy-list.php">Manage Listing</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-box">No buggy listings yet.</div>
                        <?php endif; ?>
                    </div>

                    <aside class="panel">
                        <h2>Seller Account</h2>
                        <div class="seller-info-list">
                            <div class="info-row">
                                <span>Seller Status</span>
                                <strong>
                                    <span class="badge <?php echo e(statusBadgeClass($sellerStatus)); ?>">
                                        <?php echo e(ucwords(str_replace('_', ' ', $sellerStatus))); ?>
                                    </span>
                                </strong>
                            </div>
                            <div class="info-row">
                                <span>Payment Status</span>
                                <strong>
                                    <span class="badge <?php echo e(statusBadgeClass($paymentStatus)); ?>">
                                        <?php echo e(ucwords(str_replace('_', ' ', $paymentStatus))); ?>
                                    </span>
                                </strong>
                            </div>
                            <div class="info-row">
                                <span>Email</span>
                                <strong><?php echo e($seller['email']); ?></strong>
                            </div>
                            <div class="info-row">
                                <span>Contact No.</span>
                                <strong><?php echo e($seller['contact_no']); ?></strong>
                            </div>
                            <div class="info-row">
                                <span>Listing Limit</span>
                                <strong><?php echo $totalListings; ?> / 10 used buggy listings</strong>
                            </div>
                        </div>
                        <a href="payment.php" class="action-btn dark">View Payment</a>
                        <?php if ($sellerStatus === 'active' && $totalListings < 10): ?>
                            <a href="buggy-form.php" class="action-btn">Add Listing</a>
                        <?php elseif ($sellerStatus === 'active' && $totalListings >= 10): ?>
                            <a href="#" class="action-btn disabled">Listing Limit Reached</a>
                        <?php else: ?>
                            <a href="#" class="action-btn disabled">Upload Locked</a>
                        <?php endif; ?>
                    </aside>
                </div>
            </main>
        </div>
    </div>
</section>

<div class="logout-loading-overlay" id="logoutLoadingOverlay">
    <div class="bubble-loader">
        <span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span>
    </div>
</div>

<script>
    const logoutLink    = document.querySelector('.js-logout-link');
    const logoutOverlay = document.getElementById('logoutLoadingOverlay');

    if (logoutLink && logoutOverlay) {
        logoutLink.addEventListener('click', function (e) {
            e.preventDefault();
            logoutOverlay.classList.add('show');
            setTimeout(function () {
                window.location.href = logoutLink.href;
            }, 1000);
        });
    }
</script>

<?php include '../footer.php'; ?>