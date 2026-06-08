<?php
require_once '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function productImagePath($imageUrl)
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

function formatWhatsappNumber($phone)
{
    $phone = preg_replace('/[^0-9]/', '', (string)$phone);

    if ($phone === '') {
        return '';
    }

    if (substr($phone, 0, 2) !== '60') {
        $phone = '60' . ltrim($phone, '0');
    }

    return $phone;
}

function addViewImage(&$viewImages, &$usedImages, $imagePath)
{
    $imagePath = trim((string)$imagePath);

    if ($imagePath === '') {
        return;
    }

    $imageKey = strtolower($imagePath);

    if (in_array($imageKey, $usedImages, true)) {
        return;
    }

    $viewImages[] = $imagePath;
    $usedImages[] = $imageKey;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('Invalid product ID.');
}

$stmt = $pdo->prepare("SELECT * FROM buggies WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die('Product not found.');
}

$galleryImages = [];

try {
    $galleryStmt = $pdo->prepare("
        SELECT *
        FROM buggy_images
        WHERE buggy_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $galleryStmt->execute([$id]);
    $galleryImages = $galleryStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $galleryImages = [];
}

/*
    Build image slider array:
    1. Main product image
    2. Gallery images
    Duplicate images will be removed
*/
$viewImages = [];
$usedImages = [];

if (!empty($product['image_url'])) {
    addViewImage($viewImages, $usedImages, productImagePath($product['image_url']));
}

foreach ($galleryImages as $gallery) {
    if (!empty($gallery['image_url'])) {
        addViewImage($viewImages, $usedImages, productImagePath($gallery['image_url']));
    }
}

if (count($viewImages) === 0) {
    $viewImages[] = '../images/no-image.png';
}

include 'header.php';

$listing = strtolower((string)$product['listing_type']);
$statusValue = strtolower((string)$product['status']);

if ($listing === 'sale_rent' || $listing === 'both') {
    $listingLabel = 'Sale + Rent';
    $listingBadgeClass = 'badge-sale-rent';
} elseif ($listing === 'rent') {
    $listingLabel = 'Rent';
    $listingBadgeClass = 'badge-rent';
} else {
    $listingLabel = 'Sale';
    $listingBadgeClass = 'badge-sale';
}

$statusBadgeClass = 'badge-inactive';

if ($statusValue === 'active') {
    $statusBadgeClass = 'badge-active';
} elseif ($statusValue === 'pending') {
    $statusBadgeClass = 'badge-pending';
} elseif ($statusValue === 'sold') {
    $statusBadgeClass = 'badge-sold';
} elseif ($statusValue === 'rented') {
    $statusBadgeClass = 'badge-rented';
}

$sellerName = trim((string)($product['seller_name'] ?? ''));
$sellerPhone = trim((string)($product['seller_phone'] ?? ''));
$sellerWhatsapp = trim((string)($product['seller_whatsapp'] ?? ''));

if ($sellerWhatsapp === '') {
    $sellerWhatsapp = $sellerPhone;
}

$cleanWhatsapp = formatWhatsappNumber($sellerWhatsapp);
$whatsappLink = $cleanWhatsapp !== '' ? 'https://wa.me/' . $cleanWhatsapp : '';
?>

<style>
    .view-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 22px;
    }

    .view-header h1 {
        margin: 0;
        font-size: 30px;
    }

    .view-header p {
        margin: 6px 0 0;
        color: #777;
        font-size: 15px;
    }

    .view-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-light {
        background: #fff;
        color: #222;
        border: 1px solid #ddd;
    }

    .btn-light:hover {
        border-color: #ef3f4d;
        color: #ef3f4d;
        background: #fff;
    }

    .product-view-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 8px 22px rgba(0,0,0,0.04);
        overflow: hidden;
    }

    .product-top {
        display: grid;
        grid-template-columns: 420px 1fr;
        gap: 28px;
        padding: 26px;
        border-bottom: 1px solid #eee;
    }

    .image-slider {
        position: relative;
    }

    .main-image {
        width: 100%;
        height: 300px;
        object-fit: cover;
        border-radius: 14px;
        border: 1px solid #ddd;
        background: #f1f1f1;
        display: block;
    }

    .slider-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 34px;
        height: 34px;
        border: none;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.38);
        color: #ffffff;
        font-size: 32px;
        line-height: 34px;
        cursor: pointer;
        z-index: 5;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 0 4px 0;
        transition: 0.2s ease;
        font-weight: normal;
    }

    .slider-btn:hover {
        background: rgba(0, 0, 0, 0.58);
    }

    .slider-prev {
        left: 14px;
        padding-right: 3px;
    }

    .slider-next {
        right: 14px;
        padding-left: 3px;
    }

    .image-counter {
        position: absolute;
        right: 14px;
        bottom: 14px;
        background: rgba(0, 0, 0, 0.65);
        color: #ffffff;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: bold;
    }

    .gallery-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 14px;
    }

    .gallery-row img {
        width: 92px;
        height: 68px;
        object-fit: cover;
        border-radius: 9px;
        border: 1px solid #ddd;
        background: #f1f1f1;
        cursor: pointer;
        opacity: 0.75;
        transition: 0.2s ease;
    }

    .gallery-row img:hover,
    .gallery-row img.active-thumb {
        opacity: 1;
        border-color: #ef3f4d;
        box-shadow: 0 0 0 2px rgba(239, 63, 77, 0.18);
    }

    .product-title {
        margin: 0 0 8px;
        font-size: 30px;
        color: #111;
    }

    .product-subtitle {
        color: #777;
        font-size: 15px;
        margin-bottom: 18px;
    }

    .badge-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 700;
        text-transform: capitalize;
        white-space: nowrap;
    }

    .badge-sale {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .badge-rent {
        background: #ffedd5;
        color: #c2410c;
    }

    .badge-sale-rent {
        background: #f3e8ff;
        color: #7e22ce;
    }

    .badge-active {
        background: #dcfce7;
        color: #166534;
    }

    .badge-inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-sold {
        background: #e5e7eb;
        color: #374151;
    }

    .badge-rented {
        background: #ccfbf1;
        color: #0f766e;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-top: 18px;
    }

    .info-box {
        border: 1px solid #eee;
        border-radius: 12px;
        padding: 14px;
        background: #fafafa;
    }

    .info-box span {
        display: block;
        color: #777;
        font-size: 13px;
        margin-bottom: 6px;
    }

    .info-box strong {
        display: block;
        color: #111;
        font-size: 16px;
    }

    .price-section,
    .seller-section {
        padding: 26px;
        border-bottom: 1px solid #eee;
    }

    .section-heading {
        margin: 0 0 16px;
        font-size: 20px;
        font-weight: 700;
    }

    .price-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 14px;
    }

    .price-box {
        background: #fff7f8;
        border: 1px solid #ffd4d9;
        border-radius: 14px;
        padding: 16px;
    }

    .price-box span {
        display: block;
        color: #777;
        font-size: 13px;
        margin-bottom: 8px;
    }

    .price-box strong {
        display: block;
        color: #ef3f4d;
        font-size: 20px;
    }

    .seller-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .seller-box {
        background: #fafafa;
        border: 1px solid #eee;
        border-radius: 14px;
        padding: 16px;
    }

    .seller-box span {
        display: block;
        color: #777;
        font-size: 13px;
        margin-bottom: 8px;
    }

    .seller-box strong,
    .seller-box a {
        display: block;
        color: #111;
        font-size: 16px;
        font-weight: 700;
        text-decoration: none;
        word-break: break-word;
    }

    .seller-box a:hover {
        color: #ef3f4d;
    }

    .whatsapp-admin-link {
        color: #16a34a !important;
    }

    .details-section {
        padding: 26px;
    }

    .details-box {
        background: #fafafa;
        border: 1px solid #eee;
        border-radius: 14px;
        padding: 18px;
        line-height: 1.7;
        color: #333;
        white-space: pre-line;
    }

    .empty-text {
        color: #777;
        font-style: italic;
    }

    @media (max-width: 1100px) {
        .product-top {
            grid-template-columns: 1fr;
        }

        .price-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .seller-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 700px) {
        .view-header {
            display: block;
        }

        .view-actions {
            margin-top: 14px;
        }

        .info-grid,
        .price-grid,
        .seller-grid {
            grid-template-columns: 1fr;
        }

        .main-image {
            height: 220px;
        }

        .slider-btn {
            width: 30px;
            height: 30px;
            font-size: 28px;
            line-height: 30px;
        }

        .slider-prev {
            left: 10px;
        }

        .slider-next {
            right: 10px;
        }
    }
</style>

<div class="view-header">
    <div>
        <h1>Product Details</h1>
        <p>View full product information inside admin backend.</p>
    </div>

    <div class="view-actions">
        <a href="product-list.php" class="btn btn-light">Back to Product List</a>
        <a href="product-edit.php?id=<?php echo (int)$product['id']; ?>" class="btn">Edit Product</a>
    </div>
</div>

<div class="product-view-card">
    <div class="product-top">
        <div>
            <div class="image-slider">
                <img
                    src="<?php echo e($viewImages[0]); ?>"
                    alt="<?php echo e($product['name']); ?>"
                    class="main-image"
                    id="mainProductImage"
                    onerror="this.src='../images/no-image.png';"
                >

                <?php if (count($viewImages) > 1): ?>
                    <button type="button" class="slider-btn slider-prev" id="prevImageBtn">
                        ‹
                    </button>

                    <button type="button" class="slider-btn slider-next" id="nextImageBtn">
                        ›
                    </button>

                    <div class="image-counter" id="imageCounter">
                        1 / <?php echo count($viewImages); ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($viewImages) > 1): ?>
                <div class="gallery-row">
                    <?php foreach ($viewImages as $index => $image): ?>
                        <img
                            src="<?php echo e($image); ?>"
                            alt="Gallery Image"
                            class="gallery-thumb <?php echo $index === 0 ? 'active-thumb' : ''; ?>"
                            data-index="<?php echo (int)$index; ?>"
                            onerror="this.src='../images/no-image.png';"
                        >
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <h2 class="product-title">
                <?php echo e($product['name']); ?>
            </h2>

            <div class="product-subtitle">
                <?php echo e($product['brand']); ?> / <?php echo e($product['model']); ?>
            </div>

            <div class="badge-row">
                <span class="badge <?php echo e($listingBadgeClass); ?>">
                    <?php echo e($listingLabel); ?>
                </span>

                <span class="badge <?php echo e($statusBadgeClass); ?>">
                    <?php echo e($product['status']); ?>
                </span>

                <?php if (!empty($product['tag'])): ?>
                    <span class="badge badge-sale-rent">
                        <?php echo e($product['tag']); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="info-grid">
                <div class="info-box">
                    <span>Brand</span>
                    <strong><?php echo e($product['brand']); ?></strong>
                </div>

                <div class="info-box">
                    <span>Brand Tag</span>
                    <strong><?php echo !empty($product['brand_tag']) ? e($product['brand_tag']) : '-'; ?></strong>
                </div>

                <div class="info-box">
                    <span>Model</span>
                    <strong><?php echo e($product['model']); ?></strong>
                </div>

                <div class="info-box">
                    <span>Seats</span>
                    <strong><?php echo e($product['seats']); ?> Seater</strong>
                </div>

                <div class="info-box">
                    <span>Condition</span>
                    <strong><?php echo e($product['buggy_condition']); ?></strong>
                </div>

                <div class="info-box">
                    <span>Serial #</span>
                    <strong>
                        <?php if (!empty($product['serial_number'])): ?>
                            <span style="font-family:monospace;background:#f3f4f6;padding:2px 8px;border-radius:6px;">
                                <?php echo e($product['serial_number']); ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </strong>
                </div>

                <div class="info-box">
                    <span>Total Views</span>
                    <strong><?php echo number_format((int)($product['total_views'] ?? 0)); ?></strong>
                </div>

                <div class="info-box">
                    <span>Created At</span>
                    <strong>
                        <?php echo !empty($product['created_at']) ? e(date('Y-m-d', strtotime($product['created_at']))) : '-'; ?>
                    </strong>
                </div>
            </div>
        </div>
    </div>

    <?php
        $sellingPriceVal  = (float)($product['selling_price'] ?? 0);
        $discountPriceVal = (float)($product['discount_price'] ?? 0);
        $promoEnabledVal  = (int)($product['promo_enabled'] ?? 0) === 1;
        $promoEndVal      = $product['promo_end_date'] ?? '';

        $promoActive = $promoEnabledVal
            && $discountPriceVal > 0
            && $discountPriceVal < $sellingPriceVal
            && !empty($promoEndVal)
            && strtotime($promoEndVal) > time();
    ?>
    <div class="price-section">
        <h3 class="section-heading">Price Information</h3>

        <div class="price-grid">
            <div class="price-box">
                <span>Selling Price</span>
                <strong>
                    <?php echo $sellingPriceVal > 0 ? 'RM ' . number_format($sellingPriceVal, 2) : '-'; ?>
                </strong>
            </div>

            <div class="price-box">
                <span>Discount Price</span>
                <strong>
                    <?php echo $discountPriceVal > 0 ? 'RM ' . number_format($discountPriceVal, 2) : '-'; ?>
                </strong>
            </div>

            <div class="price-box">
                <span>Promo</span>
                <strong>
                    <?php if ($promoActive): ?>
                        <span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:800;">ACTIVE</span>
                    <?php elseif ($promoEnabledVal): ?>
                        <span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:800;">ENABLED (Inactive)</span>
                    <?php else: ?>
                        <span style="color:#777;">Off</span>
                    <?php endif; ?>
                </strong>
            </div>

            <div class="price-box">
                <span>Promo End Date</span>
                <strong>
                    <?php echo !empty($promoEndVal) ? e(date('Y-m-d H:i', strtotime($promoEndVal))) : '-'; ?>
                </strong>
            </div>
        </div>
    </div>

    <div class="seller-section">
        <h3 class="section-heading">Seller Contact Information</h3>

        <div class="seller-grid">
            <div class="seller-box">
                <span>Seller Name</span>
                <strong><?php echo $sellerName !== '' ? e($sellerName) : '-'; ?></strong>
            </div>

            <div class="seller-box">
                <span>Seller WhatsApp</span>

                <?php if ($whatsappLink !== ''): ?>
                    <a class="whatsapp-admin-link" href="<?php echo e($whatsappLink); ?>" target="_blank">
                        WhatsApp: <?php echo e($sellerWhatsapp); ?>
                    </a>
                <?php else: ?>
                    <strong>-</strong>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="details-section">
        <h3 class="section-heading">Short Info</h3>

        <div class="details-box">
            <?php if (!empty($product['short_info'])): ?>
                <?php echo e($product['short_info']); ?>
            <?php else: ?>
                <span class="empty-text">No short info added.</span>
            <?php endif; ?>
        </div>

        <br>

        <h3 class="section-heading">Description</h3>

        <div class="details-box">
            <?php if (!empty($product['description'])): ?>
                <?php echo e($product['description']); ?>
            <?php else: ?>
                <span class="empty-text">No description added.</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const productImages = <?php echo json_encode($viewImages); ?>;
    const mainImage = document.getElementById('mainProductImage');
    const prevBtn = document.getElementById('prevImageBtn');
    const nextBtn = document.getElementById('nextImageBtn');
    const counter = document.getElementById('imageCounter');
    const thumbs = document.querySelectorAll('.gallery-thumb');

    let currentIndex = 0;

    function showImage(index) {
        if (!mainImage || productImages.length === 0) {
            return;
        }

        if (index < 0) {
            index = productImages.length - 1;
        }

        if (index >= productImages.length) {
            index = 0;
        }

        currentIndex = index;
        mainImage.src = productImages[currentIndex];

        if (counter) {
            counter.textContent = (currentIndex + 1) + ' / ' + productImages.length;
        }

        thumbs.forEach(function (thumb) {
            thumb.classList.remove('active-thumb');
        });

        if (thumbs[currentIndex]) {
            thumbs[currentIndex].classList.add('active-thumb');
        }
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            showImage(currentIndex - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            showImage(currentIndex + 1);
        });
    }

    thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            showImage(parseInt(this.dataset.index, 10));
        });
    });
});
</script>

<?php include 'footer.php'; ?>