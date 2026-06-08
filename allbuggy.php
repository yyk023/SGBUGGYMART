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

function formatConditionLabel($condition)
{
    $condition = trim((string)$condition);

    if ($condition === 'new') {
        return 'New';
    }

    if ($condition === 'used') {
        return 'Used';
    }

    return 'Buggy';
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
$yearFrom = trim($_GET['year_from'] ?? '');
$yearTo = trim($_GET['year_to'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$orderByMap = [
    'price_low'  => "(status = 'sold') ASC, selling_price ASC, id DESC",
    'price_high' => "(status = 'sold') ASC, selling_price DESC, id DESC",
    'newest'     => "(status = 'sold') ASC, created_at DESC, id DESC",
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

if ($yearFrom !== '' || $yearTo !== '') {
    /* Year range from the filter popup (Older → Younger) */
    if ($yearFrom !== '') {
        $where[] = "buggy_year >= :year_from";
        $params[':year_from'] = (int)$yearFrom;
    }
    if ($yearTo !== '') {
        $where[] = "buggy_year <= :year_to";
        $params[':year_to'] = (int)$yearTo;
    }
} elseif ($year !== '') {
    $where[] = "buggy_year = :buggy_year";
    $params[':buggy_year'] = (int)$year;
}

if ($condition !== '') {
    if ($condition === 'new') {
        $where[] = "buggy_condition = 'new'";
    } elseif ($condition === 'used') {
        $where[] = "buggy_condition = 'used'";
    }
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
$buggies = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    AND listing_type IN ('sale', 'sale_rent')
    AND brand IS NOT NULL
    AND brand != ''
    ORDER BY brand ASC
");
$brandStmt->execute();
$brandList = $brandStmt->fetchAll(PDO::FETCH_COLUMN);

$latestStmt = $pdo->prepare("
    SELECT *
    FROM buggies
    WHERE status = 'active'
    AND listing_type IN ('sale', 'sale_rent')
    ORDER BY created_at DESC, id DESC
    LIMIT 8
");
$latestStmt->execute();
$latestBuggies = $latestStmt->fetchAll(PDO::FETCH_ASSOC);

$hasActiveFilters = (
    $keyword !== ''
    || $brand !== ''
    || $seats !== ''
    || $minPrice !== ''
    || $maxPrice !== ''
    || $year !== ''
    || $condition !== ''
);

include 'header.php';
?>

<style>
    body {
        background: #ffffff;
    }

    .allbuggy-page {
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

    .allbuggy-search-bar {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 12px;
        align-items: center;
        margin-bottom: 22px;
        background: #ffffff;
        border-radius: 22px;
        padding: 28px 32px;
        box-shadow: 0 10px 32px rgba(0, 0, 0, 0.08);
    }

    .allbuggy-keyword-field {
        grid-column: span 3;
    }

    .allbuggy-price-range-field {
        grid-column: span 2;
    }

    .allbuggy-seat-field,
    .allbuggy-brand-field,
    .allbuggy-year-field,
    .allbuggy-sort-field,
    .allbuggy-button-field {
        grid-column: span 1;
    }

    .allbuggy-search-bar input,
    .allbuggy-search-bar select {
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

    .allbuggy-search-bar input::placeholder {
        color: #888888;
    }

    .allbuggy-search-bar input:focus,
    .allbuggy-search-bar select:focus {
        border-color: #0066cc;
        box-shadow: 0 0 0 3px rgba(0,102,204,0.08);
    }

    .allbuggy-price-field {
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

    .allbuggy-price-field select {
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

    .allbuggy-price-field select:focus {
        border: 0;
        box-shadow: none;
        outline: none;
    }

    .allbuggy-price-divider {
        width: 1px;
        height: 26px;
        background: #d8dde4;
        display: block;
    }

    .allbuggy-price-field option:disabled,
    .price-option-disabled {
        color: #bbbbbb;
        background: #f5f5f5;
    }

    .allbuggy-search-btn {
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

    .allbuggy-search-btn:hover {
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
    .seat-grid,
    .condition-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(120px, 1fr));
        gap: 14px 28px;
    }

    .brand-link,
    .seat-link,
    .condition-link {
        color: #5c6570;
        font-size: 15px;
        text-decoration: none;
        transition: 0.2s ease;
    }

    .brand-link:hover,
    .seat-link:hover,
    .condition-link:hover {
        color: #0066cc;
        transform: translateX(3px);
    }

    .latest-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .latest-item {
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

    .latest-item:hover {
        border-color: #0066cc;
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        transform: translateY(-2px);
    }

    .latest-item img {
        width: 88px;
        height: 62px;
        object-fit: cover;
        border-radius: 8px;
        background: #f1f1f1;
    }

    .latest-item strong {
        display: block;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .latest-item span {
        font-size: 13px;
        color: #0066cc;
        font-weight: 800;
    }

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
        .allbuggy-search-bar {
            grid-template-columns: 1fr 1fr;
        }

        .allbuggy-keyword-field,
        .allbuggy-price-range-field,
        .allbuggy-seat-field,
        .allbuggy-brand-field,
        .allbuggy-year-field,
        .allbuggy-sort-field,
        .allbuggy-button-field {
            grid-column: 1 / -1;
        }

        .allbuggy-search-btn {
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
        .seat-grid,
        .condition-grid {
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
        .allbuggy-page {
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

        .allbuggy-search-bar {
            grid-template-columns: 1fr;
            padding: 22px 18px;
            border-radius: 18px;
        }

        .brand-grid,
        .seat-grid,
        .condition-grid,
        .latest-list,
        .product-grid {
            grid-template-columns: 1fr;
        }

        .buggy-card-image {
            height: 210px;
        }
    }
</style>

<div class="allbuggy-page">

    <section class="top-promo-banner">
        <div class="top-promo-content">
            <h2>Explore All Buggies at SGBUGGYMART</h2>
            <p>Find new and used buggies for sale, events, resorts, golf clubs and private use.</p>
        </div>

        <a href="allbuggy.php" class="top-promo-btn">VIEW ALL BUGGY</a>
    </section>

    <form class="allbuggy-search-bar" method="get" action="allbuggy.php">
        <div class="allbuggy-keyword-field">
            <input
                type="text"
                name="keyword"
                placeholder="Buggy Brand / Model"
                value="<?php echo e($keyword); ?>"
            >
        </div>

        <div class="allbuggy-price-range-field">
            <div class="allbuggy-price-field">
                <select name="min_price" id="allMinPrice">
                    <option value="">Min Price</option>

                    <?php foreach ($priceRangeOptions as $price): ?>
                        <option value="<?php echo (int)$price; ?>" <?php echo $minPrice === (string)$price ? 'selected' : ''; ?>>
                            $<?php echo number_format((int)$price); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <span class="allbuggy-price-divider"></span>

                <select name="max_price" id="allMaxPrice">
                    <option value="">Max Price</option>

                    <?php foreach ($priceRangeOptions as $price): ?>
                        <option value="<?php echo (int)$price; ?>" <?php echo $maxPrice === (string)$price ? 'selected' : ''; ?>>
                            $<?php echo number_format((int)$price); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="allbuggy-seat-field">
            <select name="seats">
                <option value="">All Seats</option>
                <option value="2" <?php echo $seats === '2' ? 'selected' : ''; ?>>2 Seater</option>
                <option value="3" <?php echo $seats === '3' ? 'selected' : ''; ?>>3 Seater</option>
                <option value="4" <?php echo $seats === '4' ? 'selected' : ''; ?>>4 Seater</option>
                <option value="6" <?php echo $seats === '6' ? 'selected' : ''; ?>>6 Seater</option>
                <option value="8" <?php echo $seats === '8' ? 'selected' : ''; ?>>8 Seater</option>
            </select>
        </div>

        <div class="allbuggy-brand-field">
            <select name="brand">
                <option value="">All Brands</option>

                <?php foreach ($brandList as $brandName): ?>
                    <option value="<?php echo e($brandName); ?>" <?php echo $brand === $brandName ? 'selected' : ''; ?>>
                        <?php echo e($brandName); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="allbuggy-year-field">
            <select name="year">
                <option value="">All Years</option>

                <?php for ($yearOption = $startYear; $yearOption >= $endYear; $yearOption--): ?>
                    <option value="<?php echo (int)$yearOption; ?>" <?php echo $year === (string)$yearOption ? 'selected' : ''; ?>>
                        <?php echo (int)$yearOption; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="allbuggy-sort-field">
            <select name="sort">
                <option value="newest"     <?php echo $sort === 'newest'     ? 'selected' : ''; ?>>Newest</option>
                <option value="price_low"  <?php echo $sort === 'price_low'  ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
            </select>
        </div>

        <div class="allbuggy-button-field">
            <button type="submit" class="allbuggy-search-btn">Search</button>
        </div>
    </form>

    <div class="result-header">
        <div>
            <h1><?php echo (int)$totalResults; ?> Buggies Found</h1>
            <p>
                <?php if ($hasActiveFilters): ?>
                    Showing buggy results based on your selected search filters.
                <?php else: ?>
                    Showing all available active buggy listings for sale.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($hasActiveFilters): ?>
            <a href="allbuggy.php" class="clear-search-link">Clear All Filters</a>
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

            <?php if ($condition !== ''): ?>
                <a class="search-tag" href="<?php echo e(removeQueryParam('condition')); ?>">
                    <?php echo e(formatConditionLabel($condition)); ?> x
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="explore-section">
        <div class="section-title">
            <h2>Explore All Buggy</h2>
        </div>

        <div class="explore-layout">
            <div class="explore-main">
                <div class="explore-tabs">
                    <button type="button" class="explore-tab active" data-tab="brand">Buggy Brand</button>
                    <button type="button" class="explore-tab" data-tab="seats">Seats Type</button>
                    <button type="button" class="explore-tab" data-tab="condition">New / Used</button>
                    <button type="button" class="explore-tab" data-tab="latest">Latest Buggy</button>
                </div>

                <div class="tab-panel active" id="tab-brand">
                    <?php if (count($brandList) > 0): ?>
                        <div class="brand-grid">
                            <?php foreach ($brandList as $brandName): ?>
                                <a class="brand-link" href="allbuggy.php?brand=<?php echo urlencode($brandName); ?>">
                                    <?php echo e($brandName); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="brand-grid">
                            <a class="brand-link" href="allbuggy.php?brand=Club+Car">Club Car</a>
                            <a class="brand-link" href="allbuggy.php?brand=Yamaha">Yamaha</a>
                            <a class="brand-link" href="allbuggy.php?brand=EZGO">EZGO</a>
                            <a class="brand-link" href="allbuggy.php?brand=HDK">HDK</a>
                            <a class="brand-link" href="allbuggy.php?brand=Marshell">Marshell</a>
                            <a class="brand-link" href="allbuggy.php?brand=RoyPow">RoyPow</a>
                            <a class="brand-link" href="allbuggy.php?brand=Others">Others</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tab-panel" id="tab-seats">
                    <div class="seat-grid">
                        <a class="seat-link" href="allbuggy.php?seats=2">2 Seater</a>
                        <a class="seat-link" href="allbuggy.php?seats=3">3 Seater</a>
                        <a class="seat-link" href="allbuggy.php?seats=4">4 Seater</a>
                        <a class="seat-link" href="allbuggy.php?seats=6">6 Seater</a>
                        <a class="seat-link" href="allbuggy.php?seats=8">8 Seater</a>
                        <a class="seat-link" href="allbuggy.php?seats=2">Private Use Buggy</a>
                        <a class="seat-link" href="allbuggy.php?seats=4">Resort Buggy</a>
                        <a class="seat-link" href="allbuggy.php?seats=6">Commercial Use Buggy</a>
                    </div>
                </div>

                <div class="tab-panel" id="tab-condition">
                    <div class="condition-grid">
                        <a class="condition-link" href="allbuggy.php?condition=new">New Buggy</a>
                        <a class="condition-link" href="allbuggy.php?condition=used">Used Buggy</a>
                    </div>
                </div>

                <div class="tab-panel" id="tab-latest">
                    <?php if (count($latestBuggies) > 0): ?>
                        <div class="latest-list">
                            <?php foreach ($latestBuggies as $latest): ?>
                                <a href="buggy-detail.php?id=<?php echo (int)$latest['id']; ?>" class="latest-item">
                                    <img
                                        src="<?php echo e(buggyImagePath($latest['image_url'] ?? '')); ?>"
                                        alt="<?php echo e($latest['name'] ?? 'Buggy'); ?>"
                                        onerror="this.src='images/no-image.png';"
                                    >
                                    <div>
                                        <strong><?php echo e($latest['name'] ?: ($latest['brand'] . ' ' . $latest['model'])); ?></strong>
                                        <span><?php echo e(formatPrice($latest['selling_price'] ?? 0)); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-box">
                            No latest buggy yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="side-promo">
                <div class="side-promo-box">
                    <div>
                        <h3>SGBUGGYMART<br>Buggy Market</h3>
                        <p>NEW & USED BUGGY</p>
                    </div>
                </div>

                <div class="small-promo-box">
                    <div class="small-promo-icon">GO</div>
                    <div>
                        <strong>Find your ideal buggy</strong>
                        <span>For events, resort, factory, golf club and private use.</span>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section class="allbuggy-promo-section">
        <div class="promo-heading-row">
            <div class="section-title">
                <h2>All Buggy Listings</h2>
            </div>

            <a href="allbuggy.php" class="view-all-link">View All</a>
        </div>

        <?php if (count($buggies) > 0): ?>
            <div class="product-grid">
                <?php foreach ($buggies as $buggy):
                    $isSold   = ($buggy['status'] ?? '') === 'sold';
                    $hasPromo = !$isSold && isPromoActive($buggy);
                ?>
                    <a href="buggy-detail.php?id=<?php echo (int)$buggy['id']; ?>" class="buggy-card <?php echo $hasPromo ? 'has-promo' : ''; ?> <?php echo $isSold ? 'is-sold' : ''; ?>">
                        <div class="buggy-card-image">
                            <img
                                src="<?php echo e(buggyImagePath($buggy['image_url'] ?? '')); ?>"
                                alt="<?php echo e($buggy['name'] ?? 'Buggy'); ?>"
                                onerror="this.src='images/no-image.png';"
                            >

                            <?php if ($isSold): ?>
                                <span class="card-sold-badge">SOLD</span>
                            <?php elseif ($hasPromo): ?>
                                <span class="promo-badge">🔥 <?php echo e($buggy['promo_label'] ?: 'PROMO'); ?></span>
                            <?php endif; ?>

                            <span class="card-tag">
                                <?php echo e($buggy['tag'] ?: formatConditionLabel($buggy['buggy_condition'] ?? '')); ?>
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
                                    <?php echo e(formatConditionLabel($buggy['buggy_condition'] ?? '')); ?> Buggy - Contact us for availability
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
                No buggy found. Try another search filter.
            </div>
        <?php endif; ?>
    </section>

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

    const allMinPrice = document.getElementById('allMinPrice');
    const allMaxPrice = document.getElementById('allMaxPrice');

    function updateAllMaxPriceOptions() {
        if (!allMinPrice || !allMaxPrice) {
            return;
        }

        const selectedMinPrice = parseInt(allMinPrice.value, 10) || 0;
        const selectedMaxPrice = parseInt(allMaxPrice.value, 10) || 0;

        Array.from(allMaxPrice.options).forEach(function (option) {
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
            allMaxPrice.value = '';
        }
    }

    if (allMinPrice) {
        allMinPrice.addEventListener('change', function () {
            updateAllMaxPriceOptions();
        });
    }

    updateAllMaxPriceOptions();
</script>

<?php include 'footer.php'; ?>