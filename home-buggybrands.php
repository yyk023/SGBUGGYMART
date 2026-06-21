<?php
$popularBrands = [];
$allBrands = [];

try {
    $stmt = $pdo->query("
        SELECT brand_name
        FROM product_brands
        WHERE status = 'active'
        ORDER BY brand_name ASC
    ");

    $allBrands = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $popularBrands = array_slice($allBrands, 0, 12);
} catch (PDOException $e) {
    $allBrands = [
        'Club Car',
        'Yamaha',
        'EZGO',
        'HDK',
        'Marshell',
        'RoyPow'
    ];

    $popularBrands = $allBrands;
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Fetch ALL active side ads for "side_brands" location
$brandsSideAds = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM banners
        WHERE type = 'ad' AND location = 'side_brands' AND status = 'active'
        ORDER BY sort_order ASC, id ASC
        LIMIT 5
    ");
    $stmt->execute();
    $brandsSideAds = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $brandsSideAds = [];
}
?>

<section class="home-brand-section">
    <div class="home-brand-container">
        <div class="home-section-title">
            <span></span>
            <h2>Explore Buggy Brands</h2>
        </div>

        <div class="home-brand-tabs">
            <button type="button" class="brand-tab active" data-tab="popular">Popular</button>
            <button type="button" class="brand-tab" data-tab="all">All</button>
        </div>

        <div class="home-brand-layout">
            <div class="home-brand-list" id="brandList">
                <?php if (count($popularBrands) > 0): ?>
                    <?php foreach ($popularBrands as $brand): ?>
                        <button type="button" class="brand-name" data-brand="<?php echo e($brand); ?>">
                            <?php echo e($brand); ?>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty-brand-message">No brands available yet.</p>
                <?php endif; ?>
            </div>

            <?php if (count($brandsSideAds) > 0): ?>
                <div class="home-brand-ad-slider" id="brandsAdSlider">
                    <div class="brand-ad-slides">
                        <?php foreach ($brandsSideAds as $idx => $ad):
                            $adImg = $ad['image_url'];
                            if (strpos($adImg, 'http') !== 0 && strpos($adImg, '/') !== 0) {
                                $adImg = '/' . ltrim($adImg, '/');
                            }
                            $hasLink = !empty($ad['link_url']);
                        ?>
                            <div class="brand-ad-slide <?php echo $idx === 0 ? 'active' : ''; ?>" data-index="<?php echo $idx; ?>">
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

                    <div class="brand-ad-controls">
                        <?php if (count($brandsSideAds) > 1): ?>
                            <button type="button" class="brand-ad-arrow prev" id="brandsAdPrev" aria-label="Previous">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="15 18 9 12 15 6"></polyline>
                                </svg>
                            </button>
                        <?php endif; ?>

                        <div class="brand-ad-dots">
                            <?php foreach ($brandsSideAds as $idx => $ad): ?>
                                <button type="button" class="brand-ad-dot <?php echo $idx === 0 ? 'active' : ''; ?>" data-index="<?php echo $idx; ?>" aria-label="Go to slide <?php echo $idx + 1; ?>"></button>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($brandsSideAds) > 1): ?>
                            <button type="button" class="brand-ad-arrow next" id="brandsAdNext" aria-label="Next">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="home-brand-ad">
                    <div class="brand-ad-content">
                        <p>SGBUGGYMART</p>
                        <h3>New & Used Buggy Available</h3>
                        <span>For sale, events, resorts & commercial use</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <p class="selected-brand-text" id="selectedBrandText">
            Select a brand to search available buggies.
        </p>
    </div>
</section>

<style>
    .home-brand-section {
        background: #ffffff;
        padding: 30px 20px 45px;
    }

    .home-brand-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .home-section-title {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 22px;
    }

    .home-section-title span {
        width: 4px;
        height: 24px;
        background: #0d6efd;
        border-radius: 20px;
        display: inline-block;
    }

    .home-section-title h2 {
        margin: 0;
        color: #111111;
        font-size: 24px;
        font-weight: 900;
    }

    .home-brand-tabs {
        display: flex;
        gap: 12px;
        margin-bottom: 28px;
    }

    .brand-tab {
        min-width: 120px;
        height: 42px;
        border: 0;
        border-radius: 50px;
        background: #f6f6f6;
        color: #555555;
        font-size: 15px;
        font-weight: 800;
        cursor: pointer;
        transition: 0.25s ease;
    }

    .brand-tab:hover {
        background: #eef5ff;
        color: #0d6efd;
        transform: translateY(-2px);
    }

    .brand-tab.active {
        background: #eef5ff;
        color: #0d6efd;
    }

    .home-brand-layout {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 40px;
        align-items: start;
    }

    .home-brand-list {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        row-gap: 16px;
        column-gap: 35px;
    }

    .brand-name {
        width: fit-content;
        border: 0;
        background: transparent;
        color: #555555;
        font-size: 15px;
        line-height: 1.4;
        text-align: left;
        padding: 6px 0;
        cursor: pointer;
        transition: 0.25s ease;
    }

    .brand-name:hover {
        color: #0d6efd;
        transform: translateX(5px);
    }

    .brand-name.active {
        color: #0d6efd;
        font-weight: 900;
    }

    .empty-brand-message {
        grid-column: 1 / -1;
        margin: 0;
        color: #777777;
        font-size: 14px;
    }

    .home-brand-ad-slider {
        position: relative;
        margin-top: -25px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: transparent;
    }

    .brand-ad-slides {
        position: relative;
        width: 100%;
        aspect-ratio: 4 / 3;
        border-radius: 8px;
        overflow: hidden;
    }

    .brand-ad-slide {
        position: absolute;
        inset: 0;
        opacity: 0;
        transition: opacity 0.5s ease;
        pointer-events: none;
    }

    .brand-ad-slide.active {
        opacity: 1;
        pointer-events: auto;
    }

    .brand-ad-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .brand-ad-slide a {
        display: block;
        width: 100%;
        height: 100%;
    }

    /* Controls row BELOW the image — bottom-aligned: < ··●·· > */
    .brand-ad-controls {
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 14px;
        padding: 4px 0 0;
    }

    .brand-ad-arrow {
        position: static;
        transform: none;
        width: 28px;
        height: 28px;
        border: 0;
        border-radius: 50%;
        background: transparent;
        color: #111827;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s ease;
        padding: 0;
    }

    .brand-ad-arrow svg {
        width: 22px;
        height: 22px;
        display: block;
    }

    .brand-ad-dots {
        position: static;
        transform: none;
        display: flex;
        align-items: center;
        gap: 6px;
        height: 28px;
    }

    .brand-ad-dot {
        width: 10px;
        height: 10px;
        border: 0;
        border-radius: 50%;
        background: #d1d5db;
        cursor: pointer;
        transition: 0.2s ease;
        padding: 0;
        display: block;
        vertical-align: middle;
    }

    .brand-ad-dot.active {
        background: #ef3f4d;
        width: 24px;
        border-radius: 999px;
    }

    .home-brand-ad {
        margin-top: -25px;
        min-height: 170px;
        border-radius: 8px;
        overflow: hidden;
        background:
            linear-gradient(rgba(0, 0, 0, 0.18), rgba(0, 0, 0, 0.18)),
            url('images/brand-ad-buggy.jpg') center center / cover no-repeat;
        display: flex;
        align-items: center;
        padding: 24px;
        color: #ffffff;
    }

    .brand-ad-content p {
        margin: 0 0 8px;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #0066cc;
    }

    .brand-ad-content h3 {
        margin: 0 0 10px;
        font-size: 24px;
        line-height: 1.2;
        font-weight: 900;
    }

    .brand-ad-content span {
        display: block;
        font-size: 14px;
        line-height: 1.5;
        color: #f5f5f5;
    }

    .selected-brand-text {
        margin: 22px 0 0;
        color: #777777;
        font-size: 14px;
    }

    .selected-brand-text strong {
        color: #0d6efd;
    }

    @media screen and (max-width: 900px) {
        .home-brand-layout {
            grid-template-columns: 1fr;
            gap: 28px;
        }

        .home-brand-ad {
            min-height: 180px;
        }
    }

    @media screen and (max-width: 700px) {
        .home-brand-section {
            padding: 25px 16px 40px;
        }

        .home-brand-list {
            grid-template-columns: repeat(2, 1fr);
            column-gap: 20px;
        }

        .home-section-title h2 {
            font-size: 22px;
        }

        .brand-tab {
            min-width: 105px;
        }
    }

    @media screen and (max-width: 420px) {
        .home-brand-list {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /* Side ads slider */
    (function () {
        const slider = document.getElementById('brandsAdSlider');
        if (!slider) return;

        const slides = slider.querySelectorAll('.brand-ad-slide');
        const dots   = slider.querySelectorAll('.brand-ad-dot');
        const prev   = document.getElementById('brandsAdPrev');
        const next   = document.getElementById('brandsAdNext');

        if (slides.length <= 1) return;

        let current = 0;
        let timer = null;

        function showSlide(idx) {
            slides.forEach(s => s.classList.remove('active'));
            dots.forEach(d => d.classList.remove('active'));
            slides[idx].classList.add('active');
            if (dots[idx]) dots[idx].classList.add('active');
            current = idx;
        }

        function nextSlide() { showSlide((current + 1) % slides.length); }
        function prevSlide() { showSlide((current - 1 + slides.length) % slides.length); }

        function startAuto() {
            stopAuto();
            timer = setInterval(nextSlide, 5000);
        }
        function stopAuto() {
            if (timer) clearInterval(timer);
            timer = null;
        }

        if (prev) prev.addEventListener('click', function () { prevSlide(); startAuto(); });
        if (next) next.addEventListener('click', function () { nextSlide(); startAuto(); });

        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                const idx = parseInt(this.dataset.index, 10) || 0;
                showSlide(idx);
                startAuto();
            });
        });

        slider.addEventListener('mouseenter', stopAuto);
        slider.addEventListener('mouseleave', startAuto);

        startAuto();
    })();

    const popularBrands = <?php echo json_encode(array_values($popularBrands)); ?>;
    const allBrands = <?php echo json_encode(array_values($allBrands)); ?>;

    const brandList = document.getElementById('brandList');
    const selectedBrandText = document.getElementById('selectedBrandText');

    function renderBrandList(brands) {
        if (!brandList) {
            return;
        }

        if (!brands || brands.length === 0) {
            brandList.innerHTML = '<p class="empty-brand-message">No brands available yet.</p>';
            return;
        }

        brandList.innerHTML = brands.map(function (brand) {
            return '<button type="button" class="brand-name" data-brand="' + escapeHtml(brand) + '">' + escapeHtml(brand) + '</button>';
        }).join('');

        bindBrandClicks();
    }

    function bindBrandClicks() {
        document.querySelectorAll('.brand-name').forEach(function (brandButton) {
            brandButton.addEventListener('click', function () {
                document.querySelectorAll('.brand-name').forEach(function (item) {
                    item.classList.remove('active');
                });

                this.classList.add('active');

                const brand = this.getAttribute('data-brand');

                if (selectedBrandText) {
                    selectedBrandText.innerHTML = 'Searching brand: <strong>' + escapeHtml(brand) + '</strong>...';
                }

                window.location.href = 'allbuggy.php?brand=' + encodeURIComponent(brand);
            });
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    document.querySelectorAll('.brand-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.brand-tab').forEach(function (item) {
                item.classList.remove('active');
            });

            this.classList.add('active');

            const selectedTab = this.getAttribute('data-tab');

            if (selectedTab === 'all') {
                renderBrandList(allBrands);
            } else {
                renderBrandList(popularBrands);
            }

            if (selectedBrandText) {
                selectedBrandText.textContent = 'Select a brand to search available buggies.';
            }
        });
    });

    bindBrandClicks();
});
</script>