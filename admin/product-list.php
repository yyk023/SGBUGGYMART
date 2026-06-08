<?php
require_once '../includes/db.php';
require_once '../includes/csrf.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

/* Reject POST requests with missing/invalid CSRF token */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfVerify()) {
    http_response_code(403);
    exit('Invalid CSRF token. Please reload the page and try again.');
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function productImagePath($imageUrl)
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

if (isset($_GET['updated']) && $_GET['updated'] === '1') $message = 'Product updated successfully.';
if (isset($_GET['added'])   && $_GET['added']   === '1') $message = 'Product added successfully.';
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') $message = 'Product deleted successfully.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id     = (int)$_POST['id'];
    $action = $_POST['action'];

    if ($id > 0 && in_array($action, ['active', 'inactive'], true)) {
        try {
            $stmt = $pdo->prepare("UPDATE buggies SET status = ? WHERE id = ?");
            $stmt->execute([$action, $id]);
            header('Location: product-list.php?updated=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to update product status: ' . $e->getMessage();
        }
    }

    if ($id > 0 && $action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM buggy_images WHERE buggy_id = ?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM buggies WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: product-list.php?deleted=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to delete product: ' . $e->getMessage();
        }
    }
}

$keyword    = trim($_GET['keyword']    ?? '');
$status     = $_GET['status']          ?? 'all';
$ownerFilter = $_GET['owner_type']     ?? 'all';
$conditionFilter = $_GET['buggy_condition']      ?? '';

$sql    = "
    SELECT
        id, brand, model, name, seats, listing_type,
        buggy_condition, selling_price, image_url,
        tag, brand_tag, status, owner_type, created_at,
        sort_new, sort_used, serial_number
    FROM buggies
    WHERE 1 = 1
";
$params = [];

if ($keyword !== '') {
    $sql .= " AND (brand LIKE ? OR model LIKE ? OR name LIKE ? OR tag LIKE ? OR brand_tag LIKE ? OR serial_number LIKE ?)";
    $like     = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $status;
}

if ($ownerFilter !== 'all') {
    $sql .= " AND owner_type = ?";
    $params[] = $ownerFilter;
}

if ($conditionFilter !== '' && in_array($conditionFilter, ['new', 'used'], true)) {
    $sql .= " AND buggy_condition = ?";
    $params[] = $conditionFilter;
}

$sql .= " AND (listing_type = 'sale' OR listing_type = '' OR listing_type IS NULL)";
$sql .= " ORDER BY created_at DESC, id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products = [];
    $error = 'Failed to load products: ' . $e->getMessage();
}

/* Count how many advanced filters are currently active (for the badge) */
$activeFilterCount = 0;
if ($status !== 'all')        $activeFilterCount++;
if ($conditionFilter !== '')  $activeFilterCount++;
if ($ownerFilter !== 'all')   $activeFilterCount++;

include 'header.php';
?>

<style>
    .product-list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 22px;
    }

    .product-list-header h1 { margin: 0; font-size: 30px; }
    .product-list-header p  { margin: 6px 0 0; color: #777; font-size: 15px; }

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

    .filter-form > input[type="text"],
    .filter-form > .keyword-wrap {
        flex: 1;
        min-width: 0;
    }

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

    .filter-form input {
        height: 52px;
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 0 16px;
        font-size: 15px;
        background: #fff;
    }

    .filter-form > button { height: 52px; padding-left: 22px; padding-right: 22px; }

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

    .table-top strong { font-size: 20px; }
    .table-top span   { color: #777; font-size: 14px; }

    .table-scroll { width: 100%; overflow-x: auto; }

    table { width: 100%; min-width: 1080px; border-collapse: collapse; }

    th, td {
        padding: 22px 20px;
        text-align: left;
        border-bottom: 1px solid #e5e5e5;
        vertical-align: middle;
        font-size: 14px;
    }

    th { background: #fafafa; color: #444; font-size: 13px; text-transform: uppercase; letter-spacing: 0.3px; font-weight: 700; }

    tbody tr:hover { background: #fff7f8; }
    tbody tr.seller-row { background: #fffbf0; }
    tbody tr.seller-row:hover { background: #fff5e0; }

    .product-cell { display: flex; align-items: center; gap: 16px; min-width: 320px; }

    .product-img { width: 90px; height: 68px; border-radius: 10px; object-fit: cover; border: 1px solid #ddd; background: #f1f1f1; flex-shrink: 0; }

    .product-name { font-weight: 700; font-size: 16px; margin-bottom: 6px; color: #111; }
    .product-meta { color: #777; font-size: 14px; line-height: 1.4; }
    .brand-name   { font-weight: 700; color: #111; margin-bottom: 4px; font-size: 15px; }

    .badge {
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 999px; padding: 7px 12px;
        font-size: 12px; font-weight: 700; text-transform: capitalize; white-space: nowrap;
    }

    .badge-sale     { background: #dbeafe; color: #1d4ed8; }
    .badge-active   { background: #dcfce7; color: #166534; }
    .badge-inactive { background: #fee2e2; color: #991b1b; }
    .badge-pending  { background: #fef3c7; color: #92400e; }
    .badge-sold     { background: #e5e7eb; color: #374151; }
    .badge-seller   { background: #fef3c7; color: #92400e; font-size: 11px; padding: 4px 8px; margin-top: 5px; }

    .price-list { line-height: 1.8; color: #111; min-width: 150px; }
    .sale-price { color: #ef3f4d; font-weight: 900; font-size: 15px; }
    .price-list small { color: #777; }

    .action-group {
        display: flex !important;
        flex-direction: column !important;
        gap: 6px !important;
        align-items: flex-start !important;
        width: 80px !important;
        min-width: 80px !important;
    }

    .btn-small {
        display: flex !important;
        align-items: center;
        justify-content: center;
        width: 80px !important;
        height: 34px !important;
        border: none;
        outline: none;
        border-radius: 7px;
        padding: 0 !important;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: 0.2s ease;
        white-space: nowrap;
    }

    .btn-edit   { background: #2563eb; color: #fff; }
    .btn-edit:hover { background: #1d4ed8; }

    .btn-view   { background: #fff; color: #111; border: 1px solid #ddd; }
    .btn-view:hover { border-color: #ef3f4d; color: #ef3f4d; }

    .btn-hide   { background: #fee2e2; color: #991b1b; }
    .btn-hide:hover { background: #fecaca; }

    .btn-show   { background: #dcfce7; color: #166534; }
    .btn-show:hover { background: #bbf7d0; }

    .btn-delete { background: #ef3f4d; color: #ffffff; }
    .btn-delete:hover { background: #d92e3d; }

    .inline-form { display: block !important; margin: 0 !important; width: 80px !important; }
    .inline-form button { width: 80px !important; }

    .empty { padding: 55px 20px; text-align: center; color: #777; }

    .message-success { background: #e8fff0; color: #167a3c; border: 1px solid #b8e8c8; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
    .message-error   { background: #fff0f2; color: #ef3f4d; border: 1px solid #ffc4cc; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }

    @media (max-width: 900px) {
        .product-list-header { display: block; }
        .product-list-header .btn { margin-top: 14px; }
        .filter-form { flex-wrap: wrap; }
        .filter-form > input[type="text"] { flex: 1 1 100%; }
        .admin-filter-modal { max-width: 100%; }
    }
</style>

<div class="product-list-header">
    <div>
        <h1>Product List</h1>
        <p>Manage buggy information, selling price, image, brand, status and details.</p>
    </div>
    <a href="product-form.php" class="btn">+ Add Product</a>
</div>

<?php if ($message !== ''): ?>
    <div class="message-success"><?php echo e($message); ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="message-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="filter-card">
    <form method="GET" class="filter-form" id="adminFilterForm">
        <div class="keyword-wrap">
            <input type="text" name="keyword" placeholder="Search brand, model, name, tag, serial #..." value="<?php echo e($keyword); ?>">
            <button type="button" class="keyword-clear" aria-label="Clear">&times;</button>
        </div>

        <button type="button" class="btn-filter" id="openAdminFilter">
            &#9881; Apply Filter
            <?php if ($activeFilterCount > 0): ?>
                <span class="filter-count"><?php echo (int)$activeFilterCount; ?></span>
            <?php endif; ?>
        </button>

        <a href="product-list.php" class="btn">Clear</a>

        <!-- ===================== ADMIN FILTER MODAL ===================== -->
        <div class="admin-filter-overlay" id="adminFilterOverlay">
            <div class="admin-filter-modal">
                <div class="afm-header">
                    <h2>Filter Products</h2>
                    <button type="button" class="afm-close" id="closeAdminFilter" aria-label="Close">&times;</button>
                </div>

                <div class="afm-body">
                    <div class="afm-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="all"      <?php echo $status === 'all'      ? 'selected' : ''; ?>>All Status</option>
                            <option value="active"   <?php echo $status === 'active'   ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="pending"  <?php echo $status === 'pending'  ? 'selected' : ''; ?>>Pending</option>
                            <option value="sold"     <?php echo $status === 'sold'     ? 'selected' : ''; ?>>Sold</option>
                        </select>
                    </div>

                    <div class="afm-group">
                        <label>Condition</label>
                        <select name="buggy_condition">
                            <option value=""     <?php echo $conditionFilter === ''     ? 'selected' : ''; ?>>All Conditions</option>
                            <option value="new"  <?php echo $conditionFilter === 'new'  ? 'selected' : ''; ?>>New Buggy</option>
                            <option value="used" <?php echo $conditionFilter === 'used' ? 'selected' : ''; ?>>Used Buggy</option>
                        </select>
                    </div>

                    <div class="afm-group">
                        <label>Listing Type</label>
                        <select name="owner_type">
                            <option value="all"    <?php echo $ownerFilter === 'all'    ? 'selected' : ''; ?>>All Listings</option>
                            <option value="admin"  <?php echo $ownerFilter === 'admin'  ? 'selected' : ''; ?>>Admin Only</option>
                            <option value="seller" <?php echo $ownerFilter === 'seller' ? 'selected' : ''; ?>>Seller Only</option>
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
        <strong>Products</strong>
        <span><?php echo count($products); ?> item(s) found</span>
    </div>

    <?php if (count($products) > 0): ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Serial #</th>
                        <th>Brand</th>
                        <th>Condition</th>
                        <th>Seats</th>
                        <th>Selling Price</th>
                        <th>Status</th>
                        <th width="100">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php
                            $statusValue       = strtolower((string)$product['status']);
                            $statusBadgeClass  = 'badge-inactive';
                            $isSellerListing   = ($product['owner_type'] ?? '') === 'seller';

                            if ($statusValue === 'active')  $statusBadgeClass = 'badge-active';
                            elseif ($statusValue === 'pending')  $statusBadgeClass = 'badge-pending';
                            elseif ($statusValue === 'sold')     $statusBadgeClass = 'badge-sold';
                        ?>
                        <tr class="<?php echo $isSellerListing ? 'seller-row' : ''; ?>">
                            <td>
                                <div class="product-cell">
                                    <img
                                        src="<?php echo e(productImagePath($product['image_url'])); ?>"
                                        alt="<?php echo e($product['name']); ?>"
                                        class="product-img"
                                        onerror="this.src='../images/no-image.png';"
                                    >
                                    <div>
                                        <div class="product-name"><?php echo e($product['name']); ?></div>
                                        <div class="product-meta">
                                            <?php echo e($product['model']); ?>
                                            <?php if (!empty($product['tag'])): ?>
                                                &mdash; <?php echo e($product['tag']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($isSellerListing): ?>
                                            <span class="badge badge-seller">Seller Upload</span>
                                        <?php endif; ?>
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
                                <div class="brand-name"><?php echo e($product['brand']); ?></div>
                            </td>

                           <td><?php echo strtoupper(e($product['buggy_condition'])); ?></td>

                            <td><?php echo e($product['seats']); ?></td>

                            <td>
                                <div class="price-list">
                                    <?php if (!empty($product['selling_price']) && (float)$product['selling_price'] > 0): ?>
                                        <div class="sale-price">$ <?php echo number_format((float)$product['selling_price'], 2); ?></div>
                                    <?php else: ?>
                                        <span class="product-meta">No price set</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <span class="badge <?php echo e($statusBadgeClass); ?>">
                                    <?php echo e($product['status']); ?>
                                </span>
                            </td>

                            <td>
                                <div class="action-group">
                                    <a href="product-form.php?id=<?php echo (int)$product['id']; ?>" class="btn-small btn-edit">Edit</a>

                                    <a href="product-view.php?id=<?php echo (int)$product['id']; ?>" class="btn-small btn-view">View</a>

                                    <?php if ($statusValue === 'active'): ?>
                                        <form method="POST" class="inline-form">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                                            <input type="hidden" name="action" value="inactive">
                                            <button type="submit" class="btn-small btn-hide">Hide</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="inline-form">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                                            <input type="hidden" name="action" value="active">
                                            <button type="submit" class="btn-small btn-show">Show</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete this buggy product permanently? This cannot be undone.');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
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
    <?php else: ?>
        <div class="empty">No products found.</div>
    <?php endif; ?>
</div>

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

    // Clean success/deleted/added params from URL so refreshing doesn't re-show the message
    (function () {
        const url = new URL(window.location.href);
        if (url.searchParams.has('updated') || url.searchParams.has('deleted') || url.searchParams.has('added')) {
            url.searchParams.delete('updated');
            url.searchParams.delete('deleted');
            url.searchParams.delete('added');
            window.history.replaceState({}, '', url.toString());
        }
    })();

    // Auto-dismiss success message after 3 seconds
    const successMsg = document.querySelector('.message-success');
    if (successMsg) {
        setTimeout(function () {
            successMsg.style.transition = '0.4s ease';
            successMsg.style.opacity   = '0';
            successMsg.style.maxHeight = '0';
            successMsg.style.padding   = '0';
            setTimeout(function () { successMsg.remove(); }, 400);
        }, 3000);
    }

    // ===================== LIVE KEYWORD FILTER =====================
    (function () {
        const keywordInput = document.querySelector('input[name="keyword"]');
        const clearBtn     = document.querySelector('.keyword-clear');
        const tbodyRows    = document.querySelectorAll('.table-card table tbody tr');
        const counter      = document.querySelector('.table-top span');
        const emptyBox     = document.querySelector('.table-card .empty');
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
            if (emptyBox) {
                emptyBox.style.display = (shown === 0 && q !== '') ? '' : 'none';
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
