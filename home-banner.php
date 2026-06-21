<?php
$homeBanners = [];
try {
    if (isset($pdo)) {
        $bannerStmt = $pdo->prepare("
            SELECT * FROM banners
            WHERE location = 'home' AND status = 'active'
            ORDER BY sort_order ASC, id ASC
        ");
        $bannerStmt->execute();
        $homeBanners = $bannerStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $homeBanners = [];
}
$defaultBannerImage = 'images/2151014858.jpg';
$firstBannerImage   = !empty($homeBanners) ? $homeBanners[0]['image_url'] : $defaultBannerImage;
?>

<?php
$filterBrands = [];
try {
    if (isset($pdo)) {
        $brandStmt = $pdo->query("
            SELECT brand_name
            FROM product_brands
            WHERE status = 'active'
            ORDER BY brand_name ASC
        ");
        $filterBrands = $brandStmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    $filterBrands = [];
}

if (count($filterBrands) === 0) {
    $filterBrands = ['Club Car', 'Yamaha', 'EZGO', 'HDK', 'Marshell', 'RoyPow'];
}

$currentYear      = (int)date('Y');
$startYear        = $currentYear + 1;
$endYear          = 2000;
$priceRangeOptions = [5000, 8000, 10000, 12000, 15000, 18000, 20000];
?>

<section class="buggy-mobile-banner" id="mobileBannerSection">
    <img src="<?php echo htmlspecialchars($firstBannerImage); ?>" alt="SGBUGGYMART Banner" id="mobileBannerImg">
</section>

<section class="buggy-home-search">
    <form class="buggy-main-search" id="bannerSearchForm">
        <input type="text" id="bannerKeyword" placeholder="What are you looking for?">
        <button type="submit" aria-label="Search">&#128269;</button>
    </form>

    <div class="buggy-category-grid">
        <a href="newbuggy.php" class="buggy-category-item">
            <span class="buggy-category-icon">✨</span>
            <span>New Buggy</span>
        </a>
        <a href="usedbuggy.php" class="buggy-category-item">
            <span class="buggy-category-icon">💰</span>
            <span>Used Buggy</span>
        </a>
        <a href="allbuggy.php" class="buggy-category-item">
            <span class="buggy-category-icon">🛒</span>
            <span>All Buggy</span>
        </a>
        <a href="sell-buggy.php" class="buggy-category-item">
            <span class="buggy-category-icon">🏷️</span>
            <span>Sell Buggy</span>
        </a>
    </div>
</section>

<section class="buggy-hero">
    <div class="buggy-hero-content">
        <p class="buggy-hero-label">Welcome to SGBUGGYMART</p>
        <h1>Your Trusted Marketplace for Buggies in Singapore</h1>
        <p class="buggy-hero-text">
            Search new and used buggies for events, resorts, factories,
            golf courses, and personal use.
        </p>
    </div>

    <div class="buggy-search-card">
        <div class="buggy-search-tabs">
            <button type="button" class="buggy-tab active" data-link="newbuggy.php">New</button>
            <button type="button" class="buggy-tab" data-link="usedbuggy.php">Used</button>
            <button type="button" class="buggy-tab" data-link="allbuggy.php">All</button>
        </div>

        <div class="buggy-search-form">
            <div class="buggy-search-field buggy-keyword-field">
                <input type="text" id="desktopBannerKeyword" placeholder="Buggy Brand / Model">
            </div>

            <div class="buggy-search-field buggy-price-range-field">
                <div class="buggy-price-range-box">
                    <select id="bannerMinPrice">
                        <option value="">Min Price</option>
                        <?php foreach ($priceRangeOptions as $price): ?>
                            <option value="<?php echo (int)$price; ?>">$<?php echo number_format((int)$price); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="buggy-price-divider"></span>
                    <select id="bannerMaxPrice">
                        <option value="">Max Price</option>
                        <?php foreach ($priceRangeOptions as $price): ?>
                            <option value="<?php echo (int)$price; ?>">$<?php echo number_format((int)$price); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="buggy-search-field buggy-seat-field">
                <select id="bannerSeats">
                    <option value="">All Seats</option>
                    <option value="2">2 Seater</option>
                    <option value="3">3 Seater</option>
                    <option value="4">4 Seater</option>
                    <option value="6">6 Seater</option>
                    <option value="8">8 Seater</option>
                </select>
            </div>

            <div class="buggy-search-field buggy-brand-field">
                <select id="bannerBrand">
                    <option value="">All Brands</option>
                    <?php foreach ($filterBrands as $brand): ?>
                        <option value="<?php echo htmlspecialchars((string)$brand, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars((string)$brand, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="buggy-search-field buggy-year-field">
                <select id="bannerYear">
                    <option value="">All Years</option>
                    <?php for ($year = $startYear; $year >= $endYear; $year--): ?>
                        <option value="<?php echo (int)$year; ?>"><?php echo (int)$year; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <button type="button" id="bannerSearchBtn" class="buggy-search-btn buggy-button-field">Search</button>
        </div>
    </div>
</section>

<style>
    .buggy-mobile-banner,
    .buggy-home-search { display: none; }

    .buggy-hero {
        position: relative;
        min-height: 520px;
        background:
            linear-gradient(rgba(0, 0, 0, 0.42), rgba(0, 0, 0, 0.42)),
            url('<?php echo htmlspecialchars($firstBannerImage); ?>') center center / cover no-repeat;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 90px 20px 145px;
        text-align: center;
        color: #ffffff;
        margin-bottom: 115px;
        transition: background-image 0.8s ease;
    }

    .buggy-hero-content { max-width: 1100px; margin: 0 auto; }

    .buggy-hero-label {
        display: inline-block;
        margin: 0 0 18px;
        padding: 8px 22px;
        background: linear-gradient(135deg, #ef3f4d, #f97316);
        color: #ffffff;
        font-size: 15px;
        font-weight: 800;
        letter-spacing: 2px;
        text-transform: uppercase;
        border-radius: 999px;
        box-shadow: 0 6px 20px rgba(239, 63, 77, 0.45);
        animation: heroLabelPulse 2.4s ease-in-out infinite;
    }

    @keyframes heroLabelPulse {
        0%, 100% { transform: scale(1); box-shadow: 0 6px 20px rgba(239, 63, 77, 0.45); }
        50%      { transform: scale(1.05); box-shadow: 0 8px 28px rgba(239, 63, 77, 0.65); }
    }

    .buggy-hero h1 { margin: 0 0 18px; font-size: clamp(30px, 3.8vw, 56px); line-height: 1.1; font-weight: 900; }

    .buggy-hero-text { max-width: 720px; margin: 0 auto; font-size: 18px; line-height: 1.7; color: #f5f5f5; }

    .buggy-search-card {
        position: absolute; left: 50%; bottom: -92px; transform: translateX(-50%);
        width: calc(100% - 80px); max-width: 820px; background: #ffffff;
        border-radius: 22px; padding: 50px 36px 34px;
        box-shadow: 0 14px 40px rgba(0, 0, 0, 0.16); z-index: 5;
    }

    .buggy-search-tabs {
        position: absolute; left: 50%; top: -31px; transform: translateX(-50%);
        display: flex; align-items: center; gap: 8px; background: #2d2d2d;
        padding: 10px; border-radius: 50px; box-shadow: 0 8px 22px rgba(0, 0, 0, 0.18);
    }

    .buggy-tab {
        border: 0; background: transparent; color: #ffffff;
        min-width: 110px; height: 44px; border-radius: 50px;
        font-size: 15px; font-weight: 800; cursor: pointer; transition: 0.25s ease;
    }

    .buggy-tab.active[data-link="newbuggy.php"] {
        background: #ffffff;
        color: #22c55e;
    }

    .buggy-tab.active[data-link="usedbuggy.php"] {
        background: #ffffff;
        color: #f97316;
    }

    .buggy-tab.active[data-link="allbuggy.php"] {
        background: #ffffff;
        color: #0066cc;
    }

    .buggy-search-form { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; align-items: center; }
    .buggy-keyword-field     { grid-column: span 2; }
    .buggy-price-range-field { grid-column: span 2; }
    .buggy-seat-field, .buggy-brand-field, .buggy-year-field, .buggy-button-field { grid-column: span 1; }

    .buggy-search-field input, .buggy-search-field select {
        width: 100%; height: 46px; border: 1px solid #d7d7d7;
        border-radius: 50px; padding: 0 16px; font-size: 14px;
        color: #333333; background: #ffffff; box-sizing: border-box;
    }

    .buggy-search-field input::placeholder { color: #888888; }
    .buggy-search-field input:focus, .buggy-search-field select:focus { outline: none; border-color: #0066cc; }

    .buggy-price-range-box {
        width: 100%; height: 46px; border: 1px solid #cfd4da;
        border-radius: 999px; background: #ffffff;
        display: grid; grid-template-columns: 1fr 1px 1fr;
        align-items: center; overflow: hidden; box-sizing: border-box;
    }

    .buggy-price-range-box select {
        width: 100%; height: 44px; border: 0; border-radius: 0;
        padding: 0 14px; font-size: 14px; color: #333333;
        background: transparent; box-sizing: border-box; cursor: pointer;
    }

    .buggy-price-range-box select:focus { outline: none; border: 0; }
    .buggy-price-divider { width: 1px; height: 26px; background: #cfd4da; display: block; }
    .buggy-price-range-box option:disabled, .price-option-disabled { color: #bbbbbb; background: #f5f5f5; }

    .buggy-search-btn {
        width: 100%; height: 46px; border: 0; border-radius: 50px;
        background: #0066cc; color: #ffffff; font-size: 14px;
        font-weight: 900; cursor: pointer; transition: 0.25s ease;
    }

    .buggy-search-btn:hover { background: #005bb8; transform: translateY(-2px); }

    @media screen and (max-width: 992px) {
        .buggy-hero { padding-bottom: 210px; margin-bottom: 180px; }
        .buggy-search-card { bottom: -155px; width: calc(100% - 40px); max-width: 760px; }
        .buggy-search-form { grid-template-columns: 1fr 1fr; }
        .buggy-keyword-field, .buggy-price-range-field, .buggy-seat-field,
        .buggy-brand-field, .buggy-year-field, .buggy-button-field { grid-column: 1 / -1; }
        .buggy-search-btn { width: 100%; }
    }

    @media screen and (max-width: 768px) {
        .buggy-hero { display: none; }
        .buggy-mobile-banner { display: block; width: 100%; background: #f4f4f4; }
        .buggy-mobile-banner img { width: 100%; height: 205px; display: block; object-fit: cover; object-position: center; }
        .buggy-home-search { display: block; background: #ffffff; padding: 36px 18px 22px; }
        .buggy-main-search { width: 100%; height: 56px; border: 1px solid #cfd4da; border-radius: 999px; display: flex; align-items: center; background: #ffffff; overflow: hidden; }
        .buggy-main-search input { width: 100%; height: 100%; border: 0; outline: 0; padding: 0 16px; color: #333333; font-size: 17px; background: transparent; }
        .buggy-main-search input::placeholder { color: #b4bac1; }
        .buggy-main-search button { width: 56px; height: 100%; border: 0; background: transparent; color: #555555; font-size: 23px; cursor: pointer; }
        .buggy-category-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px 10px; margin-top: 30px; }
        .buggy-category-item { display: flex; flex-direction: column; align-items: center; justify-content: flex-start; gap: 8px; color: #222222; text-align: center; font-size: 15px; font-weight: 700; line-height: 1.2; text-decoration: none; }
        .buggy-category-icon { width: 62px; height: 62px; border-radius: 50%; background: #eef6ff; display: flex; align-items: center; justify-content: center; font-size: 30px;
        }

        .buggy-category-item:nth-child(1) .buggy-category-icon { background: #dcfce7; }
        .buggy-category-item:nth-child(2) .buggy-category-icon { background: #ffedd5; }
        .buggy-category-item:nth-child(3) .buggy-category-icon { background: #dbeafe; }
        .buggy-category-item:nth-child(4) .buggy-category-icon { background: #f3f4f6; }
    }

    @media screen and (max-width: 480px) {
        .buggy-mobile-banner img { height: 204px; }
        .buggy-home-search { padding-top: 36px; }
        .buggy-category-grid { gap: 20px 8px; }
        .buggy-category-item { font-size: 14px; }
        .buggy-category-icon { width: 58px; height: 58px; font-size: 28px; }
    }

    @media screen and (max-width: 375px) {
        .buggy-home-search { padding-left: 14px; padding-right: 14px; }
        .buggy-category-item { font-size: 13px; }
        .buggy-category-icon { width: 52px; height: 52px; font-size: 25px; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let selectedBannerLink = 'newbuggy.php';

    const tabs                 = document.querySelectorAll('.buggy-tab');
    const bannerSearchBtn      = document.getElementById('bannerSearchBtn');
    const desktopBannerKeyword = document.getElementById('desktopBannerKeyword');
    const bannerKeyword        = document.getElementById('bannerKeyword');
    const bannerMinPrice       = document.getElementById('bannerMinPrice');
    const bannerMaxPrice       = document.getElementById('bannerMaxPrice');
    const bannerSeats          = document.getElementById('bannerSeats');
    const bannerBrand          = document.getElementById('bannerBrand');
    const bannerYear           = document.getElementById('bannerYear');
    const bannerSearchForm     = document.getElementById('bannerSearchForm');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (item) { item.classList.remove('active'); });
            this.classList.add('active');
            selectedBannerLink = this.getAttribute('data-link') || 'newbuggy.php';
        });
    });

    function updateMaxPriceOptions() {
        if (!bannerMinPrice || !bannerMaxPrice) return;
        const selectedMinPrice = parseInt(bannerMinPrice.value, 10) || 0;
        const selectedMaxPrice = parseInt(bannerMaxPrice.value, 10) || 0;
        Array.from(bannerMaxPrice.options).forEach(function (option) {
            const optionValue = parseInt(option.value, 10) || 0;
            if (option.value !== '' && selectedMinPrice > 0 && optionValue <= selectedMinPrice) {
                option.disabled = true; option.classList.add('price-option-disabled');
            } else {
                option.disabled = false; option.classList.remove('price-option-disabled');
            }
        });
        if (selectedMaxPrice > 0 && selectedMaxPrice <= selectedMinPrice) bannerMaxPrice.value = '';
    }

    if (bannerMinPrice) bannerMinPrice.addEventListener('change', updateMaxPriceOptions);
    updateMaxPriceOptions();

    function goToSearch(keyword, minPrice, maxPrice, seats, brand, year, link) {
        const params = new URLSearchParams();
        if (keyword)  params.append('keyword',   keyword);
        if (minPrice) params.append('min_price', minPrice);
        if (maxPrice) params.append('max_price', maxPrice);
        if (seats)    params.append('seats',     seats);
        if (brand)    params.append('brand',     brand);
        if (year)     params.append('year',      year);
        const qs = params.toString();
        window.location.href = qs ? link + '?' + qs : link;
    }

    if (bannerSearchBtn) {
        bannerSearchBtn.addEventListener('click', function () {
            goToSearch(
                desktopBannerKeyword ? desktopBannerKeyword.value.trim() : '',
                bannerMinPrice ? bannerMinPrice.value : '',
                bannerMaxPrice ? bannerMaxPrice.value : '',
                bannerSeats    ? bannerSeats.value    : '',
                bannerBrand    ? bannerBrand.value    : '',
                bannerYear     ? bannerYear.value     : '',
                selectedBannerLink
            );
        });
    }

    if (desktopBannerKeyword) {
        desktopBannerKeyword.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { event.preventDefault(); if (bannerSearchBtn) bannerSearchBtn.click(); }
        });
    }

    if (bannerSearchForm) {
        bannerSearchForm.addEventListener('submit', function (event) {
            event.preventDefault();
            goToSearch(bannerKeyword ? bannerKeyword.value.trim() : '', '', '', '', '', '', 'allbuggy.php');
        });
    }

    // Banner auto-slide
    const bannerImages = <?php echo json_encode(array_map(function($b) { return $b['image_url']; }, $homeBanners)); ?>;

    if (bannerImages.length > 1) {
        let bannerIndex = 0;
        const hero      = document.querySelector('.buggy-hero');
        const mobileImg = document.getElementById('mobileBannerImg');

        setInterval(function () {
            bannerIndex = (bannerIndex + 1) % bannerImages.length;
            const img   = bannerImages[bannerIndex];
            if (hero)      hero.style.backgroundImage = 'linear-gradient(rgba(0,0,0,0.42),rgba(0,0,0,0.42)), url("' + img + '")';
            if (mobileImg) mobileImg.src = img;
        }, 4000);
    }
});
</script>