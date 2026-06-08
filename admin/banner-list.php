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

function bannerImagePath($imageUrl)
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

if (isset($_GET['added'])   && $_GET['added']   === '1') $message = 'Banner added successfully.';
if (isset($_GET['updated']) && $_GET['updated'] === '1') $message = 'Banner updated successfully.';
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') $message = 'Banner deleted successfully.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id     = (int)$_POST['id'];
    $action = $_POST['action'];

    if ($id > 0 && in_array($action, ['active', 'inactive'], true)) {
        try {
            $stmt = $pdo->prepare("UPDATE banners SET status = ? WHERE id = ?");
            $stmt->execute([$action, $id]);
            header('Location: banner-list.php?updated=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to update status: ' . $e->getMessage();
        }
    }

    if ($id > 0 && $action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM banners WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: banner-list.php?deleted=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to delete banner: ' . $e->getMessage();
        }
    }

    if ($id > 0 && $action === 'move_up') {
        try {
            $stmt = $pdo->prepare("SELECT sort_order, location FROM banners WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            if ($current) {
                $stmt = $pdo->prepare("
                    SELECT id, sort_order FROM banners
                    WHERE location = ? AND sort_order < ? AND status = 'active'
                    ORDER BY sort_order DESC LIMIT 1
                ");
                $stmt->execute([$current['location'], $current['sort_order']]);
                $prev = $stmt->fetch();
                if ($prev) {
                    $stmt = $pdo->prepare("UPDATE banners SET sort_order = ? WHERE id = ?");
                    $stmt->execute([$current['sort_order'], $prev['id']]);
                    $stmt->execute([$prev['sort_order'], $id]);
                }
            }
            header('Location: banner-list.php?updated=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to reorder: ' . $e->getMessage();
        }
    }

    if ($id > 0 && $action === 'move_down') {
        try {
            $stmt = $pdo->prepare("SELECT sort_order, location FROM banners WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            if ($current) {
                $stmt = $pdo->prepare("
                    SELECT id, sort_order FROM banners
                    WHERE location = ? AND sort_order > ? AND status = 'active'
                    ORDER BY sort_order ASC LIMIT 1
                ");
                $stmt->execute([$current['location'], $current['sort_order']]);
                $next = $stmt->fetch();
                if ($next) {
                    $stmt = $pdo->prepare("UPDATE banners SET sort_order = ? WHERE id = ?");
                    $stmt->execute([$current['sort_order'], $next['id']]);
                    $stmt->execute([$next['sort_order'], $id]);
                }
            }
            header('Location: banner-list.php?updated=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to reorder: ' . $e->getMessage();
        }
    }
}

$locationFilter = $_GET['location'] ?? 'all';

$sql    = "SELECT * FROM banners WHERE 1=1";
$params = [];

if ($locationFilter !== 'all') {
    $sql .= " AND location = ?";
    $params[] = $locationFilter;
}

$sql .= " ORDER BY location ASC, sort_order ASC, id ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $banners = [];
    $error   = 'Failed to load banners: ' . $e->getMessage();
}

$locationLabels = [
    'home'      => 'Home Banner',
    'sell_buggy'=> 'Sell Buggy Banner',
];

include 'header.php';
?>

<style>
    .banner-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
    .banner-header h1 { margin: 0; font-size: 30px; }
    .banner-header p  { margin: 6px 0 0; color: #777; font-size: 15px; }

    .filter-card { background: #fff; border-radius: 16px; padding: 20px 22px; box-shadow: 0 8px 22px rgba(0,0,0,0.04); margin-bottom: 28px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

    .location-tab { border: 1px solid #ddd; border-radius: 999px; padding: 8px 18px; font-size: 14px; font-weight: 700; text-decoration: none; color: #555; transition: 0.2s ease; }
    .location-tab:hover { border-color: #ef3f4d; color: #ef3f4d; }
    .location-tab.active { background: #ef3f4d; border-color: #ef3f4d; color: #fff; }

    .location-group { margin-bottom: 32px; }
    .location-title { font-size: 20px; font-weight: 800; color: #222; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #ef3f4d; display: flex; align-items: center; justify-content: space-between; }

    .banner-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }

    .banner-card { background: #fff; border: 1px solid #eee; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.06); }

    .banner-thumb { width: 100%; height: 160px; object-fit: cover; background: #f3f3f3; display: block; }

    .banner-body { padding: 14px; }
    .banner-title-text { font-weight: 700; font-size: 15px; color: #222; margin-bottom: 5px; }
    .banner-location-badge { display: inline-block; background: #eef6ff; color: #0066cc; border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 700; margin-bottom: 10px; }
    .banner-subtitle-text { color: #777; font-size: 13px; margin-bottom: 10px; }
    .banner-order { font-size: 13px; color: #999; margin-bottom: 12px; }

    .banner-actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; justify-content: space-between; border-top: 1px solid #eee; padding-top: 12px; }
    .banner-btn-group { display: flex; gap: 6px; }

    .b-btn { border: 0; border-radius: 7px; font-size: 12px; font-weight: 700; cursor: pointer; padding: 6px 12px; transition: 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; }
    .b-btn-edit   { background: #eef6ff; color: #0066cc; }
    .b-btn-edit:hover { background: #0066cc; color: #fff; }
    .b-btn-hide   { background: #f5f5f5; color: #555; }
    .b-btn-hide:hover { background: #555; color: #fff; }
    .b-btn-show   { background: #e8fff0; color: #167a3c; }
    .b-btn-show:hover { background: #167a3c; color: #fff; }
    .b-btn-delete { background: #fff0f2; color: #ef3f4d; }
    .b-btn-delete:hover { background: #ef3f4d; color: #fff; }
    .b-btn-order  { background: #f5f5f5; color: #444; padding: 6px 8px; }
    .b-btn-order:hover { background: #ddd; }

    .status-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 5px 10px; font-size: 12px; font-weight: 700; }
    .status-active   { background: #e8fff0; color: #167a3c; }
    .status-inactive { background: #f5f5f5; color: #777; }

    .empty-box { border: 1px dashed #ddd; border-radius: 14px; padding: 38px; text-align: center; color: #777; background: #fafafa; }

    .message-success { background: #e8fff0; color: #167a3c; border: 1px solid #b8e8c8; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
    .message-error   { background: #fff0f2; color: #ef3f4d; border: 1px solid #ffc4cc; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }

    @media (max-width: 900px) {
        .banner-grid { grid-template-columns: repeat(2, 1fr); }
        .banner-header { display: block; }
        .banner-header .btn { margin-top: 14px; }
    }

    @media (max-width: 560px) {
        .banner-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="banner-header">
    <div>
        <h1>Banner Management</h1>
        <p>Manage homepage and sell buggy page banners.</p>
    </div>
    <a href="banner-form.php" class="btn">+ Add Banner</a>
</div>

<?php if ($message): ?>
    <div class="message-success"><?php echo e($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="message-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="filter-card">
    <a href="banner-list.php" class="location-tab <?php echo $locationFilter === 'all' ? 'active' : ''; ?>">All</a>
    <a href="banner-list.php?location=home" class="location-tab <?php echo $locationFilter === 'home' ? 'active' : ''; ?>">Home Banner</a>
    <a href="banner-list.php?location=sell_buggy" class="location-tab <?php echo $locationFilter === 'sell_buggy' ? 'active' : ''; ?>">Sell Buggy Banner</a>
</div>

<?php
$grouped = [];
foreach ($banners as $banner) {
    $grouped[$banner['location']][] = $banner;
}
?>

<?php if (count($grouped) === 0): ?>
    <div class="empty-box">No banners found. <a href="banner-form.php" style="color:#ef3f4d;">Add the first banner</a>.</div>
<?php endif; ?>

<?php foreach ($grouped as $location => $locationBanners): ?>
    <div class="location-group">
        <div class="location-title">
            <span><?php echo e($locationLabels[$location] ?? ucwords(str_replace('_', ' ', $location))); ?></span>
            <a href="banner-form.php?location=<?php echo e($location); ?>" class="btn" style="font-size:13px; padding: 8px 16px;">+ Add to This Location</a>
        </div>

        <div class="banner-grid">
            <?php foreach ($locationBanners as $banner): ?>
                <div class="banner-card">
                    <img
                        src="<?php echo e(bannerImagePath($banner['image_url'])); ?>"
                        alt="<?php echo e($banner['title'] ?? 'Banner'); ?>"
                        class="banner-thumb"
                        onerror="this.src='../images/no-image.png';"
                    >
                    <div class="banner-body">
                        <div class="banner-location-badge"><?php echo e($locationLabels[$banner['location']] ?? $banner['location']); ?></div>
                        <?php if (!empty($banner['title'])): ?>
                            <div class="banner-title-text"><?php echo e($banner['title']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($banner['subtitle'])): ?>
                            <div class="banner-subtitle-text"><?php echo e($banner['subtitle']); ?></div>
                        <?php endif; ?>
                        <div class="banner-order">Sort Order: <?php echo (int)$banner['sort_order']; ?></div>

                        <div class="banner-actions">
                            <div style="display:flex; align-items:center; gap:6px;">
                                <span class="status-badge status-<?php echo e($banner['status']); ?>">
                                    <?php echo ucfirst(e($banner['status'])); ?>
                                </span>
                            </div>
                            <div class="banner-btn-group">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo (int)$banner['id']; ?>">
                                    <input type="hidden" name="action" value="move_up">
                                    <button type="submit" class="b-btn b-btn-order" title="Move Up">↑</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo (int)$banner['id']; ?>">
                                    <input type="hidden" name="action" value="move_down">
                                    <button type="submit" class="b-btn b-btn-order" title="Move Down">↓</button>
                                </form>
                                <a href="banner-form.php?id=<?php echo (int)$banner['id']; ?>" class="b-btn b-btn-edit">Edit</a>
                                <?php if ($banner['status'] === 'active'): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo (int)$banner['id']; ?>">
                                        <input type="hidden" name="action" value="inactive">
                                        <button type="submit" class="b-btn b-btn-hide">Hide</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo (int)$banner['id']; ?>">
                                        <input type="hidden" name="action" value="active">
                                        <button type="submit" class="b-btn b-btn-show">Show</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo (int)$banner['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="b-btn b-btn-delete" onclick="return confirm('Delete this banner?');">Del</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php include 'footer.php'; ?>