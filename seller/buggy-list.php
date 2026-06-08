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

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sellerProductImagePath($imageUrl)
{
    $imageUrl = trim((string)$imageUrl);
    if ($imageUrl === '') return '../images/no-image.png';
    if (preg_match('/^https?:\/\//i', $imageUrl)) return $imageUrl;
    if (strpos($imageUrl, '../') === 0) return $imageUrl;
    if (strpos($imageUrl, 'images/') === 0) return '../' . $imageUrl;
    return '../images/' . $imageUrl;
}

$message = '';
$error   = '';

if (isset($_GET['added'])   && $_GET['added']   === '1') $message = 'Buggy listing added successfully. It is now live!';
if (isset($_GET['updated']) && $_GET['updated'] === '1') $message = 'Buggy listing updated successfully.';

try {
    $sellerStmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.email, s.seller_status, s.payment_status, s.status, s.package_id,
               p.listing_limit AS pkg_listing_limit, p.name AS pkg_name
        FROM sellers s
        LEFT JOIN seller_packages p ON p.id = s.package_id
        WHERE s.id = ? LIMIT 1
    ");
    $sellerStmt->execute([$sellerId]);
    $seller = $sellerStmt->fetch(PDO::FETCH_ASSOC);

    if (!$seller) {
        session_destroy();
        header('Location: login.php?mode=login&error=' . urlencode('Seller account not found.'));
        exit;
    }

    $_SESSION['seller_name']    = $seller['full_name'];
    $_SESSION['seller_email']   = $seller['email'];
    $_SESSION['seller_status']  = $seller['seller_status'];
    $_SESSION['payment_status'] = $seller['payment_status'];
} catch (PDOException $e) {
    die('Failed to load seller account: ' . $e->getMessage());
}

$keyword = trim($_GET['keyword'] ?? '');
$status  = $_GET['status'] ?? 'all';

$sql    = "
    SELECT id, owner_type, owner_id, brand, model, name, seats,
           buggy_condition, selling_price, serial_number, short_info, description,
           image_url, tag, brand_tag, status, created_at, updated_at
    FROM buggies
    WHERE owner_type = 'seller' AND owner_id = ?
";
$params = [$sellerId];

if ($keyword !== '') {
    $sql .= " AND (brand LIKE ? OR model LIKE ? OR name LIKE ? OR short_info LIKE ?)";
    $like     = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY created_at DESC, id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $listings = [];
    $error = 'Failed to load listings: ' . $e->getMessage();
}

try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM buggies
        WHERE owner_type = 'seller' AND owner_id = ?
    ");
    $countStmt->execute([$sellerId]);
    $listingCount = (int)$countStmt->fetchColumn();
} catch (PDOException $e) {
    $listingCount = 0;
}

$sellerListingLimit = !empty($seller['pkg_listing_limit']) ? (int)$seller['pkg_listing_limit'] : 10;
$canAddMore = ($seller['seller_status'] === 'active' && $listingCount < $sellerListingLimit);

include '../header.php';
?>

<style>
    .seller-list-page { background: #ffffff; padding: 38px 15px 76px; min-height: 75vh; }
    .seller-list-wrap { max-width: 1320px; margin: 0 auto; }

    .seller-layout { display: grid; grid-template-columns: 230px minmax(0, 1fr); gap: 24px; align-items: start; }

    .seller-sidebar { background: #ffffff; border: 1px solid #dddddd; border-radius: 14px; padding: 24px 16px; }
    .seller-sidebar h2 { margin: 0 0 8px; font-size: 18px; color: #222222; }
    .seller-sidebar p  { margin: 0 0 24px; font-size: 14px; color: #888888; line-height: 1.5; }

    .seller-menu { display: grid; gap: 8px; }
    .seller-menu a { width: 100%; display: flex; align-items: center; gap: 10px; padding: 12px 13px; border-radius: 999px; color: #888888; text-decoration: none; font-size: 14px; transition: 0.2s ease; }
    .seller-menu a.active, .seller-menu a:hover { background: #f7f7f7; color: #ef3f4d; font-weight: bold; }
    .menu-icon { width: 18px; text-align: center; font-size: 15px; }
    .seller-main { min-width: 0; }

    .page-title { margin-bottom: 18px; }
    .page-title h1 { margin: 0; font-size: 30px; font-weight: 500; color: #333; }

    .breadcrumb { background: #eeeeee; padding: 12px 16px; margin-bottom: 22px; color: #999; font-size: 14px; }
    .breadcrumb a { color: #777; text-decoration: none; }
    .breadcrumb span { color: #ef3f4d; }

    .seller-list-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
    .seller-list-header h2 { margin: 0; font-size: 24px; color: #222222; }
    .seller-list-header p  { margin: 6px 0 0; color: #777777; font-size: 15px; }

    .add-btn { min-width: 150px; height: 42px; border-radius: 999px; background: #ef3f4d; color: #ffffff; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: 14px; font-weight: bold; transition: 0.2s ease; }
    .add-btn:hover { background: #d92e3d; }
    .add-btn.disabled { background: #bbbbbb; pointer-events: none; cursor: not-allowed; }

    .message-success { background: #e8fff0; color: #167a3c; border: 1px solid #b8e8c8; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
    .message-error   { background: #fff0f2; color: #ef3f4d; border: 1px solid #ffc4cc; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }

    .seller-rule-box { background: #fff7f8; border: 1px solid #ffd1d6; color: #555; padding: 14px 16px; margin-bottom: 18px; border-radius: 8px; font-size: 14px; line-height: 1.6; }
    .seller-rule-box strong { color: #ef3f4d; }

    .filter-card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 8px 22px rgba(0,0,0,0.04); margin-bottom: 24px; border: 1px solid #eeeeee; }
    .filter-form { display: grid; grid-template-columns: 1fr 180px auto; gap: 12px; align-items: center; }
    .filter-form input, .filter-form select { height: 48px; border: 1px solid #ddd; border-radius: 10px; padding: 0 14px; font-size: 14px; background: #fff; }
    .filter-form button { height: 48px; border: 0; border-radius: 10px; background: #ef3f4d; color: #ffffff; padding: 0 22px; font-weight: bold; cursor: pointer; }
    .filter-form button:hover { background: #d92e3d; }

    .table-card { background: #fff; border-radius: 16px; box-shadow: 0 8px 22px rgba(0,0,0,0.04); overflow: hidden; border: 1px solid #eeeeee; }
    .table-top { display: flex; align-items: center; justify-content: space-between; padding: 22px; border-bottom: 1px solid #e5e5e5; }
    .table-top strong { font-size: 20px; }
    .table-top span   { color: #777; font-size: 14px; }

    .table-scroll { width: 100%; overflow-x: visible; }
    table { width: 100%; border-collapse: collapse; table-layout: auto; }

    th, td { padding: 18px 12px; text-align: left; border-bottom: 1px solid #e5e5e5; vertical-align: middle; font-size: 14px; }
    th { background: #fafafa; color: #444; font-size: 13px; text-transform: uppercase; letter-spacing: 0.3px; font-weight: 700; white-space: nowrap; }
    tbody tr:hover { background: #fff7f8; }

    th.product-col, td.product-col { width: 36%; }
    th.brand-col,   td.brand-col   { width: 12%; }
    th.seats-col,   td.seats-col   { width: 9%; }
    th.price-col,   td.price-col   { width: 13%; }
    th.status-col,  td.status-col  { width: 11%; }
    th.date-col,    td.date-col    { width: 10%; }
    th.action-col,  td.action-col  { width: 9%; }

    .product-cell { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .product-img  { width: 86px; height: 64px; border-radius: 10px; object-fit: cover; border: 1px solid #ddd; background: #f1f1f1; flex-shrink: 0; }
    .product-name { font-weight: 700; font-size: 16px; margin-bottom: 6px; color: #111; line-height: 1.25; word-break: break-word; }
    .product-meta { color: #777; font-size: 13px; line-height: 1.4; word-break: break-word; }
    .brand-name   { font-weight: 800; color: #111; font-size: 15px; line-height: 1.25; word-break: break-word; }
    .brand-meta   { color: #777; font-size: 13px; line-height: 1.35; margin-top: 5px; }

    .badge { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 7px 12px; font-size: 12px; font-weight: 700; text-transform: capitalize; white-space: nowrap; }
    .badge-active   { background: #dcfce7; color: #166534; }
    .badge-pending  { background: #fef3c7; color: #92400e; }
    .badge-rejected { background: #fee2e2; color: #991b1b; }
    .badge-inactive { background: #e5e7eb; color: #374151; }
    .badge-sold     { background: #dbeafe; color: #1d4ed8; }

    .price     { font-weight: 800; color: #ef3f4d; white-space: nowrap; }
    .date-text { color: #666; font-size: 13px; white-space: nowrap; }

    .action-group { display: grid; gap: 7px; width: 78px; }

    .btn-small { width: 78px; height: 34px; border: none; outline: none; border-radius: 8px; padding: 0; font-size: 13px; font-weight: 700; cursor: pointer; transition: 0.2s ease; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
    .btn-edit { background: #2563eb; color: #ffffff; }
    .btn-edit:hover { background: #1d4ed8; }
    .btn-view { background: #ffffff; color: #111111; border: 1px solid #dddddd; }
    .btn-view:hover { border-color: #ef3f4d; color: #ef3f4d; }

    .empty { padding: 55px 20px; text-align: center; color: #777; }

    @media (max-width: 1100px) { .seller-layout { grid-template-columns: 1fr; } .seller-sidebar { order: 2; } .seller-main { order: 1; } }
    @media (max-width: 900px)  { .seller-list-header { display: block; } .seller-list-header .add-btn { margin-top: 14px; } .filter-form { grid-template-columns: 1fr; } .table-scroll { overflow-x: auto; } table { min-width: 860px; } }
    @media (max-width: 700px)  { .seller-list-page { padding: 28px 12px 56px; } }
</style>

<section class="seller-list-page">
    <div class="seller-list-wrap">
        <div class="seller-layout">

            <aside class="seller-sidebar">
                <h2>Seller Portal</h2>
                <p>Welcome back,<br><strong><?php echo e($seller['full_name']); ?></strong></p>
                <nav class="seller-menu">
                    <a href="dashboard.php"><span class="menu-icon">▦</span> Dashboard</a>
                    <a href="my-plan.php"><span class="menu-icon">📦</span> My Plan</a>
                    <a href="payment.php"><span class="menu-icon">□</span> Payment Verification</a>
                    <a href="buggy-list.php" class="active"><span class="menu-icon">☰</span> My Buggy Listings</a>
                    <a href="<?php echo $canAddMore ? 'buggy-form.php' : '#'; ?>"><span class="menu-icon">+</span> Add Listing</a>
                    <a href="logout.php"><span class="menu-icon">⏻</span> Logout</a>
                </nav>
            </aside>

            <main class="seller-main">
                <div class="page-title">
                    <h1>My Buggy Listings</h1>
                </div>

                <div class="breadcrumb">
                    <a href="dashboard.php">Seller Dashboard</a> &gt;
                    <span>My Buggy Listings</span>
                </div>

                <?php if ($message !== ''): ?>
                    <div class="message-success"><?php echo e($message); ?></div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="message-error"><?php echo e($error); ?></div>
                <?php endif; ?>

                <div class="seller-rule-box">
                    <strong>Seller listing rule:</strong>
                    You can upload maximum <strong>10 buggy listings</strong>.
                    Your listings go live immediately. Admin may hide or remove listings that violate our policies.
                    Current listing count: <strong><?php echo (int)$listingCount; ?> / <?php echo (int)$sellerListingLimit; ?></strong><?php if (!empty($seller['pkg_name'])): ?> · <em><?php echo htmlspecialchars($seller['pkg_name']); ?> package</em><?php endif; ?>.
                </div>

                <div class="seller-list-header">
                    <div>
                        <h2>Your Listings</h2>
                        <p>Manage your buggy listings. All listings are live immediately after upload.</p>
                    </div>
                    <a href="<?php echo $canAddMore ? 'buggy-form.php' : '#'; ?>" class="add-btn <?php echo $canAddMore ? '' : 'disabled'; ?>">
                        + Add Listing
                    </a>
                </div>

                <div class="filter-card">
                    <form method="GET" class="filter-form">
                        <input type="text" name="keyword" placeholder="Search brand, model, name..." value="<?php echo e($keyword); ?>">
                        <select name="status">
                            <option value="all"      <?php echo $status === 'all'      ? 'selected' : ''; ?>>All Status</option>
                            <option value="active"   <?php echo $status === 'active'   ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="sold"     <?php echo $status === 'sold'     ? 'selected' : ''; ?>>Sold</option>
                        </select>
                        <button type="submit">Search</button>
                    </form>
                </div>

                <div class="table-card">
                    <div class="table-top">
                        <strong>Listings</strong>
                        <span><?php echo count($listings); ?> item(s) found</span>
                    </div>

                    <?php if (count($listings) > 0): ?>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th class="product-col">Product</th>
                                        <th>Serial #</th>
                                        <th class="brand-col">Brand</th>
                                        <th class="seats-col">Seats</th>
                                        <th class="price-col">Price</th>
                                        <th class="status-col">Status</th>
                                        <th class="action-col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($listings as $listing): ?>
                                        <?php
                                            $title = !empty($listing['name'])
                                                ? $listing['name']
                                                : trim(($listing['brand'] ?? '') . ' ' . ($listing['model'] ?? ''));
                                            if ($title === '') $title = 'Buggy Listing';

                                            $statusValue      = strtolower((string)$listing['status']);
                                            $statusLabel      = ucwords(str_replace('_', ' ', $statusValue));
                                            $conditionLabel   = ucwords((string)($listing['buggy_condition'] ?? 'used'));

                                            $statusBadgeClass = 'badge-pending';
                                            if ($statusValue === 'active')   $statusBadgeClass = 'badge-active';
                                            elseif ($statusValue === 'inactive') $statusBadgeClass = 'badge-inactive';
                                            elseif ($statusValue === 'sold')     $statusBadgeClass = 'badge-sold';
                                            elseif ($statusValue === 'rejected') $statusBadgeClass = 'badge-rejected';
                                        ?>
                                        <tr>
                                            <td class="product-col">
                                                <div class="product-cell">
                                                    <img
                                                        src="<?php echo e(sellerProductImagePath($listing['image_url'])); ?>"
                                                        alt="<?php echo e($title); ?>"
                                                        class="product-img"
                                                        onerror="this.src='../images/no-image.png';"
                                                    >
                                                    <div>
                                                        <div class="product-name"><?php echo e($title); ?></div>
                                                        <div class="product-meta">
                                                            <?php echo e($listing['model']); ?>
                                                            <?php if (!empty($listing['short_info'])): ?>
                                                                <br><?php echo e($listing['short_info']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <?php if (!empty($listing['serial_number'])): ?>
                                                    <span style="font-family:monospace;font-weight:700;color:#111;background:#f3f4f6;padding:4px 8px;border-radius:6px;font-size:13px;">
                                                        <?php echo e($listing['serial_number']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:#999;font-size:13px;">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="brand-col">
                                                <div class="brand-name"><?php echo e($listing['brand']); ?></div>
                                                <div class="brand-meta"><?php echo e($conditionLabel); ?> &mdash; For Sale</div>
                                            </td>

                                            <td class="seats-col"><?php echo e($listing['seats']); ?></td>

                                            <td class="price-col">
                                                <span class="price">
                                                    RM <?php echo number_format((float)$listing['selling_price'], 2); ?>
                                                </span>
                                            </td>

                                            <td class="status-col">
                                                <span class="badge <?php echo e($statusBadgeClass); ?>">
                                                    <?php echo e($statusLabel); ?>
                                                </span>
                                            </td>

                                            <td class="action-col">
                                                <div class="action-group">
                                                    <a href="buggy-form.php?id=<?php echo (int)$listing['id']; ?>" class="btn-small btn-edit">Edit</a>
                                                    <?php if ($statusValue === 'active'): ?>
                                                        <a href="../buggy-detail.php?id=<?php echo (int)$listing['id']; ?>" class="btn-small btn-view" target="_blank">View</a>
                                                    <?php else: ?>
                                                        <a href="buggy-form.php?id=<?php echo (int)$listing['id']; ?>" class="btn-small btn-view">View</a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty">No buggy listings found.</div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</section>

<?php include '../footer.php'; ?>