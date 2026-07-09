<?php
require_once '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function productImagePath($imageUrl)
{
    $imageUrl = trim((string)$imageUrl);

    if ($imageUrl === '') {
        return '../images/no-image.png';
    }

    if (preg_match('/^https?:\/\//i', $imageUrl)) {
        return $imageUrl;
    }

    if (strpos($imageUrl, '../') === 0) {
        return $imageUrl;
    }

    if (strpos($imageUrl, 'images/') === 0) {
        return '../' . $imageUrl;
    }

    return '../images/' . $imageUrl;
}

$message = '';
$error = '';

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = 'Seller buggy listing updated successfully.';
}

if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $message = 'Seller buggy listing deleted successfully.';
}

/*
    Admin actions:
    - approve seller buggy: status = active
    - reject seller buggy: status = rejected
    - pending seller buggy: status = pending
    - delete seller buggy and gallery rows
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buggy_id'], $_POST['action'])) {
    $buggyId = (int)$_POST['buggy_id'];
    $action = $_POST['action'];

    if ($buggyId <= 0) {
        $error = 'Invalid buggy ID.';
    } else {
        try {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("
                    UPDATE buggies
                    SET status = 'active', updated_at = NOW()
                    WHERE id = ?
                    AND owner_type = 'seller'
                ");
                $stmt->execute([$buggyId]);

                header('Location: seller-buggy-list.php?updated=1');
                exit;
            }

            if ($action === 'reject') {
                $stmt = $pdo->prepare("
                    UPDATE buggies
                    SET status = 'rejected', updated_at = NOW()
                    WHERE id = ?
                    AND owner_type = 'seller'
                ");
                $stmt->execute([$buggyId]);

                header('Location: seller-buggy-list.php?updated=1');
                exit;
            }

            if ($action === 'pending') {
                $stmt = $pdo->prepare("
                    UPDATE buggies
                    SET status = 'pending', updated_at = NOW()
                    WHERE id = ?
                    AND owner_type = 'seller'
                ");
                $stmt->execute([$buggyId]);

                header('Location: seller-buggy-list.php?updated=1');
                exit;
            }

            if ($action === 'delete') {
                $stmt = $pdo->prepare("
                    DELETE FROM buggy_images
                    WHERE buggy_id = ?
                ");
                $stmt->execute([$buggyId]);

                $stmt = $pdo->prepare("
                    DELETE FROM buggies
                    WHERE id = ?
                    AND owner_type = 'seller'
                ");
                $stmt->execute([$buggyId]);

                header('Location: seller-buggy-list.php?deleted=1');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Failed to update seller buggy listing: ' . $e->getMessage();
        }
    }
}

/*
    Search and filter
*/
$keyword = trim($_GET['keyword'] ?? '');
$status = $_GET['status'] ?? 'all';
$sellerStatus = $_GET['seller_status'] ?? 'all';

/* Count how many advanced filters are currently active (for the badge) */
$activeFilterCount = 0;
if ($status !== 'all')       $activeFilterCount++;
if ($sellerStatus !== 'all') $activeFilterCount++;

$sql = "
    SELECT
        b.id,
        b.owner_type,
        b.owner_id,
        b.brand,
        b.model,
        b.name,
        b.seats,
        b.listing_type,
        b.buggy_condition,
        b.selling_price,
        b.serial_number,
        b.short_info,
        b.description,
        b.image_url,
        b.tag,
        b.brand_tag,
        b.status,
        b.created_at,
        b.updated_at,

        s.full_name AS seller_name,
        s.email AS seller_email,
        s.contact_no AS seller_contact,
        s.seller_status,
        s.payment_status
    FROM buggies b
    INNER JOIN sellers s ON b.owner_id = s.id
    WHERE b.owner_type = 'seller'
";

$params = [];

if ($keyword !== '') {
    $sql .= " AND (
        b.brand LIKE ?
        OR b.model LIKE ?
        OR b.name LIKE ?
        OR b.serial_number LIKE ?
        OR b.short_info LIKE ?
        OR s.full_name LIKE ?
        OR s.email LIKE ?
        OR s.contact_no LIKE ?
    )";

    $likeKeyword = '%' . $keyword . '%';

    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

if ($status !== 'all') {
    $sql .= " AND b.status = ?";
    $params[] = $status;
}

if ($sellerStatus !== 'all') {
    $sql .= " AND s.seller_status = ?";
    $params[] = $sellerStatus;
}

/* ==== CSV export ==== */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportSql = $sql . "
        ORDER BY
            CASE
                WHEN b.status = 'pending' THEN 1
                WHEN b.status = 'active' THEN 2
                WHEN b.status = 'rejected' THEN 3
                WHEN b.status = 'inactive' THEN 4
                ELSE 5
            END,
            b.created_at DESC, b.id DESC
    ";
    try {
        $exportStmt = $pdo->prepare($exportSql);
        $exportStmt->execute($params);
        $rows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $rows = [];
    }

    $filename = 'seller-buggies-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Product Name', 'Serial #', 'Seller', 'Seller Email', 'Condition', 'Seats', 'Price', 'Listing Status', 'Submitted']);
    foreach ($rows as $r) {
        $seatsVal = trim((string)($r['seats'] ?? ''));
        if ($seatsVal !== '' && stripos($seatsVal, 'seater') === false) {
            $seatsVal .= ' seater';
        }
        $priceVal   = (float)($r['selling_price'] ?? 0);
        $priceCell  = $priceVal > 0 ? number_format($priceVal, 2, '.', '') : '';

        fputcsv($out, [
            (string)($r['model']         ?? ''),
            (string)($r['serial_number'] ?? ''),
            (string)($r['seller_name']   ?? ''),
            (string)($r['seller_email']  ?? ''),
            ucfirst((string)($r['buggy_condition'] ?? '')),
            $seatsVal,
            $priceCell,
            ucwords(str_replace('_', ' ', (string)($r['status'] ?? ''))),
            !empty($r['created_at']) ? date('Y-m-d H:i', strtotime($r['created_at'])) : '',
        ]);
    }
    fclose($out);
    exit;
}

/* Pagination */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$countSql = preg_replace('/^\s*SELECT[\s\S]*?FROM\s/', 'SELECT COUNT(*) FROM ', $sql, 1);
try {
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();
} catch (PDOException $e) {
    $totalCount = 0;
}
$totalPages = max(1, (int)ceil($totalCount / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql .= "
    ORDER BY
        CASE
            WHEN b.status = 'pending' THEN 1
            WHEN b.status = 'active' THEN 2
            WHEN b.status = 'rejected' THEN 3
            WHEN b.status = 'inactive' THEN 4
            ELSE 5
        END,
        b.created_at DESC,
        b.id DESC
    LIMIT $perPage OFFSET $offset
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products = [];
    $error = 'Failed to load seller buggy listings: ' . $e->getMessage();
}

function pageUrl($pageNum) {
    $qs = array_filter($_GET, function ($v) { return $v !== '' && $v !== null; });
    $qs['page'] = $pageNum;
    return '?' . http_build_query($qs);
}

include 'header.php';
?>

<style>
    .seller-buggy-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 22px;
    }

    .seller-buggy-header h1 {
        margin: 0;
        font-size: 30px;
    }

    .seller-buggy-header p {
        margin: 6px 0 0;
        color: #777;
        font-size: 15px;
    }

    .filter-card {
        background: #fff;
        border-radius: 16px;
        padding: 22px;
        box-shadow: 0 8px 22px rgba(0,0,0,0.04);
        margin-bottom: 28px;
    }

    .filter-form {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .filter-form > .keyword-wrap {
        flex: 1;
        min-width: 0;
    }

    .filter-form input {
        height: 52px;
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 0 16px;
        font-size: 15px;
        background: #fff;
    }

    .filter-form > button {
        height: 52px;
        padding-left: 22px;
        padding-right: 22px;
    }

    .btn-filter {
        position: relative;
        background: #fff;
        color: #333;
        border: 1px solid #ddd;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
        transition: 0.2s ease;
    }

    .btn-filter:hover { border-color: #ef3f4d; color: #ef3f4d; }

    .btn-filter .filter-count {
        position: absolute;
        top: -8px;
        right: -8px;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        border-radius: 999px;
        background: #ef3f4d;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    /* ===== Admin filter modal ===== */
    .admin-filter-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 9999;
        display: none;
        align-items: flex-start;
        justify-content: center;
        padding: 60px 16px;
        overflow-y: auto;
    }

    .admin-filter-overlay.open { display: flex; }

    .admin-filter-modal {
        background: #fff;
        width: 100%;
        max-width: 460px;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        animation: afmIn 0.22s ease;
    }

    @keyframes afmIn {
        from { opacity: 0; transform: translateY(-14px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .afm-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px 14px;
        border-bottom: 1px solid #eee;
    }

    .afm-header h2 { margin: 0; font-size: 20px; font-weight: 800; color: #1a1a1a; }

    .afm-close {
        border: 0;
        background: transparent;
        font-size: 26px;
        line-height: 1;
        color: #999;
        cursor: pointer;
        padding: 0 4px;
    }

    .afm-close:hover { color: #ef3f4d; }

    .afm-body { padding: 20px 24px; }

    .afm-group { margin-bottom: 18px; }
    .afm-group:last-child { margin-bottom: 0; }

    .afm-group label {
        display: block;
        font-size: 14px;
        font-weight: 700;
        color: #444;
        margin-bottom: 8px;
    }

    .afm-group select {
        width: 100%;
        height: 48px;
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 0 14px;
        font-size: 15px;
        background: #fff;
        cursor: pointer;
    }

    .afm-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 14px;
        padding: 16px 24px 22px;
        border-top: 1px solid #eee;
    }

    .afm-apply {
        border: 0;
        background: #ef3f4d;
        color: #fff;
        font-size: 15px;
        font-weight: 800;
        padding: 0 36px;
        height: 48px;
        border-radius: 10px;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .afm-apply:hover { background: #d92e3d; }

    .keyword-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }

    .keyword-wrap input {
        width: 100%;
        padding-right: 42px !important;
    }

    .keyword-clear {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        width: 26px;
        height: 26px;
        border: 0;
        border-radius: 50%;
        background: #e5e7eb;
        color: #555;
        font-size: 18px;
        line-height: 1;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 0;
        transition: 0.15s ease;
    }

    .keyword-clear:hover {
        background: #ef3f4d;
        color: #ffffff;
    }

    .keyword-clear.is-visible { display: inline-flex; }

    .table-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 8px 22px rgba(0,0,0,0.04);
        overflow: hidden;
    }

    .table-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 22px;
        border-bottom: 1px solid #e5e5e5;
    }

    .table-top strong {
        font-size: 20px;
    }

    .table-top span {
        color: #777;
        font-size: 14px;
    }

    .table-scroll {
        width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        min-width: 1280px;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 24px 18px;
        text-align: left;
        border-bottom: 1px solid #e5e5e5;
        vertical-align: middle;
        font-size: 14px;
    }

    th {
        background: #fafafa;
        color: #444;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        font-weight: 700;
        padding-top: 20px;
        padding-bottom: 20px;
    }

    tbody tr:hover {
        background: #fff7f8;
    }

    .product-cell {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 330px;
    }

    .product-img {
        width: 100px;
        height: 76px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid #ddd;
        background: #f1f1f1;
        flex-shrink: 0;
    }

    .product-name {
        font-weight: 800;
        font-size: 17px;
        color: #111;
        line-height: 1.35;
    }

    .seller-name {
        font-weight: 800;
        color: #111;
        font-size: 15px;
        margin-bottom: 5px;
    }

    .seller-meta {
        color: #777;
        font-size: 13px;
        line-height: 1.45;
        max-width: 230px;
        word-break: break-word;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 700;
        text-transform: capitalize;
        white-space: nowrap;
    }

    .badge-active,
    .badge-verified {
        background: #dcfce7;
        color: #166534;
    }

    .badge-pending,
    .badge-pending-verification,
    .badge-pending-payment,
    .badge-submitted,
    .badge-unpaid {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-inactive,
    .badge-suspended {
        background: #e5e7eb;
        color: #374151;
    }

    .price {
        font-weight: 800;
        color: #ef3f4d;
        white-space: nowrap;
    }

    .action-group {
        display: grid;
        gap: 8px;
        width: 108px;
    }

    .btn-small {
        width: 108px;
        height: 36px;
        border: none;
        outline: none;
        border-radius: 8px;
        padding: 0;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s ease;
        font-family: inherit;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-approve {
        background: #dcfce7;
        color: #166534;
    }

    .btn-approve:hover {
        background: #bbf7d0;
    }

    .btn-reject {
        background: #fee2e2;
        color: #991b1b;
    }

    .btn-reject:hover {
        background: #fecaca;
    }

    .btn-edit {
        background: #2563eb;
        color: #ffffff;
    }

    .btn-edit:hover {
        background: #1d4ed8;
    }

    .btn-view {
        background: #ffffff;
        color: #111111;
        border: 1px solid #dddddd;
    }

    .btn-view:hover {
        border-color: #ef3f4d;
        color: #ef3f4d;
    }

    .btn-delete {
        background: #2f2f2f;
        color: #ffffff;
    }

    .btn-delete:hover {
        background: #111111;
    }

    .inline-form {
        margin: 0;
    }

    .message-success {
        background: #e8fff0;
        color: #167a3c;
        border: 1px solid #b8e8c8;
        padding: 12px 14px;
        border-radius: 8px;
        margin-bottom: 18px;
    }

    .message-error {
        background: #fff0f2;
        color: #ef3f4d;
        border: 1px solid #ffc4cc;
        padding: 12px 14px;
        border-radius: 8px;
        margin-bottom: 18px;
    }

    .empty {
        padding: 55px 20px;
        text-align: center;
        color: #777;
    }

    @media (max-width: 900px) {
        .seller-buggy-header {
            display: block;
        }

        .filter-form {
            flex-wrap: wrap;
        }
        .filter-form > .keyword-wrap { flex: 1 1 100%; }
        .admin-filter-modal { max-width: 100%; }
    }
</style>

<div class="seller-buggy-header">
    <div>
        <h1>Seller Buggy Approval</h1>
        <p>Review seller uploaded buggy listings before they appear publicly.</p>
    </div>
    <?php
        $exportQs = array_filter($_GET, function ($v) { return $v !== '' && $v !== null; });
        $exportQs['export'] = 'csv';
        unset($exportQs['page']);
    ?>
    <a href="?<?php echo e(http_build_query($exportQs)); ?>"
       class="btn"
       style="background:#16a34a;"
       title="Download the currently filtered seller buggies as an Excel-compatible CSV file">
        &#128190; Export to Excel
    </a>
</div>

<?php if ($message !== ''): ?>
    <div class="message-success">
        <?php echo e($message); ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="message-error">
        <?php echo e($error); ?>
    </div>
<?php endif; ?>

<div class="filter-card">
    <form method="GET" class="filter-form">
        <div class="keyword-wrap">
            <input
                type="text"
                name="keyword"
                placeholder="Search product, seller name, email, contact, serial #..."
                value="<?php echo e($keyword); ?>"
            >
            <button type="button" class="keyword-clear" aria-label="Clear">&times;</button>
        </div>

        <button type="button" class="btn-filter" id="openAdminFilter">
            &#9881; Apply Filter
            <?php if ($activeFilterCount > 0): ?>
                <span class="filter-count"><?php echo (int)$activeFilterCount; ?></span>
            <?php endif; ?>
        </button>

        <a href="seller-buggy-list.php" class="btn">Clear</a>

        <!-- ===================== ADMIN FILTER MODAL ===================== -->
        <div class="admin-filter-overlay" id="adminFilterOverlay">
            <div class="admin-filter-modal">
                <div class="afm-header">
                    <h2>Filter Seller Buggies</h2>
                    <button type="button" class="afm-close" id="closeAdminFilter" aria-label="Close">&times;</button>
                </div>

                <div class="afm-body">
                    <div class="afm-group">
                        <label>Listing Status</label>
                        <select name="status">
                            <option value="all"      <?php echo $status === 'all'      ? 'selected' : ''; ?>>All Listing Status</option>
                            <option value="pending"  <?php echo $status === 'pending'  ? 'selected' : ''; ?>>Pending</option>
                            <option value="active"   <?php echo $status === 'active'   ? 'selected' : ''; ?>>Active</option>
                            <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="afm-group">
                        <label>Seller Status</label>
                        <select name="seller_status">
                            <option value="all"                  <?php echo $sellerStatus === 'all'                  ? 'selected' : ''; ?>>All Seller Status</option>
                            <option value="active"               <?php echo $sellerStatus === 'active'               ? 'selected' : ''; ?>>Active Seller</option>
                            <option value="pending_payment"      <?php echo $sellerStatus === 'pending_payment'      ? 'selected' : ''; ?>>Pending Payment</option>
                            <option value="pending_verification" <?php echo $sellerStatus === 'pending_verification' ? 'selected' : ''; ?>>Pending Verification</option>
                            <option value="rejected"             <?php echo $sellerStatus === 'rejected'             ? 'selected' : ''; ?>>Rejected Seller</option>
                            <option value="suspended"            <?php echo $sellerStatus === 'suspended'            ? 'selected' : ''; ?>>Suspended Seller</option>
                        </select>
                    </div>
                </div>

                <div class="afm-footer">
                    <button type="submit" class="afm-apply">Apply Filter</button>
                </div>
            </div>
        </div>
        <!-- =================== END ADMIN FILTER MODAL =================== -->
    </form>
</div>

<div class="table-card">
    <div class="table-top">
        <strong>Seller Buggy Listings</strong>
        <span>
            <?php echo (int)$totalCount; ?> item(s) found
            <?php if ($totalPages > 1): ?>
                &middot; Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?>
            <?php endif; ?>
        </span>
    </div>

    <?php if (count($products) > 0): ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Serial #</th>
                        <th>Seller</th>
                        <th>Condition</th>
                        <th>Seats</th>
                        <th>Price</th>
                        <th>Listing Status</th>
                        <th>Submitted</th>
                        <th width="130">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php
                            $title = trim((string)($product['model'] ?? ''));
                            if ($title === '') {
                                $title = !empty($product['name'])
                                    ? $product['name']
                                    : trim(($product['brand'] ?? '') . ' ' . ($product['model'] ?? ''));
                            }

                            if ($title === '') {
                                $title = 'Buggy Listing';
                            }

                            $listingStatus = strtolower((string)$product['status']);
                            $sellerStatusValue = strtolower((string)$product['seller_status']);
                            $paymentStatusValue = strtolower((string)$product['payment_status']);

                            $listingStatusLabel = ucwords(str_replace('_', ' ', $listingStatus));
                            $sellerStatusLabel = ucwords(str_replace('_', ' ', $sellerStatusValue));
                            $paymentStatusLabel = ucwords(str_replace('_', ' ', $paymentStatusValue));

                            $listingBadgeClass = 'badge-' . str_replace('_', '-', $listingStatus);
                            $sellerBadgeClass = 'badge-' . str_replace('_', '-', $sellerStatusValue);
                            $paymentBadgeClass = 'badge-' . str_replace('_', '-', $paymentStatusValue);
                        ?>

                        <tr>
                            <td>
                                <div class="product-cell">
                                    <img
                                        src="<?php echo e(productImagePath($product['image_url'])); ?>"
                                        alt="<?php echo e($title); ?>"
                                        class="product-img"
                                        onerror="this.src='../images/no-image.png';"
                                    >

                                    <div class="product-name">
                                        <?php echo e($title); ?>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?php if (!empty($product['serial_number'])): ?>
                                    <span style="font-family:monospace;font-weight:700;color:#111;background:#f3f4f6;padding:4px 8px;border-radius:6px;font-size:13px;">
                                        <?php echo e($product['serial_number']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#999;font-size:13px;">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php $ownerLabel = trim((string)($product['seller_name'] ?? '')); ?>
                                <span style="display:inline-block;background:#fef3c7;color:#92400e;font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px;">
                                    <?php echo e($ownerLabel !== '' ? $ownerLabel : 'Seller'); ?>
                                </span>

                                <div class="seller-meta" style="margin-top:6px;">
                                    <?php echo e($product['seller_email']); ?>
                                </div>
                            </td>

                            <td><?php echo strtoupper(e((string)$product['buggy_condition'])); ?></td>

                            <td>
                                <?php
                                    $seatsVal = trim((string)$product['seats']);
                                    if ($seatsVal === '') {
                                        echo '&mdash;';
                                    } elseif (stripos($seatsVal, 'seater') !== false) {
                                        echo e($seatsVal);
                                    } else {
                                        echo e($seatsVal) . ' seater';
                                    }
                                ?>
                            </td>

                            <td>
                                <span class="price">
                                    $<?php echo number_format((float)$product['selling_price'], 2); ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?php echo e($listingBadgeClass); ?>">
                                    <?php echo e($listingStatusLabel); ?>
                                </span>
                            </td>

                            <td>
                                <div class="seller-meta">
                                    <?php echo !empty($product['created_at']) ? e(date('Y-m-d', strtotime($product['created_at']))) : '-'; ?>
                                </div>
                            </td>

                            <td>
                                <div class="action-group">
                                    <?php if ($listingStatus === 'pending'): ?>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Approve this seller buggy listing?');">
                                            <input type="hidden" name="buggy_id" value="<?php echo (int)$product['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn-small btn-approve">Approve</button>
                                        </form>

                                        <form method="POST" class="inline-form" onsubmit="return confirm('Reject this seller buggy listing?');">
                                            <input type="hidden" name="buggy_id" value="<?php echo (int)$product['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn-small btn-reject">Reject</button>
                                        </form>
                                    <?php elseif ($listingStatus === 'active'): ?>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Move this listing back to pending?');">
                                            <input type="hidden" name="buggy_id" value="<?php echo (int)$product['id']; ?>">
                                            <input type="hidden" name="action" value="pending">
                                            <button type="submit" class="btn-small btn-reject">Pending</button>
                                        </form>
                                    <?php elseif ($listingStatus === 'rejected'): ?>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Approve this rejected listing?');">
                                            <input type="hidden" name="buggy_id" value="<?php echo (int)$product['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn-small btn-approve">Approve</button>
                                        </form>
                                    <?php endif; ?>

                                    <a
                                        href="product-form.php?id=<?php echo (int)$product['id']; ?>"
                                        class="btn-small btn-edit"
                                    >
                                        Edit
                                    </a>

                                    <?php if ($listingStatus === 'active'): ?>
                                        <a
                                            href="../buggy-detail.php?id=<?php echo (int)$product['id']; ?>"
                                            class="btn-small btn-view"
                                            target="_blank"
                                        >
                                            View
                                        </a>
                                    <?php else: ?>
                                        <a
                                            href="product-view.php?id=<?php echo (int)$product['id']; ?>"
                                            class="btn-small btn-view"
                                        >
                                            View
                                        </a>
                                    <?php endif; ?>

                                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete this seller buggy listing permanently?');">
                                        <input type="hidden" name="buggy_id" value="<?php echo (int)$product['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn-small btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="admin-pagination">
                <?php if ($page > 1): ?>
                    <a class="page-link" href="<?php echo e(pageUrl($page - 1)); ?>">&laquo; Prev</a>
                <?php else: ?>
                    <span class="page-link is-disabled">&laquo; Prev</span>
                <?php endif; ?>

                <?php
                $windowStart = max(1, $page - 2);
                $windowEnd   = min($totalPages, $page + 2);
                if ($windowStart > 1): ?>
                    <a class="page-link" href="<?php echo e(pageUrl(1)); ?>">1</a>
                    <?php if ($windowStart > 2): ?><span class="page-ellipsis">&hellip;</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                    <?php if ($p === $page): ?>
                        <span class="page-link is-active"><?php echo (int)$p; ?></span>
                    <?php else: ?>
                        <a class="page-link" href="<?php echo e(pageUrl($p)); ?>"><?php echo (int)$p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($windowEnd < $totalPages): ?>
                    <?php if ($windowEnd < $totalPages - 1): ?><span class="page-ellipsis">&hellip;</span><?php endif; ?>
                    <a class="page-link" href="<?php echo e(pageUrl($totalPages)); ?>"><?php echo (int)$totalPages; ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a class="page-link" href="<?php echo e(pageUrl($page + 1)); ?>">Next &raquo;</a>
                <?php else: ?>
                    <span class="page-link is-disabled">Next &raquo;</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty">
            No seller buggy listings found.
        </div>
    <?php endif; ?>
</div>

<style>
    .admin-pagination { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 8px; padding: 22px; border-top: 1px solid #e5e5e5; }
    .admin-pagination .page-link { min-width: 40px; height: 40px; padding: 0 14px; border-radius: 999px; border: 1px solid #d8dde4; background: #ffffff; color: #333; font-size: 14px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: 0.2s ease; }
    .admin-pagination .page-link:hover { border-color: #ef3f4d; color: #ef3f4d; }
    .admin-pagination .page-link.is-active { background: #ef3f4d; border-color: #ef3f4d; color: #ffffff; }
    .admin-pagination .page-link.is-disabled { opacity: 0.4; pointer-events: none; }
    .admin-pagination .page-ellipsis { color: #999; padding: 0 4px; font-weight: 700; }
</style>

<script>
    // ===================== FILTER MODAL =====================
    (function () {
        const overlay  = document.getElementById('adminFilterOverlay');
        const openBtn  = document.getElementById('openAdminFilter');
        const closeBtn = document.getElementById('closeAdminFilter');

        if (!overlay || !openBtn) return;

        function openModal()  { overlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
        function closeModal() { overlay.classList.remove('open'); document.body.style.overflow = ''; }

        openBtn.addEventListener('click', openModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
        });
    })();

    // ===================== LIVE KEYWORD FILTER =====================
    (function () {
        const keywordInput = document.querySelector('input[name="keyword"]');
        const clearBtn     = document.querySelector('.keyword-clear');
        const tbodyRows    = document.querySelectorAll('.table-card table tbody tr');
        const counter      = document.querySelector('.table-top span');
        if (!keywordInput) return;

        const originalCounterText = counter ? counter.textContent : '';

        function toggleClear() {
            if (clearBtn) clearBtn.classList.toggle('is-visible', keywordInput.value !== '');
        }

        function liveFilter() {
            const q = keywordInput.value.trim().toLowerCase();
            let shown = 0;
            tbodyRows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                const match = q === '' || text.indexOf(q) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) shown++;
            });
            if (counter) {
                counter.textContent = q === ''
                    ? originalCounterText
                    : shown + ' item(s) match "' + keywordInput.value + '"';
            }
            toggleClear();
        }

        keywordInput.addEventListener('input', liveFilter);

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                keywordInput.value = '';
                liveFilter();
                keywordInput.focus();
            });
        }

        toggleClear();
    })();
</script>

<?php include 'footer.php'; ?>