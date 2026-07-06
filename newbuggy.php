<?php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function buggyImagePath($imageUrl)
{
    $imageUrl = trim((string)$imageUrl);

    if ($imageUrl === '') {
        return 'images/no-image.png';
    }

    if (preg_match('/^https?:\/\//i', $imageUrl)) {
        return $imageUrl;
    }

    if (strpos($imageUrl, '../') === 0) {
        return substr($imageUrl, 3);
    }

    if (strpos($imageUrl, 'images/') === 0) {
        return $imageUrl;
    }

    return 'images/' . $imageUrl;
}

function formatPrice($price)
{
    $price = (float)$price;

    if ($price <= 0) {
        return 'Price on request';
    }

    return '$' . number_format($price, 0);
}

function isPromoActive($buggy)
{
    if (empty($buggy['promo_enabled']) || (int)$buggy['promo_enabled'] !== 1) return false;
    if (empty($buggy['promo_end_date'])) return false;
    if (empty($buggy['discount_price']) || (float)$buggy['discount_price'] >= (float)$buggy['selling_price']) return false;

    return strtotime($buggy['promo_end_date']) > time();
}

function formatPromoTimeLeft($endDate)
{
    $diff = strtotime($endDate) - time();

    if ($diff <= 0) return 'Expired';

    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);

    if ($days > 0) {
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ' . $hours . 'hr left';
    }

    if ($hours > 0) {
        return $hours . 'hr ' . $minutes . 'min left';
    }

    return $minutes . ' min left';
}

function formatSeatsLabel($seats)
{
    $seats = trim((string)$seats);

    if ($seats === '') {
        return 'Seats not specified';
    }

    if (stripos($seats, 'seater') !== false) {
        return ucwords($seats);
    }

    return $seats . ' Seater';
}

function removeQueryParam($key)
{
    $query = $_GET;
    unset($query[$key]);

    $path = strtok($_SERVER['REQUEST_URI'], '?');
    $queryString = http_build_query($query);

    return $queryString ? $path . '?' . $queryString : $path;
}

$keyword = trim($_GET['keyword'] ?? '');
$brand = trim($_GET['brand'] ?? '');
$seats = trim($_GET['seats'] ?? '');
$minPrice = trim($_GET['min_price'] ?? '');
$maxPrice = trim($_GET['max_price'] ?? '');
$year = trim($_GET['year'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$orderByMap = [
    'price_low'  => "(status = 'sold') ASC, selling_price ASC, id DESC",
    'price_high' => "(status = 'sold') ASC, selling_price DESC, id DESC",
    'newest'     => "(status = 'sold') ASC, sort_new DESC, created_at DESC, id DESC",
];
$orderBy = $orderByMap[$sort] ?? $orderByMap['newest'];

$currentYear = (int)date('Y');
$startYear = $currentYear;
$endYear = 2016;

$priceRangeOptions = [
    5000,
    8000,
    10000,
    12000,
    15000,
    18000,
    20000
];

$where = [];
$params = [];

$where[] = "status IN ('active', 'sold')";
$where[] = "buggy_condition = 'new'";
$where[] = "listing_type IN ('sale', 'sale_rent')";

if ($keyword !== '') {
    $where[] = "(
        brand LIKE :keyword_brand
        OR model LIKE :keyword_model
        OR name LIKE :keyword_name
        OR short_info LIKE :keyword_short_info
        OR description LIKE :keyword_description
    )";

    $params[':keyword_brand'] = '%' . $keyword . '%';
    $params[':keyword_model'] = '%' . $keyword . '%';
    $params[':keyword_name'] = '%' . $keyword . '%';
    $params[':keyword_short_info'] = '%' . $keyword . '%';
    $params[':keyword_description'] = '%' . $keyword . '%';
}

if ($brand !== '') {
    $where[] = "brand = :brand";
    $params[':brand'] = $brand;
}

if ($seats !== '') {
    $where[] = "(seats = :seats OR seats = :seats_label)";
    $params[':seats'] = $seats;
    $params[':seats_label'] = $seats . ' seater';
}

if ($minPrice !== '') {
    $where[] = "selling_price >= :min_price";
    $params[':min_price'] = (float)$minPrice;
}

if ($maxPrice !== '') {
    $where[] = "selling_price <= :max_price";
    $params[':max_price'] = (float)$maxPrice;
}

if ($year !== '') {
    $where[] = "buggy_year = :buggy_year";
    $params[':buggy_year'] = (int)$year;
}

$whereSql = implode(' AND ', $where);

/* Pagination */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM buggies WHERE $whereSql");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));

if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$mainSql = "SELECT * FROM buggies WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($mainSql);
$stmt->execute($params);
$newBuggies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalResults = $totalCount;

/* Pagination URL helper */
function pageUrl($pageNum)
{
    $params = $_GET;
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}

$brandStmt = $pdo->prepare("
    SELECT DISTINCT brand
    FROM buggies
    WHERE status = 'active'
    AND buggy_condition = 'new'
    AND listing_type IN ('sale', 'sale_rent')
    AND brand IS NOT NULL
    AND brand != ''
    ORDER BY brand ASC
");
$brandStmt->execute();
$brandList = $brandStmt->fetchAll(PDO::FETCH_COLUMN);

$launchStmt = $pdo->prepare("
    SELECT *
    FROM buggies
    WHERE status = 'active'
    AND buggy_condition = 'new'
    AND listing_type IN ('sale', 'sale_rent')
    ORDER BY sort_new DESC, created_at DESC, id DESC
    LIMIT 8
");
$launchStmt->execute();
$newLaunches = $launchStmt->fetchAll(PDO::FETCH_ASSOC);

$hasActiveFilters = (
    $keyword !== ''
    || $brand !== ''
    || $seats !== ''
    || $minPrice !== ''
    || $maxPrice !== ''
    || $year !== ''
);

// Fetch side ads for "side_new_buggy"
$sideAds = [];
try {
    $adStmt = $pdo->prepare("
        SELECT * FROM banners
        WHERE type = 'ad' AND location = 'side_new_buggy' AND status = 'active'
        ORDER BY sort_order ASC, id ASC LIMIT 5
    ");
    $adStmt->execute();
    $sideAds = $adStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $sideAds = [];
}

// Fetch latest accessories to show under the new buggy listings
$relatedAccessories = [];
try {
    $accStmt = $pdo->prepare("
        SELECT * FROM accessories
        WHERE status = 'active'
        ORDER BY created_at DESC, id DESC
        LIMIT 8
    ");
    $accStmt->execute();
    $relatedAccessories = $accStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $relatedAccessories = [];
}

function newbuggyAccImagePath($imageUrl)
{
    $imageUrl = trim((string)$imageUrl);
    if ($imageUrl === '') return 'images/no-image.png';
    if (preg_match('/^https?:\/\//i', $imageUrl)) return $imageUrl;
    if (strpos($imageUrl, 'images/') === 0) return $imageUrl;
    return 'images/' . $imageUrl;
}

function newbuggyAccFormatPrice($price)
{
    $price = (float)$price;
    if ($price <= 0) return 'Price on request';
    return '$' . number_format($price, 0);
}

include 'header.php';
?>

<style>
    body {
        background: #ffffff;
    }

    .newbuggy-page {
        max-width: 1400px;
        margin: 0 auto;
        padding: 26px 18px 60px;
    }

    .top-promo-banner {
        width: 100%;
        min-height: 86px;
        border-radius: 4px;
        background: linear-gradient(90deg, #0066cc, #1e88e5);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        padding: 18px 34px;
        color: #ffffff;
        overflow: hidden;
        margin-bottom: 28px;
    }

    .top-promo-content h2 {
        margin: 0;
        font-size: 24px;
        line-height: 1.25;
        font-weight: 800;
    }

    .top-promo-content p {
        margin: 6px 0 0;
        font-size: 15px;
        opacity: 0.95;
    }

    .top-promo-btn {
        border: 0;
        background: #ffffff;
        color: #0066cc;
        padding: 11px 20px;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
        transition: 0.2s ease;
    }

    .top-promo-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(0,0,0,0.15);
    }

    /* ========== Compact Filter Bar + Modal ========== */
    .ubuggy-search-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 22px; background: #fff; border-radius: 22px; padding: 18px 24px; box-shadow: 0 10px 32px rgba(0,0,0,0.08); }
    .ubuggy-search-input { flex: 1; height: 46px; border: 1px solid #d8dde4; border-radius: 999px; padding: 0 18px; font-size: 15px; color: #333; background: #fff; outline: none; transition: 0.2s ease; }
    .ubuggy-search-input:focus { border-color: #0066cc; box-shadow: 0 0 0 3px rgba(0,102,204,0.08); }
    .ubuggy-filter-btn { height: 46px; padding: 0 20px; border: 2px solid #1f2937; border-radius: 999px; background: #fff; color: #1f2937; font-size: 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s ease; }
    .ubuggy-filter-btn:hover { background: #1f2937; color: #fff; }
    .ubuggy-filter-count { background: #ef3f4d; color: #fff; border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 800; line-height: 1; margin-left: 4px; }
    .ubuggy-search-btn { height: 46px; padding: 0 28px; border: 0; border-radius: 999px; background: #0066cc; color: #fff; font-size: 14px; font-weight: 900; cursor: pointer; transition: 0.2s ease; }
    .ubuggy-search-btn:hover { background: #005bb8; transform: translateY(-1px); }
    .ubuggy-filter-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 20px; }
    .ubuggy-filter-overlay.active { display: flex; }
    .ubuggy-filter-modal { width: 100%; max-width: 640px; max-height: 90vh; background: #fff; border-radius: 18px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
    .ubuggy-filter-head { padding: 18px 24px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; }
    .ubuggy-filter-head h2 { margin: 0; font-size: 20px; font-weight: 800; color: #111827; }
    .ubuggy-filter-close { width: 36px; height: 36px; border: 0; border-radius: 50%; background: #f3f4f6; color: #555; font-size: 24px; line-height: 1; cursor: pointer; }
    .ubuggy-filter-close:hover { background: #e5e7eb; }
    .ubuggy-filter-body { padding: 22px 24px; overflow-y: auto; flex: 1; }
    .ubuggy-fl-title { margin: 18px 0 10px; font-size: 14px; font-weight: 800; color: #111827; }
    .ubuggy-fl-title:first-child { margin-top: 0; }
    .ubuggy-fl-range { display: flex; align-items: center; gap: 10px; }
    .ubuggy-fl-range select { flex: 1; height: 44px; border: 1px solid #d8dde4; border-radius: 999px; padding: 0 14px; background: #fff; font-size: 14px; outline: none; }
    .ubuggy-fl-to { color: #6b7280; font-size: 13px; font-weight: 600; }
    .ubuggy-fl-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .ubuggy-chip { height: 36px; padding: 0 14px; border: 1px solid #d8dde4; border-radius: 999px; background: #fff; color: #1f2937; font-size: 13px; font-weight: 700; cursor: pointer; transition: 0.2s ease; }
    .ubuggy-chip:hover { border-color: #0066cc; color: #0066cc; }
    .ubuggy-chip.active { background: #ef3f4d; border-color: #ef3f4d; color: #fff; }
    .ubuggy-filter-foot { padding: 16px 24px; border-top: 1px solid #e5e7eb; display: flex; gap: 10px; justify-content: space-between; }
    .ubuggy-fl-clear { height: 44px; padding: 0 22px; border: 1px solid #d8dde4; border-radius: 999px; background: #fff; color: #1f2937; font-weight: 700; cursor: pointer; }
    .ubuggy-fl-clear:hover { border-color: #ef3f4d; color: #ef3f4d; }
    .ubuggy-fl-apply { flex: 1; height: 44px; padding: 0 22px; border: 0; border-radius: 999px; background: #0066cc; color: #fff; font-weight: 800; cursor: pointer; }
    .ubuggy-fl-apply:hover { background: #005bb8; }
    @media (max-width: 620px) {
        .ubuggy-search-bar { flex-direction: column; align-items: stretch; padding: 18px; border-radius: 18px; }
        .ubuggy-search-input, .ubuggy-filter-btn, .ubuggy-search-btn { height: 54px; font-size: 16px; }
        .ubuggy-filter-btn, .ubuggy-search-btn { width: 100%; justify-content: center; }
    }
    /* ========== END Compact Filter ========== */

    /* ========== Accessories under New Buggy ========== */
    .newbuggy-accessories-section {
        margin-top: 50px;
        padding-top: 32px;
        border-top: 2px solid #f3f4f6;
    }

    .nb-acc-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
        margin-top: 22px;
    }

    .nb-acc-card {
        border: 1px solid #eeeeee;
        border-radius: 10px;
        background: #ffffff;
        overflow: hidden;
        text-decoration: none;
        color: #222;
        transition: 0.2s ease;
        display: block;
    }

    .nb-acc-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.08);
        border-color: #0066cc;
    }

    .nb-acc-image {
        width: 100%;
        height: 160px;
        background: #f3f3f3;
        position: relative;
        overflow: hidden;
    }

    .nb-acc-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: 0.3s ease;
    }

    .nb-acc-card:hover .nb-acc-image img {
        transform: scale(1.05);
    }

    .nb-acc-tag {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #0066cc;
        color: #fff;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
    }

    .nb-acc-body { padding: 14px; }

    .nb-acc-title {
        font-size: 15px;
        font-weight: 800;
        margin: 0 0 7px;
        color: #1a1a1a;
        line-height: 1.35;
        min-height: 40px;
    }

    .nb-acc-meta {
        font-size: 13px;
        color: #6a7280;
        margin-bottom: 10px;
        line-height: 1.4;
    }

    .nb-acc-price {
        font-size: 17px;
        color: #0066cc;
        font-weight: 900;
    }

    @media (max-width: 980px) {
        .nb-acc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 620px) {
        .nb-acc-grid { grid-template-columns: 1fr; }
    }
    /* ========== END Accessories ========== */

    .newbuggy-search-bar {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        align-items: center;
        margin-bottom: 22px;
        background: #ffffff;
        border-radius: 22px;
        padding: 28px 32px;
        box-shadow: 0 10px 32px rgba(0, 0, 0, 0.08);
    }

    .newbuggy-keyword-field {
        grid-column: span 2;
    }

    .newbuggy-price-range-field {
        grid-column: span 2;
    }

    .newbuggy-seat-field,
    .newbuggy-brand-field,
    .newbuggy-sort-field,
    .newbuggy-button-field {
        grid-column: span 1;
    }

    .newbuggy-search-bar input,
    .newbuggy-search-bar select {
        width: 100%;
        height: 46px;
        border: 1px solid #d8dde4;
        border-radius: 999px;
        padding: 0 16px;
        font-size: 14px;
        color: #333333;
        background: #ffffff;
        outline: none;
        transition: 0.2s ease;
        box-sizing: border-box;
    }

    .newbuggy-search-bar input::placeholder {
        color: #888888;
    }

    .newbuggy-search-bar input:focus,
    .newbuggy-search-bar select:focus {
        border-color: #0066cc;
        box-shadow: 0 0 0 3px rgba(0,102,204,0.08);
    }

    .newbuggy-price-field {
        width: 100%;
        height: 46px;
        border: 1px solid #d8dde4;
        border-radius: 999px;
        background: #ffffff;
        display: grid;
        grid-template-columns: 1fr 1px 1fr;
        align-items: center;
        overflow: hidden;
        box-sizing: border-box;
    }

    .newbuggy-price-field select {
        width: 100%;
        height: 44px;
        border: 0;
        border-radius: 0;
        padding: 0 14px;
        font-size: 14px;
        color: #333333;
        background: transparent;
        box-sizing: border-box;
        cursor: pointer;
        box-shadow: none;
    }

    .newbuggy-price-field select:focus {
        border: 0;
        box-shadow: none;
        outline: none;
    }

    .newbuggy-price-divider {
        width: 1px;
        height: 26px;
        background: #d8dde4;
        display: block;
    }

    .newbuggy-price-field option:disabled,
    .price-option-disabled {
        color: #bbbbbb;
        background: #f5f5f5;
    }

    .newbuggy-search-btn {
        width: 100%;
        height: 46px;
        border: 0;
        border-radius: 999px;
        background: #0066cc;
        color: #ffffff;
        font-size: 14px;
        font-weight: 900;
        cursor: pointer;
        transition: 0.25s ease;
    }

    .newbuggy-search-btn:hover {
        background: #005bb8;
        transform: translateY(-1px);
    }

    .result-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin: 18px 0 22px;
        padding: 18px 0;
        border-top: 1px solid #eeeeee;
        border-bottom: 1px solid #eeeeee;
    }

    .result-header h1 {
        margin: 0;
        font-size: 27px;
        line-height: 1.2;
        color: #141414;
        font-weight: 900;
    }

    .result-header p {
        margin: 6px 0 0;
        color: #6a7280;
        font-size: 15px;
    }

    .clear-search-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 0 16px;
        border-radius: 999px;
        border: 1px solid #0066cc;
        color: #0066cc;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
    }

    .clear-search-link:hover {
        background: #0066cc;
        color: #ffffff;
    }

    .active-search-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 30px;
    }

    .search-tag {
        display: inline-flex;
        align-items: center;
        min-height: 36px;
        padding: 0 14px;
        border-radius: 999px;
        background: #eef6ff;
        color: #0066cc;
        border: 1px solid #cfe5ff;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
    }

    .search-tag:hover {
        background: #0066cc;
        color: #ffffff;
    }

    .section-title {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
    }

    .section-title::before {
        content: "";
        width: 4px;
        height: 24px;
        background: #0066cc;
        border-radius: 999px;
        display: block;
    }

    .section-title h2 {
        margin: 0;
        font-size: 23px;
        color: #141414;
        font-weight: 800;
    }

    .explore-layout {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 34px;
        align-items: start;
        margin-bottom: 38px;
    }

    .explore-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .explore-tab {
        border: 0;
        background: #f7f7f8;
        color: #555;
        padding: 12px 23px;
        border-radius: 999px;
        font-size: 15px;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .explore-tab.active {
        background: #eef6ff;
        color: #0066cc;
        font-weight: 800;
    }

    .explore-tab:hover {
        background: #eef6ff;
        color: #0066cc;
    }

    .tab-panel {
        display: none;
    }

    .tab-panel.active {
        display: block;
    }

    .brand-grid,
    .seat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(120px, 1fr));
        gap: 14px 28px;
    }

    .brand-link,
    .seat-link {
        color: #5c6570;
        font-size: 15px;
        text-decoration: none;
        transition: 0.2s ease;
    }

    .brand-link:hover,
    .seat-link:hover {
        color: #0066cc;
        transform: translateX(3px);
    }

    .launch-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .launch-item {
        display: flex;
        gap: 12px;
        align-items: center;
        border: 1px solid #eeeeee;
        border-radius: 12px;
        padding: 10px;
        text-decoration: none;
        color: #222;
        transition: 0.2s ease;
    }

    .launch-item:hover {
        border-color: #0066cc;
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        transform: translateY(-2px);
    }

    .launch-item img {
        width: 88px;
        height: 62px;
        object-fit: cover;
        border-radius: 8px;
        background: #f1f1f1;
    }

    .launch-item strong {
        display: block;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .launch-item span {
        font-size: 13px;
        color: #0066cc;
        font-weight: 800;
    }

    /* Side ad slider */
    .side-ad-slider { display: flex; flex-direction: column; gap: 12px; }
    .side-ad-slides { position: relative; width: 100%; aspect-ratio: 4 / 3; border-radius: 8px; overflow: hidden; }
    .side-ad-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 0.5s ease; pointer-events: none; }
    .side-ad-slide.active { opacity: 1; pointer-events: auto; }
    .side-ad-slide img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .side-ad-slide a { display: block; width: 100%; height: 100%; }
    .side-ad-controls { display: flex; align-items: flex-end; justify-content: center; gap: 14px; padding: 4px 0 0; }
    .side-ad-arrow { width: 28px; height: 28px; border: 0; border-radius: 50%; background: transparent; color: #111827; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 0; }
    .side-ad-arrow svg { width: 22px; height: 22px; display: block; }
    .side-ad-dots { display: flex; align-items: center; gap: 6px; height: 28px; }
    .side-ad-dot { width: 10px; height: 10px; border: 0; border-radius: 50%; background: #d1d5db; cursor: pointer; transition: 0.2s ease; padding: 0; }
    .side-ad-dot.active { background: #ef3f4d; width: 24px; border-radius: 999px; }

    .side-promo {
        display: grid;
        gap: 14px;
    }

    .side-promo-box {
        border: 1px solid #e4e8ee;
        border-radius: 4px;
        min-height: 210px;
        background:
            radial-gradient(circle at 20% 70%, rgba(0,102,204,0.15), transparent 28%),
            linear-gradient(135deg, #ffffff, #f5f7fb);
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 22px;
    }

    .side-promo-box h3 {
        margin: 0;
        font-size: 25px;
        color: #1d4fa3;
        line-height: 1.15;
        font-weight: 900;
    }

    .side-promo-box p {
        margin: 12px 0 0;
        color: #0066cc;
        font-weight: 900;
        font-size: 15px;
    }

    .small-promo-box {
        border-radius: 7px;
        background: #f3f7ff;
        padding: 15px;
        display: flex;
        gap: 14px;
        align-items: center;
    }

    .small-promo-icon {
        width: 62px;
        height: 46px;
        border-radius: 12px;
        background: #dfeaff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 900;
        color: #0b55e5;
    }

    .small-promo-box strong {
        display: block;
        color: #0b55e5;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .small-promo-box span {
        color: #333;
        font-size: 13px;
        line-height: 1.35;
    }

    .promo-heading-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 18px;
    }

    .promo-heading-row .section-title {
        margin-bottom: 0;
    }

    .view-all-link {
        color: #0066cc;
        text-decoration: none;
        font-size: 15px;
    }

    .view-all-link:hover {
        color: #005bb8;
    }

    .product-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }

    .buggy-card {
        border: 1px solid #eeeeee;
        border-radius: 10px;
        background: #ffffff;
        overflow: hidden;
        text-decoration: none;
        color: #222;
        transition: 0.2s ease;
        display: block;
    }

    .buggy-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.1);
        border-color: #0066cc;
    }

    .buggy-card-image {
        width: 100%;
        height: 160px;
        background: #f3f3f3;
        position: relative;
        overflow: hidden;
    }

    .buggy-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: 0.3s ease;
    }

    .buggy-card:hover .buggy-card-image img {
        transform: scale(1.05);
    }

    .card-tag {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #0066cc;
        color: #ffffff;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
    }

    .buggy-card-body {
        padding: 14px;
    }

    .buggy-card-title {
        font-size: 15px;
        font-weight: 800;
        margin: 0 0 7px;
        color: #1a1a1a;
        line-height: 1.35;
        min-height: 40px;
    }

    .buggy-card-meta {
        font-size: 13px;
        color: #6a7280;
        margin-bottom: 10px;
    }

    .buggy-card-price {
        font-size: 17px;
        color: #0066cc;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .buggy-card.has-promo {
        border-color: #f97316;
        box-shadow: 0 4px 14px rgba(249, 115, 22, 0.18);
    }

    .promo-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: linear-gradient(135deg, #ef4444, #f97316);
        color: #fff;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 10px rgba(249, 115, 22, 0.35);
        z-index: 2;
        animation: promoPulse 2s ease-in-out infinite;
    }

    @keyframes promoPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.06); }
    }

    .buggy-card-original-price {
        color: #6b7280;
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 2px;
        line-height: 1.1;
    }

    .buggy-card-original-price s {
        text-decoration: line-through;
        text-decoration-color: #ef4444;
        text-decoration-thickness: 2px;
    }

    .buggy-card-price.promo {
        color: #ef4444;
        font-size: 15px;
        font-weight: 700;
    }

    .buggy-card-price.promo::before {
        content: 'Now: ';
        color: #6b7280;
        font-size: 12px;
        font-weight: 600;
    }

    .buggy-card-countdown {
        display: inline-block;
        margin-top: 6px;
        background: #fff7ed;
        color: #c2410c;
        font-size: 12px;
        font-weight: 700;
        padding: 5px 10px;
        border-radius: 6px;
        border: 1px solid #fed7aa;
    }

    .buggy-card-note {
        font-size: 13px;
        color: #333;
    }

    .card-sold-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #222222;
        color: #ffffff;
        font-size: 11px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 50px;
        z-index: 3;
        letter-spacing: 0.5px;
    }

    .buggy-card.is-sold .buggy-card-image::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.28);
        z-index: 2;
    }

    .buggy-card.is-sold .buggy-card-note {
        color: #ef3f4d;
        font-weight: 700;
    }

    .empty-box {
        border: 1px dashed #d8dde4;
        border-radius: 14px;
        padding: 38px 18px;
        text-align: center;
        color: #777;
        background: #fafafa;
    }

    .pagination {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 36px;
    }

    .page-link {
        min-width: 40px;
        height: 40px;
        padding: 0 14px;
        border-radius: 999px;
        border: 1px solid #d8dde4;
        background: #ffffff;
        color: #333;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s ease;
    }

    .page-link:hover {
        border-color: #0066cc;
        color: #0066cc;
    }

    .page-link.is-active {
        background: #0066cc;
        border-color: #0066cc;
        color: #ffffff;
    }

    .page-link.is-disabled {
        opacity: 0.4;
        pointer-events: none;
    }

    .page-ellipsis {
        color: #999;
        padding: 0 4px;
        font-weight: 700;
    }

    @media (max-width: 1080px) {
        .newbuggy-search-bar {
            grid-template-columns: 1fr 1fr;
        }

        .newbuggy-keyword-field,
        .newbuggy-price-range-field,
        .newbuggy-seat-field,
        .newbuggy-brand-field,
        .newbuggy-sort-field,
        .newbuggy-button-field {
            grid-column: 1 / -1;
        }

        .newbuggy-search-btn {
            width: 100%;
        }
    }

    @media (max-width: 980px) {
        .explore-layout {
            grid-template-columns: 1fr;
        }

        .product-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .brand-grid,
        .seat-grid {
            grid-template-columns: repeat(3, minmax(100px, 1fr));
        }
    }

    @media (max-width: 780px) {
        .result-header {
            display: block;
        }

        .clear-search-link {
            margin-top: 14px;
        }
    }

    @media (max-width: 620px) {
        .newbuggy-page {
            padding: 18px 14px 45px;
        }

        .top-promo-banner {
            display: block;
            padding: 18px;
        }

        .top-promo-btn {
            display: inline-block;
            margin-top: 14px;
        }

        .newbuggy-search-bar {
            grid-template-columns: 1fr;
            padding: 22px 18px;
            border-radius: 18px;
        }

        .brand-grid,
        .seat-grid,
        .launch-list,
        .product-grid {
            grid-template-columns: 1fr;
        }

        .buggy-card-image {
            height: 210px;
        }
    }
</style>

<div class="newbuggy-page">

    <section class="top-promo-banner">
        <div class="top-promo-content">
            <h2>Explore New Electric Buggies at SGBUGGYMART</h2>
            <p>Find new buggies for sale, events, resorts, golf clubs and private use.</p>
        </div>

        <a href="newbuggy.php" class="top-promo-btn">VIEW NEW BUGGY</a>
    </section>

    <!-- Compact one-line filter bar -->
    <form class="ubuggy-search-bar" method="get" action="newbuggy.php" id="ubuggyMainForm">
        <input
            type="text"
            class="ubuggy-search-input"
            name="keyword"
            placeholder="Search buggy: brand, model..."
            value="<?php echo e($keyword); ?>"
        >

        <input type="hidden" name="min_price" id="ubuggyMinPriceHidden" value="<?php echo e($minPrice); ?>">
        <input type="hidden" name="max_price" id="ubuggyMaxPriceHidden" value="<?php echo e($maxPrice); ?>">
        <input type="hidden" name="seats"     id="ubuggySeatsHidden"    value="<?php echo e($seats); ?>">
        <input type="hidden" name="brand"     id="ubuggyBrandHidden"    value="<?php echo e($brand); ?>">
        <input type="hidden" name="sort"      id="ubuggySortHidden"     value="<?php echo e($sort); ?>">

        <button type="button" class="ubuggy-filter-btn" id="ubuggyOpenFilter">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="10" y1="18" x2="14" y2="18"/></svg>
            Filter
            <span class="ubuggy-filter-count" id="ubuggyFilterCount" style="display:none;">0</span>
        </button>

        <button type="submit" class="ubuggy-search-btn">Search</button>
    </form>

    <!-- FILTER MODAL -->
    <div class="ubuggy-filter-overlay" id="ubuggyFilterOverlay">
        <div class="ubuggy-filter-modal">
            <div class="ubuggy-filter-head">
                <h2>Buggy Filters</h2>
                <button type="button" class="ubuggy-filter-close" id="ubuggyCloseFilter" aria-label="Close">&times;</button>
            </div>

            <div class="ubuggy-filter-body">

                <h3 class="ubuggy-fl-title">💰 Price Range</h3>
                <div class="ubuggy-fl-range">
                    <select id="ubuggyMinPrice">
                        <option value="">Min Price</option>
                        <?php foreach ($priceRangeOptions as $price): ?>
                            <option value="<?php echo (int)$price; ?>" <?php echo $minPrice === (string)$price ? 'selected' : ''; ?>>
                                $<?php echo number_format((int)$price); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="ubuggy-fl-to">to</span>
                    <select id="ubuggyMaxPrice">
                        <option value="">Max Price</option>
                        <?php foreach ($priceRangeOptions as $price): ?>
                            <option value="<?php echo (int)$price; ?>" <?php echo $maxPrice === (string)$price ? 'selected' : ''; ?>>
                                $<?php echo number_format((int)$price); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <h3 class="ubuggy-fl-title">🚗 Seats</h3>
                <div class="ubuggy-fl-chips" data-target="seats-chip">
                    <button type="button" class="ubuggy-chip <?php echo $seats === '' ? 'active' : ''; ?>" data-value="">All Seats</button>
                    <?php foreach (['2','3','4','6','8'] as $s): ?>
                        <button type="button" class="ubuggy-chip <?php echo $seats === $s ? 'active' : ''; ?>" data-value="<?php echo $s; ?>"><?php echo $s; ?> Seater</button>
                    <?php endforeach; ?>
                </div>

                <?php if (count($brandList) > 0): ?>
                    <h3 class="ubuggy-fl-title">🏷️ Brand</h3>
                    <div class="ubuggy-fl-chips" data-target="brand-chip">
                        <button type="button" class="ubuggy-chip <?php echo $brand === '' ? 'active' : ''; ?>" data-value="">All Brands</button>
                        <?php foreach ($brandList as $brandName): ?>
                            <button type="button" class="ubuggy-chip <?php echo $brand === $brandName ? 'active' : ''; ?>" data-value="<?php echo e($brandName); ?>"><?php echo e($brandName); ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h3 class="ubuggy-fl-title">↕️ Sort By</h3>
                <div class="ubuggy-fl-chips" data-target="sort-chip">
                    <button type="button" class="ubuggy-chip <?php echo $sort === 'newest' ? 'active' : ''; ?>" data-value="newest">Newest</button>
                    <button type="button" class="ubuggy-chip <?php echo $sort === 'price_low' ? 'active' : ''; ?>" data-value="price_low">Price: Low to High</button>
                    <button type="button" class="ubuggy-chip <?php echo $sort === 'price_high' ? 'active' : ''; ?>" data-value="price_high">Price: High to Low</button>
                </div>

            </div>

            <div class="ubuggy-filter-foot">
                <button type="button" class="ubuggy-fl-clear" id="ubuggyClearFilter">Clear All</button>
                <button type="button" class="ubuggy-fl-apply" id="ubuggyApplyFilter">Apply Filters</button>
            </div>
        </div>
    </div>

    <div class="result-header">
        <div>
            <h1><?php echo (int)$totalResults; ?> New Buggies Found</h1>
            <p>
                <?php if ($hasActiveFilters): ?>
                    Showing new buggy results based on your selected search filters.
                <?php else: ?>
                    Showing all available active new buggy listings for sale.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($hasActiveFilters): ?>
            <a href="newbuggy.php" class="clear-search-link">Clear All Filters</a>
        <?php endif; ?>
    </div>

    <?php if ($hasActiveFilters): ?>
        <div class="active-search-tags">
            <?php if ($keyword !== ''): ?>
                <a class="search-tag" href="<?php echo e(removeQueryParam('keyword')); ?>">
                    Keyword: <?php echo e($keyword); ?> x
                </a>
            <?php endif; ?>

            <?php if ($brand !== ''): ?>
                <a class="search-tag" href="<?php echo e(removeQueryParam('brand')); ?>">
                    Brand: <?php echo e($brand); ?> x
                </a>
            <?php endif; ?>

            <?php if ($seats !== ''): ?>
                <a class="search-tag" href="<?php echo e(removeQueryParam('seats')); ?>">
                    <?php echo e($seats); ?> Seater x
                </a>
            <?php endif; ?>

            <?php if ($minPrice !== ''): ?>
                <a class="search-tag" href="<?php echo e(removeQueryParam('min_price')); ?>">
                    Min Price: $<?php echo number_format((float)$minPrice, 0); ?> x
                </a>
            <?php endif; ?>

            <?php if ($maxPrice !== ''): ?>
                <a class="search-tag" href="<?php echo e(removeQueryParam('max_price')); ?>">
                    Max Price: $<?php echo number_format((float)$maxPrice, 0); ?> x
                </a>
            <?php endif; ?>

            <?php if ($year !== ''): ?>
                <a class="search-tag" href="<?php echo e(removeQueryParam('year')); ?>">
                    Year: <?php echo e($year); ?> x
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="explore-section">
        <div class="section-title">
            <h2>Explore New Buggy</h2>
        </div>

        <div class="explore-layout">
            <div class="explore-main">
                <div class="explore-tabs">
                    <button type="button" class="explore-tab active" data-tab="brand">Buggy Brand</button>
                    <button type="button" class="explore-tab" data-tab="seats">Seats Type</button>
                    <button type="button" class="explore-tab" data-tab="launches">New Launches</button>
                </div>

                <div class="tab-panel active" id="tab-brand">
                    <?php if (count($brandList) > 0): ?>
                        <div class="brand-grid">
                            <?php foreach ($brandList as $brandName): ?>
                                <a class="brand-link" href="newbuggy.php?brand=<?php echo urlencode($brandName); ?>">
                                    <?php echo e($brandName); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="brand-grid">
                            <a class="brand-link" href="newbuggy.php?brand=Club+Car">Club Car</a>
                            <a class="brand-link" href="newbuggy.php?brand=Yamaha">Yamaha</a>
                            <a class="brand-link" href="newbuggy.php?brand=EZGO">EZGO</a>
                            <a class="brand-link" href="newbuggy.php?brand=HDK">HDK</a>
                            <a class="brand-link" href="newbuggy.php?brand=Marshell">Marshell</a>
                            <a class="brand-link" href="newbuggy.php?brand=RoyPow">RoyPow</a>
                            <a class="brand-link" href="newbuggy.php?brand=Others">Others</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tab-panel" id="tab-seats">
                    <div class="seat-grid">
                        <a class="seat-link" href="newbuggy.php?seats=2">2 Seater</a>
                        <a class="seat-link" href="newbuggy.php?seats=3">3 Seater</a>
                        <a class="seat-link" href="newbuggy.php?seats=4">4 Seater</a>
                        <a class="seat-link" href="newbuggy.php?seats=6">6 Seater</a>
                        <a class="seat-link" href="newbuggy.php?seats=8">8 Seater</a>
                        <a class="seat-link" href="newbuggy.php?seats=2">Private Use Buggy</a>
                        <a class="seat-link" href="newbuggy.php?seats=4">Resort Buggy</a>
                        <a class="seat-link" href="newbuggy.php?seats=6">Commercial Use Buggy</a>
                    </div>
                </div>

                <div class="tab-panel" id="tab-launches">
                    <?php if (count($newLaunches) > 0): ?>
                        <div class="launch-list">
                            <?php foreach ($newLaunches as $launch): ?>
                                <a href="buggy-detail.php?id=<?php echo (int)$launch['id']; ?>" class="launch-item">
                                    <img
                                        src="<?php echo e(buggyImagePath($launch['image_url'] ?? '')); ?>"
                                        alt="<?php echo e($launch['name'] ?? 'New Buggy'); ?>"
                                        onerror="this.src='images/no-image.png';"
                                    >
                                    <div>
                                        <strong><?php echo e($launch['name'] ?: ($launch['brand'] . ' ' . $launch['model'])); ?></strong>
                                        <span><?php echo e(formatPrice($launch['selling_price'] ?? 0)); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-box">
                            No new launches yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="side-promo">
                <?php if (count($sideAds) > 0): ?>
                    <div class="side-ad-slider" id="newSideAdSlider">
                        <div class="side-ad-slides">
                            <?php foreach ($sideAds as $idx => $ad):
                                $adImg = $ad['image_url'];
                                if (strpos($adImg, 'http') !== 0 && strpos($adImg, '/') !== 0) $adImg = '/' . ltrim($adImg, '/');
                                $hasLink = !empty($ad['link_url']);
                            ?>
                                <div class="side-ad-slide <?php echo $idx === 0 ? 'active' : ''; ?>" data-index="<?php echo $idx; ?>">
                                    <?php if ($hasLink): ?>
                                        <a href="<?php echo e($ad['link_url']); ?>" target="_blank" rel="noopener">
                                            <img src="<?php echo e($adImg); ?>" alt="<?php echo e($ad['title'] ?: 'Ad'); ?>">
                                        </a>
                                    <?php else: ?>
                                        <img src="<?php echo e($adImg); ?>" alt="<?php echo e($ad['title'] ?: 'Ad'); ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="side-ad-controls">
                            <?php if (count($sideAds) > 1): ?>
                                <button type="button" class="side-ad-arrow side-ad-prev" aria-label="Previous">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                </button>
                            <?php endif; ?>
                            <div class="side-ad-dots">
                                <?php foreach ($sideAds as $idx => $ad): ?>
                                    <button type="button" class="side-ad-dot <?php echo $idx === 0 ? 'active' : ''; ?>" data-index="<?php echo $idx; ?>"></button>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($sideAds) > 1): ?>
                                <button type="button" class="side-ad-arrow side-ad-next" aria-label="Next">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="side-promo-box">
                        <div>
                            <h3>SGBUGGYMART<br>Buggy Expo</h3>
                            <p>NEW BUGGY SHOWCASE</p>
                        </div>
                    </div>

                    <div class="small-promo-box">
                        <div class="small-promo-icon">EV</div>
                        <div>
                            <strong>Explore electric buggy options</strong>
                            <span>For events, resort, factory, golf club and more.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </section>

    <section class="newbuggy-promo-section">
        <div class="promo-heading-row">
            <div class="section-title">
                <h2>New Buggy Promo</h2>
            </div>

            <a href="newbuggy.php" class="view-all-link">View All</a>
        </div>

        <?php if (count($newBuggies) > 0): ?>
            <div class="product-grid">
                <?php foreach ($newBuggies as $buggy):
                    $isSold   = ($buggy['status'] ?? '') === 'sold';
                    $hasPromo = !$isSold && isPromoActive($buggy);
                ?>
                    <a href="buggy-detail.php?id=<?php echo (int)$buggy['id']; ?>" class="buggy-card <?php echo $hasPromo ? 'has-promo' : ''; ?> <?php echo $isSold ? 'is-sold' : ''; ?>">
                        <div class="buggy-card-image">
                            <img
                                src="<?php echo e(buggyImagePath($buggy['image_url'] ?? '')); ?>"
                                alt="<?php echo e($buggy['name'] ?? 'New Buggy'); ?>"
                                onerror="this.src='images/no-image.png';"
                            >

                            <?php if ($isSold): ?>
                                <span class="card-sold-badge">SOLD</span>
                            <?php elseif ($hasPromo): ?>
                                <span class="promo-badge">🔥 <?php echo e($buggy['promo_label'] ?: 'PROMO'); ?></span>
                            <?php endif; ?>

                            <span class="card-tag">
                                <?php echo e($buggy['tag'] ?: 'New'); ?>
                            </span>
                        </div>

                        <div class="buggy-card-body">
                            <h3 class="buggy-card-title">
                                <?php echo e($buggy['name'] ?: ($buggy['brand'] . ' ' . $buggy['model'])); ?>
                            </h3>

                            <div class="buggy-card-meta">
                                <?php echo e(formatSeatsLabel($buggy['seats'] ?? '')); ?> - For Sale
                                <?php if (!empty($buggy['buggy_year'])): ?>
                                    - <?php echo e($buggy['buggy_year']); ?>
                                <?php endif; ?>
                            </div>

                            <?php if ($isSold): ?>
                                <div class="buggy-card-price">
                                    <?php echo e(formatPrice($buggy['selling_price'] ?? 0)); ?>
                                </div>
                                <div class="buggy-card-note">
                                    This buggy has been sold
                                </div>
                            <?php elseif ($hasPromo): ?>
                                <div class="buggy-card-original-price">
                                    <s>$<?php echo number_format((float)$buggy['selling_price'], 0); ?></s>
                                </div>
                                <div class="buggy-card-price promo">
                                    <?php echo e(formatPrice($buggy['discount_price'] ?? 0)); ?>
                                </div>
                                <div class="buggy-card-countdown">
                                    ⏰ <?php echo e(formatPromoTimeLeft($buggy['promo_end_date'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="buggy-card-price">
                                    <?php echo e(formatPrice($buggy['selling_price'] ?? 0)); ?>
                                </div>
                                <div class="buggy-card-note">
                                    Contact us for availability
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo e(pageUrl($page - 1)); ?>" class="page-link">&laquo; Prev</a>
                    <?php else: ?>
                        <span class="page-link is-disabled">&laquo; Prev</span>
                    <?php endif; ?>

                    <?php
                    $windowStart = max(1, $page - 2);
                    $windowEnd   = min($totalPages, $page + 2);
                    if ($windowStart > 1): ?>
                        <a href="<?php echo e(pageUrl(1)); ?>" class="page-link">1</a>
                        <?php if ($windowStart > 2): ?>
                            <span class="page-ellipsis">…</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="page-link is-active"><?php echo (int)$p; ?></span>
                        <?php else: ?>
                            <a href="<?php echo e(pageUrl($p)); ?>" class="page-link"><?php echo (int)$p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($windowEnd < $totalPages): ?>
                        <?php if ($windowEnd < $totalPages - 1): ?>
                            <span class="page-ellipsis">…</span>
                        <?php endif; ?>
                        <a href="<?php echo e(pageUrl($totalPages)); ?>" class="page-link"><?php echo (int)$totalPages; ?></a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo e(pageUrl($page + 1)); ?>" class="page-link">Next &raquo;</a>
                    <?php else: ?>
                        <span class="page-link is-disabled">Next &raquo;</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-box">
                No new buggy found. Try another search filter.
            </div>
        <?php endif; ?>
    </section>

    <!-- ===================== ACCESSORIES SECTION ===================== -->
    <?php if (count($relatedAccessories) > 0): ?>
    <section class="newbuggy-accessories-section">
        <div class="promo-heading-row">
            <div class="section-title">
                <h2>Accessories For Your Buggy</h2>
            </div>
            <a href="accessories.php" class="view-all-link">View All Accessories →</a>
        </div>

        <div class="nb-acc-grid">
            <?php foreach ($relatedAccessories as $acc): ?>
                <a href="accessory-detail.php?id=<?php echo (int)$acc['id']; ?>" class="nb-acc-card">
                    <div class="nb-acc-image">
                        <img
                            src="<?php echo e(newbuggyAccImagePath($acc['image_url'] ?? '')); ?>"
                            alt="<?php echo e($acc['name'] ?? 'Accessory'); ?>"
                            onerror="this.src='images/no-image.png';"
                        >
                        <span class="nb-acc-tag"><?php echo e($acc['tag'] ?: 'Accessory'); ?></span>
                    </div>
                    <div class="nb-acc-body">
                        <h3 class="nb-acc-title">
                            <?php echo e($acc['name'] ?: trim(($acc['brand'] ?? '') . ' ' . ($acc['model'] ?? ''))); ?>
                        </h3>
                        <div class="nb-acc-meta">
                            <?php echo e($acc['brand'] ?: 'Accessory'); ?>
                            <?php if (!empty($acc['short_info'])): ?>
                                — <?php echo e($acc['short_info']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="nb-acc-price">
                            <?php echo e(newbuggyAccFormatPrice($acc['selling_price'] ?? 0)); ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <!-- =================== END ACCESSORIES SECTION =================== -->

</div>

<script>
    const exploreTabs = document.querySelectorAll('.explore-tab');
    const tabPanels = document.querySelectorAll('.tab-panel');

    exploreTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const target = this.dataset.tab;

            exploreTabs.forEach(function (item) {
                item.classList.remove('active');
            });

            tabPanels.forEach(function (panel) {
                panel.classList.remove('active');
            });

            this.classList.add('active');

            const activePanel = document.getElementById('tab-' + target);
            if (activePanel) {
                activePanel.classList.add('active');
            }
        });
    });

    const newMinPrice = document.getElementById('newMinPrice');
    const newMaxPrice = document.getElementById('newMaxPrice');

    function updateNewMaxPriceOptions() {
        if (!newMinPrice || !newMaxPrice) {
            return;
        }

        const selectedMinPrice = parseInt(newMinPrice.value, 10) || 0;
        const selectedMaxPrice = parseInt(newMaxPrice.value, 10) || 0;

        Array.from(newMaxPrice.options).forEach(function (option) {
            const optionValue = parseInt(option.value, 10) || 0;

            if (option.value !== '' && selectedMinPrice > 0 && optionValue <= selectedMinPrice) {
                option.disabled = true;
                option.classList.add('price-option-disabled');
            } else {
                option.disabled = false;
                option.classList.remove('price-option-disabled');
            }
        });

        if (selectedMaxPrice > 0 && selectedMaxPrice <= selectedMinPrice) {
            newMaxPrice.value = '';
        }
    }

    if (newMinPrice) {
        newMinPrice.addEventListener('change', function () {
            updateNewMaxPriceOptions();
        });
    }

    updateNewMaxPriceOptions();

    /* ========== Compact filter modal ========== */
    (function () {
        const overlay = document.getElementById('ubuggyFilterOverlay');
        const openBtn = document.getElementById('ubuggyOpenFilter');
        const closeBtn = document.getElementById('ubuggyCloseFilter');
        const applyBtn = document.getElementById('ubuggyApplyFilter');
        const clearBtn = document.getElementById('ubuggyClearFilter');
        const mainForm = document.getElementById('ubuggyMainForm');
        if (!overlay || !openBtn) return;

        // Disable Max options that are <= Min (and reset Max if it becomes invalid)
        const ubMin = document.getElementById('ubuggyMinPrice');
        const ubMax = document.getElementById('ubuggyMaxPrice');
        function updateUbuggyMaxOptions() {
            if (!ubMin || !ubMax) return;
            const min = parseInt(ubMin.value, 10) || 0;
            Array.from(ubMax.options).forEach(opt => {
                const val = parseInt(opt.value, 10) || 0;
                opt.disabled = (opt.value !== '' && min > 0 && val <= min);
            });
            const curMax = parseInt(ubMax.value, 10) || 0;
            if (curMax > 0 && curMax <= min) ubMax.value = '';
        }
        if (ubMin) ubMin.addEventListener('change', updateUbuggyMaxOptions);
        updateUbuggyMaxOptions();

        openBtn.addEventListener('click', () => { overlay.classList.add('active'); document.body.style.overflow = 'hidden'; });
        closeBtn.addEventListener('click', () => { overlay.classList.remove('active'); document.body.style.overflow = ''; });
        overlay.addEventListener('click', (e) => { if (e.target === overlay) { overlay.classList.remove('active'); document.body.style.overflow = ''; } });

        document.querySelectorAll('.ubuggy-fl-chips').forEach(group => {
            group.addEventListener('click', (e) => {
                const chip = e.target.closest('.ubuggy-chip');
                if (!chip) return;
                group.querySelectorAll('.ubuggy-chip').forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
            });
        });

        function getSelected(target) {
            const group = document.querySelector('.ubuggy-fl-chips[data-target="' + target + '"]');
            if (!group) return '';
            const active = group.querySelector('.ubuggy-chip.active');
            return active ? (active.dataset.value || '') : '';
        }

        function syncHidden() {
            const min  = document.getElementById('ubuggyMinPrice');
            const max  = document.getElementById('ubuggyMaxPrice');
            document.getElementById('ubuggyMinPriceHidden').value = min ? min.value : '';
            document.getElementById('ubuggyMaxPriceHidden').value = max ? max.value : '';
            document.getElementById('ubuggySeatsHidden').value    = getSelected('seats-chip');
            document.getElementById('ubuggyBrandHidden').value    = getSelected('brand-chip');
            document.getElementById('ubuggySortHidden').value     = getSelected('sort-chip') || 'newest';
        }

        function updateFilterCount() {
            let count = 0;
            if (document.getElementById('ubuggyMinPriceHidden').value) count++;
            if (document.getElementById('ubuggyMaxPriceHidden').value) count++;
            if (document.getElementById('ubuggySeatsHidden').value)    count++;
            if (document.getElementById('ubuggyBrandHidden').value)    count++;
            if (document.getElementById('ubuggySortHidden').value && document.getElementById('ubuggySortHidden').value !== 'newest') count++;
            const badge = document.getElementById('ubuggyFilterCount');
            if (count > 0) { badge.textContent = count; badge.style.display = 'inline-block'; }
            else           { badge.style.display = 'none'; }
        }
        updateFilterCount();

        applyBtn.addEventListener('click', () => { syncHidden(); mainForm.submit(); });
        clearBtn.addEventListener('click', () => {
            document.querySelectorAll('.ubuggy-fl-chips').forEach(group => {
                group.querySelectorAll('.ubuggy-chip').forEach(c => c.classList.remove('active'));
                const first = group.querySelector('.ubuggy-chip[data-value=""]');
                if (first) first.classList.add('active');
            });
            const min = document.getElementById('ubuggyMinPrice');
            const max = document.getElementById('ubuggyMaxPrice');
            if (min) min.value = '';
            if (max) max.value = '';
            syncHidden();
            mainForm.submit();
        });
    })();

    /* Side ad slider */
    (function () {
        const slider = document.getElementById('newSideAdSlider');
        if (!slider) return;
        const slides = slider.querySelectorAll('.side-ad-slide');
        const dots   = slider.querySelectorAll('.side-ad-dot');
        const prev   = slider.querySelector('.side-ad-prev');
        const next   = slider.querySelector('.side-ad-next');
        if (slides.length <= 1) return;
        let current = 0, timer = null;
        function show(idx) {
            slides.forEach(s => s.classList.remove('active'));
            dots.forEach(d => d.classList.remove('active'));
            slides[idx].classList.add('active');
            if (dots[idx]) dots[idx].classList.add('active');
            current = idx;
        }
        function nextSlide() { show((current + 1) % slides.length); }
        function prevSlide() { show((current - 1 + slides.length) % slides.length); }
        function startAuto() { stopAuto(); timer = setInterval(nextSlide, 5000); }
        function stopAuto()  { if (timer) clearInterval(timer); timer = null; }
        if (prev) prev.addEventListener('click', () => { prevSlide(); startAuto(); });
        if (next) next.addEventListener('click', () => { nextSlide(); startAuto(); });
        dots.forEach(d => d.addEventListener('click', function () {
            show(parseInt(this.dataset.index, 10) || 0); startAuto();
        }));
        slider.addEventListener('mouseenter', stopAuto);
        slider.addEventListener('mouseleave', startAuto);
        startAuto();
    })();
</script>

<?php include 'footer.php'; ?>