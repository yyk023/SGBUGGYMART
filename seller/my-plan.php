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

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

try {
    $sellerStmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.email, s.seller_status, s.payment_status, s.status, s.package_id,
               p.id AS pkg_id, p.name AS pkg_name, p.tagline AS pkg_tagline,
               p.amount AS pkg_amount, p.listing_limit AS pkg_listing_limit,
               p.benefits AS pkg_benefits, p.color_theme AS pkg_color
        FROM sellers s
        LEFT JOIN seller_packages p ON p.id = s.package_id
        WHERE s.id = ? LIMIT 1
    ");
    $sellerStmt->execute([$sellerId]);
    $seller = $sellerStmt->fetch(PDO::FETCH_ASSOC);

    if (!$seller) {
        session_destroy();
        header('Location: login.php?mode=login&error=' . urlencode('Seller account not found.'));
        exit;
    }

    $_SESSION['seller_name']    = $seller['full_name'];
    $_SESSION['seller_email']   = $seller['email'];
    $_SESSION['seller_status']  = $seller['seller_status'];
    $_SESSION['payment_status'] = $seller['payment_status'];
} catch (PDOException $e) {
    die('Failed to load seller account: ' . $e->getMessage());
}

// Listing count
try {
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM buggies WHERE owner_type = 'seller' AND owner_id = ?");
    $cntStmt->execute([$sellerId]);
    $listingCount = (int)$cntStmt->fetchColumn();
} catch (PDOException $e) {
    $listingCount = 0;
}

// Load all active packages
$packages = [];
try {
    $stmt = $pdo->query("SELECT * FROM seller_packages WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $packages = [];
}

$currentBenefits = [];
if (!empty($seller['pkg_benefits'])) {
    $decoded = json_decode($seller['pkg_benefits'], true);
    if (is_array($decoded)) $currentBenefits = $decoded;
}

$currentLimit = !empty($seller['pkg_listing_limit']) ? (int)$seller['pkg_listing_limit'] : 10;
$usagePercent = $currentLimit > 0 ? min(100, ($listingCount / $currentLimit) * 100) : 0;

include '../header.php';
?>

<style>
    .plan-page {
        background: #ffffff;
        padding: 38px 15px 76px;
        min-height: 75vh;
    }
    .plan-wrap { max-width: 1320px; margin: 0 auto; }

    .plan-layout {
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 30px;
        align-items: start;
    }

    .plan-sidebar {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 14px;
        padding: 26px 18px;
    }
    .plan-sidebar h2 { margin: 0 0 8px; font-size: 18px; color: #222; }
    .plan-sidebar p  { margin: 0 0 24px; font-size: 14px; color: #888; line-height: 1.5; }
    .plan-menu { display: grid; gap: 8px; }
    .plan-menu a {
        width: 100%; display: flex; align-items: center; gap: 11px;
        padding: 12px 14px; border-radius: 999px;
        color: #888; text-decoration: none; font-size: 15px;
        transition: 0.2s ease;
    }
    .plan-menu a.active, .plan-menu a:hover {
        background: #f7f7f7; color: #ef3f4d; font-weight: bold;
    }
    .menu-icon { width: 18px; text-align: center; font-size: 15px; }

    .plan-main { min-width: 0; }

    .plan-page-title { margin-bottom: 20px; }
    .plan-page-title h1 { margin: 0; font-size: 28px; color: #333; }
    .plan-page-title p  { margin: 6px 0 0; color: #777; font-size: 14px; }

    .breadcrumb { background: #eeeeee; padding: 12px 16px; margin-bottom: 22px; color: #999; font-size: 14px; }
    .breadcrumb a { color: #777; text-decoration: none; }
    .breadcrumb span { color: #ef3f4d; }

    /* Current plan summary */
    .current-plan-card {
        background: linear-gradient(135deg, #0066cc, #0052a3);
        color: #fff;
        border-radius: 18px;
        padding: 30px 28px;
        margin-bottom: 32px;
        box-shadow: 0 14px 40px rgba(0, 102, 204, 0.25);
    }

    .current-plan-label {
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        opacity: 0.85;
        margin-bottom: 6px;
    }

    .current-plan-name {
        font-size: 30px;
        font-weight: 900;
        margin: 0 0 6px;
    }

    .current-plan-tagline {
        font-size: 15px;
        opacity: 0.9;
        margin-bottom: 22px;
    }

    .plan-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 22px;
        margin-bottom: 20px;
    }

    .plan-stat {
        background: rgba(255,255,255,0.13);
        backdrop-filter: blur(4px);
        border-radius: 12px;
        padding: 16px;
    }
    .plan-stat-label {
        font-size: 12px;
        opacity: 0.85;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .plan-stat-value {
        font-size: 22px;
        font-weight: 800;
    }

    .usage-bar-wrap {
        margin-top: 4px;
    }
    .usage-bar-label {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        margin-bottom: 6px;
        opacity: 0.9;
    }
    .usage-bar {
        height: 8px;
        background: rgba(255,255,255,0.2);
        border-radius: 999px;
        overflow: hidden;
    }
    .usage-bar-fill {
        height: 100%;
        background: #ffffff;
        border-radius: 999px;
        transition: width 0.4s ease;
    }
    .usage-bar-fill.warn { background: #fbbf24; }
    .usage-bar-fill.full { background: #ef4444; }

    .no-plan-card {
        background: #fff8e6;
        border: 1px solid #ffe3a3;
        color: #7a5700;
        padding: 22px 26px;
        border-radius: 14px;
        margin-bottom: 28px;
        font-size: 15px;
        line-height: 1.6;
    }
    .no-plan-card strong { color: #92400e; }

    /* Available plans */
    .plans-section-title {
        font-size: 22px;
        font-weight: 800;
        color: #111;
        margin: 0 0 6px;
    }
    .plans-section-sub {
        color: #6b7280;
        margin: 0 0 22px;
        font-size: 14px;
    }

    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 18px;
    }

    .plan-card {
        position: relative;
        background: #fff;
        border: 2px solid #e5e7eb;
        border-radius: 16px;
        padding: 24px 22px;
        display: flex;
        flex-direction: column;
        transition: 0.2s ease;
    }

    .plan-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.08);
        border-color: #0066cc;
    }

    .plan-card.is-current {
        border-color: #16a34a;
        background: #f0fdf4;
    }

    .plan-card.is-highlighted { border-color: #f59e0b; }

    .plan-badge {
        position: absolute;
        top: -10px;
        left: 18px;
        background: #f59e0b;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 999px;
    }

    .plan-current-badge {
        position: absolute;
        top: -10px;
        right: 18px;
        background: #16a34a;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 999px;
    }

    .plan-card-name {
        margin: 0 0 4px;
        font-size: 17px;
        font-weight: 800;
        color: #111;
    }
    .plan-card-tagline {
        margin: 0 0 14px;
        color: #6b7280;
        font-size: 13px;
        min-height: 18px;
    }
    .plan-card-price {
        display: flex;
        align-items: baseline;
        gap: 6px;
        margin-bottom: 10px;
    }
    .plan-card-price .amount {
        font-size: 28px;
        font-weight: 900;
        color: #ef3f4d;
    }
    .plan-card-price .note {
        font-size: 12px;
        color: #6b7280;
        font-weight: 600;
    }
    .plan-card-limit {
        background: #eef6ff;
        color: #0066cc;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 14px;
    }
    .plan-card-benefits {
        list-style: none;
        padding: 0;
        margin: 0 0 18px;
        font-size: 13px;
        color: #444;
        flex: 1;
    }
    .plan-card-benefits li {
        padding: 5px 0 5px 20px;
        position: relative;
        line-height: 1.5;
    }
    .plan-card-benefits li::before {
        content: '✓';
        position: absolute; left: 0;
        color: #16a34a; font-weight: 900;
    }
    .plan-card-action {
        text-align: center;
        height: 42px;
        line-height: 42px;
        border-radius: 8px;
        background: #0066cc;
        color: #fff;
        font-weight: 800;
        font-size: 14px;
        text-decoration: none;
        transition: 0.2s ease;
    }
    .plan-card-action:hover {
        background: #0052a3;
        transform: translateY(-1px);
        color: #fff;
    }
    .plan-card-action.is-current {
        background: #d1fae5;
        color: #166534;
        cursor: default;
    }
    .plan-card-action.is-current:hover {
        background: #d1fae5;
        transform: none;
    }

    @media (max-width: 1000px) {
        .plan-layout { grid-template-columns: 1fr; }
        .plan-sidebar { order: 2; }
        .plan-main { order: 1; }
        .plan-stats { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 560px) {
        .plan-stats { grid-template-columns: 1fr; }
        .current-plan-card { padding: 22px 20px; }
    }
</style>

<section class="plan-page">
    <div class="plan-wrap">
        <div class="plan-layout">

            <aside class="plan-sidebar">
                <h2>Seller Portal</h2>
                <p>Welcome back,<br><strong><?php echo e($seller['full_name']); ?></strong></p>
                <nav class="plan-menu">
                    <a href="dashboard.php"><span class="menu-icon">▦</span> Dashboard</a>
                    <a href="my-plan.php" class="active"><span class="menu-icon">📦</span> My Plan</a>
                    <a href="payment.php"><span class="menu-icon">□</span> Payment Verification</a>
                    <a href="buggy-list.php"><span class="menu-icon">☰</span> My Buggy Listings</a>
                    <a href="buggy-form.php"><span class="menu-icon">+</span> Add Listing</a>
                    <a href="logout.php"><span class="menu-icon">⏻</span> Logout</a>
                </nav>
            </aside>

            <main class="plan-main">
                <div class="plan-page-title">
                    <h1>My Plan</h1>
                    <p>View your current package, listing usage, and explore other available plans.</p>
                </div>

                <div class="breadcrumb">
                    <a href="dashboard.php">Seller Dashboard</a> &gt; <span>My Plan</span>
                </div>

                <?php if (!empty($seller['pkg_id'])): ?>
                    <div class="current-plan-card">
                        <div class="current-plan-label">Your Current Plan</div>
                        <h2 class="current-plan-name"><?php echo e($seller['pkg_name']); ?></h2>
                        <?php if (!empty($seller['pkg_tagline'])): ?>
                            <p class="current-plan-tagline"><?php echo e($seller['pkg_tagline']); ?></p>
                        <?php endif; ?>

                        <div class="plan-stats">
                            <div class="plan-stat">
                                <div class="plan-stat-label">Amount Paid</div>
                                <div class="plan-stat-value">$<?php echo number_format((float)$seller['pkg_amount'], 0); ?></div>
                            </div>
                            <div class="plan-stat">
                                <div class="plan-stat-label">Listing Limit</div>
                                <div class="plan-stat-value"><?php echo (int)$currentLimit; ?></div>
                            </div>
                            <div class="plan-stat">
                                <div class="plan-stat-label">Listings Used</div>
                                <div class="plan-stat-value"><?php echo (int)$listingCount; ?> / <?php echo (int)$currentLimit; ?></div>
                            </div>
                        </div>

                        <div class="usage-bar-wrap">
                            <div class="usage-bar-label">
                                <span>Usage</span>
                                <span><?php echo round($usagePercent); ?>%</span>
                            </div>
                            <div class="usage-bar">
                                <div class="usage-bar-fill <?php echo $usagePercent >= 100 ? 'full' : ($usagePercent >= 80 ? 'warn' : ''); ?>"
                                     style="width: <?php echo $usagePercent; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-plan-card">
                        <strong>No active package.</strong><br>
                        You don't have an active package yet. Choose a plan below and pay to start posting listings.
                    </div>
                <?php endif; ?>

                <h2 class="plans-section-title">Available Plans</h2>
                <p class="plans-section-sub">Choose a different plan to upgrade. Payment is required again for the new plan.</p>

                <?php if (count($packages) > 0): ?>
                    <div class="plans-grid">
                        <?php foreach ($packages as $p):
                            $benefits = !empty($p['benefits']) ? json_decode($p['benefits'], true) : [];
                            if (!is_array($benefits)) $benefits = [];
                            $isCurrent = ((int)$p['id'] === (int)($seller['pkg_id'] ?? 0));
                        ?>
                            <div class="plan-card <?php echo $isCurrent ? 'is-current' : ''; ?> <?php echo (int)$p['is_highlighted'] === 1 ? 'is-highlighted' : ''; ?>">
                                <?php if ($isCurrent): ?>
                                    <span class="plan-current-badge">✓ Current</span>
                                <?php elseif ((int)$p['is_highlighted'] === 1): ?>
                                    <span class="plan-badge">⭐ Most Popular</span>
                                <?php endif; ?>

                                <h3 class="plan-card-name"><?php echo e($p['name']); ?></h3>
                                <?php if (!empty($p['tagline'])): ?>
                                    <p class="plan-card-tagline"><?php echo e($p['tagline']); ?></p>
                                <?php endif; ?>

                                <div class="plan-card-price">
                                    <span class="amount">$<?php echo number_format((float)$p['amount'], 0); ?></span>
                                    <span class="note">one-time</span>
                                </div>

                                <div class="plan-card-limit">
                                    Up to <?php echo (int)$p['listing_limit']; ?> listing<?php echo (int)$p['listing_limit'] === 1 ? '' : 's'; ?>
                                </div>

                                <?php if (count($benefits) > 0): ?>
                                    <ul class="plan-card-benefits">
                                        <?php foreach ($benefits as $b): ?>
                                            <li><?php echo e($b); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <?php if ($isCurrent): ?>
                                    <div class="plan-card-action is-current">Current Plan</div>
                                <?php else: ?>
                                    <a href="payment.php?package=<?php echo (int)$p['id']; ?>" class="plan-card-action">
                                        Switch to this plan
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-plan-card">
                        No plans available at the moment. Please contact SGBUGGYMART admin.
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>
</section>

<?php include '../footer.php'; ?>
