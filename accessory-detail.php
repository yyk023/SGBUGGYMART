<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('Invalid accessory ID.');
}

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM accessories
        WHERE id = :id
        AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();

    if (!$item) {
        die('Accessory not found.');
    }
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

$title           = !empty($item['name']) ? $item['name'] : trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? ''));
if ($title === '') $title = 'Accessory Detail';

$brand           = $item['brand'] ?? '';
$model           = $item['model'] ?? '';
$price           = $item['selling_price'] ?? 0;

/* Promo detection: active when discount_price < selling_price */
$hasPromo = false;
if (!empty($item['promo_enabled']) && (int)$item['promo_enabled'] === 1
    && !empty($item['promo_end_date'])
    && !empty($item['discount_price'])
    && (float)$item['discount_price'] < (float)$item['selling_price']
    && strtotime($item['promo_end_date']) > time()) {
    $hasPromo = true;
}

function accPromoTimeLeft($endDate)
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

$shortInfo       = $item['short_info'] ?? '';
$description     = $item['description'] ?? '';
$tag             = $item['tag'] ?? '';
$serialNumber    = $item['serial_number'] ?? '';
$leadTime        = $item['lead_time'] ?? '';
$manufactureYear = $item['manufacture_year'] ?? '';

$sgbuggymartWhatsapp = '65XXXXXXXX';
$cleanWhatsapp       = preg_replace('/[^0-9]/', '', $sgbuggymartWhatsapp);
$whatsappMessage     = rawurlencode('Hi SGBUGGYMART, I am interested in this accessory: ' . $title);
$whatsappLink        = $cleanWhatsapp !== ''
    ? 'https://wa.me/' . $cleanWhatsapp . '?text=' . $whatsappMessage
    : '#';

$mainImage = !empty($item['image_url']) ? $item['image_url'] : 'images/no-image.jpg';

$galleryImages = [];
try {
    $galleryStmt = $pdo->prepare("
        SELECT image_url
        FROM accessory_images
        WHERE accessory_id = :accessory_id
        ORDER BY sort_order ASC, id ASC
    ");
    $galleryStmt->execute([':accessory_id' => $id]);
    $galleryImages = $galleryStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $galleryImages = [];
}

$allImages = [];
if (!empty($mainImage)) $allImages[] = $mainImage;
foreach ($galleryImages as $galleryImage) {
    if (!empty($galleryImage) && !in_array($galleryImage, $allImages, true)) {
        $allImages[] = $galleryImage;
    }
}
if (count($allImages) === 0) $allImages[] = 'images/no-image.jpg';

include 'header.php';
?>

<style>
    html { scroll-behavior: smooth; }
    body { background: #fff; color: #1f2937; }

    .detail-page {
        max-width: 1220px;
        margin: 0 auto;
        padding: 24px 20px 60px;
    }

    .product-focus-anchor { height: 1px; scroll-margin-top: 95px; }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: #8b95a1;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }

    .breadcrumb a { color: #8b95a1; text-decoration: none; }
    .breadcrumb a:hover { color: #3b41c8; }
    .breadcrumb span { color: #c0c5cc; }

    .detail-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 22px;
    }

    .detail-title { font-size: 22px; font-weight: 800; color: #172033; margin: 0; }

    .detail-tabs {
        display: flex;
        gap: 40px;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 22px;
    }

    .detail-tabs a {
        text-decoration: none;
        color: #111827;
        padding-bottom: 15px;
        font-size: 15px;
        position: relative;
        cursor: pointer;
    }

    .detail-tabs a.active { color: #3b41c8; font-weight: 700; }

    .detail-tabs a.active::after {
        content: "";
        position: absolute;
        left: 0; bottom: -1px;
        width: 100%; height: 3px;
        background: #3b41c8;
    }

    .detail-main {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 32px;
        align-items: start;
        scroll-margin-top: 95px;
    }

    .gallery-area { scroll-margin-top: 95px; }

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

    .gallery-main img {
        width: 100%; height: 100%;
        object-fit: cover;
        object-position: center;
        display: block;
        background: #f3f4f6;
    }

    .gallery-arrow {
        position: absolute;
        top: 50%; transform: translateY(-50%);
        width: 40px; height: 40px;
        border: none; border-radius: 50%;
        background: rgba(0,0,0,0.38);
        color: #fff; font-size: 40px; line-height: 40px;
        cursor: pointer; z-index: 5;
        display: flex; align-items: center; justify-content: center;
        padding: 0 0 4px 0; transition: 0.2s ease;
    }

    .gallery-arrow:hover { background: rgba(0,0,0,0.58); }
    .gallery-arrow.prev { left: 16px; padding-right: 3px; }
    .gallery-arrow.next { right: 16px; padding-left: 3px; }

    .thumb-slider-wrap { position: relative; margin-top: 20px; padding: 0 42px; }
    .thumb-viewport { overflow: hidden; width: 100%; }

    .thumb-row {
        display: flex; gap: 16px;
        transition: transform 0.35s ease;
        will-change: transform;
    }

    .thumb {
        width: calc((100% - 80px) / 6);
        height: 70px;
        border-radius: 4px; overflow: hidden;
        border: 2px solid transparent;
        flex: 0 0 calc((100% - 80px) / 6);
        cursor: pointer; opacity: 0.45;
        background: #f3f4f6; transition: 0.2s ease;
    }

    .thumb:hover { opacity: 0.8; }
    .thumb.active { border-color: #3b41c8; opacity: 1; }
    .thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

    .thumb-arrow {
        position: absolute; top: 0; bottom: 0; width: 34px;
        border: none; background: rgba(0,0,0,0.35);
        color: #fff; font-size: 24px; cursor: pointer; z-index: 4;
        display: flex; align-items: center; justify-content: center;
        transition: 0.2s ease;
    }

    .thumb-arrow:hover { background: rgba(0,0,0,0.55); }
    .thumb-arrow.left  { left: 0; }
    .thumb-arrow.right { right: 0; }
    .thumb-arrow:disabled { opacity: 0.25; cursor: not-allowed; }

    .price {
        color: #3b41c8;
        font-size: 28px;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .price.promo { color: #ef3f4d; }

    .promo-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        background: linear-gradient(135deg, #fff1f0, #fff7ed);
        border: 1px solid #fed7aa;
        border-radius: 10px;
        padding: 10px 14px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    .promo-banner-badge {
        background: linear-gradient(135deg, #ef4444, #f97316);
        color: #fff;
        font-size: 12px;
        font-weight: 800;
        padding: 5px 12px;
        border-radius: 999px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .promo-banner-countdown {
        color: #c2410c;
        font-size: 13px;
        font-weight: 800;
    }

    .promo-original {
        font-size: 18px;
        color: #6b7280;
        font-weight: 700;
        margin-bottom: 2px;
    }

    .promo-original s {
        text-decoration: line-through;
        text-decoration-color: #ef4444;
        text-decoration-thickness: 2px;
    }

    .promo-save {
        display: inline-block;
        background: #dcfce7;
        color: #16a34a;
        font-size: 13px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 999px;
        margin-bottom: 20px;
    }

    .monthly { font-size: 14px; color: #111827; margin-bottom: 26px; }

    .spec-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 22px 34px;
        margin-bottom: 24px;
    }

    .spec-item {
        display: flex; align-items: center; gap: 10px;
        color: #111827; font-size: 15px; font-weight: 600;
    }

    .spec-icon {
        width: 18px; height: 18px;
        border: 1.5px solid #3b41c8;
        border-radius: 5px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 11px; color: #3b41c8; flex: 0 0 auto;
    }

    .premium-label {
        font-size: 10px; font-weight: 700;
        letter-spacing: 0.04em; color: #555;
        margin-bottom: 24px; text-transform: uppercase;
    }

    .contact-btn {
        width: 100%; height: 44px; border: none;
        background: #3b41c8; color: #fff;
        font-size: 16px; font-weight: 700; border-radius: 6px;
        cursor: pointer; margin-bottom: 12px; transition: 0.2s ease;
        display: flex; align-items: center; justify-content: center;
        text-decoration: none; box-sizing: border-box;
    }

    .contact-btn:hover { background: #2c31a8; color: #fff; }


    .analysis-box {
        background: #f0f1ff;
        padding: 28px 20px;
        text-align: center;
        font-size: 14px; line-height: 1.6; color: #172033;
        border-radius: 8px;
    }

    .analysis-box strong { font-weight: 800; }
    .analysis-box a { display: block; margin-top: 2px; color: #3b41c8; text-decoration: none; font-weight: 700; }

    .section-box {
        margin-top: 42px;
        border-top: 1px solid #e5e7eb;
        padding-top: 28px;
        scroll-margin-top: 95px;
    }

    .section-box h2 { font-size: 22px; margin: 0 0 16px; color: #172033; }

    .description-text {
        color: #4b5563; font-size: 15px;
        line-height: 1.8; white-space: pre-line;
    }

    .detail-table {
        width: 100%; border-collapse: collapse;
        margin-top: 8px; font-size: 15px;
    }

    .detail-table tr { border-bottom: 1px solid #edf0f3; }

    .detail-table th {
        width: 220px; text-align: left;
        padding: 14px 0; color: #6b7280; font-weight: 500;
    }

    .detail-table td { padding: 14px 0; color: #111827; font-weight: 600; }

    .sg-contact-modal-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.55);
        z-index: 9998; display: none;
        align-items: center; justify-content: center; padding: 20px;
    }

    .sg-contact-modal-overlay.active { display: flex; }

    .sg-contact-modal {
        width: 100%; max-width: 560px;
        background: #fff; border-radius: 16px;
        padding: 34px 42px 40px; position: relative;
        box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    }

    .sg-contact-modal-close {
        position: absolute; top: 18px; right: 22px;
        border: none; background: transparent;
        color: #777; font-size: 34px; line-height: 1; cursor: pointer;
    }

    .sg-contact-modal h2       { margin: 0 0 22px; color: #333; font-size: 28px; font-weight: 800; }
    .sg-contact-product-label  { color: #777; font-size: 14px; margin-bottom: 7px; }
    .sg-contact-product-name   { color: #172033; font-size: 17px; font-weight: 800; margin-bottom: 18px; line-height: 1.5; }
    .sg-contact-text           { color: #4b5563; font-size: 15px; line-height: 1.7; margin-bottom: 24px; }

    .sg-whatsapp-btn {
        width: 100%; height: 46px; border: none; border-radius: 8px;
        background: #25d366; color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        gap: 8px; text-decoration: none; font-size: 16px; font-weight: 800;
        transition: 0.2s ease;
    }

    .sg-whatsapp-btn:hover { background: #1fb85a; color: #fff; }

    .sg-whatsapp-icon {
        width: 22px; height: 22px; border-radius: 50%;
        background: rgba(255,255,255,0.25);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 800;
    }

    @media (max-width: 900px) {
        .detail-main          { grid-template-columns: 1fr; }
        .detail-title-row     { align-items: flex-start; flex-direction: column; }
        .detail-tabs          { gap: 24px; overflow-x: auto; }
        .thumb                { width: calc((100% - 48px) / 4); flex-basis: calc((100% - 48px) / 4); height: 76px; }
    }

    @media (max-width: 520px) {
        .detail-page          { padding: 18px 14px 50px; }
        .detail-title         { font-size: 20px; }
        .gallery-arrow        { width: 36px; height: 36px; font-size: 34px; }
        .gallery-arrow.prev   { left: 10px; }
        .gallery-arrow.next   { right: 10px; }
        .thumb-slider-wrap    { padding: 0 34px; }
        .thumb                { width: calc((100% - 24px) / 3); flex-basis: calc((100% - 24px) / 3); height: 66px; }
        .spec-grid            { grid-template-columns: 1fr; }
        .detail-table th      { width: 145px; }
        .sg-contact-modal     { padding: 30px 24px 32px; }
        .sg-contact-modal h2  { font-size: 24px; }
    }
</style>

<main class="detail-page">

    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>›</span>
        <a href="accessories.php">Accessories</a>
        <span>›</span>
        <a href="accessories.php?brand=<?php echo urlencode($brand); ?>"><?php echo htmlspecialchars($brand); ?></a>
        <span>›</span>
        <strong><?php echo htmlspecialchars($title); ?></strong>
    </div>

    <div id="product-focus" class="product-focus-anchor"></div>

    <div class="detail-title-row">
        <h1 class="detail-title"><?php echo htmlspecialchars($title); ?></h1>
    </div>

    <nav class="detail-tabs">
        <a href="javascript:void(0)" class="active" onclick="return scrollToSection(event, 'overview')">Overview</a>
        <a href="javascript:void(0)" onclick="return scrollToSection(event, 'photos')">Photos</a>
        <a href="javascript:void(0)" onclick="return scrollToSection(event, 'specification')">Specification</a>
    </nav>

    <section class="detail-main" id="overview">
        <div class="gallery-area" id="photos">
            <div class="gallery-main">
                <?php if (count($allImages) > 1): ?>
                    <button type="button" class="gallery-arrow prev" onclick="changeImage(-1)">&#8249;</button>
                <?php endif; ?>

                <img
                    id="mainAccImage"
                    src="<?php echo htmlspecialchars($allImages[0]); ?>"
                    alt="<?php echo htmlspecialchars($title); ?>"
                    onerror="this.src='images/no-image.jpg';"
                >

                <?php if (count($allImages) > 1): ?>
                    <button type="button" class="gallery-arrow next" onclick="changeImage(1)">&#8250;</button>
                <?php endif; ?>
            </div>

            <?php if (count($allImages) > 1): ?>
                <div class="thumb-slider-wrap">
                    <button type="button" class="thumb-arrow left" id="thumbPrevBtn" onclick="scrollThumbs(-1)">&#8249;</button>

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

                    <button type="button" class="thumb-arrow right" id="thumbNextBtn" onclick="scrollThumbs(1)">&#8250;</button>
                </div>
            <?php endif; ?>
        </div>

        <aside class="info-panel">
            <?php if ($hasPromo): ?>
                <div class="promo-banner">
                    <span class="promo-banner-badge">🔥 <?php echo htmlspecialchars($item['promo_label'] ?: 'LIMITED TIME OFFER'); ?></span>
                    <span class="promo-banner-countdown">⏰ <?php echo htmlspecialchars(accPromoTimeLeft($item['promo_end_date'])); ?></span>
                </div>

                <div class="promo-original">
                    <s>$<?php echo number_format((float)$price, 0); ?></s>
                </div>
                <div class="price promo">
                    $<?php echo number_format((float)$item['discount_price'], 0); ?>
                </div>
                <div class="promo-save">
                    You save $<?php echo number_format((float)$price - (float)$item['discount_price'], 0); ?>
                </div>
            <?php else: ?>
                <div class="price">
                    <?php if ((float)$price > 0): ?>
                        $<?php echo number_format((float)$price, 0); ?>
                    <?php else: ?>
                        Price on request
                    <?php endif; ?>
                </div>

                <div class="monthly">Contact SGBUGGYMART for best offer</div>
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
                <?php if (!empty($manufactureYear)): ?>
                    <div class="spec-item">
                        <span class="spec-icon">Y</span>
                        <?php echo htmlspecialchars($manufactureYear); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($leadTime)): ?>
                    <div class="spec-item">
                        <span class="spec-icon">L</span>
                        <?php echo htmlspecialchars($leadTime); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="premium-label">
                <?php echo htmlspecialchars($tag ?: 'Accessory'); ?>
            </div>

            <button type="button" class="contact-btn" onclick="openSgContactModal()">
                Contact SGBUGGYMART
            </button>

            <?php /* Add to Cart removed */ ?>
            </a>

            <div class="analysis-box">
                This accessory is available for purchase.
                <a href="javascript:void(0)" onclick="return scrollToSection(event, 'specification')">View Full Details</a>
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
                <th>Model</th>
                <td><?php echo htmlspecialchars($model ?: 'N/A'); ?></td>
            </tr>
            <?php if (!empty($manufactureYear)): ?>
                <tr>
                    <th>Manufacture Year</th>
                    <td><?php echo htmlspecialchars($manufactureYear); ?></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($serialNumber)): ?>
                <tr>
                    <th>Serial Number</th>
                    <td><?php echo htmlspecialchars($serialNumber); ?></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($leadTime)): ?>
                <tr>
                    <th>Lead Time</th>
                    <td><?php echo htmlspecialchars($leadTime); ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <th>Selling Price</th>
                <td>
                    <?php if ((float)$price > 0): ?>
                        $<?php echo number_format((float)$price, 0); ?>
                    <?php else: ?>
                        Price on request
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </section>

    <div class="sg-contact-modal-overlay" id="sgContactModalOverlay" onclick="closeSgContactModal(event)">
        <div class="sg-contact-modal" onclick="event.stopPropagation();">
            <button type="button" class="sg-contact-modal-close" onclick="closeSgContactModal()">&times;</button>
            <h2>SGBUGGYMART</h2>
            <div class="sg-contact-product-label">Interested in:</div>
            <div class="sg-contact-product-name"><?php echo htmlspecialchars($title); ?></div>
            <div class="sg-contact-text">
                Our team will assist you with price, availability and product details.
            </div>
            <a href="<?php echo $whatsappLink; ?>" class="sg-whatsapp-btn" target="_blank" rel="noopener">
                <span class="sg-whatsapp-icon">&#10003;</span>
                WhatsApp SGBUGGYMART
            </a>
        </div>
    </div>

</main>

<script>
    const accImages    = <?php echo json_encode($allImages); ?>;
    let currentImageIndex = 0;
    let thumbPage         = 0;

    const mainAccImage = document.getElementById('mainAccImage');
    const thumbs       = document.querySelectorAll('.thumb');
    const thumbRow     = document.getElementById('thumbRow');
    const thumbPrevBtn = document.getElementById('thumbPrevBtn');
    const thumbNextBtn = document.getElementById('thumbNextBtn');

    function smoothScrollTo(targetPosition, duration) {
        const startPosition = window.pageYOffset;
        const distance      = targetPosition - startPosition;
        let startTime       = null;

        function animation(currentTime) {
            if (startTime === null) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const progress    = Math.min(timeElapsed / duration, 1);
            const ease        = progress < 0.5
                ? 2 * progress * progress
                : 1 - Math.pow(-2 * progress + 2, 2) / 2;
            window.scrollTo(0, startPosition + distance * ease);
            if (timeElapsed < duration) requestAnimationFrame(animation);
        }

        requestAnimationFrame(animation);
    }

    function scrollToSection(event, sectionId) {
        if (event) { event.preventDefault(); event.stopPropagation(); }
        const section = document.getElementById(sectionId);
        if (!section) return false;
        const targetPosition = Math.max(section.getBoundingClientRect().top + window.pageYOffset - 95, 0);
        smoothScrollTo(targetPosition, 700);
        return false;
    }

    function getVisibleThumbCount() {
        if (window.innerWidth <= 520) return 3;
        if (window.innerWidth <= 900) return 4;
        return 6;
    }

    function getMaxThumbPage() {
        const visibleCount = getVisibleThumbCount();
        if (accImages.length <= visibleCount) return 0;
        return Math.ceil(accImages.length / visibleCount) - 1;
    }

    function updateThumbSlider() {
        if (!thumbRow) return;
        const maxPage = getMaxThumbPage();
        if (thumbPage < 0) thumbPage = 0;
        if (thumbPage > maxPage) thumbPage = maxPage;
        thumbRow.style.transform = 'translateX(-' + (thumbPage * 100) + '%)';
        if (thumbPrevBtn) thumbPrevBtn.disabled = thumbPage === 0;
        if (thumbNextBtn) thumbNextBtn.disabled = thumbPage === maxPage;
    }

    function moveThumbPageToImage(index) {
        thumbPage = Math.floor(index / getVisibleThumbCount());
        updateThumbSlider();
    }

    function updateGallery() {
        if (!accImages.length) return;
        mainAccImage.src = accImages[currentImageIndex];
        thumbs.forEach(t => t.classList.remove('active'));
        const activeThumb = document.querySelector('.thumb[data-index="' + currentImageIndex + '"]');
        if (activeThumb) activeThumb.classList.add('active');
        moveThumbPageToImage(currentImageIndex);
    }

    function setImage(index)    { currentImageIndex = index; updateGallery(); }

    function changeImage(direction) {
        currentImageIndex += direction;
        if (currentImageIndex < 0) currentImageIndex = accImages.length - 1;
        if (currentImageIndex >= accImages.length) currentImageIndex = 0;
        updateGallery();
    }

    function scrollThumbs(direction) { thumbPage += direction; updateThumbSlider(); }

    function openSgContactModal() {
        const modal = document.getElementById('sgContactModalOverlay');
        if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }
    }

    function closeSgContactModal(event) {
        if (event && event.target && event.target.id !== 'sgContactModalOverlay') return;
        const modal = document.getElementById('sgContactModalOverlay');
        if (modal) { modal.classList.remove('active'); document.body.style.overflow = ''; }
    }

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSgContactModal(); });
    window.addEventListener('resize', function () { moveThumbPageToImage(currentImageIndex); });

    updateThumbSlider();
</script>

<?php include 'footer.php'; ?>