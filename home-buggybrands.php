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

            <div class="home-brand-ad">
                <div class="brand-ad-content">
                    <p>SGBUGGYMART</p>
                    <h3>New & Used Buggy Available</h3>
                    <span>For sale, events, resorts & commercial use</span>
                </div>
            </div>
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