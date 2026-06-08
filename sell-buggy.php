<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

/*
    sell-buggy.php

    Public seller landing page.
    New flow:
    - Seller account is separate from member account.
    - Seller register/login path: /seller/login.php?mode=register
*/

/*
    Replace this WhatsApp number with SGBUGGYMART real WhatsApp number.
    Format must be country code + number, without + or spaces.
    Example Malaysia: 60123456789
*/
$whatsappNumber = '60123456789';

$sellBuggyBanner = '';
try {
    $bannerStmt = $pdo->prepare("
        SELECT image_url FROM banners
        WHERE location = 'sell_buggy' AND status = 'active'
        ORDER BY sort_order ASC, id ASC
        LIMIT 1
    ");
    $bannerStmt->execute();
    $bannerRow = $bannerStmt->fetch();
    if ($bannerRow) $sellBuggyBanner = $bannerRow['image_url'];
} catch (Exception $e) {
    $sellBuggyBanner = '';
}
$sellBuggyBannerFinal = $sellBuggyBanner ?: 'https://touristicenter.com/wp-content/uploads/2017/01/dune-buggy-fuerteventura.jpg';

$assistedMessage = urlencode('Hi SGBUGGYMART, I would like help to sell my buggy.');
$consignmentMessage = urlencode('Hi SGBUGGYMART, I am interested in buggy consignment / trade-in service.');

$postBuggyLink = '/seller/login.php?mode=register';
$sellerLoginLink = '/seller/login.php?mode=login';

include 'header.php';
?>

<style>
    .sell-page {
        background: #ffffff;
        min-height: 70vh;
    }

    .sell-hero {
        position: relative;
        background:
            linear-gradient(90deg, rgba(3, 22, 56, 0.92), rgba(13, 75, 150, 0.76), rgba(0, 0, 0, 0.36)),
            url('<?php echo htmlspecialchars($sellBuggyBannerFinal); ?>') center/cover no-repeat;
        color: #ffffff;
        padding: 76px 24px 82px;
    }

    .sell-hero-inner {
        max-width: 1400px;
        margin: 0 auto;
    }

    .sell-breadcrumb {
        font-size: 14px;
        margin-bottom: 26px;
        color: rgba(255, 255, 255, 0.85);
    }

    .sell-breadcrumb a {
        color: #ffffff;
        text-decoration: none;
        font-weight: bold;
    }

    .sell-breadcrumb span {
        margin: 0 8px;
        opacity: 0.75;
    }

    .sell-hero h1 {
        max-width: 800px;
        margin: 0 0 16px;
        font-size: clamp(36px, 5vw, 60px);
        line-height: 1.02;
        letter-spacing: -1.7px;
    }

    .sell-hero p {
        max-width: 720px;
        margin: 0;
        font-size: 17px;
        line-height: 1.65;
        color: rgba(255, 255, 255, 0.92);
    }

    .sell-stats-row {
        margin-top: 34px;
        display: flex;
        align-items: stretch;
        gap: 14px;
        flex-wrap: wrap;
    }

    .sell-stat-box {
        min-width: 180px;
        background: rgba(0, 0, 0, 0.34);
        border: 1px solid rgba(255, 255, 255, 0.18);
        padding: 16px 20px;
        border-radius: 12px;
    }

    .sell-stat-box strong {
        display: block;
        font-size: 28px;
        color: #ffffff;
        line-height: 1;
        margin-bottom: 6px;
    }

    .sell-stat-box span {
        display: block;
        font-size: 13px;
        color: rgba(255,255,255,0.84);
        line-height: 1.35;
        font-weight: bold;
    }

    .sell-content {
        max-width: 1400px;
        margin: 0 auto;
        padding: 56px 24px 82px;
    }

    .seller-login-strip {
        margin: -42px auto 54px;
        max-width: 1400px;
        padding: 0 24px;
        position: relative;
        z-index: 5;
    }

    .seller-login-card {
        background: #ffffff;
        border: 1px solid #eeeeee;
        border-radius: 16px;
        box-shadow: 0 18px 45px rgba(0,0,0,0.14);
        padding: 22px 24px;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 18px;
        align-items: center;
    }

    .seller-login-card h3 {
        margin: 0 0 6px;
        font-size: 20px;
        color: #222222;
    }

    .seller-login-card p {
        margin: 0;
        color: #666666;
        line-height: 1.5;
        font-size: 14px;
    }

    .seller-login-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .small-action-btn {
        min-height: 40px;
        border-radius: 999px;
        padding: 0 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: 13px;
        font-weight: 800;
        white-space: nowrap;
        transition: 0.2s ease;
    }

    .small-action-primary {
        background: #0d6efd;
        color: #ffffff;
        border: 1px solid #0d6efd;
    }

    .small-action-primary:hover {
        background: #0b5ed7;
        border-color: #0b5ed7;
    }

    .small-action-outline {
        background: #ffffff;
        color: #0d6efd;
        border: 1px solid #0d6efd;
    }

    .small-action-outline:hover {
        background: #f4f8ff;
    }

    .sell-section-head {
        text-align: center;
        margin-bottom: 34px;
    }

    .sell-section-head h2 {
        margin: 0 0 10px;
        font-size: 32px;
        color: #222222;
        letter-spacing: -0.5px;
    }

    .sell-section-head p {
        margin: 0 auto;
        max-width: 700px;
        color: #666666;
        font-size: 15px;
        line-height: 1.65;
    }

    .sell-options {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
        align-items: stretch;
    }

    .sell-card {
        position: relative;
        background: #ffffff;
        border: 1px solid #e5e5e5;
        border-radius: 16px;
        padding: 34px 30px 28px;
        min-height: 365px;
        display: flex;
        flex-direction: column;
        box-shadow: 0 8px 26px rgba(0, 0, 0, 0.06);
        transition: 0.2s ease;
        overflow: hidden;
    }

    .sell-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 5px;
        background: #eeeeee;
    }

    .sell-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.1);
        border-color: #0d6efd;
    }

    .sell-card.featured {
        border: 2px solid #0d6efd;
    }

    .sell-card.featured::before {
        background: #0d6efd;
    }

    .feature-badge {
        position: absolute;
        top: 16px;
        right: 18px;
        background: #0d6efd;
        color: #ffffff;
        font-size: 12px;
        font-weight: bold;
        border-radius: 999px;
        padding: 7px 13px;
    }

    .sell-card-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        background: #f4f8ff;
        color: #0d6efd;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 27px;
        margin: 0 auto 18px;
    }

    .sell-card h3 {
        margin: 0 0 10px;
        color: #0d6efd;
        font-size: 27px;
        line-height: 1.15;
        text-align: center;
        text-transform: uppercase;
    }

    .sell-card-subtitle {
        text-align: center;
        color: #222222;
        font-weight: bold;
        margin-bottom: 20px;
        font-size: 15px;
    }

    .sell-card ul {
        list-style: none;
        padding: 0;
        margin: 0 0 26px;
        display: grid;
        gap: 13px;
    }

    .sell-card li {
        position: relative;
        padding-left: 28px;
        color: #444444;
        line-height: 1.45;
        font-size: 15px;
    }

    .sell-card li::before {
        content: "✓";
        position: absolute;
        left: 0;
        top: 0;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #e9fbf7;
        color: #00a884;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
    }

    .sell-card-action {
        margin-top: auto;
    }

    .sell-btn {
        width: 100%;
        min-height: 46px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-weight: bold;
        font-size: 15px;
        transition: 0.2s ease;
        cursor: pointer;
    }

    .sell-btn-primary {
        background: #0d6efd;
        color: #ffffff;
        border: 1px solid #0d6efd;
    }

    .sell-btn-primary:hover {
        background: #0b5ed7;
        border-color: #0b5ed7;
    }

    .sell-btn-outline {
        background: #ffffff;
        color: #0d6efd;
        border: 1px solid #0d6efd;
    }

    .sell-btn-outline:hover {
        background: #f4f8ff;
    }

    .seller-note {
        margin-top: 38px;
        background: #f9f9f9;
        border: 1px solid #eeeeee;
        border-radius: 16px;
        padding: 24px;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 20px;
        align-items: center;
    }

    .seller-note h3 {
        margin: 0 0 8px;
        color: #222222;
        font-size: 20px;
    }

    .seller-note p {
        margin: 0;
        color: #666666;
        font-size: 15px;
        line-height: 1.6;
    }

    .seller-note .sell-btn {
        min-width: 210px;
    }

    .sell-flow {
        margin-top: 58px;
    }

    .sell-flow h2 {
        text-align: center;
        margin: 0 0 30px;
        font-size: 30px;
        color: #222222;
        letter-spacing: -0.5px;
    }

    .flow-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 16px;
    }

    .flow-step {
        background: #ffffff;
        border: 1px solid #eeeeee;
        border-radius: 14px;
        padding: 23px 18px;
        text-align: center;
        transition: 0.2s ease;
    }

    .flow-step:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    }

    .flow-number {
        width: 40px;
        height: 40px;
        margin: 0 auto 14px;
        border-radius: 50%;
        background: #0d6efd;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }

    .flow-step h4 {
        margin: 0 0 8px;
        color: #222222;
        font-size: 15px;
    }

    .flow-step p {
        margin: 0;
        color: #666666;
        font-size: 13px;
        line-height: 1.5;
    }

    .seller-rules {
        margin-top: 54px;
        background: #f4f8ff;
        border: 1px solid #cfe2ff;
        border-radius: 16px;
        padding: 28px;
    }

    .seller-rules h2 {
        margin: 0 0 14px;
        color: #222222;
        font-size: 24px;
    }

    .seller-rules-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        margin-top: 20px;
    }

    .seller-rule-item {
        background: #ffffff;
        border: 1px solid #dce9ff;
        border-radius: 12px;
        padding: 17px;
    }

    .seller-rule-item strong {
        display: block;
        color: #0d6efd;
        margin-bottom: 7px;
        font-size: 15px;
    }

    .seller-rule-item span {
        display: block;
        color: #555555;
        font-size: 14px;
        line-height: 1.5;
    }

    @media (max-width: 1000px) {
        .sell-options {
            grid-template-columns: 1fr;
        }

        .flow-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .seller-note,
        .seller-login-card {
            grid-template-columns: 1fr;
        }

        .seller-rules-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 560px) {
        .sell-hero {
            padding: 46px 18px 94px;
        }

        .seller-login-strip {
            margin-top: -36px;
            padding: 0 18px;
        }

        .sell-content {
            padding: 38px 18px 60px;
        }

        .sell-card {
            padding: 32px 22px 24px;
        }

        .feature-badge {
            position: static;
            display: inline-flex;
            margin: 0 auto 16px;
            justify-content: center;
        }

        .flow-grid {
            grid-template-columns: 1fr;
        }

        .sell-stats-row {
            width: 100%;
        }

        .sell-stat-box {
            width: 100%;
        }

        .seller-login-actions {
            align-items: stretch;
            flex-direction: column;
        }

        .small-action-btn {
            width: 100%;
        }
    }
</style>

<main class="sell-page">

    <section class="sell-hero">
        <div class="sell-hero-inner">
            <div class="sell-breadcrumb">
                <a href="/index.php">Home</a>
                <span>›</span>
                Sell Buggy
            </div>

            <h1>The easier way to sell your buggy to the right buyer</h1>

            <p>
                Reach customers who are already looking for new and used buggies.
                Choose SGBUGGYMART assistance, post your own buggy listing, or discuss consignment and trade-in with our team.
            </p>

            <div class="sell-stats-row">
                <div class="sell-stat-box">
                    <strong>10</strong>
                    <span>selling options for buggy owners</span>
                </div>

                <div class="sell-stat-box">
                    <strong>10</strong>
                    <span>maximum buggy listings per seller</span>
                </div>

                <div class="sell-stat-box">
                    <strong>100%</strong>
                    <span>listings go live immediately after upload</span>
                </div>
            </div>
        </div>
    </section>

    <div class="seller-login-strip">
        <div class="seller-login-card">
            <div>
                <h3>Already registered as a seller?</h3>
                <p>
                    Sign in to your seller dashboard to check payment verification, manage your profile,
                    and upload buggy listings after approval.
                </p>
            </div>

            <div class="seller-login-actions">
                <a class="small-action-btn small-action-outline" href="<?php echo htmlspecialchars($sellerLoginLink); ?>">
                    Seller Sign In
                </a>

                <a class="small-action-btn small-action-primary" href="<?php echo htmlspecialchars($postBuggyLink); ?>">
                    Register Seller Account
                </a>
            </div>
        </div>
    </div>

    <section class="sell-content">

        <div class="sell-section-head">
            <h2>Choose how you want to sell</h2>
            <p>
                Inspired by marketplace-style selling options, SGBUGGYMART gives sellers
                three clear ways to start: assisted selling, self-posting, or consignment / trade-in discussion.
            </p>
        </div>

        <div class="sell-options">

            <div class="sell-card">
                <div class="sell-card-icon">🤝</div>

                <h3>Sell Through SGBUGGYMART</h3>
                <div class="sell-card-subtitle">Let SGBUGGYMART assist your sale</div>

                <ul>
                    <li>Suitable if you want SGBUGGYMART to guide the selling process</li>
                    <li>SGBUGGYMART team can help review your buggy details</li>
                    <li>Buyer enquiry can be handled through SGBUGGYMART</li>
                    <li>Good option if you prefer assisted selling</li>
                </ul>

                <div class="sell-card-action">
                    <a
                        class="sell-btn sell-btn-primary"
                        href="https://wa.me/<?php echo htmlspecialchars($whatsappNumber); ?>?text=<?php echo $assistedMessage; ?>"
                        target="_blank"
                    >
                        Request SGBUGGYMART Help
                    </a>
                </div>
            </div>

            <div class="sell-card featured">
                <div class="feature-badge">Seller Portal</div>

                <div class="sell-card-icon">📣</div>

                <h3>Post My Buggy</h3>
                <div class="sell-card-subtitle">Upload your own buggy listing</div>

                <ul>
                    <li>Register or login with a separate SGBUGGYMART seller account</li>
                    <li>Submit payment reference and receipt for verification</li>
                    <li>Upload up to 10 used buggy listings after approval</li>
                  <li>Listing goes live immediately after upload</li>
                </ul>

                <div class="sell-card-action">
                    <a class="sell-btn sell-btn-outline" href="<?php echo htmlspecialchars($postBuggyLink); ?>">
                        Post My Buggy
                    </a>
                </div>
            </div>

            <div class="sell-card">
                <div class="sell-card-icon">🔁</div>

                <h3>Consignment</h3>
                <div class="sell-card-subtitle">Consignment / trade-in discussion</div>

                <ul>
                    <li>Good for sellers unsure about market price</li>
                    <li>SGBUGGYMART can advise based on buggy condition</li>
                    <li>Suitable for consignment or trade-in discussion</li>
                    <li>Contact SGBUGGYMART before deciding the best selling method</li>
                </ul>

                <div class="sell-card-action">
                    <a
                        class="sell-btn sell-btn-outline"
                        href="https://wa.me/<?php echo htmlspecialchars($whatsappNumber); ?>?text=<?php echo $consignmentMessage; ?>"
                        target="_blank"
                    >
                        Contact SGBUGGYMART
                    </a>
                </div>
            </div>

        </div>

        <div class="seller-note">
            <div>
                <h3>Want to post your own buggy?</h3>
                <p>
                    You will need a separate seller account. After payment verification,
                    your seller dashboard will unlock and listings go live immediately after upload.
                </p>
            </div>

            <a class="sell-btn sell-btn-primary" href="<?php echo htmlspecialchars($postBuggyLink); ?>">
                Start Seller Registration
            </a>
        </div>

        <div class="sell-flow">
            <h2>How seller listing works</h2>

            <div class="flow-grid">
                <div class="flow-step">
                    <div class="flow-number">1</div>
                    <h4>Seller Register</h4>
                    <p>Create or login to your SGBUGGYMART seller account.</p>
                </div>

                <div class="flow-step">
                    <div class="flow-number">2</div>
                    <h4>Submit Payment</h4>
                    <p>Submit your payment reference and receipt.</p>
                </div>

                <div class="flow-step">
                    <div class="flow-number">3</div>
                    <h4>Admin Verify</h4>
                    <p>SGBUGGYMART admin verifies payment and activates your seller account.</p>
                </div>

                <div class="flow-step">
                    <div class="flow-number">4</div>
                    <h4>Upload Buggy</h4>
                    <p>Upload buggy details, price and images from seller portal.</p>
                </div>

                <div class="flow-step">
                    <div class="flow-number">5</div>
                    <h4>Go Live</h4>
                    <p>Your buggy appears publicly on SGBUGGYMART immediately after upload.</p>
                </div>
            </div>
        </div>

        <div class="seller-rules">
            <h2>Seller portal rules</h2>

            <div class="seller-rules-grid">
                <div class="seller-rule-item">
                    <strong>Separate seller account</strong>
                    <span>
                        Seller login is separate from normal member login. Seller data is saved in the sellers table.
                    </span>
                </div>

                <div class="seller-rule-item">
                    <strong>Payment verification required</strong>
                    <span>
                        New sellers must submit payment reference and receipt before admin approval.
                    </span>
                </div>

                <div class="seller-rule-item">
                    <strong>Admin approval required</strong>
                    <span>
                         Your buggy listings go live immediately after upload. Admin may hide or remove listings that violate our policies.
                    </span>
                </div>
            </div>
        </div>

    </section>

</main>

<?php include 'footer.php'; ?>

</body>
</html>