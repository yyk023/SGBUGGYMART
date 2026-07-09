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

function accImagePath($imageUrl)
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

if (isset($_GET['added'])   && $_GET['added']   === '1') $message = 'Accessory added successfully.';
if (isset($_GET['updated']) && $_GET['updated'] === '1') $message = 'Accessory updated successfully.';
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') $message = 'Accessory deleted successfully.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id     = (int)$_POST['id'];
    $action = $_POST['action'];

    if ($id > 0 && in_array($action, ['active', 'inactive'], true)) {
        try {
            $stmt = $pdo->prepare("UPDATE accessories SET status = ? WHERE id = ?");
            $stmt->execute([$action, $id]);
            header('Location: accessory-list.php?updated=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to update status: ' . $e->getMessage();
        }
    }

    if ($id > 0 && $action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM accessory_images WHERE accessory_id = ?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM accessories WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: accessory-list.php?deleted=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to delete accessory: ' . $e->getMessage();
        }
    }
}

$keyword = trim($_GET['keyword'] ?? '');
$status  = $_GET['status'] ?? 'all';
$categoryFilter = trim($_GET['category'] ?? '');

$accCategoryOptions = ['Batteries', 'Tyres', 'Mechanical Parts', 'Electrical Parts', 'Others'];

$sql    = "SELECT * FROM accessories WHERE 1=1";
$params = [];

if ($keyword !== '') {
    $sql .= " AND (brand LIKE ? OR model LIKE ? OR name LIKE ? OR tag LIKE ? OR serial_number LIKE ?)";
    $like = '%' . $keyword . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($status !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $status;
}

if ($categoryFilter !== '') {
    $sql .= " AND category = ?";
    $params[] = $categoryFilter;
}

/* Pagination */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$countSql = preg_replace('/^SELECT \*/', 'SELECT COUNT(*)', $sql, 1);
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

$sql .= " ORDER BY created_at DESC, id DESC LIMIT $perPage OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $accessories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $accessories = [];
    $error = 'Failed to load accessories: ' . $e->getMessage();
}

function pageUrl($pageNum) {
    $qs = array_filter($_GET, function ($v) { return $v !== '' && $v !== null; });
    $qs['page'] = $pageNum;
    return '?' . http_build_query($qs);
}

/* Count active advanced filters (for the badge) */
$activeFilterCount = 0;
if ($status !== 'all')      $activeFilterCount++;
if ($categoryFilter !== '') $activeFilterCount++;

include 'header.php';
?>

<style>
    .list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 22px;
    }

    .list-header h1 { margin: 0; font-size: 30px; }
    .list-header p  { margin: 6px 0 0; color: #777; font-size: 15px; }

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

    .filter-form > button { height: 52px; padding: 0 22px; }

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

    table { width: 100%; min-width: 1000px; border-collapse: collapse; }

    th, td {
        padding: 18px 20px;
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
    }

    tbody tr:hover { background: #fff7f8; }

    .acc-thumb {
        width: 60px;
        height: 50px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #eee;
        background: #f5f5f5;
        display: block;
    }

    .status-badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }

    .status-active   { background: #e8fff0; color: #167a3c; }
    .status-inactive { background: #f5f5f5; color: #777; }
    .status-sold     { background: #fff0f2; color: #ef3f4d; }
    .status-pending  { background: #fff8e1; color: #b07800; }

    .row-actions { display: flex; flex-direction: column; gap: 6px; align-items: stretch; }

    /* Fixed column widths so layout stays consistent regardless of name length */
    table { table-layout: fixed; }
    table th:nth-child(1), table td:nth-child(1) { width: 80px;  }   /* Image */
    table th:nth-child(2), table td:nth-child(2) { width: auto;  }   /* Name (flexible) */
    table th:nth-child(3), table td:nth-child(3) { width: 110px; }   /* Brand */
    table th:nth-child(4), table td:nth-child(4) { width: 140px; }   /* Model */
    table th:nth-child(5), table td:nth-child(5) { width: 130px; }   /* Serial No */
    table th:nth-child(6), table td:nth-child(6) { width: 110px; }   /* Lead Time */
    table th:nth-child(7), table td:nth-child(7) { width: 90px;  }   /* Price */
    table th:nth-child(8), table td:nth-child(8) { width: 110px; }   /* Status */
    table th:nth-child(9), table td:nth-child(9) { width: 130px; }   /* Actions */

    /* Wrap long content gracefully */
    table td { word-break: break-word; }

    /* Sticky Actions column on the right */
    table th:last-child,
    table td:last-child {
        position: sticky;
        right: 0;
        background: #ffffff;
        box-shadow: -6px 0 12px -8px rgba(0, 0, 0, 0.12);
        z-index: 2;
    }
    table th:last-child { background: #fafafa; }
    tbody tr:hover td:last-child { background: #fff7f8; }
    
    .row-btn {
            border: 0;
            padding: 7px 0;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            transition: 0.2s ease;
        }

    .btn-edit    { background: #eef6ff; color: #0066cc; }
    .btn-edit:hover { background: #0066cc; color: #fff; }

    .btn-hide    { background: #f5f5f5; color: #555; }
    .btn-hide:hover { background: #555; color: #fff; }

    .btn-show    { background: #e8fff0; color: #167a3c; }
    .btn-show:hover { background: #167a3c; color: #fff; }

    .btn-delete  { background: #fff0f2; color: #ef3f4d; }
    .btn-delete:hover { background: #ef3f4d; color: #fff; }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #777;
    }

    .empty-state p { margin: 10px 0 0; font-size: 15px; }

    .response-box {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 9999;
        min-width: 280px;
        max-width: 420px;
        padding: 16px 18px;
        border-radius: 12px;
        font-weight: bold;
        box-shadow: 0 12px 30px rgba(0,0,0,0.18);
        animation: slideDown 0.3s ease;
    }

    .response-success { background: #e8fff0; color: #167a3c; border: 1px solid #b8e8c8; }
    .response-error   { background: #fff0f2; color: #ef3f4d; border: 1px solid #ffc4cc; }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-12px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 700px) {
        .filter-form { flex-wrap: wrap; }
        .filter-form > input[type="text"] { flex: 1 1 100%; }
        .admin-filter-modal { max-width: 100%; }
        .list-header { display: block; }
        .list-header .btn { margin-top: 14px; }
    }
</style>

<?php if ($message): ?>
    <div class="response-box response-success" id="responseBox"><?php echo e($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="response-box response-error" id="responseBox"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="list-header">
    <div>
        <h1>Accessories</h1>
        <p>Manage all accessory listings.</p>
    </div>
    <a href="accessory-form.php" class="btn">+ Add Accessory</a>
</div>

<div class="filter-card">
    <form class="filter-form" method="get" action="accessory-list.php" id="adminFilterForm">
        <div class="keyword-wrap">
            <input
                type="text"
                name="keyword"
                placeholder="Search by brand, model, name, serial #..."
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

        <a href="accessory-list.php" class="btn">Clear</a>

        <!-- ===================== ADMIN FILTER MODAL ===================== -->
        <div class="admin-filter-overlay" id="adminFilterOverlay">
            <div class="admin-filter-modal">
                <div class="afm-header">
                    <h2>Filter Accessories</h2>
                    <button type="button" class="afm-close" id="closeAdminFilter" aria-label="Close">&times;</button>
                </div>

                <div class="afm-body">
                    <div class="afm-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="all"      <?php echo $status === 'all'      ? 'selected' : ''; ?>>All Status</option>
                            <option value="active"   <?php echo $status === 'active'   ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="sold"     <?php echo $status === 'sold'     ? 'selected' : ''; ?>>Sold</option>
                            <option value="pending"  <?php echo $status === 'pending'  ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>

                    <div class="afm-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($accCategoryOptions as $catOpt): ?>
                                <option value="<?php echo e($catOpt); ?>" <?php echo $categoryFilter === $catOpt ? 'selected' : ''; ?>>
                                    <?php echo e($catOpt); ?>
                                </option>
                            <?php endforeach; ?>
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

<script>
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
</script>

<div class="table-card">
    <div class="table-top">
        <strong>Accessory List</strong>
        <span>
            <?php echo (int)$totalCount; ?> record(s) found
            <?php if ($totalPages > 1): ?>
                &middot; Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?>
            <?php endif; ?>
        </span>
    </div>

    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Serial No.</th>
                    <th>Lead Time</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($accessories) > 0): ?>
                    <?php foreach ($accessories as $item): ?>
                        <tr>
                            <td>
                                <img
                                    src="<?php echo e(accImagePath($item['image_url'] ?? '')); ?>"
                                    alt="<?php echo e($item['name'] ?? ''); ?>"
                                    class="acc-thumb"
                                    onerror="this.src='../images/no-image.png';"
                                >
                            </td>
                            <td>
                                <strong><?php echo e($item['name'] ?: ($item['brand'] . ' ' . $item['model'])); ?></strong>
                                <?php if (!empty($item['short_info'])): ?>
                                    <div style="color:#777;font-size:13px;margin-top:3px;"><?php echo e($item['short_info']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($item['brand'] ?? '-'); ?></td>
                            <td><?php echo e($item['model'] ?? '-'); ?></td>
                            <td><?php echo e($item['serial_number'] ?? '-'); ?></td>
                            <td><?php echo e($item['lead_time'] ?? '-'); ?></td>
                            <td>
                                <?php
                                $price = (float)($item['selling_price'] ?? 0);
                                echo $price > 0 ? '$' . number_format($price, 0) : '-';
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo e($item['status'] ?? 'inactive'); ?>">
                                    <?php echo ucfirst(e($item['status'] ?? 'inactive')); ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="accessory-form.php?id=<?php echo (int)$item['id']; ?>" class="row-btn btn-edit">Edit</a>

                                    <?php if (($item['status'] ?? '') === 'active'): ?>
                                        <form method="post" style="display:inline;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                            <input type="hidden" name="action" value="inactive">
                                            <button type="submit" class="row-btn btn-hide">Hide</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="display:inline;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                            <input type="hidden" name="action" value="active">
                                            <button type="submit" class="row-btn btn-show">Show</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" style="display:inline;">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button
                                            type="submit"
                                            class="row-btn btn-delete"
                                            onclick="return confirm('Delete this accessory? This cannot be undone.');"
                                        >Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <div style="font-size:48px;">🔧</div>
                                <p>No accessories found. <a href="accessory-form.php" style="color:#ef3f4d;">Add the first one</a>.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
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
    const responseBox = document.getElementById('responseBox');
    if (responseBox) {
        setTimeout(function () {
            responseBox.style.opacity = '0';
            responseBox.style.transform = 'translateY(-12px)';
            responseBox.style.transition = '0.3s ease';
            setTimeout(function () { responseBox.remove(); }, 300);
        }, 3000);
    }

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
                    : shown + ' record(s) match "' + keywordInput.value + '"';
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