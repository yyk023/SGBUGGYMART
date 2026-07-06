<section class="home-auto-section">
    <div class="home-auto-container">
        <div class="home-auto-top">
            <a href="automotive.php" class="home-section-title home-section-title-link">
                <span></span>
                <h2>Explore Automotive</h2>
            </a>

            <a href="automotive.php" class="home-view-all">View All</a>
        </div>

        <div class="home-auto-tabs">
            <button type="button" class="auto-tab active" data-filter="popular">Popular</button>
            <button type="button" class="auto-tab" data-filter="latest">Latest</button>
            <button type="button" class="auto-tab" data-filter="type">By Type</button>
        </div>

        <div class="home-auto-slider-wrap">
            <button type="button" class="auto-slider-arrow auto-slider-prev" id="autoSliderPrev">
                &#10094;
            </button>

            <div class="home-auto-slider" id="homeAutoSlider">
                <div id="homeAutoList" class="home-auto-track">
                    <div class="home-auto-loading">Loading automotive...</div>
                </div>
            </div>

            <button type="button" class="auto-slider-arrow auto-slider-next" id="autoSliderNext">
                &#10095;
            </button>
        </div>
    </div>
</section>

<style>
    .home-auto-section {
        background: #f8f9ff;
        padding: 35px 20px 55px;
    }

    .home-auto-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .home-auto-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 16px;
    }

    .home-auto-tabs {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .auto-tab {
        min-width: 120px;
        height: 42px;
        border: 0;
        border-radius: 50px;
        background: #f6f6f6;
        color: #555555;
        font-size: 15px;
        font-weight: 800;
        cursor: pointer;
        transition: background 0.25s ease, color 0.25s ease, transform 0.25s ease;
    }

    .auto-tab:hover {
        background: #eef5ff;
        color: #0d6efd;
        transform: translateY(-2px);
    }

    .auto-tab.active {
        background: #eef5ff;
        color: #0d6efd;
    }

    .home-auto-slider-wrap {
        position: relative;
    }

    .home-auto-slider {
        overflow: hidden;
        width: 100%;
    }

    .home-auto-track {
        display: flex;
        gap: 18px;
        transition: transform 0.35s ease;
        will-change: transform;
    }

    .auto-item-link {
        flex: 0 0 calc((100% - 72px) / 5);
        text-decoration: none;
        color: inherit;
    }

    .auto-item-card {
        background: #ffffff;
        border-radius: 10px;
        border: 1px solid #e8e9f8;
        transition: 0.25s ease;
        overflow: hidden;
    }

    .auto-item-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 24px rgba(59, 65, 200, 0.1);
        border-color: #0066cc;
    }

    .auto-item-image {
        position: relative;
        width: 100%;
        height: 150px;
        background: #f1f1f1;
        overflow: hidden;
    }

    .auto-item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .auto-item-tag {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #0066cc;
        color: #ffffff;
        border-radius: 50px;
        padding: 5px 10px;
        font-size: 11px;
        font-weight: 800;
    }

    .auto-item-body {
        padding: 12px 14px 14px;
    }

    .auto-item-price {
        margin: 0 0 4px;
        color: #0066cc;
        font-size: 16px;
        font-weight: 900;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .auto-item-price.promo {
        color: #ef3f4d;
    }

    .auto-item-original {
        color: #9ca3af;
        font-size: 13px;
        font-weight: 600;
        text-decoration: line-through;
    }

    .auto-item-save {
        display: inline-block;
        background: #dcfce7;
        color: #16a34a;
        font-size: 11px;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 50px;
        margin-bottom: 6px;
    }

    .auto-item-countdown {
        display: inline-block;
        background: #fff7ed;
        color: #c2410c;
        font-size: 11px;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 50px;
        margin-bottom: 6px;
        border: 1px solid #fed7aa;
    }

    .auto-item-sale-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #ef3f4d;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        padding: 4px 9px;
        border-radius: 50px;
    }

    .auto-item-name {
        margin: 0;
        color: #222222;
        font-size: 15px;
        line-height: 1.35;
        font-weight: 600;
    }

    .auto-item-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }

    .auto-item-meta span {
        background: #f0f1ff;
        color: #0066cc;
        border-radius: 50px;
        padding: 5px 8px;
        font-size: 11px;
        font-weight: 700;
    }

    .auto-slider-arrow {
        position: absolute;
        top: 58px;
        width: 48px;
        height: 48px;
        border: 0;
        border-radius: 50%;
        background: #ffffff;
        color: #555555;
        font-size: 25px;
        line-height: 1;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.16);
        cursor: pointer;
        z-index: 5;
        transition: 0.25s ease;
    }

    .auto-slider-arrow:hover {
        background: #0066cc;
        color: #ffffff;
    }

    .auto-slider-prev {
        left: -24px;
    }

    .auto-slider-next {
        right: -24px;
    }

    .auto-slider-arrow.is-hidden {
        opacity: 0;
        pointer-events: none;
    }

    .home-auto-loading,
    .home-auto-empty {
        width: 100%;
        text-align: center;
        color: #777777;
        padding: 34px;
        background: #f8f8f8;
        border-radius: 12px;
        border: 1px solid #eeeeee;
    }

    @media screen and (max-width: 1100px) {
        .auto-item-link {
            flex-basis: calc((100% - 54px) / 4);
        }
    }

    @media screen and (max-width: 900px) {
        .auto-item-link {
            flex-basis: calc((100% - 36px) / 3);
        }
    }

    @media screen and (max-width: 700px) {
        .home-auto-section {
            padding: 30px 16px 45px;
        }

        .home-auto-top {
            align-items: flex-start;
        }

        .auto-item-link {
            flex-basis: calc((100% - 18px) / 2);
        }

        .auto-item-image {
            height: 140px;
        }

        .auto-tab {
            min-width: 105px;
        }

        .auto-slider-prev {
            left: -10px;
        }

        .auto-slider-next {
            right: -10px;
        }
    }

    @media screen and (max-width: 480px) {
        .auto-item-link {
            flex-basis: 100%;
        }

        .auto-item-image {
            height: 210px;
        }

        .auto-slider-arrow {
            top: 82px;
        }
    }
</style>

<script>
    let autoCurrentIndex = 0;
    let autoTotalItems = 0;

    function autoPromoTimeLeft(endDate) {
        const diff = Math.floor((new Date(endDate) - new Date()) / 1000);
        if (diff <= 0) return 'Expired';
        const days = Math.floor(diff / 86400);
        const hours = Math.floor((diff % 86400) / 3600);
        const mins = Math.floor((diff % 3600) / 60);
        if (days > 0) return days + 'd ' + hours + 'h left';
        if (hours > 0) return hours + 'h ' + mins + 'm left';
        return mins + 'm left';
    }

    function autoEscapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function autoFormatPrice(value) {
        const amount = Number(value || 0);

        if (amount <= 0) {
            return 'Price on request';
        }

        return '$' + amount.toLocaleString('en-SG', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    function getAutoVisibleCount() {
        if (window.innerWidth <= 480) return 1;
        if (window.innerWidth <= 700) return 2;
        if (window.innerWidth <= 900) return 3;
        if (window.innerWidth <= 1100) return 4;
        return 5;
    }

    function updateAutoSlider() {
        const track = document.getElementById('homeAutoList');
        const prevBtn = document.getElementById('autoSliderPrev');
        const nextBtn = document.getElementById('autoSliderNext');
        const firstItem = track.querySelector('.auto-item-link');

        if (!firstItem) {
            prevBtn.classList.add('is-hidden');
            nextBtn.classList.add('is-hidden');
            return;
        }

        const visibleCount = getAutoVisibleCount();
        const maxIndex = Math.max(0, autoTotalItems - visibleCount);

        if (autoCurrentIndex > maxIndex) {
            autoCurrentIndex = maxIndex;
        }

        const itemWidth = firstItem.offsetWidth;
        const gap = 18;
        const moveX = autoCurrentIndex * (itemWidth + gap);

        track.style.transform = 'translateX(-' + moveX + 'px)';

        prevBtn.classList.toggle('is-hidden', autoCurrentIndex <= 0);
        nextBtn.classList.toggle('is-hidden', autoCurrentIndex >= maxIndex || autoTotalItems <= visibleCount);
    }

    function loadHomeAutomotive() {
        const container = document.getElementById('homeAutoList');

        container.style.transform = 'translateX(0)';
        container.innerHTML = '<div class="home-auto-loading">Loading automotive...</div>';

        fetch('ajax.php?action=list&listing_type=automotive')
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result.success || !result.data || !result.data.items) {
                    container.innerHTML = '<div class="home-auto-empty">Failed to load automotive.</div>';
                    autoTotalItems = 0;
                    updateAutoSlider();
                    return;
                }

                const items = result.data.items;
                autoTotalItems = items.length;
                autoCurrentIndex = 0;

                if (items.length === 0) {
                    container.innerHTML = '<div class="home-auto-empty">No automotive available yet.</div>';
                    updateAutoSlider();
                    return;
                }

                container.innerHTML = items.map(function (item) {
                    const title = item.name ? item.name : ((item.brand || '') + ' ' + (item.model || '')).trim();
                    const imageUrl = item.image_url ? item.image_url : 'images/no-image.jpg';
                    const tag = item.tag ? item.tag : 'Automotive';

                    const promoActive = item.promo_enabled == 1
                        && parseFloat(item.discount_price) < parseFloat(item.selling_price)
                        && item.promo_end_date
                        && new Date(item.promo_end_date) > new Date();

                    let priceHtml;
                    if (promoActive) {
                        const save = Math.round(parseFloat(item.selling_price) - parseFloat(item.discount_price));
                        const timeLeft = autoPromoTimeLeft(item.promo_end_date);
                        priceHtml = `
                            <p class="auto-item-price promo">
                                <s class="auto-item-original">${autoFormatPrice(item.selling_price)}</s>
                                ${autoFormatPrice(item.discount_price)}
                            </p>
                            <span class="auto-item-save">Save $${save.toLocaleString('en-SG')}</span>
                            <span class="auto-item-countdown">&#9200; ${autoEscapeHtml(timeLeft)}</span>
                        `;
                    } else {
                        priceHtml = `<p class="auto-item-price">${autoFormatPrice(item.selling_price)}</p>`;
                    }

                    return `
                        <a href="automotive-detail.php?id=${encodeURIComponent(item.id)}" class="auto-item-link">
                            <div class="auto-item-card">
                                <div class="auto-item-image">
                                    <img src="${autoEscapeHtml(imageUrl)}" alt="${autoEscapeHtml(title)}">
                                    <span class="auto-item-tag">${autoEscapeHtml(tag)}</span>
                                    ${promoActive ? '<span class="auto-item-sale-badge">&#128293; SALE</span>' : ''}
                                </div>
                                <div class="auto-item-body">
                                    ${priceHtml}
                                    <h3 class="auto-item-name">${autoEscapeHtml(title)}</h3>
                                    <div class="auto-item-meta">
                                        <span>${autoEscapeHtml(item.brand || 'Automotive')}</span>
                                        <span>New</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    `;
                }).join('');

                updateAutoSlider();
            })
            .catch(function () {
                container.innerHTML = '<div class="home-auto-empty">Failed to connect to product system.</div>';
                autoTotalItems = 0;
                updateAutoSlider();
            });
    }

    document.querySelectorAll('.auto-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.auto-tab').forEach(function (item) {
                item.classList.remove('active');
            });
            this.classList.add('active');
        });
    });

    document.getElementById('autoSliderPrev').addEventListener('click', function () {
        if (autoCurrentIndex > 0) {
            autoCurrentIndex--;
            updateAutoSlider();
        }
    });

    document.getElementById('autoSliderNext').addEventListener('click', function () {
        const visibleCount = getAutoVisibleCount();
        const maxIndex = Math.max(0, autoTotalItems - visibleCount);

        if (autoCurrentIndex < maxIndex) {
            autoCurrentIndex++;
            updateAutoSlider();
        }
    });

    window.addEventListener('resize', function () {
        updateAutoSlider();
    });

    loadHomeAutomotive();
</script>
