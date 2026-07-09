<?php
// buggy-detail.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/track-visitor.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die("Invalid buggy ID.");
}

// Which view to render: 'overview' (default) or 'photos'
$view = isset($_GET['view']) ? trim($_GET['view']) : 'overview';
if (!in_array($view, ['overview', 'photos'], true)) {
    $view = 'overview';
}

$sql = "
    SELECT 
        id,
        owner_type,
        owner_id,
        brand,
        model,
        name,
        seats,
        buggy_condition,
        selling_price,
        promo_enabled,
        discount_price,
        promo_end_date,
        promo_label,
        short_info,
        description,
        specifications,
        image_url,
        datasheet_url,
        tag,
        brand_tag,
        status,
        created_at
    FROM buggies
    WHERE id = :id
    AND status IN ('active', 'sold')
    LIMIT 1
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $id
    ]);

    $buggy = $stmt->fetch();

    if (!$buggy) {
        die("Buggy not found.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| SAVE RECENT VIEW FOR LOGGED-IN MEMBER
|--------------------------------------------------------------------------
*/
if (isset($_SESSION['member_id'])) {
    $memberId = (int) $_SESSION['member_id'];

    if ($memberId > 0) {
        try {
            $recentStmt = $pdo->prepare("
                INSERT INTO member_recent_views (member_id, buggy_id, viewed_at)
                VALUES (:member_id, :buggy_id, NOW())
                ON DUPLICATE KEY UPDATE viewed_at = NOW()
            ");

            $recentStmt->execute([
                ':member_id' => $memberId,
                ':buggy_id' => $id
            ]);
        } catch (PDOException $e) {
            /*
                Do not stop buggy detail page if recent view save fails.
            */
        }
    }
}

$title = trim((string)($buggy['model'] ?? ''));
if ($title === '') {
    $title = !empty($buggy['name'])
        ? $buggy['name']
        : trim(($buggy['brand'] ?? '') . ' ' . ($buggy['model'] ?? ''));
}
if ($title === '') {
    $title = 'Buggy Detail';
}

$brand = $buggy['brand'] ?? '';
$model = $buggy['model'] ?? '';
$condition = $buggy['buggy_condition'] ?? '';
$price = $buggy['selling_price'] ?? 0;
$seats = $buggy['seats'] ?? '';
$shortInfo       = $buggy['short_info']       ?? '';
$description     = $buggy['description']      ?? '';
$buggySpecs = [];
if (!empty($buggy['specifications'])) {
    $decodedSpecs = json_decode($buggy['specifications'], true);
    if (is_array($decodedSpecs)) {
        $buggySpecs = $decodedSpecs;
    }
}
$tag = $buggy['tag'] ?? '';

/*
|--------------------------------------------------------------------------
| SGBUGGYMART PUBLIC CONTACT
|--------------------------------------------------------------------------
| Buyers only contact SGBUGGYMART.
| Seller name, seller phone, seller WhatsApp and internal remark are not shown publicly.
|--------------------------------------------------------------------------
*/
$sgbuggymartWhatsapp = '6566624140';
$cleanWhatsapp = preg_replace('/[^0-9]/', '', $sgbuggymartWhatsapp);

$whatsappMessage = rawurlencode(
    'Hi SGBUGGYMART, I am interested in this buggy: ' . $title
);

$whatsappLink = $cleanWhatsapp !== ''
    ? 'https://wa.me/' . $cleanWhatsapp . '?text=' . $whatsappMessage
    : '#';

$mainImage = !empty($buggy['image_url']) ? $buggy['image_url'] : 'images/no-image.jpg';

$galleryImages = [];

try {
    $galleryStmt = $pdo->prepare("
        SELECT image_url
        FROM buggy_images
        WHERE buggy_id = :buggy_id
        ORDER BY sort_order ASC, id ASC
    ");
    $galleryStmt->execute([
        ':buggy_id' => $id
    ]);

    $galleryImages = $galleryStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $galleryImages = [];
}

$allImages = [];

if (!empty($mainImage)) {
    $allImages[] = $mainImage;
}

foreach ($galleryImages as $galleryImage) {
    if (!empty($galleryImage) && !in_array($galleryImage, $allImages, true)) {
        $allImages[] = $galleryImage;
    }
}

if (count($allImages) === 0) {
    $allImages[] = 'images/no-image.jpg';
}

$conditionText = ucfirst($condition);

/* Promo detection */
$hasPromo = false;
$promoEndTs = 0;
if (!empty($buggy['promo_enabled']) && (int)$buggy['promo_enabled'] === 1
    && !empty($buggy['promo_end_date'])
    && !empty($buggy['discount_price'])
    && (float)$buggy['discount_price'] < (float)$buggy['selling_price']) {
    $promoEndTs = strtotime($buggy['promo_end_date']);
    if ($promoEndTs > time()) {
        $hasPromo = true;
    }
}

function buggyPromoTimeLeft($endDate)
{
    $diff = strtotime($endDate) - time();
    if ($diff <= 0) return 'Expired';
    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);
    if ($days > 0) return $days . ' day' . ($days > 1 ? 's' : '') . ' ' . $hours . 'hr left';
    if ($hours > 0) return $hours . 'hr ' . $minutes . 'min left';
    return $minutes . ' min left';
}

$similarBuggies = [];
try {
    $similarStmt = $pdo->prepare("
        SELECT id, brand, model, name, seats, buggy_condition, selling_price, image_url, tag
        FROM buggies
        WHERE status = 'active'
        AND id != :current_id
        AND (brand = :brand OR buggy_condition = :condition)
        ORDER BY (brand = :brand2) DESC, created_at DESC
        LIMIT 4
    ");
    $similarStmt->execute([
        ':current_id' => $id,
        ':brand'      => $brand,
        ':condition'  => $condition,
        ':brand2'     => $brand,
    ]);
    $similarBuggies = $similarStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $similarBuggies = [];
}

/*
|--------------------------------------------------------------------------
| FILTER MODAL OPTIONS (for the "Filter" popup)
|--------------------------------------------------------------------------
*/
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$filterBrandList = [];
try {
    $filterBrandStmt = $pdo->query("
        SELECT DISTINCT brand
        FROM buggies
        WHERE status IN ('active', 'sold')
        AND listing_type IN ('sale', 'sale_rent')
        AND brand IS NOT NULL
        AND brand != ''
        ORDER BY brand ASC
    ");
    $filterBrandList = $filterBrandStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $filterBrandList = [];
}

$filterSeatOptions  = ['2', '3', '4', '6', '8'];
$filterPriceOptions = [2000, 4000, 6000, 8000, 10000, 12000, 15000, 18000, 20000, 25000, 30000];
$filterYearStart    = (int)date('Y');
$filterYearEnd      = $filterYearStart - 15;
?>

<?php include 'header.php'; ?>

<style>
    html {
        scroll-behavior: smooth;
    }

    body {
        background: #fff;
        color: #1f2937;
    }

    .detail-page {
        max-width: 1220px;
        margin: 0 auto;
        padding: 24px 20px 60px;
    }

    .product-focus-anchor {
        height: 1px;
        scroll-margin-top: 95px;
    }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: #8b95a1;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }

    .breadcrumb a {
        color: #8b95a1;
        text-decoration: none;
    }

    .breadcrumb span {
        color: #c0c5cc;
    }

    .detail-search-row {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 34px;
    }

    .detail-category {
        width: 160px;
        height: 43px;
        border: 1px solid #ccd3dc;
        border-radius: 24px 0 0 24px;
        padding: 0 18px;
        color: #1f2937;
        background: #fff;
        outline: none;
    }

    .detail-search-input {
        flex: 1;
        height: 43px;
        border: 1px solid #ccd3dc;
        border-left: 0;
        border-radius: 0 24px 24px 0;
        padding: 0 18px;
        outline: none;
        font-size: 15px;
    }

    .filter-btn {
        width: 125px;
        height: 43px;
        border: 2px solid #111;
        border-radius: 24px;
        background: #fff;
        font-size: 15px;
        cursor: pointer;
    }

    .search-btn {
        width: 120px;
        height: 43px;
        border: none;
        border-radius: 24px;
        background: #0066cc;
        color: #fff;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
    }

    .detail-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 22px;
    }

    /* Share button + menu */
    .share-wrap {
        position: relative;
        flex-shrink: 0;
    }

    .share-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        height: 40px;
        padding: 0 16px;
        border: 1px solid #d8dde4;
        background: #fff;
        color: #1f2937;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .share-btn:hover {
        border-color: #0066cc;
        color: #0066cc;
    }

    .share-menu {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        min-width: 200px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        z-index: 100;
        padding: 8px;
        display: none;
        flex-direction: column;
        gap: 4px;
    }

    .share-menu.active {
        display: flex;
    }

    .share-opt {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 700;
        color: #1f2937;
        border: 0;
        background: transparent;
        cursor: pointer;
        transition: 0.15s ease;
        text-align: left;
        width: 100%;
    }

    .share-opt:hover {
        background: #f3f4f6;
    }

    .share-whatsapp { color: #25d366; }
    .share-copy     { color: #6b7280; }

    .share-copy.copied {
        background: #dcfce7 !important;
        color: #166534;
    }

    /* Mobile-only icon share button next to price */
    .price-row-mobile {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .share-wrap-mobile {
        display: none;
        margin-right: 14px;
    }
    .share-icon-btn {
        width: 48px;
        height: 48px;
        border: 0;
        border-radius: 50%;
        background: transparent;
        color: #1f2937;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s ease;
        padding: 0;
    }
    .share-icon-btn svg {
        width: 26px;
        height: 26px;
    }
    .share-icon-btn:hover {
        background: #f3f4f6;
        color: #0066cc;
    }
    .share-menu-mobile {
        right: 0;
    }

    @media (max-width: 900px) {
        /* On mobile, hide the title-row Share button and show the icon one */
        .detail-title-row .share-wrap:not(.share-wrap-mobile) { display: none; }
        .share-wrap-mobile { display: block; }
    }

    .detail-title {
        font-size: 22px;
        font-weight: 800;
        color: #172033;
        margin: 0;
    }

    .sold-notice-banner {
        display: flex;
        align-items: center;
        gap: 14px;
        background: #1a1a1a;
        color: #ffffff;
        border-radius: 10px;
        padding: 16px 22px;
        margin-bottom: 22px;
        font-size: 15px;
        font-weight: 700;
    }

    .sold-notice-badge {
        background: #ef3f4d;
        color: #fff;
        font-size: 13px;
        font-weight: 900;
        padding: 5px 14px;
        border-radius: 50px;
        white-space: nowrap;
        letter-spacing: 1px;
    }

    .sold-notice-text {
        font-weight: 500;
        opacity: 0.9;
    }

    /* ===================== FILTER MODAL ===================== */
    .buggy-filter-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 9999;
        display: none;
        align-items: flex-start;
        justify-content: center;
        padding: 40px 16px;
        overflow-y: auto;
    }

    .buggy-filter-overlay.open {
        display: flex;
    }

    .buggy-filter-modal {
        background: #ffffff;
        width: 100%;
        max-width: 720px;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 80px);
        animation: bfSlideIn 0.25s ease;
    }

    @keyframes bfSlideIn {
        from { opacity: 0; transform: translateY(-16px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .bf-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 22px 26px 16px;
        border-bottom: 1px solid #eef0f4;
    }

    .bf-title {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
        color: #1a1a1a;
    }

    .bf-close {
        border: 0;
        background: transparent;
        font-size: 28px;
        line-height: 1;
        color: #8a93a0;
        cursor: pointer;
        padding: 0 4px;
        transition: 0.2s ease;
    }

    .bf-close:hover {
        color: #ef3f4d;
    }

    .bf-body {
        padding: 20px 26px;
        overflow-y: auto;
    }

    .bf-search-wrap {
        margin-bottom: 8px;
    }

    .bf-search-input {
        width: 100%;
        height: 50px;
        border: 1px solid #d8dde4;
        border-radius: 50px;
        padding: 0 22px;
        font-size: 15px;
        outline: none;
        transition: 0.2s ease;
        box-sizing: border-box;
    }

    .bf-search-input:focus {
        border-color: #0066cc;
        box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.12);
    }

    .bf-section-title {
        margin: 22px 0 12px;
        font-size: 16px;
        font-weight: 800;
        color: #1a1a1a;
    }

    .bf-range-row {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .bf-select {
        flex: 1;
        height: 46px;
        border: 1px solid #d8dde4;
        border-radius: 50px;
        padding: 0 18px;
        font-size: 14px;
        color: #333;
        background: #fff;
        outline: none;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .bf-select:focus {
        border-color: #0066cc;
    }

    .bf-range-to {
        color: #8a93a0;
        font-size: 14px;
        flex: 0 0 auto;
    }

    .bf-chip-group {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .bf-chip {
        border: 1px solid #e3e6ec;
        background: #f5f6f8;
        color: #4a5160;
        font-size: 14px;
        font-weight: 600;
        padding: 9px 18px;
        border-radius: 50px;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .bf-chip:hover {
        background: #eef5ff;
        color: #0066cc;
        border-color: #cfe5ff;
    }

    .bf-chip.active {
        background: #eef5ff;
        color: #0066cc;
        border-color: #0066cc;
        font-weight: 800;
    }

    .bf-footer {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
        padding: 18px 26px 22px;
        border-top: 1px solid #eef0f4;
    }

    .bf-clear {
        border: 0;
        background: transparent;
        font-size: 16px;
        font-weight: 700;
        color: #1a1a1a;
        cursor: pointer;
        padding: 10px 8px;
    }

    .bf-clear:hover {
        color: #ef3f4d;
    }

    .bf-search {
        border: 0;
        background: #ef3f4d;
        color: #fff;
        font-size: 16px;
        font-weight: 800;
        padding: 0 46px;
        height: 50px;
        border-radius: 50px;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .bf-search:hover {
        background: #d92e3d;
    }

    @media (max-width: 600px) {
        .buggy-filter-overlay {
            padding: 0;
        }

        .buggy-filter-modal {
            max-width: 100%;
            min-height: 100vh;
            max-height: 100vh;
            border-radius: 0;
        }

        .bf-range-row {
            flex-wrap: wrap;
        }

        .bf-select {
            flex: 1 1 100%;
        }

        .bf-range-to {
            display: none;
        }
    }

    .detail-tabs {
        display: flex;
        gap: 40px;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 22px;
        position: sticky;
        top: 0;
        background: #ffffff;
        z-index: 90;
        padding-top: 8px;
        padding-bottom: 0;
        /* Extend white background full-width so no blank edges on mobile */
        box-shadow: -100vw 0 0 #ffffff, 100vw 0 0 #ffffff;
        clip-path: inset(0 -100vw);
    }

    .detail-tabs a {
        text-decoration: none;
        color: #111827;
        padding-bottom: 15px;
        font-size: 15px;
        position: relative;
        cursor: pointer;
    }

    .detail-tabs a.active {
        color: #0066cc;
        font-weight: 700;
    }

    .detail-tabs a.active::after {
        content: "";
        position: absolute;
        left: 0;
        bottom: -1px;
        width: 100%;
        height: 3px;
        background: #0066cc;
    }

    .detail-main {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 32px;
        align-items: start;
        scroll-margin-top: 95px;
    }

    .gallery-area {
        scroll-margin-top: 95px;
    }

    .gallery-main {
        width: 100%;
        max-height: 420px;
        aspect-ratio: 4 / 3;
        border-radius: 6px;
        overflow: hidden;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        position: relative;
    }

    .gallery-track {
        display: flex;
        height: 100%;
        width: 100%;
        transition: transform 0.4s ease;
        will-change: transform;
    }

    .gallery-slide {
        flex: 0 0 100%;
        height: 100%;
        width: 100%;
    }

    .gallery-main img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center center;
        display: block;
        background: #f3f4f6;
    }

    .gallery-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);

        width: 42px;
        height: 42px;

        display: flex;
        align-items: center;
        justify-content: center;

        border: none;
        border-radius: 50%;

        background: rgba(0, 0, 0, 0.22);
        color: #fff;

        font-size: 28px;
        font-weight: 700;
        line-height: 1;

        cursor: pointer;
        z-index: 5;

        padding: 0;
        transition: background 0.2s ease;
    }

    .gallery-arrow:hover {
        background: rgba(0, 0, 0, 0.4);
    }

    .gallery-arrow.prev {
        left: 16px;
    }

    .gallery-arrow.next {
        right: 16px;
    }

    .thumb-slider-wrap {
        position: relative;
        margin-top: 20px;
        padding: 0 42px;
    }

    .thumb-viewport {
        overflow: hidden;
        width: 100%;
    }

    .thumb-row {
        display: flex;
        gap: 16px;
        transition: transform 0.35s ease;
        will-change: transform;
    }

    .thumb {
        width: calc((100% - 80px) / 6);
        height: 70px;
        border-radius: 4px;
        overflow: hidden;
        border: 2px solid transparent;
        flex: 0 0 calc((100% - 80px) / 6);
        cursor: pointer;
        opacity: 0.45;
        background: #f3f4f6;
        transition: 0.2s ease;
    }

    .thumb:hover {
        opacity: 0.8;
    }

    .thumb.active {
        border-color: #0066cc;
        opacity: 1;
    }

    .thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        background: #f3f4f6;
    }

    .thumb-arrow {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 34px;
        border: none;
        background: rgba(0, 0, 0, 0.35);
        color: #fff;
        font-size: 24px;
        cursor: pointer;
        z-index: 4;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s ease;
    }

    .thumb-arrow:hover {
        background: rgba(0, 0, 0, 0.55);
    }

    .thumb-arrow.left {
        left: 0;
    }

    .thumb-arrow.right {
        right: 0;
    }

    .thumb-arrow:disabled {
        opacity: 0.25;
        cursor: not-allowed;
    }

    .price {
        color: #ef3f4d;
        font-size: 28px;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .price.promo {
        color: #dc2626;
        font-size: 24px;
        font-weight: 800;
    }

    .price.promo::before {
        content: 'Now: ';
        color: #6b7280;
        font-size: 14px;
        font-weight: 600;
    }

    .monthly {
        font-size: 14px;
        color: #111827;
        margin-bottom: 26px;
    }

    /* Promo banner */
    .promo-banner {
        background: linear-gradient(135deg, #ef4444, #f97316);
        color: #fff;
        border-radius: 12px;
        padding: 14px 18px;
        margin-bottom: 14px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        box-shadow: 0 8px 22px rgba(249, 115, 22, 0.3);
    }

    .promo-banner-top {
        display: flex; align-items: center; justify-content: space-between;
    }

    .promo-banner-badge {
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    .promo-banner-countdown {
        font-size: 18px;
        font-weight: 800;
        background: rgba(0,0,0,0.18);
        padding: 6px 12px;
        border-radius: 8px;
        text-align: center;
        animation: pulseBeat 2s ease-in-out infinite;
    }

    @keyframes pulseBeat {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.8; }
    }

    .promo-original {
        color: #6b7280;
        font-size: 36px;
        font-weight: 800;
        margin-bottom: 4px;
        line-height: 1.1;
    }

    .promo-original s {
        text-decoration: line-through;
        text-decoration-color: #ef4444;
        text-decoration-thickness: 3px;
    }

    .promo-save {
        color: #16a34a !important;
        font-weight: 900;
        background: #f0fdf4;
        border: 2px solid #86efac;
        padding: 14px 24px;
        border-radius: 10px;
        display: inline-block;
        font-size: 18px;
        margin: 6px 0 22px !important;
        box-shadow: 0 4px 14px rgba(22, 163, 74, 0.18);
    }

    .price.promo {
        margin-bottom: 4px;
    }

    .promo-original {
        margin-bottom: 0;
    }

    .promo-banner {
        margin-bottom: 10px !important;
    }

    .spec-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 22px 34px;
        margin-bottom: 24px;
    }

    .spec-item {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #111827;
        font-size: 15px;
        font-weight: 600;
    }

    .spec-icon {
        width: 18px;
        height: 18px;
        border: 1.5px solid #111;
        border-radius: 5px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #111;
        flex: 0 0 auto;
    }

    .premium-label {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.04em;
        color: #555;
        margin-bottom: 24px;
        text-transform: uppercase;
    }

    .contact-btn {
        width: 100%;
        height: 44px;
        border: none;
        background: #0066cc;
        color: #fff;
        font-size: 16px;
        font-weight: 700;
        border-radius: 6px;
        cursor: pointer;
        margin-bottom: 12px;
        transition: 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        box-sizing: border-box;
    }

    .contact-btn:hover {
        background: #0056ad;
        color: #fff;
    }


    .analysis-box {
        background: #fafafa;
        padding: 28px 20px;
        text-align: center;
        font-size: 14px;
        line-height: 1.6;
        color: #172033;
    }

    .analysis-box strong {
        font-weight: 800;
    }

    .analysis-box a {
        display: block;
        margin-top: 2px;
        color: #0057ff;
        text-decoration: none;
        font-weight: 700;
    }

    .section-box {
        margin-top: 32px;
        border-top: 1px solid #e5e7eb;
        padding-top: 24px;
        scroll-margin-top: 200px;
    }

    .section-box h2 {
        font-size: 22px;
        margin: 0 0 16px;
        color: #172033;
    }

    .description-text {
        color: #4b5563;
        font-size: 15px;
        line-height: 1.8;
        white-space: pre-line;
    }

    .detail-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
        font-size: 15px;
    }

    .detail-table tr {
        border-bottom: 1px solid #edf0f3;
    }

    .detail-table tr:last-child {
        border-bottom: none;
    }

    .detail-table th {
        width: 220px;
        text-align: left;
        padding: 14px 0;
        color: #6b7280;
        font-weight: 500;
    }

    .detail-table td {
        padding: 14px 0;
        color: #111827;
        font-weight: 600;
    }

    .sg-contact-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        z-index: 9998;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .sg-contact-modal-overlay.active {
        display: flex;
    }

    .sg-contact-modal {
        width: 100%;
        max-width: 560px;
        background: #fff;
        border-radius: 16px;
        padding: 34px 42px 40px;
        position: relative;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    }

    .sg-contact-modal-close {
        position: absolute;
        top: 18px;
        right: 22px;
        border: none;
        background: transparent;
        color: #777;
        font-size: 34px;
        line-height: 1;
        cursor: pointer;
    }

    .sg-contact-modal h2 {
        margin: 0 0 22px;
        color: #333;
        font-size: 28px;
        font-weight: 800;
    }

    .sg-contact-product-label {
        color: #777;
        font-size: 14px;
        margin-bottom: 7px;
    }

    .sg-contact-product-name {
        color: #172033;
        font-size: 17px;
        font-weight: 800;
        margin-bottom: 18px;
        line-height: 1.5;
    }

    .sg-contact-text {
        color: #4b5563;
        font-size: 15px;
        line-height: 1.7;
        margin-bottom: 24px;
    }

    .sg-whatsapp-btn {
        width: 100%;
        height: 46px;
        border: none;
        border-radius: 8px;
        background: #25d366;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        font-size: 16px;
        font-weight: 800;
        transition: 0.2s ease;
    }

    .sg-whatsapp-btn:hover {
        background: #1fb85a;
        color: #fff;
    }

    .sg-whatsapp-icon {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.25);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 800;
    }

    #similar.section-box {
        margin-top: 70px;
    }

    /* Photos grid section */
    .photos-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 28px;
    }

    .photo-tile {
        position: relative;
        width: 100%;
        aspect-ratio: 4 / 3;
        border-radius: 10px;
        overflow: hidden;
        background: #f3f4f6;
        cursor: zoom-in;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .photo-tile:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
    }

    .photo-tile img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .photos-cta {
        display: flex;
        justify-content: center;
        margin-top: 8px;
    }

    .photos-contact-btn {
        min-width: 280px;
        height: 48px;
        padding: 0 32px;
        background: #ef3f4d;
        color: #ffffff;
        border: 0;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 800;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.2s ease;
    }

    .photos-contact-btn:hover {
        background: #d92e3d;
        transform: translateY(-2px);
    }

    @media (max-width: 900px) {
        .photos-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 520px) {
        .photos-grid {
            grid-template-columns: 1fr;
        }

        .photos-contact-btn {
            width: 100%;
            min-width: 0;
        }
    }

    .similar-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-top: 8px;
    }

    .sim-card {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #ffffff;
        overflow: hidden;
        text-decoration: none;
        color: #222;
        transition: 0.2s ease;
        display: block;
    }

    .sim-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.1);
        border-color: #0066cc;
    }

    .sim-card-image {
        position: relative;
        width: 100%;
        height: 148px;
        background: #f3f4f6;
        overflow: hidden;
    }

    .sim-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: 0.3s ease;
    }

    .sim-card:hover .sim-card-image img {
        transform: scale(1.05);
    }

    .sim-tag {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #0066cc;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        padding: 4px 9px;
        border-radius: 999px;
    }

    .sim-card-body {
        padding: 12px 14px 14px;
    }

    .sim-card-title {
        font-size: 14px;
        font-weight: 800;
        color: #172033;
        line-height: 1.35;
        margin-bottom: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sim-card-meta {
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 6px;
    }

    .sim-card-price {
        font-size: 15px;
        font-weight: 900;
        color: #ef3f4d;
    }

    @media (max-width: 900px) {
        .similar-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .detail-search-row {
            flex-wrap: wrap;
        }

        .detail-category,
        .detail-search-input,
        .filter-btn,
        .search-btn {
            width: 100%;
            border-radius: 24px;
            border: 1px solid #ccd3dc;
        }

        .detail-main {
            grid-template-columns: 1fr;
        }

        .detail-title-row {
            align-items: flex-start;
            flex-direction: column;
        }

        .detail-tabs {
            gap: 24px;
            overflow-x: auto;
        }

        .thumb {
            width: calc((100% - 48px) / 4);
            flex-basis: calc((100% - 48px) / 4);
            height: 76px;
        }
    }

    @media (max-width: 620px) {
        .sg-contact-modal {
            padding: 30px 24px 32px;
        }

        .sg-contact-modal h2 {
            font-size: 24px;
        }
    }

    @media (max-width: 520px) {
        .similar-grid {
            grid-template-columns: 1fr;
        }

        .detail-page {
            padding: 18px 14px 50px;
        }

        .detail-title {
            font-size: 20px;
        }

        .gallery-arrow {
            width: 36px;
            height: 36px;
            font-size: 24px;
            line-height: 1;
            padding: 0;
        }

        .gallery-arrow.prev {
            left: 10px;
        }

        .gallery-arrow.next {
            right: 10px;
        }

        .thumb-slider-wrap {
            padding: 0 34px;
        }

        .thumb {
            width: calc((100% - 24px) / 3);
            flex-basis: calc((100% - 24px) / 3);
            height: 66px;
        }

        .spec-grid {
            grid-template-columns: 1fr;
        }

        .detail-table th {
            width: 145px;
        }
    }
</style>

<main class="detail-page">

    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>›</span>
        <a href="<?php echo strtolower($condition) === 'new' ? 'newbuggy.php' : 'usedbuggy.php'; ?>">
            <?php echo htmlspecialchars($conditionText ?: 'Buggy'); ?> Buggy
        </a>
        <span>›</span>
        <a href="#"><?php echo htmlspecialchars($brand); ?></a>
        <span>›</span>
        <strong><?php echo htmlspecialchars($title); ?></strong>
    </div>

    <form class="detail-search-row" action="usedbuggy.php" method="get">
        <select class="detail-category" name="category">
            <option value="">All Category</option>
            <option value="new">New Buggy</option>
            <option value="used">Used Buggy</option>
        </select>

        <input 
            class="detail-search-input" 
            type="text" 
            name="keyword" 
            placeholder="Buggy Make/Model"
        >

        <button type="button" class="filter-btn" id="openFilterModal">☷ Filter</button>
        <button type="submit" class="search-btn">Search</button>
    </form>

    <!-- ===================== BUGGY FILTER MODAL ===================== -->
    <div class="buggy-filter-overlay" id="buggyFilterOverlay">
        <form class="buggy-filter-modal" action="allbuggy.php" method="get" id="buggyFilterForm">
            <div class="bf-header">
                <h2 class="bf-title">Buggy Filters</h2>
                <button type="button" class="bf-close" id="closeFilterModal" aria-label="Close">&times;</button>
            </div>

            <div class="bf-body">
                <!-- Keyword -->
                <div class="bf-search-wrap">
                    <input type="text" name="keyword" class="bf-search-input" placeholder="Buggy Make, Model, Type">
                </div>

                <!-- Category / Condition -->
                <h3 class="bf-section-title">Category</h3>
                <div class="bf-chip-group" data-target="bf-condition">
                    <button type="button" class="bf-chip active" data-value="">All Categories</button>
                    <button type="button" class="bf-chip" data-value="new">New Buggy</button>
                    <button type="button" class="bf-chip" data-value="used">Used Buggy</button>
                </div>
                <input type="hidden" name="condition" id="bf-condition" value="">

                <!-- Price Range -->
                <h3 class="bf-section-title">Price Range</h3>
                <div class="bf-range-row">
                    <select name="min_price" class="bf-select">
                        <option value="">Lowest</option>
                        <?php foreach ($filterPriceOptions as $priceOpt): ?>
                            <option value="<?php echo (int)$priceOpt; ?>">$<?php echo number_format((int)$priceOpt); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="bf-range-to">to</span>
                    <select name="max_price" class="bf-select">
                        <option value="">Highest</option>
                        <?php foreach ($filterPriceOptions as $priceOpt): ?>
                            <option value="<?php echo (int)$priceOpt; ?>">$<?php echo number_format((int)$priceOpt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Registration / Manufacture Year -->
                <h3 class="bf-section-title">Registration Year</h3>
                <div class="bf-range-row">
                    <select name="year_from" class="bf-select">
                        <option value="">Older</option>
                        <?php for ($y = $filterYearEnd; $y <= $filterYearStart; $y++): ?>
                            <option value="<?php echo (int)$y; ?>"><?php echo (int)$y; ?></option>
                        <?php endfor; ?>
                    </select>
                    <span class="bf-range-to">to</span>
                    <select name="year_to" class="bf-select">
                        <option value="">Younger</option>
                        <?php for ($y = $filterYearStart; $y >= $filterYearEnd; $y--): ?>
                            <option value="<?php echo (int)$y; ?>"><?php echo (int)$y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Seats / Vehicle Type -->
                <h3 class="bf-section-title">Seats</h3>
                <div class="bf-chip-group" data-target="bf-seats">
                    <button type="button" class="bf-chip active" data-value="">All Seats</button>
                    <?php foreach ($filterSeatOptions as $seatOpt): ?>
                        <button type="button" class="bf-chip" data-value="<?php echo e($seatOpt); ?>"><?php echo e($seatOpt); ?> Seater</button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="seats" id="bf-seats" value="">

                <!-- Brand -->
                <?php if (count($filterBrandList) > 0): ?>
                    <h3 class="bf-section-title">Brand</h3>
                    <div class="bf-chip-group" data-target="bf-brand">
                        <button type="button" class="bf-chip active" data-value="">All Brands</button>
                        <?php foreach ($filterBrandList as $brandName): ?>
                            <button type="button" class="bf-chip" data-value="<?php echo e($brandName); ?>"><?php echo e($brandName); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="brand" id="bf-brand" value="">
                <?php endif; ?>
            </div>

            <div class="bf-footer">
                <button type="button" class="bf-clear" id="bfClear">Clear</button>
                <button type="submit" class="bf-search">Search</button>
            </div>
        </form>
    </div>
    <!-- =================== END BUGGY FILTER MODAL =================== -->

    <div id="product-focus" class="product-focus-anchor"></div>

    <?php if (($buggy['status'] ?? '') === 'sold'): ?>
    <div class="sold-notice-banner">
        <span class="sold-notice-badge">SOLD</span>
        <span class="sold-notice-text">This buggy has already been sold. You may contact us if you are interested in similar units.</span>
    </div>
    <?php endif; ?>

    <div class="detail-title-row">
        <h1 class="detail-title"><?php echo htmlspecialchars($title); ?></h1>

        <div class="share-wrap">
            <button type="button" class="share-btn" id="shareBtn" aria-label="Share this buggy">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                </svg>
                <span>Share</span>
            </button>

            <div class="share-menu" id="shareMenu">
                <a class="share-opt share-whatsapp" id="shareWhatsapp" target="_blank" rel="noopener">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.6 6.32A7.85 7.85 0 0 0 12.05 4a7.94 7.94 0 0 0-6.88 11.9L4 20l4.22-1.1a7.93 7.93 0 0 0 3.82.98h.01a7.94 7.94 0 0 0 5.55-13.56zm-5.55 12.21h-.01a6.6 6.6 0 0 1-3.36-.92l-.24-.14-2.5.66.67-2.44-.16-.25a6.59 6.59 0 1 1 12.23-3.5 6.6 6.6 0 0 1-6.63 6.59zm3.62-4.94c-.2-.1-1.18-.58-1.36-.65-.18-.07-.31-.1-.45.1-.13.2-.5.65-.62.78-.11.13-.23.15-.43.05-.2-.1-.84-.31-1.6-1-.59-.53-.99-1.18-1.1-1.38-.12-.2-.01-.31.09-.41.09-.09.2-.23.3-.35.1-.12.13-.2.2-.33.07-.13.03-.25-.02-.35-.05-.1-.45-1.08-.62-1.48-.16-.39-.33-.34-.45-.34h-.39c-.13 0-.35.05-.53.25-.18.2-.7.69-.7 1.68 0 .99.72 1.94.82 2.07.1.13 1.41 2.15 3.42 3.02.48.21.85.33 1.14.42.48.15.91.13 1.26.08.38-.06 1.18-.48 1.34-.95.17-.47.17-.86.12-.95-.05-.09-.18-.13-.38-.23z"/></svg>
                    <span>WhatsApp</span>
                </a>
                <button type="button" class="share-opt share-copy" id="shareCopy">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    <span id="shareCopyLabel">Copy Link</span>
                </button>
            </div>
        </div>
    </div>

    <nav class="detail-tabs">
        <a href="buggy-detail.php?id=<?php echo (int) $id; ?>" class="<?php echo $view === 'overview' ? 'active' : ''; ?>">Overview</a>
        <a href="buggy-detail.php?id=<?php echo (int) $id; ?>&view=photos" class="<?php echo $view === 'photos' ? 'active' : ''; ?>">Photos</a>
        <?php if ($view === 'overview'): ?>
            <a href="javascript:void(0)" onclick="return scrollToSection(event, 'specification')">Specification</a>
        <?php else: ?>
            <a href="buggy-detail.php?id=<?php echo (int) $id; ?>#specification">Specification</a>
        <?php endif; ?>
    </nav>

    <?php if ($view === 'overview'): ?>

    <section class="detail-main" id="overview">

        <div class="gallery-area">
            <div class="gallery-main">
                <?php if (count($allImages) > 1): ?>
                    <button type="button" class="gallery-arrow prev" onclick="changeImage(-1)">‹</button>
                <?php endif; ?>

                <div class="gallery-track" id="galleryTrack">
                    <?php if (count($allImages) > 1): /* clone of last image for left-loop */ ?>
                        <div class="gallery-slide">
                            <img src="<?php echo htmlspecialchars($allImages[count($allImages) - 1]); ?>"
                                 alt="<?php echo htmlspecialchars($title); ?>"
                                 onerror="this.src='images/no-image.jpg';">
                        </div>
                    <?php endif; ?>

                    <?php foreach ($allImages as $gIdx => $gImg): ?>
                        <div class="gallery-slide" data-real-index="<?php echo (int)$gIdx; ?>">
                            <img <?php echo $gIdx === 0 ? 'id="mainBuggyImage"' : ''; ?>
                                 src="<?php echo htmlspecialchars($gImg); ?>"
                                 alt="<?php echo htmlspecialchars($title); ?>"
                                 onerror="this.src='images/no-image.jpg';">
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($allImages) > 1): /* clone of first image for right-loop */ ?>
                        <div class="gallery-slide">
                            <img src="<?php echo htmlspecialchars($allImages[0]); ?>"
                                 alt="<?php echo htmlspecialchars($title); ?>"
                                 onerror="this.src='images/no-image.jpg';">
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (count($allImages) > 1): ?>
                    <button type="button" class="gallery-arrow next" onclick="changeImage(1)">›</button>
                <?php endif; ?>
            </div>

            <?php if (count($allImages) > 1): ?>
                <div class="thumb-slider-wrap">
                    <button type="button" class="thumb-arrow left" id="thumbPrevBtn" onclick="scrollThumbs(-1)">‹</button>

                    <div class="thumb-viewport">
                        <div class="thumb-row" id="thumbRow">
                            <?php foreach ($allImages as $index => $image): ?>
                                <div 
                                    class="thumb <?php echo $index === 0 ? 'active' : ''; ?>" 
                                    onclick="setImage(<?php echo $index; ?>)"
                                    data-index="<?php echo $index; ?>"
                                >
                                    <img 
                                        src="<?php echo htmlspecialchars($image); ?>" 
                                        alt="<?php echo htmlspecialchars($title); ?>"
                                        onerror="this.src='images/no-image.jpg';"
                                    >
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="button" class="thumb-arrow right" id="thumbNextBtn" onclick="scrollThumbs(1)">›</button>
                </div>
            <?php endif; ?>
        </div>

        <aside class="info-panel">
            <?php if ($hasPromo): ?>
                <div class="promo-banner">
                    <div class="promo-banner-top">
                        <span class="promo-banner-badge">🔥 <?php echo htmlspecialchars($buggy['promo_label'] ?: 'LIMITED TIME OFFER'); ?></span>
                    </div>
                    <div class="promo-banner-countdown">
                        ⏰ <?php echo htmlspecialchars(buggyPromoTimeLeft($buggy['promo_end_date'])); ?>
                    </div>
                </div>

                <div class="promo-original">
                    <s>$<?php echo number_format((float)$price, 0); ?></s>
                </div>
                <div class="price promo">
                    $<?php echo number_format((float)$buggy['discount_price'], 0); ?>
                </div>
                <div class="monthly promo-save">
                    You save $<?php echo number_format((float)$price - (float)$buggy['discount_price'], 0); ?>
                </div>
            <?php else: ?>
                <div class="price-row-mobile">
                    <div class="price">
                        <?php if ((float) $price > 0): ?>
                            $<?php echo number_format((float) $price, 0); ?>
                        <?php else: ?>
                            Price on request
                        <?php endif; ?>
                    </div>

                    <!-- Mobile share icon (right of price) -->
                    <div class="share-wrap share-wrap-mobile">
                        <button type="button" class="share-icon-btn" id="shareBtnMobile" aria-label="Share">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                            </svg>
                        </button>

                        <div class="share-menu share-menu-mobile" id="shareMenuMobile">
                            <a class="share-opt share-whatsapp" id="shareWhatsappMobile" target="_blank" rel="noopener">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.6 6.32A7.85 7.85 0 0 0 12.05 4a7.94 7.94 0 0 0-6.88 11.9L4 20l4.22-1.1a7.93 7.93 0 0 0 3.82.98h.01a7.94 7.94 0 0 0 5.55-13.56zm-5.55 12.21h-.01a6.6 6.6 0 0 1-3.36-.92l-.24-.14-2.5.66.67-2.44-.16-.25a6.59 6.59 0 1 1 12.23-3.5 6.6 6.6 0 0 1-6.63 6.59z"/></svg>
                                <span>WhatsApp</span>
                            </a>
                            <button type="button" class="share-opt share-copy" id="shareCopyMobile">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                <span id="shareCopyLabelMobile">Copy Link</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="monthly">
                    Contact SGBUGGYMART for best offer
                </div>
            <?php endif; ?>

            <div class="spec-grid">
                <div class="spec-item">
                    <span class="spec-icon">B</span>
                    <?php echo htmlspecialchars($brand ?: 'N/A'); ?>
                </div>

                <div class="spec-item">
                    <span class="spec-icon">M</span>
                    <?php echo htmlspecialchars($model ?: 'N/A'); ?>
                </div>

                <div class="spec-item">
                    <span class="spec-icon">S</span>
                    <?php
                        $seatsDisplay = $seats ?: 'N/A';
                        if ($seatsDisplay !== 'N/A' && stripos($seatsDisplay, 'seater') === false) {
                            $seatsDisplay .= ' Seater';
                        }
                        echo htmlspecialchars($seatsDisplay);
                    ?>
                </div>

                <div class="spec-item">
                    <span class="spec-icon">C</span>
                    <?php echo htmlspecialchars($conditionText ?: 'N/A'); ?>
                </div>
            </div>

            <div class="premium-label">
                <?php echo htmlspecialchars($tag ?: 'Premium Ad'); ?>
            </div>

            <button type="button" class="contact-btn" onclick="openSgContactModal()">
                Contact SGBUGGYMART
            </button>


            <div class="analysis-box">
                This buggy is available for sale.
                <a href="javascript:void(0)" onclick="return scrollToSection(event, 'specification')">View Buggy Details</a>
            </div>
        </aside>

    </section>

    <section class="section-box" id="description">
        <h2>Description</h2>

        <div class="description-text">
            <?php
            if (!empty($description)) {
                echo htmlspecialchars($description);
            } elseif (!empty($shortInfo)) {
                echo htmlspecialchars($shortInfo);
            } else {
                echo 'No description available.';
            }
            ?>
        </div>
    </section>

    <section class="section-box" id="specification">
        <h2>Specification</h2>

        <table class="detail-table">
            <tr>
                <th>Product Name</th>
                <td><?php echo htmlspecialchars($title); ?></td>
            </tr>

            <tr>
                <th>Brand</th>
                <td><?php echo htmlspecialchars($brand ?: 'N/A'); ?></td>
            </tr>

            <tr>
                <th>Condition</th>
                <td><?php echo htmlspecialchars($conditionText ?: 'N/A'); ?></td>
            </tr>

            <tr>
                <th>Seats</th>
                <td><?php echo htmlspecialchars($seats ?: 'N/A'); ?></td>
            </tr>

            <tr>
                <th>Selling Price</th>
                <td>
                    <?php if ((float) $price > 0): ?>
                        $<?php echo number_format((float) $price, 0); ?>
                    <?php else: ?>
                        Price on request
                    <?php endif; ?>
                </td>
            </tr>
            <?php foreach ($buggySpecs as $spec): ?>
                <?php if (!empty($spec['label']) && !empty($spec['value'])): ?>
                    <tr>
                        <th><?php echo htmlspecialchars($spec['label']); ?></th>
                        <td><?php echo htmlspecialchars($spec['value']); ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>
    </section>

    <?php if (!empty($buggy['datasheet_url'])): ?>
        <section class="section-box" id="datasheet">
            <h2>Datasheet</h2>
            <a href="<?php echo htmlspecialchars($buggy['datasheet_url']); ?>"
               target="_blank"
               style="display:inline-flex;align-items:center;gap:8px;background:#ef3f4d;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:700;">
                📄 Download Datasheet (PDF)
            </a>
        </section>
    <?php endif; ?>

    <?php if (!empty($similarBuggies)): ?>
    <section class="section-box" id="similar">
        <h2>Similar Buggies</h2>

        <div class="similar-grid">
            <?php foreach ($similarBuggies as $sim):
                $simTitle = !empty($sim['name']) ? $sim['name'] : trim(($sim['brand'] ?? '') . ' ' . ($sim['model'] ?? ''));
                $simImage = !empty($sim['image_url']) ? $sim['image_url'] : 'images/no-image.jpg';
                $simPrice = (float)($sim['selling_price'] ?? 0);
                $simCond  = ucfirst($sim['buggy_condition'] ?? '');
                $simTag   = $sim['tag'] ?: $simCond;
            ?>
                <a href="buggy-detail.php?id=<?php echo (int)$sim['id']; ?>" class="sim-card">
                    <div class="sim-card-image">
                        <img
                            src="<?php echo htmlspecialchars($simImage); ?>"
                            alt="<?php echo htmlspecialchars($simTitle); ?>"
                            onerror="this.src='images/no-image.jpg';"
                        >
                        <?php if ($simTag): ?>
                            <span class="sim-tag"><?php echo htmlspecialchars($simTag); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="sim-card-body">
                        <div class="sim-card-title"><?php echo htmlspecialchars($simTitle); ?></div>
                        <div class="sim-card-meta">
                            <?php echo htmlspecialchars($sim['brand'] ?? ''); ?>
                            <?php if (!empty($sim['seats'])): ?> · <?php
                                $simSeats = $sim['seats'];
                                if (stripos($simSeats, 'seater') === false) $simSeats .= ' Seater';
                                echo htmlspecialchars($simSeats);
                            ?><?php endif; ?>
                        </div>
                        <div class="sim-card-price">
                            <?php echo $simPrice > 0 ? '$' . number_format($simPrice, 0) : 'Price on request'; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php else: /* === PHOTOS VIEW === */ ?>

    <section class="photos-view">
        <div class="photos-grid">
            <?php foreach ($allImages as $i => $photo): ?>
                <div class="photo-tile" data-index="<?php echo (int) $i; ?>">
                    <img
                        src="<?php echo htmlspecialchars($photo); ?>"
                        alt="<?php echo htmlspecialchars($title); ?>"
                        onerror="this.src='images/no-image.jpg';"
                        loading="lazy"
                    >
                </div>
            <?php endforeach; ?>
        </div>

        <div class="photos-cta">
            <button type="button" class="photos-contact-btn" onclick="openSgContactModal()">
                Contact Seller
            </button>
        </div>
    </section>

    <style>
        .photos-view { padding: 16px 0 40px; }
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 30px;
        }
        .photo-tile {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            border-radius: 12px;
            overflow: hidden;
            background: #f3f4f6;
            cursor: zoom-in;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .photo-tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
        }
        .photo-tile img {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        .photos-cta {
            display: flex;
            justify-content: center;
            margin: 30px 0 10px;
        }
        .photos-contact-btn {
            min-width: 320px;
            height: 50px;
            padding: 0 36px;
            background: #0066cc;
            color: #fff;
            border: 0;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s ease;
        }
        .photos-contact-btn:hover {
            background: #0056ad;
            transform: translateY(-2px);
        }
        .photo-lightbox {
            position: fixed; inset: 0;
            background: rgba(0, 0, 0, 0.92);
            display: none;
            align-items: center; justify-content: center;
            padding: 30px;
            z-index: 99999;
        }
        .photo-lightbox.active { display: flex; }
        .photo-lightbox img {
            max-width: 95vw; max-height: 90vh;
            border-radius: 12px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.5);
        }
        .photo-lightbox-close {
            position: absolute; top: 18px; right: 22px;
            width: 44px; height: 44px;
            border: 0; border-radius: 50%;
            background: #ef3f4d; color: #fff;
            font-size: 26px; line-height: 1;
            cursor: pointer; z-index: 2;
        }
        .photo-lightbox-arrow {
            position: absolute; top: 50%;
            transform: translateY(-50%);
            width: 52px; height: 52px;
            border: 0; border-radius: 50%;
            background: rgba(255, 255, 255, 0.18);
            color: #fff; font-size: 32px; line-height: 1;
            cursor: pointer;
        }
        .photo-lightbox-arrow:hover { background: rgba(255, 255, 255, 0.35); }
        .photo-lightbox-prev { left: 24px; }
        .photo-lightbox-next { right: 24px; }
        .photo-lightbox-counter {
            position: absolute; bottom: 24px; left: 50%;
            transform: translateX(-50%);
            color: #fff; font-size: 14px; font-weight: 700;
            background: rgba(0, 0, 0, 0.5);
            padding: 8px 16px; border-radius: 999px;
        }
        @media (max-width: 900px) { .photos-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 520px) {
            .photos-grid { grid-template-columns: 1fr; }
            .photos-contact-btn { width: 100%; min-width: 0; }
        }
    </style>

    <div class="photo-lightbox" id="photoLightbox">
        <button type="button" class="photo-lightbox-close" id="lightboxClose">×</button>
        <button type="button" class="photo-lightbox-arrow photo-lightbox-prev" id="lightboxPrev">‹</button>
        <img id="lightboxImg" src="" alt="">
        <button type="button" class="photo-lightbox-arrow photo-lightbox-next" id="lightboxNext">›</button>
        <div class="photo-lightbox-counter" id="lightboxCounter"></div>
    </div>

    <script>
        (function () {
            const allPhotos = <?php echo json_encode($allImages); ?>;
            let lightboxIndex = 0;

            const lightbox        = document.getElementById('photoLightbox');
            const lightboxImg     = document.getElementById('lightboxImg');
            const lightboxCounter = document.getElementById('lightboxCounter');

            function showLightbox() {
                if (lightboxIndex < 0) lightboxIndex = allPhotos.length - 1;
                if (lightboxIndex >= allPhotos.length) lightboxIndex = 0;
                lightboxImg.src = allPhotos[lightboxIndex];
                lightboxCounter.textContent = (lightboxIndex + 1) + ' / ' + allPhotos.length;
            }

            function openLightbox(i) {
                lightboxIndex = i;
                showLightbox();
                lightbox.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeLightbox() {
                lightbox.classList.remove('active');
                document.body.style.overflow = '';
            }

            document.querySelectorAll('.photo-tile').forEach(function (tile) {
                tile.addEventListener('click', function () {
                    openLightbox(parseInt(this.dataset.index, 10) || 0);
                });
            });

            document.getElementById('lightboxClose').addEventListener('click', closeLightbox);
            document.getElementById('lightboxPrev').addEventListener('click', function () { lightboxIndex--; showLightbox(); });
            document.getElementById('lightboxNext').addEventListener('click', function () { lightboxIndex++; showLightbox(); });
            lightbox.addEventListener('click', function (e) { if (e.target === lightbox) closeLightbox(); });
            document.addEventListener('keydown', function (e) {
                if (!lightbox.classList.contains('active')) return;
                if (e.key === 'Escape')     closeLightbox();
                if (e.key === 'ArrowLeft')  { lightboxIndex--; showLightbox(); }
                if (e.key === 'ArrowRight') { lightboxIndex++; showLightbox(); }
            });
        })();
    </script>

    <?php endif; /* end view check */ ?>

    <div class="sg-contact-modal-overlay" id="sgContactModalOverlay" onclick="closeSgContactModal(event)">
        <div class="sg-contact-modal" onclick="event.stopPropagation();">
            <button type="button" class="sg-contact-modal-close" onclick="closeSgContactModal()">×</button>

            <h2>SGBUGGYMART</h2>

            <div class="sg-contact-product-label">
                Interested in:
            </div>

            <div class="sg-contact-product-name">
                <?php echo htmlspecialchars($title); ?>
            </div>

            <div class="sg-contact-text">
                Our team will assist you with price, viewing arrangement, and buggy details.
            </div>

            <a
                href="<?php echo htmlspecialchars($whatsappLink); ?>"
                class="sg-whatsapp-btn"
                target="_blank"
                rel="noopener"
            >
                <span class="sg-whatsapp-icon">✓</span>
                WhatsApp SGBUGGYMART
            </a>
        </div>
    </div>

</main>

<script>
    const buggyImages = <?php echo json_encode($allImages); ?>;

    /*
    |--------------------------------------------------------------------------
    | SAVE RECENT VIEW IN BROWSER
    |--------------------------------------------------------------------------
    */
    const currentBuggyRecentView = {
        id: <?php echo (int) $id; ?>,
        title: <?php echo json_encode($title); ?>,
        brand: <?php echo json_encode($brand); ?>,
        model: <?php echo json_encode($model); ?>,
        image: <?php echo json_encode($allImages[0] ?? 'images/no-image.jpg'); ?>,
        price: <?php echo json_encode((float) $price); ?>,
        condition: <?php echo json_encode($conditionText); ?>,
        url: 'buggy-detail.php?id=<?php echo (int) $id; ?>',
        viewed_at: new Date().toISOString()
    };

    function saveGuestRecentView(buggy) {
        const storageKey = 'sgbuggymart_recent_buggies';
        let recentViews = [];

        try {
            const stored = localStorage.getItem(storageKey);

            if (stored) {
                recentViews = JSON.parse(stored);
            }

            if (!Array.isArray(recentViews)) {
                recentViews = [];
            }

            recentViews = recentViews.filter(function (item) {
                return parseInt(item.id) !== parseInt(buggy.id);
            });

            recentViews.unshift(buggy);

            recentViews = recentViews.slice(0, 12);

            localStorage.setItem(storageKey, JSON.stringify(recentViews));
        } catch (error) {
            console.log('Unable to save recent view.');
        }
    }

    saveGuestRecentView(currentBuggyRecentView);

    let currentImageIndex = 0;
    let thumbPage = 0;

    const mainBuggyImage = document.getElementById('mainBuggyImage');
    const thumbs = document.querySelectorAll('.thumb');
    const thumbRow = document.getElementById('thumbRow');
    const thumbPrevBtn = document.getElementById('thumbPrevBtn');
    const thumbNextBtn = document.getElementById('thumbNextBtn');

    function smoothScrollTo(targetPosition, duration) {
        const startPosition = window.pageYOffset;
        const distance = targetPosition - startPosition;
        let startTime = null;

        function animation(currentTime) {
            if (startTime === null) {
                startTime = currentTime;
            }

            const timeElapsed = currentTime - startTime;
            const progress = Math.min(timeElapsed / duration, 1);

            const ease = progress < 0.5
                ? 2 * progress * progress
                : 1 - Math.pow(-2 * progress + 2, 2) / 2;

            window.scrollTo(0, startPosition + distance * ease);

            if (timeElapsed < duration) {
                requestAnimationFrame(animation);
            }
        }

        requestAnimationFrame(animation);
    }

    function scrollToSection(event, sectionId) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        // Move the active styling to the clicked tab
        if (event && event.currentTarget) {
            const allTabs = document.querySelectorAll('.detail-tabs a');
            allTabs.forEach(function (tab) { tab.classList.remove('active'); });
            event.currentTarget.classList.add('active');
        }

        const section = document.getElementById(sectionId);

        if (!section) {
            return false;
        }

        // Header (95) + sticky tabs (~60) for proper scroll offset
        const headerOffset = 190;
        const sectionPosition = section.getBoundingClientRect().top + window.pageYOffset;
        const targetPosition = Math.max(sectionPosition - headerOffset, 0);

        smoothScrollTo(targetPosition, 700);

        return false;
    }

    function getVisibleThumbCount() {
        if (window.innerWidth <= 520) {
            return 3;
        }

        if (window.innerWidth <= 900) {
            return 4;
        }

        return 6;
    }

    function getMaxThumbPage() {
        const visibleCount = getVisibleThumbCount();

        if (buggyImages.length <= visibleCount) {
            return 0;
        }

        return Math.ceil(buggyImages.length / visibleCount) - 1;
    }

    function updateThumbSlider() {
        if (!thumbRow) {
            return;
        }

        const maxPage = getMaxThumbPage();

        if (maxPage <= 0) {
            thumbPage = 0;
        } else if (thumbPage < 0) {
            thumbPage = maxPage;
        } else if (thumbPage > maxPage) {
            thumbPage = 0;
        }

        thumbRow.style.transform = 'translateX(-' + (thumbPage * 100) + '%)';

        if (thumbPrevBtn) {
            thumbPrevBtn.disabled = false;
        }

        if (thumbNextBtn) {
            thumbNextBtn.disabled = false;
        }
    }

    function moveThumbPageToImage(index) {
        const visibleCount = getVisibleThumbCount();
        thumbPage = Math.floor(index / visibleCount);
        updateThumbSlider();
    }

    function updateGallery() {
        thumbs.forEach(function (thumb) { thumb.classList.remove('active'); });
        const activeThumb = document.querySelector('.thumb[data-index="' + currentImageIndex + '"]');
        if (activeThumb) activeThumb.classList.add('active');
        moveThumbPageToImage(currentImageIndex);
    }

    /* ===== Carousel (infinite loop) =====
       The track holds [cloneOfLast, real_0, real_1, ..., real_N-1, cloneOfFirst].
       After a slide animation lands on a clone, we snap (no animation) to the
       matching real slide so the next arrow click continues sliding in the
       same direction. isAnimating prevents rapid clicks from breaking the snap. */
    const galleryTrack = document.getElementById('galleryTrack');
    const totalImages  = buggyImages.length;
    let displayIndex   = totalImages > 1 ? 1 : 0;
    let isAnimating    = false;

    function applyTrackTransform(animate) {
        if (!galleryTrack) return;
        galleryTrack.style.transition = animate ? 'transform 0.4s ease' : 'none';
        galleryTrack.style.transform  = 'translateX(-' + (displayIndex * 100) + '%)';
    }

    if (galleryTrack) {
        applyTrackTransform(false);

        galleryTrack.addEventListener('transitionend', function (e) {
            if (e.propertyName !== 'transform') return;
            if (totalImages > 1) {
                if (displayIndex === 0) {
                    displayIndex = totalImages;
                    applyTrackTransform(false);
                } else if (displayIndex === totalImages + 1) {
                    displayIndex = 1;
                    applyTrackTransform(false);
                }
            }
            isAnimating = false;
        });
    }

    function setImage(index) {
        if (!totalImages || isAnimating) return;
        currentImageIndex = index;
        displayIndex      = totalImages > 1 ? index + 1 : 0;
        isAnimating = true;
        applyTrackTransform(true);
        setTimeout(function () { isAnimating = false; }, 500);
        updateGallery();
    }

    function changeImage(direction) {
        if (totalImages <= 1 || isAnimating) return;
        currentImageIndex += direction;
        displayIndex      += direction;

        if (currentImageIndex < 0)            currentImageIndex = totalImages - 1;
        if (currentImageIndex >= totalImages) currentImageIndex = 0;

        isAnimating = true;
        applyTrackTransform(true);
        setTimeout(function () { isAnimating = false; }, 500);
        updateGallery();
    }

    function scrollThumbs(direction) {
        thumbPage += direction;
        updateThumbSlider();
    }

    function openSgContactModal() {
        const modal = document.getElementById('sgContactModalOverlay');

        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeSgContactModal(event) {
        if (event && event.target && event.target.id !== 'sgContactModalOverlay') {
            return;
        }

        const modal = document.getElementById('sgContactModalOverlay');

        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSgContactModal();
        }
    });

    window.addEventListener('resize', function () {
        moveThumbPageToImage(currentImageIndex);
    });

    window.addEventListener('load', function () {
        // If page loaded with a hash like #specification, highlight that tab
        const hash = window.location.hash;
        if (hash) {
            const sectionId = hash.replace('#', '');
            const allTabs = document.querySelectorAll('.detail-tabs a');
            allTabs.forEach(function (tab) {
                const onclickAttr = tab.getAttribute('onclick') || '';
                const hrefAttr    = tab.getAttribute('href') || '';
                const matches     = onclickAttr.indexOf("'" + sectionId + "'") !== -1
                                 || hrefAttr.indexOf('#' + sectionId) !== -1;
                if (matches) {
                    allTabs.forEach(function (t) { t.classList.remove('active'); });
                    tab.classList.add('active');
                }
            });
        }
        // Note: Auto-scroll-to-gallery on entry removed — page now opens at the top
    });

    /* ========== Share button (WhatsApp + Copy Link) — desktop + mobile ========== */
    (function () {
        const pageUrl   = window.location.origin + window.location.pathname + '?id=<?php echo (int)$id; ?>';
        const pageTitle = <?php echo json_encode($title); ?>;
        const shareText = pageTitle + ' - SGBUGGYMART';

        function bindShare(btnId, menuId, waId, copyId, copyLabelId) {
            const shareBtn  = document.getElementById(btnId);
            const shareMenu = document.getElementById(menuId);
            if (!shareBtn || !shareMenu) return;

            const waLink = document.getElementById(waId);
            if (waLink) waLink.href = 'https://wa.me/?text=' + encodeURIComponent(shareText + '\n' + pageUrl);

            shareBtn.addEventListener('click', async function (e) {
                e.stopPropagation();
                if (navigator.share && /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent)) {
                    try {
                        await navigator.share({ title: pageTitle, text: shareText, url: pageUrl });
                        return;
                    } catch (err) { /* fall back to menu */ }
                }
                shareMenu.classList.toggle('active');
            });

            document.addEventListener('click', function (e) {
                if (!shareMenu.contains(e.target) && e.target !== shareBtn) {
                    shareMenu.classList.remove('active');
                }
            });

            const copyBtn   = document.getElementById(copyId);
            const copyLabel = document.getElementById(copyLabelId);
            if (copyBtn && copyLabel) {
                copyBtn.addEventListener('click', async function () {
                    try {
                        await navigator.clipboard.writeText(pageUrl);
                        copyBtn.classList.add('copied');
                        copyLabel.textContent = '✓ Copied!';
                        setTimeout(function () {
                            copyBtn.classList.remove('copied');
                            copyLabel.textContent = 'Copy Link';
                        }, 2000);
                    } catch (err) {
                        alert('Link: ' + pageUrl);
                    }
                });
            }
        }

        // Desktop share (title row)
        bindShare('shareBtn',       'shareMenu',       'shareWhatsapp',       'shareCopy',       'shareCopyLabel');
        // Mobile share (next to price)
        bindShare('shareBtnMobile', 'shareMenuMobile', 'shareWhatsappMobile', 'shareCopyMobile', 'shareCopyLabelMobile');
    })();

    // Dynamically set sticky tabs `top` to match the actual topbar height (no gap, no overlap)
    function syncStickyTabsTop() {
        const topbar = document.querySelector('.topbar');
        const tabs   = document.querySelector('.detail-tabs');
        if (!topbar || !tabs) return;
        tabs.style.top = Math.round(topbar.getBoundingClientRect().height) + 'px';
    }
    syncStickyTabsTop();
    window.addEventListener('DOMContentLoaded', syncStickyTabsTop);
    window.addEventListener('load',   syncStickyTabsTop);
    window.addEventListener('resize', syncStickyTabsTop);
    if (window.ResizeObserver) {
        const topbarEl = document.querySelector('.topbar');
        if (topbarEl) new ResizeObserver(syncStickyTabsTop).observe(topbarEl);
    }

    updateThumbSlider();
</script>

<script>
    /* ===================== FILTER MODAL ===================== */
    (function () {
        const overlay   = document.getElementById('buggyFilterOverlay');
        const openBtn   = document.getElementById('openFilterModal');
        const closeBtn  = document.getElementById('closeFilterModal');
        const clearBtn  = document.getElementById('bfClear');
        const form      = document.getElementById('buggyFilterForm');

        if (!overlay || !openBtn || !form) return;

        function openModal() {
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        openBtn.addEventListener('click', openModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        /* Close when clicking the dark backdrop (but not the modal itself) */
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });

        /* Close on Escape */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
        });

        /* Chip groups: single-select, write value into the linked hidden input */
        document.querySelectorAll('.bf-chip-group').forEach(function (group) {
            const targetId = group.getAttribute('data-target');
            const hidden   = targetId ? document.getElementById(targetId) : null;

            group.querySelectorAll('.bf-chip').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    group.querySelectorAll('.bf-chip').forEach(function (c) {
                        c.classList.remove('active');
                    });
                    chip.classList.add('active');
                    if (hidden) hidden.value = chip.getAttribute('data-value') || '';
                });
            });
        });

        /* Clear: reset all chips to first option, clear selects + keyword */
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                form.querySelectorAll('.bf-chip-group').forEach(function (group) {
                    const chips  = group.querySelectorAll('.bf-chip');
                    const target = group.getAttribute('data-target');
                    const hidden = target ? document.getElementById(target) : null;
                    chips.forEach(function (c, i) {
                        c.classList.toggle('active', i === 0);
                    });
                    if (hidden) hidden.value = '';
                });
                form.querySelectorAll('select').forEach(function (sel) { sel.value = ''; });
                form.querySelectorAll('input[type="text"]').forEach(function (inp) { inp.value = ''; });
            });
        }
    })();
</script>

<?php include 'footer.php'; ?>