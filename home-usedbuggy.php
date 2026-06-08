<section class="home-used-section">
    <div class="home-used-container">
        <div class="home-used-top">
            <a href="usedbuggy.php" class="home-section-title home-section-title-link">
                <span></span>
                <h2>Explore Used Buggy</h2>
            </a>

            <a href="usedbuggy.php" class="home-view-all">View All</a>
        </div>

        <div class="home-used-tabs">
            <button type="button" class="used-tab active" data-filter="popular">Popular</button>
            <button type="button" class="used-tab" data-filter="latest">Latest</button>
            <button type="button" class="used-tab" data-filter="type">By Type</button>
        </div>

        <div class="home-used-slider-wrap">
            <button type="button" class="used-slider-arrow used-slider-prev" id="usedSliderPrev">
                &#10094;
            </button>

            <div class="home-used-slider" id="homeUsedSlider">
                <div id="homeUsedBuggyList" class="home-used-track">
                    <div class="home-used-loading">Loading used buggies...</div>
                </div>
            </div>

            <button type="button" class="used-slider-arrow used-slider-next" id="usedSliderNext">
                &#10095;
            </button>
        </div>
    </div>
</section>

<style>
    .home-used-section {
        background: #ffffff;
        padding: 35px 20px 55px;
    }

    .home-used-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .home-used-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 16px;
    }

    .home-section-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .home-section-title-link {
        text-decoration: none;
        color: inherit;
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

    .home-view-all {
        color: #006bff;
        font-size: 15px;
        text-decoration: none;
        font-weight: 600;
    }

    .home-view-all:hover {
        color: #0d6efd;
    }

    .home-used-tabs {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .used-tab {
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

    .used-tab:hover {
        background: #eef5ff;
        color: #0d6efd;
        transform: translateY(-2px);
    }

    .used-tab.active {
        background: #eef5ff;
        color: #0d6efd;
    }

    .home-used-slider-wrap {
        position: relative;
    }

    .home-used-slider {
        overflow: hidden;
        width: 100%;
    }

    .home-used-track {
        display: flex;
        gap: 18px;
        transition: transform 0.35s ease;
        will-change: transform;
    }

    .used-buggy-link {
        flex: 0 0 calc((100% - 72px) / 5);
        text-decoration: none;
        color: inherit;
    }

    .used-buggy-card {
        background: #ffffff;
        transition: 0.25s ease;
    }

    .used-buggy-card:hover {
        transform: translateY(-5px);
    }

    .used-buggy-image {
        position: relative;
        width: 100%;
        height: 150px;
        background: #f1f1f1;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 12px;
    }

    .used-buggy-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .used-buggy-tag {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #0d6efd;
        color: #ffffff;
        border-radius: 50px;
        padding: 5px 10px;
        font-size: 11px;
        font-weight: 800;
    }

    .used-buggy-price {
        margin: 0 0 4px;
        color: #0066cc;
        font-size: 16px;
        font-weight: 900;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .used-buggy-price.promo {
        color: #ef3f4d;
    }

    .used-buggy-original {
        color: #9ca3af;
        font-size: 13px;
        font-weight: 600;
        text-decoration: line-through;
    }

    .used-buggy-save {
        display: inline-block;
        background: #dcfce7;
        color: #16a34a;
        font-size: 11px;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 50px;
        margin-bottom: 8px;
    }

    .used-buggy-countdown {
        display: inline-block;
        background: #fff7ed;
        color: #c2410c;
        font-size: 11px;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 50px;
        margin-bottom: 8px;
        border: 1px solid #fed7aa;
    }

    .used-buggy-sale-badge {
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

    .used-buggy-sold-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #222222;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 50px;
        letter-spacing: 0.5px;
        z-index: 3;
    }

    .used-buggy-link.is-sold .used-buggy-image::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.28);
        z-index: 2;
    }

    .used-buggy-sold-note {
        color: #ef3f4d;
        font-size: 13px;
        font-weight: 700;
        margin-top: 6px;
    }

    .used-buggy-name {
        margin: 0;
        color: #222222;
        font-size: 16px;
        line-height: 1.35;
        font-weight: 500;
    }

    .used-buggy-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }

    .used-buggy-meta span {
        background: #f5f5f5;
        color: #555555;
        border-radius: 50px;
        padding: 5px 8px;
        font-size: 11px;
        font-weight: 700;
    }

    .used-slider-arrow {
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

    .used-slider-arrow:hover {
        background: #0d6efd;
        color: #ffffff;
    }

    .used-slider-prev {
        left: -24px;
    }

    .used-slider-next {
        right: -24px;
    }

    .used-slider-arrow.is-hidden {
        opacity: 0;
        pointer-events: none;
    }

    .home-used-loading,
    .home-used-empty {
        width: 100%;
        text-align: center;
        color: #777777;
        padding: 34px;
        background: #f8f8f8;
        border-radius: 12px;
        border: 1px solid #eeeeee;
    }

    @media screen and (max-width: 1100px) {
        .used-buggy-link {
            flex-basis: calc((100% - 54px) / 4);
        }
    }

    @media screen and (max-width: 900px) {
        .used-buggy-link {
            flex-basis: calc((100% - 36px) / 3);
        }
    }

    @media screen and (max-width: 700px) {
        .home-used-section {
            padding: 30px 16px 45px;
        }

        .home-used-top {
            align-items: flex-start;
        }

        .home-section-title h2 {
            font-size: 22px;
        }

        .used-buggy-link {
            flex-basis: calc((100% - 18px) / 2);
        }

        .used-buggy-image {
            height: 140px;
        }

        .used-tab {
            min-width: 105px;
        }

        .used-slider-prev {
            left: -10px;
        }

        .used-slider-next {
            right: -10px;
        }
    }

    @media screen and (max-width: 480px) {
        .used-buggy-link {
            flex-basis: 100%;
        }

        .used-buggy-image {
            height: 210px;
        }

        .used-slider-arrow {
            top: 82px;
        }
    }
</style>

<script>
    let usedCurrentIndex = 0;
    let usedTotalItems = 0;

    function usedBuggyEscapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function usedBuggyPromoTimeLeft(endDate) {
        const diff = Math.floor((new Date(endDate) - new Date()) / 1000);
        if (diff <= 0) return 'Expired';
        const days = Math.floor(diff / 86400);
        const hours = Math.floor((diff % 86400) / 3600);
        const mins = Math.floor((diff % 3600) / 60);
        if (days > 0) return days + 'd ' + hours + 'h left';
        if (hours > 0) return hours + 'h ' + mins + 'm left';
        return mins + 'm left';
    }

    function usedBuggyFormatPrice(value) {
        const amount = Number(value || 0);

        if (amount <= 0) {
            return 'Price on request';
        }

        return '$' + amount.toLocaleString('en-SG', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    function getUsedVisibleCount() {
        if (window.innerWidth <= 480) {
            return 1;
        }

        if (window.innerWidth <= 700) {
            return 2;
        }

        if (window.innerWidth <= 900) {
            return 3;
        }

        if (window.innerWidth <= 1100) {
            return 4;
        }

        return 5;
    }

    function updateUsedSlider() {
        const track = document.getElementById('homeUsedBuggyList');
        const prevBtn = document.getElementById('usedSliderPrev');
        const nextBtn = document.getElementById('usedSliderNext');
        const firstItem = track.querySelector('.used-buggy-link');

        if (!firstItem) {
            prevBtn.classList.add('is-hidden');
            nextBtn.classList.add('is-hidden');
            return;
        }

        const visibleCount = getUsedVisibleCount();
        const maxIndex = Math.max(0, usedTotalItems - visibleCount);

        if (usedCurrentIndex > maxIndex) {
            usedCurrentIndex = maxIndex;
        }

        const itemWidth = firstItem.offsetWidth;
        const gap = 18;
        const moveX = usedCurrentIndex * (itemWidth + gap);

        track.style.transform = 'translateX(-' + moveX + 'px)';

        prevBtn.classList.toggle('is-hidden', usedCurrentIndex <= 0);
        nextBtn.classList.toggle('is-hidden', usedCurrentIndex >= maxIndex || usedTotalItems <= visibleCount);
    }

    function loadHomeUsedBuggies() {
        const container = document.getElementById('homeUsedBuggyList');

        container.style.transform = 'translateX(0)';
        container.innerHTML = '<div class="home-used-loading">Loading used buggies...</div>';

        fetch('ajax.php?action=list&listing_type=sale&buggy_condition=used')
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result.success || !result.data || !result.data.items) {
                    container.innerHTML = '<div class="home-used-empty">Failed to load used buggies.</div>';
                    usedTotalItems = 0;
                    updateUsedSlider();
                    return;
                }

                const items = result.data.items;
                usedTotalItems = items.length;
                usedCurrentIndex = 0;

                if (items.length === 0) {
                    container.innerHTML = '<div class="home-used-empty">No used buggies available yet.</div>';
                    updateUsedSlider();
                    return;
                }

                container.innerHTML = items.map(function (item) {
                    const title = item.name ? item.name : ((item.brand || '') + ' ' + (item.model || '')).trim();
                    const imageUrl = item.image_url ? item.image_url : 'images/no-image.jpg';
                    const tag = item.tag ? item.tag : 'Used';
                    const seats = item.seats ? item.seats + ' Seater' : 'Buggy';
                    const isSold = item.status === 'sold';

                    const promoActive = !isSold
                        && item.promo_enabled == 1
                        && parseFloat(item.discount_price) < parseFloat(item.selling_price)
                        && item.promo_end_date
                        && new Date(item.promo_end_date) > new Date();

                    let priceHtml;
                    if (promoActive) {
                        const save = Math.round(parseFloat(item.selling_price) - parseFloat(item.discount_price));
                        const timeLeft = usedBuggyPromoTimeLeft(item.promo_end_date);
                        priceHtml = `
                            <p class="used-buggy-price promo">
                                <s class="used-buggy-original">${usedBuggyFormatPrice(item.selling_price)}</s>
                                ${usedBuggyFormatPrice(item.discount_price)}
                            </p>
                            <span class="used-buggy-save">Save $${save.toLocaleString('en-SG')}</span>
                            <span class="used-buggy-countdown">⏰ ${usedBuggyEscapeHtml(timeLeft)}</span>
                        `;
                    } else {
                        priceHtml = `<p class="used-buggy-price">${usedBuggyFormatPrice(item.selling_price)}</p>`;
                    }

                    const soldNote = isSold ? '<div class="used-buggy-sold-note">This buggy has been sold</div>' : '';
                    const imageBadge = isSold
                        ? '<span class="used-buggy-sold-badge">SOLD</span>'
                        : (promoActive ? '<span class="used-buggy-sale-badge">🔥 SALE</span>' : '');

                    return `
                        <a href="buggy-detail.php?id=${encodeURIComponent(item.id)}" class="used-buggy-link${isSold ? ' is-sold' : ''}">
                        <div class="used-buggy-card">
                            <div class="used-buggy-image">
                                <img src="${usedBuggyEscapeHtml(imageUrl)}" alt="${usedBuggyEscapeHtml(title)}">
                                <span class="used-buggy-tag">${usedBuggyEscapeHtml(tag)}</span>
                                ${imageBadge}
                            </div>

                            ${priceHtml}
                            ${soldNote}

                            <h3 class="used-buggy-name">
                                ${usedBuggyEscapeHtml(title)}
                            </h3>

                            <div class="used-buggy-meta">
                                <span>${usedBuggyEscapeHtml(item.brand || 'Brand')}</span>
                                <span>${usedBuggyEscapeHtml(seats)}</span>
                                <span>Used</span>
                            </div>
                        </div>
                        </a>
                    `;
                }).join('');

                updateUsedSlider();
            })
            .catch(function () {
                container.innerHTML = '<div class="home-used-empty">Failed to connect to product system.</div>';
                usedTotalItems = 0;
                updateUsedSlider();
            });
    }

    document.querySelectorAll('.used-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.used-tab').forEach(function (item) {
                item.classList.remove('active');
            });

            this.classList.add('active');
        });
    });

    document.getElementById('usedSliderPrev').addEventListener('click', function () {
        if (usedCurrentIndex > 0) {
            usedCurrentIndex--;
            updateUsedSlider();
        }
    });

    document.getElementById('usedSliderNext').addEventListener('click', function () {
        const visibleCount = getUsedVisibleCount();
        const maxIndex = Math.max(0, usedTotalItems - visibleCount);

        if (usedCurrentIndex < maxIndex) {
            usedCurrentIndex++;
            updateUsedSlider();
        }
    });

    window.addEventListener('resize', function () {
        updateUsedSlider();
    });

    loadHomeUsedBuggies();
</script>