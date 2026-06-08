<section class="home-new-section">
    <div class="home-new-container">
        <div class="home-new-top">
            <a href="newbuggy.php" class="home-section-title home-section-title-link">
                <span></span>
                <h2>New Launches</h2>
            </a>

            <a href="newbuggy.php" class="home-view-all">View All</a>
        </div>

        <div class="home-new-tabs">
            <button type="button" class="new-tab active" data-filter="popular">Popular</button>
            <button type="button" class="new-tab" data-filter="latest">Latest</button>
            <button type="button" class="new-tab" data-filter="type">By Type</button>
        </div>

        <div class="home-new-slider-wrap">
            <button type="button" class="new-slider-arrow new-slider-prev" id="newSliderPrev">
                &#10094;
            </button>

            <div class="home-new-slider" id="homeNewSlider">
                <div id="homeNewBuggyList" class="home-new-track">
                    <div class="home-new-loading">Loading new buggies...</div>
                </div>
            </div>

            <button type="button" class="new-slider-arrow new-slider-next" id="newSliderNext">
                &#10095;
            </button>
        </div>
    </div>
</section>

<style>
    .home-new-section {
        background: #ffffff;
        padding: 35px 20px 65px;
    }

    .home-new-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .home-new-top {
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
        background: #0066cc;
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
        color: #0066cc;
    }

    .home-new-tabs {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .new-tab {
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

    .new-tab:hover {
        background: #eef5ff;
        color: #0d6efd;
        transform: translateY(-2px);
    }

    .new-tab.active {
        background: #eef5ff;
        color: #0d6efd;
    }

    .home-new-slider-wrap {
        position: relative;
    }

    .home-new-slider {
        overflow: hidden;
        width: 100%;
    }

    .home-new-track {
        display: flex;
        gap: 18px;
        transition: transform 0.35s ease;
        will-change: transform;
    }

    .new-buggy-link {
        flex: 0 0 calc((100% - 72px) / 5);
        text-decoration: none;
        color: inherit;
    }

    .new-buggy-card {
        background: #ffffff;
        transition: 0.25s ease;
    }

    .new-buggy-card:hover {
        transform: translateY(-5px);
    }

    .new-buggy-image {
        position: relative;
        width: 100%;
        height: 150px;
        background: #f1f1f1;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 12px;
    }

    .new-buggy-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .new-buggy-tag {
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

    .new-buggy-price {
        margin: 0 0 4px;
        color: #0066cc;
        font-size: 16px;
        font-weight: 900;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .new-buggy-price.promo {
        color: #ef3f4d;
    }

    .new-buggy-original {
        color: #9ca3af;
        font-size: 13px;
        font-weight: 600;
        text-decoration: line-through;
    }

    .new-buggy-save {
        display: inline-block;
        background: #dcfce7;
        color: #16a34a;
        font-size: 11px;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 50px;
        margin-bottom: 8px;
    }

    .new-buggy-countdown {
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

    .new-buggy-sale-badge {
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

    .new-buggy-sold-badge {
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

    .new-buggy-link.is-sold .new-buggy-image::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.28);
        z-index: 2;
    }

    .new-buggy-sold-note {
        color: #ef3f4d;
        font-size: 13px;
        font-weight: 700;
        margin-top: 6px;
    }

    .new-buggy-name {
        margin: 0;
        color: #222222;
        font-size: 16px;
        line-height: 1.35;
        font-weight: 500;
    }

    .new-buggy-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }

    .new-buggy-meta span {
        background: #f5f5f5;
        color: #555555;
        border-radius: 50px;
        padding: 5px 8px;
        font-size: 11px;
        font-weight: 700;
    }

    .new-slider-arrow {
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

    .new-slider-arrow:hover {
        background: #0066cc;
        color: #ffffff;
    }

    .new-slider-prev {
        left: -24px;
    }

    .new-slider-next {
        right: -24px;
    }

    .new-slider-arrow.is-hidden {
        opacity: 0;
        pointer-events: none;
    }

    .home-new-loading,
    .home-new-empty {
        width: 100%;
        text-align: center;
        color: #777777;
        padding: 34px;
        background: #f8f8f8;
        border-radius: 12px;
        border: 1px solid #eeeeee;
    }

    @media screen and (max-width: 1100px) {
        .new-buggy-link {
            flex-basis: calc((100% - 54px) / 4);
        }
    }

    @media screen and (max-width: 900px) {
        .new-buggy-link {
            flex-basis: calc((100% - 36px) / 3);
        }
    }

    @media screen and (max-width: 700px) {
        .home-new-section {
            padding: 30px 16px 50px;
        }

        .home-new-top {
            align-items: flex-start;
        }

        .home-section-title h2 {
            font-size: 22px;
        }

        .new-buggy-link {
            flex-basis: calc((100% - 18px) / 2);
        }

        .new-buggy-image {
            height: 140px;
        }

        .new-tab {
            min-width: 105px;
        }

        .new-slider-prev {
            left: -10px;
        }

        .new-slider-next {
            right: -10px;
        }
    }

    @media screen and (max-width: 480px) {
        .new-buggy-link {
            flex-basis: 100%;
        }

        .new-buggy-image {
            height: 210px;
        }

        .new-slider-arrow {
            top: 82px;
        }
    }
</style>

<script>
    let newCurrentIndex = 0;
    let newTotalItems = 0;

    function newBuggyPromoTimeLeft(endDate) {
        const diff = Math.floor((new Date(endDate) - new Date()) / 1000);
        if (diff <= 0) return 'Expired';
        const days = Math.floor(diff / 86400);
        const hours = Math.floor((diff % 86400) / 3600);
        const mins = Math.floor((diff % 3600) / 60);
        if (days > 0) return days + 'd ' + hours + 'h left';
        if (hours > 0) return hours + 'h ' + mins + 'm left';
        return mins + 'm left';
    }

    function newBuggyEscapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function newBuggyFormatPrice(value) {
        const amount = Number(value || 0);

        if (amount <= 0) {
            return 'Price on request';
        }

        return '$' + amount.toLocaleString('en-SG', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    function getNewVisibleCount() {
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

    function updateNewSlider() {
        const track = document.getElementById('homeNewBuggyList');
        const prevBtn = document.getElementById('newSliderPrev');
        const nextBtn = document.getElementById('newSliderNext');
        const firstItem = track.querySelector('.new-buggy-link');

        if (!firstItem) {
            prevBtn.classList.add('is-hidden');
            nextBtn.classList.add('is-hidden');
            return;
        }

        const visibleCount = getNewVisibleCount();
        const maxIndex = Math.max(0, newTotalItems - visibleCount);

        if (newCurrentIndex > maxIndex) {
            newCurrentIndex = maxIndex;
        }

        const itemWidth = firstItem.offsetWidth;
        const gap = 18;
        const moveX = newCurrentIndex * (itemWidth + gap);

        track.style.transform = 'translateX(-' + moveX + 'px)';

        prevBtn.classList.toggle('is-hidden', newCurrentIndex <= 0);
        nextBtn.classList.toggle('is-hidden', newCurrentIndex >= maxIndex || newTotalItems <= visibleCount);
    }

    function loadHomeNewBuggies() {
        const container = document.getElementById('homeNewBuggyList');

        container.style.transform = 'translateX(0)';
        container.innerHTML = '<div class="home-new-loading">Loading new buggies...</div>';

        fetch('ajax.php?action=list&listing_type=sale&buggy_condition=new')
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result.success || !result.data || !result.data.items) {
                    container.innerHTML = '<div class="home-new-empty">Failed to load new buggies.</div>';
                    newTotalItems = 0;
                    updateNewSlider();
                    return;
                }

                const items = result.data.items;
                newTotalItems = items.length;
                newCurrentIndex = 0;

                if (items.length === 0) {
                    container.innerHTML = '<div class="home-new-empty">No new buggies available yet.</div>';
                    updateNewSlider();
                    return;
                }

                container.innerHTML = items.map(function (item) {
                    const title = item.name ? item.name : ((item.brand || '') + ' ' + (item.model || '')).trim();
                    const imageUrl = item.image_url ? item.image_url : 'images/no-image.jpg';
                    const tag = item.tag ? item.tag : 'New';
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
                        const timeLeft = newBuggyPromoTimeLeft(item.promo_end_date);
                        priceHtml = `
                            <p class="new-buggy-price promo">
                                <s class="new-buggy-original">${newBuggyFormatPrice(item.selling_price)}</s>
                                ${newBuggyFormatPrice(item.discount_price)}
                            </p>
                            <span class="new-buggy-save">Save $${save.toLocaleString('en-SG')}</span>
                            <span class="new-buggy-countdown">⏰ ${newBuggyEscapeHtml(timeLeft)}</span>
                        `;
                    } else {
                        priceHtml = `<p class="new-buggy-price">${newBuggyFormatPrice(item.selling_price)}</p>`;
                    }

                    const soldNote = isSold ? '<div class="new-buggy-sold-note">This buggy has been sold</div>' : '';
                    const imageBadge = isSold
                        ? '<span class="new-buggy-sold-badge">SOLD</span>'
                        : (promoActive ? '<span class="new-buggy-sale-badge">🔥 SALE</span>' : '');

                    return `
                         <a href="buggy-detail.php?id=${encodeURIComponent(item.id)}" class="new-buggy-link${isSold ? ' is-sold' : ''}">
                            <div class="new-buggy-card">
                                <div class="new-buggy-image">
                                    <img src="${newBuggyEscapeHtml(imageUrl)}" alt="${newBuggyEscapeHtml(title)}">
                                    <span class="new-buggy-tag">${newBuggyEscapeHtml(tag)}</span>
                                    ${imageBadge}
                                </div>

                                ${priceHtml}
                                ${soldNote}

                                <h3 class="new-buggy-name">
                                    ${newBuggyEscapeHtml(title)}
                                </h3>

                                <div class="new-buggy-meta">
                                    <span>${newBuggyEscapeHtml(item.brand || 'Brand')}</span>
                                    <span>${newBuggyEscapeHtml(seats)}</span>
                                    <span>New</span>
                                </div>
                            </div>
                        </a>
                    `;
                }).join('');

                updateNewSlider();
            })
            .catch(function () {
                container.innerHTML = '<div class="home-new-empty">Failed to connect to product system.</div>';
                newTotalItems = 0;
                updateNewSlider();
            });
    }

    document.querySelectorAll('.new-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.new-tab').forEach(function (item) {
                item.classList.remove('active');
            });

            this.classList.add('active');
        });
    });

    document.getElementById('newSliderPrev').addEventListener('click', function () {
        if (newCurrentIndex > 0) {
            newCurrentIndex--;
            updateNewSlider();
        }
    });

    document.getElementById('newSliderNext').addEventListener('click', function () {
        const visibleCount = getNewVisibleCount();
        const maxIndex = Math.max(0, newTotalItems - visibleCount);

        if (newCurrentIndex < maxIndex) {
            newCurrentIndex++;
            updateNewSlider();
        }
    });

    window.addEventListener('resize', function () {
        updateNewSlider();
    });

    loadHomeNewBuggies();
</script>