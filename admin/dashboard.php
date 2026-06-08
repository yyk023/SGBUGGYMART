<?php
require_once '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$addToCart = 0;
$visitors = 0;
$pageViews = 0;
$dashboardError = '';

$topViewedNewBuggies = [];
$topViewedUsedBuggies = [];
$topCartProducts = [];

$chartLabels = [];
$chartAddToCartData = [];
$chartVisitorsData = [];
$chartPageViewsData = [];

try {
    /*
        Check required tables
    */
    $stmt = $pdo->query("SHOW TABLES LIKE 'visitor_logs'");
    $visitorTableExists = $stmt->fetchColumn();

    $stmt = $pdo->query("SHOW TABLES LIKE 'add_to_cart_logs'");
    $cartTableExists = $stmt->fetchColumn();

    if (!$visitorTableExists) {
        $dashboardError .= 'Table visitor_logs does not exist. ';
    }

    if (!$cartTableExists) {
        $dashboardError .= 'Table add_to_cart_logs does not exist. ';
    }

    /*
        Main metrics
    */
    if ($cartTableExists) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM add_to_cart_logs");
        $addToCart = (int)$stmt->fetchColumn();
    }

    if ($visitorTableExists) {
        $stmt = $pdo->query("SELECT COUNT(DISTINCT session_id) FROM visitor_logs");
        $visitors = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM visitor_logs");
        $pageViews = (int)$stmt->fetchColumn();
    }

    /*
        Last 7 days chart data
    */
    $chartStartDate = date('Y-m-d', strtotime('-6 days'));
    $chartEndDate = date('Y-m-d');

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));

        $chartLabels[] = date('M d', strtotime($date));
        $chartAddToCartData[$date] = 0;
        $chartVisitorsData[$date] = 0;
        $chartPageViewsData[$date] = 0;
    }

    if ($cartTableExists) {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(added_at) AS log_date,
                COUNT(*) AS total_cart
            FROM add_to_cart_logs
            WHERE DATE(added_at) BETWEEN :start_date AND :end_date
            GROUP BY DATE(added_at)
        ");

        $stmt->execute([
            ':start_date' => $chartStartDate,
            ':end_date' => $chartEndDate
        ]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $chartAddToCartData[$row['log_date']] = (int)$row['total_cart'];
        }
    }

    if ($visitorTableExists) {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(visited_at) AS log_date,
                COUNT(DISTINCT session_id) AS total_visitors,
                COUNT(*) AS total_page_views
            FROM visitor_logs
            WHERE DATE(visited_at) BETWEEN :start_date AND :end_date
            GROUP BY DATE(visited_at)
        ");

        $stmt->execute([
            ':start_date' => $chartStartDate,
            ':end_date' => $chartEndDate
        ]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $chartVisitorsData[$row['log_date']] = (int)$row['total_visitors'];
            $chartPageViewsData[$row['log_date']] = (int)$row['total_page_views'];
        }
    }

    $chartAddToCartData = array_values($chartAddToCartData);
    $chartVisitorsData = array_values($chartVisitorsData);
    $chartPageViewsData = array_values($chartPageViewsData);

    /*
        Top Viewed Products

        This works when visitor_logs.page_url contains:
        ?id=PRODUCT_ID or &id=PRODUCT_ID
    */
    /*
        Reads the per-buggy total_views counter directly (fast, no REGEXP scan).
        The counter is incremented by includes/track-visitor.php on every detail page view.
    */
    $topViewSql = "
        SELECT id, name, brand, model, image_url, total_views
        FROM buggies
        WHERE buggy_condition = :condition
        AND total_views > 0
        ORDER BY total_views DESC, id DESC
        LIMIT 5
    ";

    try {
        $newStmt = $pdo->prepare($topViewSql);
        $newStmt->execute([':condition' => 'new']);
        $topViewedNewBuggies = $newStmt->fetchAll(PDO::FETCH_ASSOC);

        $usedStmt = $pdo->prepare($topViewSql);
        $usedStmt->execute([':condition' => 'used']);
        $topViewedUsedBuggies = $usedStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $topViewedNewBuggies  = [];
        $topViewedUsedBuggies = [];
    }

    /*
        Top Add To Cart Products
    */
    if ($cartTableExists) {
        $stmt = $pdo->query("
            SELECT 
                b.id,
                b.name,
                b.brand,
                b.model,
                b.image_url,
                SUM(a.quantity) AS total_units
            FROM add_to_cart_logs a
            INNER JOIN buggies b ON b.id = a.buggy_id
            GROUP BY b.id, b.name, b.brand, b.model, b.image_url
            ORDER BY total_units DESC
            LIMIT 5
        ");

        $topCartProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $dashboardError = 'Dashboard SQL Error: ' . $e->getMessage();
}

/*
    Promos ending within the next 24 hours.
    Detects active promos where end_date is between NOW and NOW + 24 hours.
*/
$expiringBuggyPromos     = [];
$expiringAccessoryPromos = [];

try {
    $expiringStmt = $pdo->prepare("
        SELECT id, brand, model, name, selling_price, discount_price, promo_end_date, image_url
        FROM buggies
        WHERE promo_enabled = 1
        AND status IN ('active', 'sold')
        AND discount_price > 0
        AND discount_price < selling_price
        AND promo_end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        ORDER BY promo_end_date ASC
        LIMIT 20
    ");
    $expiringStmt->execute();
    $expiringBuggyPromos = $expiringStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $expiringBuggyPromos = [];
}

try {
    $expiringAccStmt = $pdo->prepare("
        SELECT id, brand, model, name, selling_price, discount_price, promo_end_date, image_url
        FROM accessories
        WHERE promo_enabled = 1
        AND status = 'active'
        AND discount_price > 0
        AND discount_price < selling_price
        AND promo_end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        ORDER BY promo_end_date ASC
        LIMIT 20
    ");
    $expiringAccStmt->execute();
    $expiringAccessoryPromos = $expiringAccStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $expiringAccessoryPromos = [];
}

function dashboardPromoTimeLeft($endDate)
{
    $diff = strtotime($endDate) - time();
    if ($diff <= 0) return 'Expired';
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    if ($hours > 0) return $hours . 'hr ' . $minutes . 'min left';
    return $minutes . ' min left';
}

function dashboardProductImage($imageUrl)
{
    $imageUrl = trim((string)$imageUrl);

    if ($imageUrl === '') {
        return '../images/no-image.png';
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

function dashboardProductName($product)
{
    if (!empty($product['name'])) {
        return $product['name'];
    }

    return trim(($product['brand'] ?? '') . ' ' . ($product['model'] ?? ''));
}

include 'header.php';
?>

<style>
    .admin-brand-logo {
        margin: 0 0 4px;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: 0.5px;
        color: #111;
        line-height: 1.2;
    }

    .admin-brand-logo .buggy-blue {
        color: #1ca0e6;
    }

    .dashboard-filter-bar {
        background: #fff;
        padding: 16px;
        margin-bottom: 24px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.06);
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
    }

    .dashboard-filter-bar label {
        color: #555;
        font-size: 14px;
        margin-right: 4px;
    }

    .date-period-wrap {
        position: relative;
    }

    .date-period-input {
        width: 230px;
        height: 38px;
        border: 1px solid #cfcfcf;
        border-radius: 3px;
        padding: 0 12px;
        font-size: 14px;
        color: #666;
        background: #fff;
        cursor: pointer;
    }

    .date-period-input:focus {
        outline: none;
        border-color: #43a7ff;
        box-shadow: 0 0 0 2px rgba(67,167,255,0.15);
    }

    .period-btn {
        height: 38px;
        border: 1px solid #d5d5d5;
        background: #fff;
        color: #e63246;
        padding: 0 14px;
        cursor: pointer;
        font-size: 13px;
        transition: 0.2s ease;
    }

    .period-btn:hover,
    .period-btn.active {
        background: #df3045;
        color: #fff;
        border-color: #df3045;
    }

    .date-dropdown {
        display: none;
        position: absolute;
        top: 44px;
        left: 0;
        width: 255px;
        background: #fff;
        border-radius: 4px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.16);
        padding: 8px;
        z-index: 1000;
    }

    .date-dropdown.show {
        display: block;
    }

    .date-tabs {
        display: flex;
        margin-bottom: 8px;
    }

    .date-tab {
        border: 0;
        background: #fff;
        color: #e63246;
        padding: 8px 10px;
        cursor: pointer;
        font-size: 13px;
    }

    .date-tab.active {
        background: #df3045;
        color: #fff;
    }

    .custom-range-title {
        background: #df3045;
        color: #fff;
        padding: 8px;
        border-radius: 4px;
        font-size: 13px;
        margin-bottom: 10px;
    }

    .custom-date-row {
        display: flex;
        gap: 8px;
        margin-bottom: 10px;
    }

    .custom-date-row input {
        width: 50%;
        height: 34px;
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 0 8px;
        color: #666;
        font-size: 13px;
    }

    .custom-actions {
        display: flex;
        gap: 8px;
    }

    .apply-date-btn {
        border: 0;
        background: #df3045;
        color: #fff;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
    }

    .cancel-date-btn {
        border: 0;
        background: #f1f1f1;
        color: #348bd6;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
    }

    .dashboard-error-box {
        background: #fff0f0;
        color: #d60000;
        padding: 14px 16px;
        margin-bottom: 18px;
        border: 1px solid #ffb5b5;
        border-radius: 8px;
        font-size: 14px;
    }

    .key-metrics-title {
        margin: 0 0 12px;
        font-size: 18px;
        color: #222;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 24px;
    }

    .dashboard-card {
        background: #fff;
        min-height: 100px;
        padding: 18px 20px;
        box-shadow: 0 8px 22px rgba(0,0,0,0.06);
        position: relative;
        border-top: 3px solid transparent;
    }

    /* ===== Expiring promo alert ===== */
    .promo-alert-card {
        background: linear-gradient(135deg, #fff7ed, #fff1f0);
        border: 1px solid #fdba74;
        border-radius: 14px;
        padding: 18px 22px;
        margin-bottom: 22px;
        box-shadow: 0 4px 14px rgba(249, 115, 22, 0.12);
    }

    .promo-alert-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }

    .promo-alert-head h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
        color: #9a3412;
    }

    .promo-alert-count {
        background: #ef4444;
        color: #ffffff;
        font-size: 12px;
        font-weight: 800;
        padding: 3px 10px;
        border-radius: 999px;
    }

    .promo-alert-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 10px;
    }

    .promo-alert-item {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #ffffff;
        border: 1px solid #fed7aa;
        border-radius: 10px;
        padding: 8px 10px;
        text-decoration: none;
        color: inherit;
        transition: 0.2s ease;
    }

    .promo-alert-item:hover {
        border-color: #ef4444;
        transform: translateY(-1px);
    }

    .promo-alert-thumb {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        object-fit: cover;
        background: #f3f4f6;
        flex-shrink: 0;
    }

    .promo-alert-info {
        flex: 1;
        min-width: 0;
    }

    .promo-alert-name {
        font-size: 13px;
        font-weight: 700;
        color: #1a1a1a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .promo-alert-time {
        font-size: 12px;
        color: #c2410c;
        font-weight: 700;
    }

    .dashboard-card.add-cart-card {
        border-top-color: #16a34a;
    }

    .dashboard-card.visitors-card {
        border-top-color: #8e55cc;
    }

    .dashboard-card.pageviews-card {
        border-top-color: #ff6045;
    }

    .dashboard-card span {
        display: block;
        color: #666;
        font-size: 14px;
        margin-bottom: 8px;
    }

    .dashboard-card span .help-dot,
    .ranking-card .help-dot {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #e63246;
        color: #fff;
        font-size: 9px;
        margin-left: 4px;
        vertical-align: middle;
        font-style: normal;
    }

    .dashboard-card strong {
        display: block;
        font-size: 22px;
        color: #222;
        margin-bottom: 8px;
    }

    .metric-compare {
        color: #777;
        font-size: 13px;
    }

    .metric-neutral {
        color: #333;
        font-weight: bold;
    }

    .chart-card {
        background: #fff;
        padding: 26px;
        margin-bottom: 24px;
        box-shadow: 0 8px 22px rgba(0,0,0,0.06);
    }

    .chart-card h2 {
        margin: 0 0 18px;
        font-size: 17px;
        color: #111;
    }

    .chart-wrap {
        width: 100%;
        height: 330px;
        position: relative;
    }

    #metricsChart {
        width: 100%;
        height: 100%;
        display: block;
    }

    .chart-legend {
        display: flex;
        gap: 18px;
        flex-wrap: wrap;
        margin-top: 14px;
        justify-content: flex-end;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #444;
        font-size: 14px;
    }

    .legend-line {
        width: 28px;
        height: 3px;
        display: inline-block;
        border-radius: 10px;
    }

    .legend-cart {
        background: #16a34a;
    }

    .legend-visitors {
        background: #8e55cc;
    }

    .legend-pageviews {
        background: #ff6045;
    }

    .dashboard-ranking-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 24px;
        margin-bottom: 24px;
        align-items: stretch;
    }

    .ranking-card {
        background: #fff;
        padding: 16px 14px 30px;
        box-shadow: 0 8px 22px rgba(0,0,0,0.06);
        width: 100%;
        min-width: 0;
    }

    .ranking-card h2 {
        margin: 4px 0 12px;
        font-size: 18px;
        color: #333;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .ranking-table {
        width: 100%;
        border-collapse: collapse;
        overflow: hidden;
    }

    .ranking-table thead th {
        background: #df3045;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        padding: 14px 12px;
        text-align: center;
    }

    .ranking-table tbody td {
        padding: 14px 12px;
        border-bottom: 1px solid #ddd;
        color: #555;
        font-size: 14px;
        text-align: center;
        vertical-align: middle;
    }

    .ranking-table tbody tr:nth-child(odd) {
        background: #f7f7f7;
    }

    .ranking-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .ranking-product {
        display: flex;
        align-items: center;
        gap: 12px;
        text-align: left;
    }

    .ranking-product img {
        width: 52px;
        height: 42px;
        object-fit: cover;
        background: #fff;
        border: 1px solid #eee;
    }

    .ranking-product-name {
        color: #666;
        font-size: 14px;
        line-height: 1.35;
    }

    .ranking-number {
        color: #ff1d25;
        font-weight: bold;
    }

    .ranking-empty {
        padding: 30px 12px;
        text-align: center;
        color: #888;
        background: #fafafa;
        border: 1px solid #eee;
        font-size: 14px;
    }

    .dashboard-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    @media (max-width: 1000px) {
        .dashboard-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 900px) {
        .dashboard-ranking-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 650px) {
        .admin-brand-logo {
            font-size: 24px;
        }

        .dashboard-grid {
            grid-template-columns: 1fr;
        }

        .date-period-input {
            width: 100%;
        }

        .date-period-wrap {
            width: 100%;
        }

        .date-dropdown {
            width: 100%;
        }

        .period-btn {
            flex: 1;
        }

        .chart-card {
            padding: 18px;
        }

        .ranking-card {
            padding: 14px 10px 24px;
            overflow-x: auto;
        }

        .ranking-table {
            min-width: 520px;
        }
    }
</style>

<div class="admin-page-title">
    <h1 class="admin-brand-logo">SG<span class="buggy-blue">BUGGY</span>MART Dashboard</h1>
    <p>Welcome back, SGBUGGYMART Admin.</p>
</div>

<?php if ($dashboardError !== ''): ?>
    <div class="dashboard-error-box">
        <?php echo htmlspecialchars($dashboardError); ?>
    </div>
<?php endif; ?>

<?php
    $totalExpiring = count($expiringBuggyPromos) + count($expiringAccessoryPromos);
?>
<?php if ($totalExpiring > 0): ?>
    <div class="promo-alert-card">
        <div class="promo-alert-head">
            <span style="font-size:22px;">⏰</span>
            <h2>Promos Ending Within 24 Hours</h2>
            <span class="promo-alert-count"><?php echo (int)$totalExpiring; ?></span>
        </div>

        <div class="promo-alert-list">
            <?php foreach ($expiringBuggyPromos as $bp): ?>
                <a href="product-form.php?id=<?php echo (int)$bp['id']; ?>" class="promo-alert-item">
                    <img
                        src="<?php echo htmlspecialchars(dashboardProductImage($bp['image_url'])); ?>"
                        alt=""
                        class="promo-alert-thumb"
                        onerror="this.src='../images/no-image.png';"
                    >
                    <div class="promo-alert-info">
                        <div class="promo-alert-name">
                            <?php echo htmlspecialchars($bp['name'] ?: ($bp['brand'] . ' ' . $bp['model'])); ?>
                        </div>
                        <div class="promo-alert-time">
                            ⏰ <?php echo htmlspecialchars(dashboardPromoTimeLeft($bp['promo_end_date'])); ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>

            <?php foreach ($expiringAccessoryPromos as $ap): ?>
                <a href="accessory-form.php?id=<?php echo (int)$ap['id']; ?>" class="promo-alert-item">
                    <img
                        src="<?php echo htmlspecialchars(dashboardProductImage($ap['image_url'])); ?>"
                        alt=""
                        class="promo-alert-thumb"
                        onerror="this.src='../images/no-image.png';"
                    >
                    <div class="promo-alert-info">
                        <div class="promo-alert-name">
                            <?php echo htmlspecialchars($ap['name'] ?: ($ap['brand'] . ' ' . $ap['model'])); ?>
                        </div>
                        <div class="promo-alert-time">
                            ⏰ <?php echo htmlspecialchars(dashboardPromoTimeLeft($ap['promo_end_date'])); ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="dashboard-filter-bar">
    <label>Date Period</label>

    <div class="date-period-wrap">
        <input
            type="text"
            id="datePeriodInput"
            class="date-period-input"
            value="<?php echo date('Y-m-d', strtotime('-7 days')); ?> - <?php echo date('Y-m-d'); ?>"
            readonly
        >

        <div class="date-dropdown" id="dateDropdown">
            <div class="date-tabs">
                <button type="button" class="date-tab active" data-range="day">Day</button>
                <button type="button" class="date-tab" data-range="week">Week</button>
                <button type="button" class="date-tab" data-range="month">Month</button>
                <button type="button" class="date-tab" data-range="year">Year</button>
            </div>

            <div class="custom-range-title">Custom Range</div>

            <div class="custom-date-row">
                <input type="date" id="startDateInput" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                <input type="date" id="endDateInput" value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="custom-actions">
                <button type="button" class="apply-date-btn" id="applyDateBtn">Apply</button>
                <button type="button" class="cancel-date-btn" id="cancelDateBtn">Cancel</button>
            </div>
        </div>
    </div>

    <button type="button" class="period-btn" data-period="yesterday">Yesterday</button>
    <button type="button" class="period-btn active" data-period="past7">Past 7 Days</button>
    <button type="button" class="period-btn" data-period="past30">Past 30 Days</button>
</div>

<h2 class="key-metrics-title">Key Metrics</h2>

<div class="dashboard-grid">
    <div class="dashboard-card visitors-card">
        <span>Visitors <i class="help-dot">?</i></span>
        <strong id="visitorsValue"><?php echo number_format($visitors); ?></strong>
        <div class="metric-compare">
            vs Previous 7 Days <span class="metric-neutral">0.00%</span>
        </div>
    </div>

    <div class="dashboard-card pageviews-card">
        <span>Page Views <i class="help-dot">?</i></span>
        <strong id="pageViewsValue"><?php echo number_format($pageViews); ?></strong>
        <div class="metric-compare">
            vs Previous 7 Days <span class="metric-neutral">0.00%</span>
        </div>
    </div>
</div>

<div class="chart-card">
    <h2>Trend Chart of Each Metric</h2>

    <div class="chart-wrap">
        <canvas id="metricsChart"></canvas>
    </div>

    <div class="chart-legend">
        <div class="legend-item">
            <span class="legend-line legend-visitors"></span>
            Visitors
        </div>

        <div class="legend-item">
            <span class="legend-line legend-pageviews"></span>
            Page Views
        </div>
    </div>
</div>

<div class="dashboard-ranking-grid">
    <div class="ranking-card">
        <h2>Top Views New Buggy <i class="help-dot">?</i></h2>

        <?php if (count($topViewedNewBuggies) > 0): ?>
            <table class="ranking-table">
                <thead>
                    <tr>
                        <th style="width: 90px;">Ranking</th>
                        <th>Product Information</th>
                        <th style="width: 120px;">Page Views</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($topViewedNewBuggies as $index => $product): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>

                            <td>
                                <div class="ranking-product">
                                    <img
                                        src="<?php echo htmlspecialchars(dashboardProductImage($product['image_url'])); ?>"
                                        alt="Product"
                                        onerror="this.src='../images/no-image.png';"
                                    >

                                    <span class="ranking-product-name">
                                        <?php echo htmlspecialchars(dashboardProductName($product)); ?>
                                    </span>
                                </div>
                            </td>

                            <td class="ranking-number">
                                <?php echo number_format((int)$product['total_views']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="ranking-empty">
                No new buggy view data yet.
            </div>
        <?php endif; ?>
    </div>

    <div class="ranking-card">
        <h2>Top Views Used Buggy <i class="help-dot">?</i></h2>

        <?php if (count($topViewedUsedBuggies) > 0): ?>
            <table class="ranking-table">
                <thead>
                    <tr>
                        <th style="width: 90px;">Ranking</th>
                        <th>Product Information</th>
                        <th style="width: 120px;">Page Views</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($topViewedUsedBuggies as $index => $product): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>

                            <td>
                                <div class="ranking-product">
                                    <img
                                        src="<?php echo htmlspecialchars(dashboardProductImage($product['image_url'])); ?>"
                                        alt="Product"
                                        onerror="this.src='../images/no-image.png';"
                                    >

                                    <span class="ranking-product-name">
                                        <?php echo htmlspecialchars(dashboardProductName($product)); ?>
                                    </span>
                                </div>
                            </td>

                            <td class="ranking-number">
                                <?php echo number_format((int)$product['total_views']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="ranking-empty">
                No used buggy view data yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2 style="margin-top:0;">Quick Actions</h2>
    <p style="color:#777; margin-bottom:0;">
        Manage buggy products, add new listings, update prices, change product images and view the website.
    </p>

    <div class="dashboard-actions">
        <a href="product-list.php" class="btn">View Product List</a>
        <a href="product-add.php" class="btn">Add Product</a>
        <a href="../index.php" target="_blank" class="btn btn-secondary">View Website</a>
    </div>
</div>

<script>
    const datePeriodInput = document.getElementById('datePeriodInput');
    const dateDropdown = document.getElementById('dateDropdown');
    const startDateInput = document.getElementById('startDateInput');
    const endDateInput = document.getElementById('endDateInput');
    const applyDateBtn = document.getElementById('applyDateBtn');
    const cancelDateBtn = document.getElementById('cancelDateBtn');
    const periodButtons = document.querySelectorAll('.period-btn');
    const dateTabs = document.querySelectorAll('.date-tab');

    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return year + '-' + month + '-' + day;
    }

    function setDateRange(startDate, endDate) {
        startDateInput.value = formatDate(startDate);
        endDateInput.value = formatDate(endDate);
        datePeriodInput.value = formatDate(startDate) + ' - ' + formatDate(endDate);
        drawChart();
    }

    datePeriodInput.addEventListener('click', function () {
        dateDropdown.classList.toggle('show');
    });

    applyDateBtn.addEventListener('click', function () {
        if (startDateInput.value && endDateInput.value) {
            datePeriodInput.value = startDateInput.value + ' - ' + endDateInput.value;
            dateDropdown.classList.remove('show');

            periodButtons.forEach(function (btn) {
                btn.classList.remove('active');
            });

            drawChart();
        }
    });

    cancelDateBtn.addEventListener('click', function () {
        dateDropdown.classList.remove('show');
    });

    dateTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            dateTabs.forEach(function (item) {
                item.classList.remove('active');
            });

            this.classList.add('active');

            const today = new Date();
            const start = new Date(today);

            if (this.dataset.range === 'day') {
                start.setDate(today.getDate());
            }

            if (this.dataset.range === 'week') {
                start.setDate(today.getDate() - 7);
            }

            if (this.dataset.range === 'month') {
                start.setMonth(today.getMonth() - 1);
            }

            if (this.dataset.range === 'year') {
                start.setFullYear(today.getFullYear() - 1);
            }

            setDateRange(start, today);
        });
    });

    periodButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            periodButtons.forEach(function (btn) {
                btn.classList.remove('active');
            });

            this.classList.add('active');

            const today = new Date();
            const start = new Date(today);

            if (this.dataset.period === 'yesterday') {
                start.setDate(today.getDate() - 1);

                const end = new Date(today);
                end.setDate(today.getDate() - 1);

                setDateRange(start, end);
            }

            if (this.dataset.period === 'past7') {
                start.setDate(today.getDate() - 7);
                setDateRange(start, today);
            }

            if (this.dataset.period === 'past30') {
                start.setDate(today.getDate() - 30);
                setDateRange(start, today);
            }
        });
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.date-period-wrap')) {
            dateDropdown.classList.remove('show');
        }
    });

    const canvas = document.getElementById('metricsChart');
    const ctx = canvas.getContext('2d');

    function resizeCanvas() {
        const parent = canvas.parentElement;
        canvas.width = parent.clientWidth;
        canvas.height = parent.clientHeight;
    }

    function drawLine(points, color, maxValue, chartLeft, chartTop, chartWidth, chartHeight) {
        ctx.beginPath();
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;

        points.forEach(function (value, index) {
            const x = chartLeft + (chartWidth / (points.length - 1)) * index;
            const y = chartTop + chartHeight - ((value / maxValue) * chartHeight);

            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });

        ctx.stroke();
    }

    function drawChart() {
        resizeCanvas();

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        const labels = <?php echo json_encode($chartLabels); ?>;
        const visitorsData = <?php echo json_encode($chartVisitorsData); ?>;
        const pageViewsData = <?php echo json_encode($chartPageViewsData); ?>;

        // Fixed Y-axis scale: 0, 100, 200, 300, 400, 500
        const maxValue = 500;

        const chartLeft = 55;
        const chartTop = 20;
        const chartWidth = canvas.width - 80;
        const chartHeight = canvas.height - 70;

        ctx.font = '12px Arial';
        ctx.fillStyle = '#555';
        ctx.strokeStyle = '#e5e5e5';
        ctx.lineWidth = 1;

        for (let i = 0; i <= 5; i++) {
            const value = (maxValue / 5) * i;
            const y = chartTop + chartHeight - ((value / maxValue) * chartHeight);

            ctx.beginPath();
            ctx.moveTo(chartLeft, y);
            ctx.lineTo(chartLeft + chartWidth, y);
            ctx.stroke();

            ctx.fillStyle = '#555';
            ctx.fillText(Math.round(value).toLocaleString(), 5, y + 4);
        }

        labels.forEach(function (label, index) {
            const x = chartLeft + (chartWidth / (labels.length - 1)) * index;

            ctx.beginPath();
            ctx.moveTo(x, chartTop);
            ctx.lineTo(x, chartTop + chartHeight);
            ctx.stroke();

            ctx.fillStyle = '#555';
            ctx.fillText(label, x - 15, chartTop + chartHeight + 28);
        });

        drawLine(visitorsData, '#8e55cc', maxValue, chartLeft, chartTop, chartWidth, chartHeight);
        drawLine(pageViewsData, '#ff6045', maxValue, chartLeft, chartTop, chartWidth, chartHeight);
    }

    window.addEventListener('resize', drawChart);
    drawChart();
</script>

<?php include 'footer.php'; ?>