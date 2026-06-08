<section class="home-acc-section">
    <div class="home-acc-container">
        <div class="home-acc-top">
            <a href="accessories.php" class="home-section-title home-section-title-link">
                <span></span>
                <h2>Explore Accessories</h2>
            </a>

            <a href="accessories.php" class="home-view-all">View All</a>
        </div>

        <div class="home-acc-tabs">
            <button type="button" class="acc-tab active" data-filter="popular">Popular</button>
            <button type="button" class="acc-tab" data-filter="latest">Latest</button>
            <button type="button" class="acc-tab" data-filter="type">By Type</button>
        </div>

        <div class="home-acc-slider-wrap">
            <button type="button" class="acc-slider-arrow acc-slider-prev" id="accSliderPrev">
                &#10094;
            </button>

            <div class="home-acc-slider" id="homeAccSlider">
                <div id="homeAccList" class="home-acc-track">
                    <div class="home-acc-loading">Loading accessories...</div>
                </div>
            </div>

            <button type="button" class="acc-slider-arrow acc-slider-next" id="accSliderNext">
                &#10095;
            </button>
        </div>
    </div>
</section>

<style>
    .home-acc-section {
        background: #f8f9ff;
        padding: 35px 20px 55px;
    }

    .home-acc-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .home-acc-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 16px;
    }

    .home-acc-tabs {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .acc-tab {
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

    .acc-tab:hover {
        background: #eef5ff;
        color: #0d6efd;
        transform: translateY(-2px);
    }

    .acc-tab.active {
        background: #eef5ff;
        color: #0d6efd;
    }

    .home-acc-slider-wrap {
        position: relative;
    }

    .home-acc-slider {
        overflow: hidden;
        width: 100%;
    }

    .home-acc-track {
        display: flex;
        gap: 18px;
        transition: transform 0.35s ease;
        will-change: transform;
    }

    .acc-item-link {
        flex: 0 0 calc((100% - 72px) / 5);
        text-decoration: none;
        color: inherit;
    }

    .acc-item-card {
        background: #ffffff;
        border-radius: 10px;
        border: 1px solid #e8e9f8;
        transition: 0.25s ease;
        overflow: hidden;
    }

    .acc-item-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 24px rgba(59, 65, 200, 0.1);
        border-color: #0066cc;
    }

    .acc-item-image {
        position: relative;
        width: 100%;
        height: 150px;
        background: #f1f1f1;
        overflow: hidden;
    }

    .acc-item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .acc-item-tag {
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

    .acc-item-body {
        padding: 12px 14px 14px;
    }

    .acc-item-price {
        margin: 0 0 4px;
        color: #0066cc;
        font-size: 16px;
        font-weight: 900;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .acc-item-price.promo {
        color: #ef3f4d;
    }

    .acc-item-original {
        color: #9ca3af;
        font-size: 13px;
        font-weight: 600;
        text-decoration: line-through;
    }

    .acc-item-save {
        display: inline-block;
        background: #dcfce7;
        color: #16a34a;
        font-size: 11px;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 50px;
        margin-bottom: 6px;
    }

    .acc-item-countdown {
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

    .acc-item-sale-badge {
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

    .acc-item-name {
        margin: 0;
        color: #222222;
        font-size: 15px;
        line-height: 1.35;
        font-weight: 600;
    }

    .acc-item-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }

    .acc-item-meta span {
        background: #f0f1ff;
        color: #0066cc;
        border-radius: 50px;
        padding: 5px 8px;
        font-size: 11px;
        font-weight: 700;
    }

    .acc-slider-arrow {
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

    .acc-slider-arrow:hover {
        background: #0066cc;
        color: #ffffff;
    }

    .acc-slider-prev {
        left: -24px;
    }

    .acc-slider-next {
        right: -24px;
    }

    .acc-slider-arrow.is-hidden {
        opacity: 0;
        pointer-events: none;
    }

    .home-acc-loading,
    .home-acc-empty {
        width: 100%;
        text-align: center;
        color: #777777;
        padding: 34px;
        background: #f8f8f8;
        border-radius: 12px;
        border: 1px solid #eeeeee;
    }

    @media screen and (max-width: 1100px) {
        .acc-item-link {
            flex-basis: calc((100% - 54px) / 4);
        }
    }

    @media screen and (max-width: 900px) {
        .acc-item-link {
            flex-basis: calc((100% - 36px) / 3);
        }
    }

    @media screen and (max-width: 700px) {
        .home-acc-section {
            padding: 30px 16px 45px;
        }

        .home-acc-top {
            align-items: flex-start;
        }

        .acc-item-link {
            flex-basis: calc((100% - 18px) / 2);
        }

        .acc-item-image {
            height: 140px;
        }

        .acc-tab {
            min-width: 105px;
        }

        .acc-slider-prev {
            left: -10px;
        }

        .acc-slider-next {
            right: -10px;
        }
    }

    @media screen and (max-width: 480px) {
        .acc-item-link {
            flex-basis: 100%;
        }

        .acc-item-image {
            height: 210px;
        }

        .acc-slider-arrow {
            top: 82px;
        }
    }
</style>

<script>
    let accCurrentIndex = 0;
    let accTotalItems = 0;

    function accPromoTimeLeft(endDate) {
        const diff = Math.floor((new Date(endDate) - new Date()) / 1000);
        if (diff <= 0) return 'Expired';
        const days = Math.floor(diff / 86400);
        const hours = Math.floor((diff % 86400) / 3600);
        const mins = Math.floor((diff % 3600) / 60);
        if (days > 0) return days + 'd ' + hours + 'h left';
        if (hours > 0) return hours + 'h ' + mins + 'm left';
        return mins + 'm left';
    }

    function accEscapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function accFormatPrice(value) {
        const amount = Number(value || 0);

        if (amount <= 0) {
            return 'Price on request';
        }

        return '$' + amount.toLocaleString('en-SG', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    function getAccVisibleCount() {
        if (window.innerWidth <= 480) return 1;
        if (window.innerWidth <= 700) return 2;
        if (window.innerWidth <= 900) return 3;
        if (window.innerWidth <= 1100) return 4;
        return 5;
    }

    function updateAccSlider() {
        const track = document.getElementById('homeAccList');
        const prevBtn = document.getElementById('accSliderPrev');
        const nextBtn = document.getElementById('accSliderNext');
        const firstItem = track.querySelector('.acc-item-link');

        if (!firstItem) {
            prevBtn.classList.add('is-hidden');
            nextBtn.classList.add('is-hidden');
            return;
        }

        const visibleCount = getAccVisibleCount();
        const maxIndex = Math.max(0, accTotalItems - visibleCount);

        if (accCurrentIndex > maxIndex) {
            accCurrentIndex = maxIndex;
        }

        const itemWidth = firstItem.offsetWidth;
        const gap = 18;
        const moveX = accCurrentIndex * (itemWidth + gap);

        track.style.transform = 'translateX(-' + moveX + 'px)';

        prevBtn.classList.toggle('is-hidden', accCurrentIndex <= 0);
        nextBtn.classList.toggle('is-hidden', accCurrentIndex >= maxIndex || accTotalItems <= visibleCount);
    }

    function loadHomeAccessories() {
        const container = document.getElementById('homeAccList');

        container.style.transform = 'translateX(0)';
        container.innerHTML = '<div class="home-acc-loading">Loading accessories...</div>';

        fetch('ajax.php?action=list&listing_type=accessory')
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result.success || !result.data || !result.data.items) {
                    container.innerHTML = '<div class="home-acc-empty">Failed to load accessories.</div>';
                    accTotalItems = 0;
                    updateAccSlider();
                    return;
                }

                const items = result.data.items;
                accTotalItems = items.length;
                accCurrentIndex = 0;

                if (items.length === 0) {
                    container.innerHTML = '<div class="home-acc-empty">No accessories available yet.</div>';
                    updateAccSlider();
                    return;
                }

                container.innerHTML = items.map(function (item) {
                    const title = item.name ? item.name : ((item.brand || '') + ' ' + (item.model || '')).trim();
                    const imageUrl = item.image_url ? item.image_url : 'images/no-image.jpg';
                    const tag = item.tag ? item.tag : 'Accessory';

                    const promoActive = item.promo_enabled == 1
                        && parseFloat(item.discount_price) < parseFloat(item.selling_price)
                        && item.promo_end_date
                        && new Date(item.promo_end_date) > new Date();

                    let priceHtml;
                    if (promoActive) {
                        const save = Math.round(parseFloat(item.selling_price) - parseFloat(item.discount_price));
                        const timeLeft = accPromoTimeLeft(item.promo_end_date);
                        priceHtml = `
                            <p class="acc-item-price promo">
                                <s class="acc-item-original">${accFormatPrice(item.selling_price)}</s>
                                ${accFormatPrice(item.discount_price)}
                            </p>
                            <span class="acc-item-save">Save $${save.toLocaleString('en-SG')}</span>
                            <span class="acc-item-countdown">⏰ ${accEscapeHtml(timeLeft)}</span>
                        `;
                    } else {
                        priceHtml = `<p class="acc-item-price">${accFormatPrice(item.selling_price)}</p>`;
                    }

                    return `
                        <a href="accessory-detail.php?id=${encodeURIComponent(item.id)}" class="acc-item-link">
                            <div class="acc-item-card">
                                <div class="acc-item-image">
                                    <img src="${accEscapeHtml(imageUrl)}" alt="${accEscapeHtml(title)}">
                                    <span class="acc-item-tag">${accEscapeHtml(tag)}</span>
                                    ${promoActive ? '<span class="acc-item-sale-badge">🔥 SALE</span>' : ''}
                                </div>
                                <div class="acc-item-body">
                                    ${priceHtml}
                                    <h3 class="acc-item-name">${accEscapeHtml(title)}</h3>
                                    <div class="acc-item-meta">
                                        <span>${accEscapeHtml(item.brand || 'Accessory')}</span>
                                        <span>New</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    `;
                }).join('');

                updateAccSlider();
            })
            .catch(function () {
                container.innerHTML = '<div class="home-acc-empty">Failed to connect to product system.</div>';
                accTotalItems = 0;
                updateAccSlider();
            });
    }

    document.querySelectorAll('.acc-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.acc-tab').forEach(function (item) {
                item.classList.remove('active');
            });
            this.classList.add('active');
        });
    });

    document.getElementById('accSliderPrev').addEventListener('click', function () {
        if (accCurrentIndex > 0) {
            accCurrentIndex--;
            updateAccSlider();
        }
    });

    document.getElementById('accSliderNext').addEventListener('click', function () {
        const visibleCount = getAccVisibleCount();
        const maxIndex = Math.max(0, accTotalItems - visibleCount);

        if (accCurrentIndex < maxIndex) {
            accCurrentIndex++;
            updateAccSlider();
        }
    });

    window.addEventListener('resize', function () {
        updateAccSlider();
    });

    loadHomeAccessories();
</script>