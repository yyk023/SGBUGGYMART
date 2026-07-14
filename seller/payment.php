<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

if (!isset($_SESSION['seller_id'])) {
    header('Location: login.php?mode=login');
    exit;
}

$sellerId = (int)$_SESSION['seller_id'];
$message  = '';
$error    = '';

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function paymentReceiptPath($path)
{
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('/^https?:\/\//i', $path)) return $path;
    if (strpos($path, '../') === 0) return $path;
    return '../' . $path;
}

function uploadPaymentReceipt($fileInputName, &$error)
{
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Please upload your payment receipt.';
        return '';
    }

    if ($_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        $error = 'Receipt upload error. Please try again.';
        return '';
    }

    $uploadDir = __DIR__ . '/../uploads/seller-receipts/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $originalName      = $_FILES[$fileInputName]['name'];
    $tmpName           = $_FILES[$fileInputName]['tmp_name'];
    $fileSize          = $_FILES[$fileInputName]['size'];
    $extension         = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $allowedMimeTypes  = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    if (!in_array($extension, $allowedExtensions, true)) {
        $error = 'Only JPG, JPEG, PNG, WEBP, and PDF receipts are allowed.';
        return '';
    }

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        $error = 'Uploaded receipt file is not valid.';
        return '';
    }

    if ($fileSize > 4 * 1024 * 1024) {
        $error = 'Receipt file size must be below 4MB.';
        return '';
    }

    $safeName    = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $newFileName = 'seller-receipt-' . time() . '-' . rand(1000, 9999) . '-' . $safeName . '.' . $extension;
    $targetPath  = $uploadDir . $newFileName;

    if (move_uploaded_file($tmpName, $targetPath)) {
        return 'uploads/seller-receipts/' . $newFileName;
    }

    $error = 'Failed to upload payment receipt.';
    return '';
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id, full_name, contact_no, email, address,
            seller_status, payment_status, payment_reference,
            payment_receipt, rejected_reason, package_id, created_at, updated_at
        FROM sellers
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$sellerId]);
    $seller = $stmt->fetch();

    if (!$seller) {
        session_destroy();
        header('Location: login.php?mode=login&error=' . urlencode('Seller account not found.'));
        exit;
    }
} catch (PDOException $e) {
    die('Failed to load seller account: ' . $e->getMessage());
}

// Load active packages
$packages = [];
try {
    $stmt = $pdo->query("SELECT * FROM seller_packages WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $packages = [];
}

// Selected package
$selectedPackageId = isset($_GET['package']) ? (int)$_GET['package'] : (int)($seller['package_id'] ?? 0);
if ($selectedPackageId === 0 && count($packages) > 0) {
    $selectedPackageId = (int)$packages[0]['id'];
}
$selectedPackage = null;
foreach ($packages as $p) {
    if ((int)$p['id'] === $selectedPackageId) {
        $selectedPackage = $p;
        break;
    }
}
if (!$selectedPackage && count($packages) > 0) {
    $selectedPackage = $packages[0];
    $selectedPackageId = (int)$selectedPackage['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentReference = trim($_POST['payment_reference'] ?? '');
    $postedPackageId  = (int)($_POST['package_id'] ?? 0);

    if ($seller['seller_status'] === 'active') {
        $error = 'Your seller account is already active.';
    } elseif ($paymentReference === '') {
        $error = 'Payment reference is required.';
    } elseif ($postedPackageId <= 0) {
        $error = 'Please select a package.';
    } else {
        $receiptPath = uploadPaymentReceipt('payment_receipt', $error);

        if ($error === '') {
            try {
                $stmt = $pdo->prepare("
                    UPDATE sellers SET
                        payment_reference = :payment_reference,
                        payment_receipt   = :payment_receipt,
                        seller_status     = 'pending_verification',
                        payment_status    = 'submitted',
                        package_id        = :package_id,
                        rejected_reason   = NULL,
                        updated_at        = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':payment_reference' => $paymentReference,
                    ':payment_receipt'   => $receiptPath,
                    ':package_id'        => $postedPackageId,
                    ':id'                => $sellerId,
                ]);

                $_SESSION['seller_status']  = 'pending_verification';
                $_SESSION['payment_status'] = 'submitted';

                header('Location: payment.php?submitted=1');
                exit;
            } catch (PDOException $e) {
                $error = 'Failed to submit payment: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['submitted']) && $_GET['submitted'] === '1') {
    $message = 'Payment submitted successfully. Please wait for SGBUGGYMART admin payment verification before accessing seller dashboard.';
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id, full_name, contact_no, email, address,
            seller_status, payment_status, payment_reference,
            payment_receipt, rejected_reason, created_at, updated_at
        FROM sellers
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$sellerId]);
    $seller = $stmt->fetch();
} catch (PDOException $e) {
    die('Failed to refresh seller account: ' . $e->getMessage());
}

$statusLabel  = ucwords(str_replace('_', ' ', $seller['seller_status']));
$paymentLabel = ucwords(str_replace('_', ' ', $seller['payment_status']));
$receiptUrl   = paymentReceiptPath($seller['payment_receipt'] ?? '');

if (
    isset($_GET['error']) &&
    $_GET['error'] !== '' &&
    !(
        $seller['seller_status'] === 'active' &&
        $seller['payment_status'] === 'verified'
    )
) {
    $error = trim($_GET['error']);
}

if (
    isset($_GET['error']) &&
    $seller['seller_status'] === 'active' &&
    $seller['payment_status'] === 'verified'
) {
    header('Location: payment.php');
    exit;
}

if (isset($_GET['notice']) && $_GET['notice'] !== '') {
    $message = trim($_GET['notice']);
}

include '../header.php';
?>

<style>
    .seller-payment-page {
        background: #f5f5f5;
        padding: 42px 15px 76px;
        min-height: 75vh;
    }

    .seller-payment-wrap {
        max-width: 1320px;
        margin: 0 auto;
    }

    .seller-payment-header { margin-bottom: 22px; }
    .seller-payment-header h1 { margin: 0 0 8px; font-size: 30px; color: #222222; }
    .seller-payment-header p  { margin: 0; color: #666666; font-size: 15px; line-height: 1.6; }

    .payment-layout {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 24px;
        align-items: start;
    }

    .payment-card {
        background: #ffffff;
        border: 1px solid #dddddd;
        border-radius: 12px;
        padding: 28px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        box-sizing: border-box;
        height: 100%;
    }

    .payment-card h2 { margin: 0 0 18px; font-size: 22px; color: #222222; }
    .payment-card p  { color: #666666; font-size: 15px; line-height: 1.6; }

    .price-box {
        background: #fff0f2;
        border: 1px solid #ffc4cc;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 22px;
        text-align: center;
    }

    .price-box span   { display: block; color: #777777; font-size: 14px; margin-bottom: 6px; }
    .price-box strong { display: block; color: #ef3f4d; font-size: 36px; line-height: 1.1; }
    .price-box small  { display: block; color: #555555; font-size: 13px; margin-top: 8px; }

    /* Package selector cards (Claude-style) */
    .pkg-selector-title {
        font-size: 20px;
        font-weight: 800;
        color: #111;
        margin: 0 0 18px;
    }

    .pkg-selector-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }

    .pkg-select-card {
        position: relative;
        background: #fff;
        border: 2px solid #e5e7eb;
        border-radius: 16px;
        padding: 22px 20px;
        text-decoration: none;
        color: inherit;
        transition: 0.2s ease;
        display: flex;
        flex-direction: column;
    }

    .pkg-select-card:hover {
        border-color: #0066cc;
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.08);
    }

    .pkg-select-card.is-selected {
        border-color: #0066cc;
        background: #f4f9ff;
        box-shadow: 0 8px 22px rgba(0, 102, 204, 0.15);
    }

    .pkg-select-card.is-highlighted {
        border-color: #f59e0b;
    }

    .pkg-select-card.is-highlighted.is-selected {
        border-color: #0066cc;
    }

    .pkg-badge {
        position: absolute;
        top: -10px;
        left: 18px;
        background: #f59e0b;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 999px;
    }

    .pkg-select-name {
        margin: 0 0 4px;
        font-size: 17px;
        font-weight: 800;
        color: #111;
    }

    .pkg-select-tagline {
        margin: 0 0 14px;
        color: #6b7280;
        font-size: 13px;
        min-height: 18px;
    }

    .pkg-select-price {
        display: flex;
        align-items: baseline;
        gap: 6px;
        margin-bottom: 10px;
    }

    .pkg-select-price .amount {
        font-size: 30px;
        font-weight: 900;
        color: #ef3f4d;
    }

    .pkg-select-price .note {
        font-size: 12px;
        color: #6b7280;
        font-weight: 600;
    }

    .pkg-select-limit {
        background: #eef6ff;
        color: #0066cc;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 14px;
    }

    .pkg-select-benefits {
        list-style: none;
        padding: 0;
        margin: 0 0 18px;
        font-size: 13px;
        color: #444;
        flex: 1;
    }

    .pkg-select-benefits li {
        padding: 5px 0 5px 20px;
        position: relative;
        line-height: 1.5;
    }

    .pkg-select-benefits li::before {
        content: '✓';
        position: absolute;
        left: 0;
        color: #16a34a;
        font-weight: 900;
    }

    .pkg-select-action {
        text-align: center;
        height: 40px;
        line-height: 40px;
        border-radius: 8px;
        background: #f3f4f6;
        color: #1f2937;
        font-weight: 700;
        font-size: 13px;
        transition: 0.2s ease;
    }

    .pkg-select-card:hover .pkg-select-action {
        background: #0066cc;
        color: #fff;
    }

    .pkg-select-card.is-selected .pkg-select-action {
        background: #0066cc;
        color: #fff;
    }

    .bank-box {
        background: #fff7f8;
        border: 1px solid #ffd1d6;
        border-radius: 10px;
        padding: 18px;
        margin-bottom: 22px;
    }

    .bank-box h3 { margin: 0 0 12px; color: #ef3f4d; font-size: 18px; }

    .bank-row {
        display: grid;
        grid-template-columns: 145px 1fr;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px dashed #f2c3c8;
        font-size: 14px;
    }

    .bank-row:last-child { border-bottom: none; }
    .bank-row span       { color: #777777; font-weight: bold; }
    .bank-row strong     { color: #222222; }

    .qr-payment-box {
        background: #ffffff;
        border: 1px solid #eeeeee;
        border-radius: 12px;
        padding: 18px;
        margin-bottom: 20px;
        text-align: center;
    }

    .qr-payment-box h3 { margin: 0 0 8px; color: #222222; font-size: 18px; }
    .qr-payment-box p  { margin: 0 0 14px; color: #666666; font-size: 14px; line-height: 1.5; }

    .qr-show-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        height: 46px;
        padding: 0 28px;
        border: 2px solid #ef3f4d;
        border-radius: 8px;
        background: #fff0f2;
        color: #ef3f4d;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .qr-show-btn:hover {
        background: #ef3f4d;
        color: #ffffff;
    }

    .qr-zoom-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.78);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }

    .qr-zoom-overlay.active { display: flex; }
    .qr-zoom-content { position: relative; max-width: 92vw; max-height: 92vh; }

    .qr-zoom-content img {
        max-width: 92vw; max-height: 92vh;
        background: #ffffff; border-radius: 12px; padding: 14px;
    }

    .qr-zoom-close {
        position: absolute; top: -18px; right: -18px;
        width: 38px; height: 38px;
        border: none; border-radius: 50%;
        background: #ef3f4d; color: #ffffff;
        font-size: 24px; line-height: 38px;
        cursor: pointer; font-weight: bold;
    }

    .form-group { margin-bottom: 17px; }

    .form-group label {
        display: block; margin-bottom: 7px;
        color: #333333; font-size: 14px; font-weight: bold;
    }

    .form-control {
        width: 100%; height: 44px;
        border: 1px solid #cccccc; border-radius: 6px;
        padding: 0 12px; font-size: 14px;
        outline: none; background: #ffffff;
    }

    .form-control:focus { border-color: #ef3f4d; }
    .file-control       { padding: 10px 12px; height: auto; }
    .help               { margin-top: 6px; color: #888888; font-size: 13px; line-height: 1.4; }

    .payment-btn {
        width: 100%; height: 46px;
        border: none; border-radius: 6px;
        background: #ef3f4d; color: #ffffff;
        font-size: 15px; font-weight: bold;
        cursor: pointer; transition: 0.2s ease;
    }

    .payment-btn:hover    { background: #d92e3d; }
    .payment-btn:disabled { background: #bbbbbb; cursor: not-allowed; }

    .alert {
        padding: 12px 14px; border-radius: 6px;
        margin-bottom: 20px; font-size: 14px; line-height: 1.5;
    }

    .alert-success { background: #eaf7ea; color: #237b23; border: 1px solid #bfe6bf; }
    .alert-error   { background: #fdeaea; color: #b9151b; border: 1px solid #f1b8b8; }
    .alert-notice  { background: #fff8e6; color: #7a5700; border: 1px solid #ffe3a3; }

 .status-card {
        background: #ffffff;
        border: 1px solid #dddddd;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
    }

    .status-card-stretch { height: 100%; align-self: stretch; }
    .status-card-auto    { height: auto; align-self: start; }

    .status-card h2 { margin: 0 0 18px; font-size: 21px; color: #222222; }

    .seller-info     { display: grid; gap: 12px; }
    .seller-info-row { border-bottom: 1px solid #eeeeee; padding-bottom: 10px; }
    .seller-info-row span   { display: block; color: #888888; font-size: 13px; margin-bottom: 4px; }
    .seller-info-row strong { display: block; color: #222222; font-size: 15px; word-break: break-word; }

    .badge {
        display: inline-flex; align-items: center;
        border-radius: 999px; padding: 7px 11px;
        font-size: 12px; font-weight: bold; text-transform: capitalize;
    }

    .badge-pending   { background: #fff3cd; color: #856404; }
    .badge-active    { background: #dcfce7; color: #166534; }
    .badge-rejected  { background: #fee2e2; color: #991b1b; }
    .badge-suspended { background: #e5e7eb; color: #374151; }

    .receipt-link {
        display: inline-flex; margin-top: 8px;
        color: #ef3f4d; font-weight: bold;
        text-decoration: none; font-size: 14px;
    }

    .receipt-link:hover { text-decoration: underline; }

    .dashboard-link-btn {
        min-width: 240px;
        height: 46px;
        padding: 0 28px;
        border-radius: 8px;
        background: #222222;
        color: #ffffff;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: bold;
        margin: 20px auto 0;
        transition: 0.2s ease;
    }

    .dashboard-link-btn:hover {
        background: #111111;
        transform: translateY(-2px);
    }

    .dashboard-link-wrap {
        display: flex;
        justify-content: center;
        margin-top: 10px;
    }

    .waiting-box {
        background: #fff8e6;
        border: 1px solid #ffe3a3;
        border-radius: 8px;
        padding: 14px;
        color: #7a5700;
        font-size: 14px;
        line-height: 1.6;
        margin-top: 16px;
    }

    .rejected-box {
        background: #fff0f2;
        border: 1px solid #ffc4cc;
        border-radius: 8px;
        padding: 14px;
        color: #991b1b;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 18px;
    }

    @media (max-width: 900px) {
        .payment-layout {
            grid-template-columns: 1fr;
            align-items: start;
        }

        .payment-layout > * { height: auto; min-height: unset; }

        .payment-card,
        .status-card { height: auto; }

        .qr-zoom-close { top: -14px; right: -8px; }
    }
</style>

<section class="seller-payment-page">
    <div class="seller-payment-wrap">

<div class="seller-payment-header">
            <h1>Seller Payment Verification</h1>
            <?php if ($seller['seller_status'] !== 'pending_verification'): ?>
            <p>
                Submit your payment reference and receipt. SGBUGGYMART admin will verify your payment during working hours (Mon &ndash; Fri, 9:00 AM &ndash; 6:00 PM).<br>
                If you submit at night or on weekends, please wait until the next working day for admin to review your receipt and activate your account.
            </p>
            <?php endif; ?>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert <?php echo isset($_GET['notice']) ? 'alert-notice' : 'alert-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="payment-layout">

            <div class="payment-card">
                <h2>Submit Payment Details</h2>

                <?php if ($seller['seller_status'] === 'rejected' && !empty($seller['rejected_reason'])): ?>
                    <div class="rejected-box">
                        <strong>Rejected reason:</strong><br>
                        <?php echo e($seller['rejected_reason']); ?>
                    </div>
                <?php endif; ?>

                <?php if (count($packages) > 0 && $seller['seller_status'] !== 'active' && $seller['seller_status'] !== 'pending_verification' && $seller['seller_status'] !== 'suspended'): ?>
                    <div class="pkg-selector-title">Choose Your Package</div>

                    <div class="pkg-selector-grid">
                        <?php foreach ($packages as $p):
                            $benefits = !empty($p['benefits']) ? json_decode($p['benefits'], true) : [];
                            if (!is_array($benefits)) $benefits = [];
                            $isSelected = ((int)$p['id'] === $selectedPackageId);
                        ?>
                            <a href="payment.php?package=<?php echo (int)$p['id']; ?>"
                               class="pkg-select-card <?php echo $isSelected ? 'is-selected' : ''; ?> <?php echo (int)$p['is_highlighted'] === 1 ? 'is-highlighted' : ''; ?>">
                                <?php if ((int)$p['is_highlighted'] === 1): ?>
                                    <span class="pkg-badge">⭐ Most Popular</span>
                                <?php endif; ?>

                                <h3 class="pkg-select-name"><?php echo e($p['name']); ?></h3>
                                <?php if (!empty($p['tagline'])): ?>
                                    <p class="pkg-select-tagline"><?php echo e($p['tagline']); ?></p>
                                <?php endif; ?>

                                <div class="pkg-select-price">
                                    <span class="amount">$<?php echo number_format((float)$p['amount'], 0); ?></span>
                                    <span class="note">one-time</span>
                                </div>

                                <div class="pkg-select-limit">
                                    Up to <?php echo (int)$p['listing_limit']; ?> listing<?php echo (int)$p['listing_limit'] === 1 ? '' : 's'; ?>
                                </div>

                                <?php if (count($benefits) > 0): ?>
                                    <ul class="pkg-select-benefits">
                                        <?php foreach ($benefits as $b): ?>
                                            <li><?php echo e($b); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <div class="pkg-select-action">
                                    <?php echo $isSelected ? '✓ Selected' : 'Choose this plan'; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($selectedPackage): ?>
                <div class="bank-box">
                    <h3>Payment Information</h3>
                    <div class="bank-row">
                        <span>Selected Package</span>
                        <strong><?php echo e($selectedPackage['name']); ?></strong>
                    </div>
                    <div class="bank-row">
                        <span>Amount</span>
                        <strong>$<?php echo number_format((float)$selectedPackage['amount'], 0); ?> one-time payment</strong>
                    </div>
                    <div class="bank-row">
                        <span>Listing Limit</span>
                        <strong>Up to <?php echo (int)$selectedPackage['listing_limit']; ?> buggy listing<?php echo (int)$selectedPackage['listing_limit'] === 1 ? '' : 's'; ?></strong>
                    </div>
                    <div class="bank-row">
                        <span>Bank Name</span>
                        <strong>Maybank</strong>
                    </div>
                    <div class="bank-row">
                        <span>Account Name</span>
                        <strong>SGBUGGYMART</strong>
                    </div>
                    <div class="bank-row">
                        <span>Account No.</span>
                        <strong>123456789012</strong>
                    </div>
                    <div class="bank-row">
                        <span>Reference</span>
                        <strong>Use your seller email</strong>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($seller['seller_status'] === 'active'): ?>
                    <p>Your seller account is already active. You can now access your seller dashboard and upload buggy listings.</p>
                    <div class="dashboard-link-wrap">
                        <a href="dashboard.php" class="dashboard-link-btn">Go to Seller Dashboard</a>
                    </div>

                <?php elseif ($seller['seller_status'] === 'pending_verification'): ?>
                    <p>Your payment has already been submitted and is waiting for admin verification.</p>
<div class="waiting-box">
                        <strong>Dashboard access is locked.</strong><br><br>
                        Your payment is pending SGBUGGYMART admin verification. Our team operates during working hours
                        (<strong>Mon &ndash; Fri, 9:00 AM &ndash; 6:00 PM</strong>).<br><br>
                        If you submitted outside of working hours, your payment will be reviewed on the next working day. Thank you for your patience.
                    </div>
                    <?php if (!empty($receiptUrl)): ?>
                        <a href="<?php echo e($receiptUrl); ?>" class="receipt-link" target="_blank">View Submitted Receipt</a>
                    <?php endif; ?>

                <?php elseif ($seller['seller_status'] === 'suspended'): ?>
                    <p>Your seller account is suspended. Please contact SGBUGGYMART admin for more information.</p>

                <?php else: ?>
                    <form method="POST" enctype="multipart/form-data">

                        <input type="hidden" name="package_id" value="<?php echo (int)$selectedPackageId; ?>">

                        <div class="qr-payment-box">
                            <h3>Scan to Pay</h3>
                            <p>Please scan this QR code and pay <strong>$<?php echo number_format((float)($selectedPackage['amount'] ?? 0), 0); ?></strong> for your seller account setup fee.</p>
                            <button type="button" class="qr-show-btn" id="qrPaymentBtn">
                                🔍 Click to View QR Code
                            </button>
                        </div>

                        <div class="form-group">
                            <label>Payment Reference *</label>
                            <input
                                type="text"
                                name="payment_reference"
                                class="form-control"
                                value="<?php echo e($seller['payment_reference'] ?? ''); ?>"
                                placeholder="Example: Bank transfer ref / transaction ID"
                                required
                            >
                            <div class="help">Enter your bank transfer reference number or transaction ID after paying $<?php echo number_format((float)($selectedPackage['amount'] ?? 0), 0); ?>.</div>
                        </div>

                        <div class="form-group">
                            <label>Payment Receipt *</label>
                            <input
                                type="file"
                                name="payment_receipt"
                                class="form-control file-control"
                                accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf"
                                required
                            >
                            <div class="help">Accepted files: JPG, JPEG, PNG, WEBP, PDF. Max size: 4MB.</div>
                        </div>

                        <button type="submit" class="payment-btn">Submit Payment for Verification</button>
                    </form>
                <?php endif; ?>
            </div>

              <aside class="status-card <?php echo ($seller['seller_status'] === 'pending_verification' || $seller['seller_status'] === 'active') ? 'status-card-stretch' : 'status-card-auto'; ?>">
                <h2>Seller Account Status</h2>
                <div class="seller-info">
                    <div class="seller-info-row">
                        <span>Seller Name</span>
                        <strong><?php echo e($seller['full_name']); ?></strong>
                    </div>
                    <div class="seller-info-row">
                        <span>Email</span>
                        <strong><?php echo e($seller['email']); ?></strong>
                    </div>
                    <div class="seller-info-row">
                        <span>Contact No.</span>
                        <strong><?php echo e($seller['contact_no']); ?></strong>
                    </div>
                    <div class="seller-info-row">
                        <span>Seller Status</span>
                        <strong>
                            <?php
                                $sellerBadgeClass = 'badge-pending';
                                if ($seller['seller_status'] === 'active')        $sellerBadgeClass = 'badge-active';
                                elseif ($seller['seller_status'] === 'rejected')  $sellerBadgeClass = 'badge-rejected';
                                elseif ($seller['seller_status'] === 'suspended') $sellerBadgeClass = 'badge-suspended';
                            ?>
                            <span class="badge <?php echo e($sellerBadgeClass); ?>">
                                <?php echo e($statusLabel); ?>
                            </span>
                        </strong>
                    </div>
                    <div class="seller-info-row">
                        <span>Payment Status</span>
                        <strong>
                            <?php
                                $paymentBadgeClass = 'badge-pending';
                                if ($seller['payment_status'] === 'verified')     $paymentBadgeClass = 'badge-active';
                                elseif ($seller['payment_status'] === 'rejected') $paymentBadgeClass = 'badge-rejected';
                            ?>
                            <span class="badge <?php echo e($paymentBadgeClass); ?>">
                                <?php echo e($paymentLabel); ?>
                            </span>
                        </strong>
                    </div>
                    <div class="seller-info-row">
                        <span>Setup Fee</span>
                        <strong>$<?php echo number_format((float)($selectedPackage['amount'] ?? 0), 0); ?> one-time payment</strong>
                    </div>
                    <?php if (!empty($seller['payment_reference'])): ?>
                        <div class="seller-info-row">
                            <span>Payment Reference</span>
                            <strong><?php echo e($seller['payment_reference']); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($receiptUrl)): ?>
                        <div class="seller-info-row">
                            <span>Receipt</span>
                            <strong>
                                <a href="<?php echo e($receiptUrl); ?>" class="receipt-link" target="_blank">View Receipt</a>
                            </strong>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>

        </div>
    </div>
</section>

<div class="qr-zoom-overlay" id="qrZoomOverlay">
    <div class="qr-zoom-content">
        <button type="button" class="qr-zoom-close" id="qrZoomClose">&times;</button>
        <img src="../images/YHI_CORPORATION_PAYMENT.jpeg" alt="YHI Corporation (Singapore) Pte Ltd Payment QR Code">
    </div>
</div>

<script>
    const qrPaymentBtn  = document.getElementById('qrPaymentBtn');
    const qrZoomOverlay = document.getElementById('qrZoomOverlay');
    const qrZoomClose   = document.getElementById('qrZoomClose');

    if (qrPaymentBtn && qrZoomOverlay && qrZoomClose) {
        qrPaymentBtn.addEventListener('click', function () {
            qrZoomOverlay.classList.add('active');
        });

        qrZoomClose.addEventListener('click', function () {
            qrZoomOverlay.classList.remove('active');
        });

        qrZoomOverlay.addEventListener('click', function (event) {
            if (event.target === qrZoomOverlay) {
                qrZoomOverlay.classList.remove('active');
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                qrZoomOverlay.classList.remove('active');
            }
        });
    }
</script>

<?php include '../footer.php'; ?>