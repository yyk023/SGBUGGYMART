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

function adImagePath($imageUrl)
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

if (isset($_GET['added'])   && $_GET['added']   === '1') $message = 'Ad added successfully.';
if (isset($_GET['updated']) && $_GET['updated'] === '1') $message = 'Ad updated successfully.';
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') $message = 'Ad deleted successfully.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id     = (int)$_POST['id'];
    $action = $_POST['action'];

    if ($id > 0 && in_array($action, ['active', 'inactive'], true)) {
        try {
            $stmt = $pdo->prepare("UPDATE banners SET status = ? WHERE id = ? AND type = 'ad'");
            $stmt->execute([$action, $id]);
            header('Location: ad-list.php?updated=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to update status: ' . $e->getMessage();
        }
    }

    if ($id > 0 && $action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM banners WHERE id = ? AND type = 'ad'");
            $stmt->execute([$id]);
            header('Location: ad-list.php?deleted=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to delete ad: ' . $e->getMessage();
        }
    }
}

$locationFilter = $_GET['location'] ?? 'all';

$sql    = "SELECT * FROM banners WHERE type = 'ad'";
$params = [];

if ($locationFilter !== 'all') {
    $sql .= " AND location = ?";
    $params[] = $locationFilter;
}

$sql .= " ORDER BY location ASC, sort_order ASC, id ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ads = [];
    $error = 'Failed to load ads: ' . $e->getMessage();
}

$locationLabels = [
    'side_brands'      => 'Side — Buggy Brands',
    'side_new_buggy'   => 'Side — New Buggy',
    'side_used_buggy'  => 'Side — Used Buggy',
    'side_allbuggy'    => 'Side — All Buggy',
    'side_accessories' => 'Side — Accessories',
];

// Count ads per location (used + max limit)
$locationCounts = [];
try {
    $countStmt = $pdo->query("SELECT location, COUNT(*) AS total FROM banners WHERE type = 'ad' GROUP BY location");
    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $locationCounts[$row['location']] = (int)$row['total'];
    }
} catch (PDOException $e) {
    $locationCounts = [];
}
$MAX_ADS_PER_LOCATION = 5;

include 'header.php';
?>

<style>
    .ad-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
    .ad-header h1 { margin: 0; font-size: 30px; }
    .ad-header p  { margin: 6px 0 0; color: #777; font-size: 15px; }

    .filter-card { background: #fff; border-radius: 16px; padding: 20px 22px; box-shadow: 0 8px 22px rgba(0,0,0,0.04); margin-bottom: 28px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .location-tab { border: 1px solid #ddd; border-radius: 999px; padding: 8px 18px; font-size: 14px; font-weight: 700; text-decoration: none; color: #555; transition: 0.2s ease; }
    .location-tab:hover { border-color: #ef3f4d; color: #ef3f4d; }
    .location-tab.active { background: #ef3f4d; border-color: #ef3f4d; color: #fff; }

    .location-group { margin-bottom: 32px; }
    .location-title { font-size: 20px; font-weight: 800; color: #222; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #ef3f4d; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }

    .usage-badge { display: inline-block; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; margin-left: 10px; vertical-align: middle; }
    .usage-badge.usage-ok   { background: #dcfce7; color: #166534; }
    .usage-badge.usage-warn { background: #fef3c7; color: #92400e; }
    .usage-badge.usage-full { background: #fee2e2; color: #991b1b; }

    .ad-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
    .ad-card { background: #fff; border: 1px solid #eee; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.06); }
    .ad-thumb { width: 100%; height: 180px; object-fit: cover; background: #f3f3f3; display: block; }
    .ad-body { padding: 14px; }
    .ad-location-badge { display: inline-block; background: #fef3c7; color: #92400e; border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 700; margin-bottom: 10px; }
    .ad-title-text { font-weight: 700; font-size: 15px; color: #222; margin-bottom: 5px; }
    .ad-link-text { color: #0066cc; font-size: 12px; word-break: break-all; margin-bottom: 10px; }
    .ad-order { font-size: 13px; color: #999; margin-bottom: 12px; }

    .ad-actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; justify-content: space-between; border-top: 1px solid #eee; padding-top: 12px; }
    .ad-btn-group { display: flex; gap: 6px; }
    .a-btn { border: 0; border-radius: 7px; font-size: 12px; font-weight: 700; cursor: pointer; padding: 6px 12px; transition: 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; }
    .a-btn-edit   { background: #eef6ff; color: #0066cc; }
    .a-btn-edit:hover { background: #0066cc; color: #fff; }
    .a-btn-hide   { background: #f5f5f5; color: #555; }
    .a-btn-show   { background: #e8fff0; color: #167a3c; }
    .a-btn-delete { background: #fff0f2; color: #ef3f4d; }
    .a-btn-delete:hover { background: #ef3f4d; color: #fff; }

    .status-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 5px 10px; font-size: 12px; font-weight: 700; }
    .status-active   { background: #e8fff0; color: #167a3c; }
    .status-inactive { background: #f5f5f5; color: #777; }

    .empty-box { border: 1px dashed #ddd; border-radius: 14px; padding: 38px; text-align: center; color: #777; background: #fafafa; }

    .message-success { background: #e8fff0; color: #167a3c; border: 1px solid #b8e8c8; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
    .message-error   { background: #fff0f2; color: #ef3f4d; border: 1px solid #ffc4cc; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
</style>

<div class="ad-header">
    <div>
        <h1>Side Ad Management</h1>
        <p>Manage promotional side ads shown beside home page sections.</p>
    </div>
    <a href="ad-form.php" class="btn">+ Add Ad</a>
</div>

<?php if ($message): ?>
    <div class="message-success"><?php echo e($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="message-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="filter-card">
    <a href="ad-list.php" class="location-tab <?php echo $locationFilter === 'all' ? 'active' : ''; ?>">All</a>
    <?php foreach ($locationLabels as $locKey => $locLabel): ?>
        <a href="ad-list.php?location=<?php echo e($locKey); ?>" class="location-tab <?php echo $locationFilter === $locKey ? 'active' : ''; ?>">
            <?php echo e($locLabel); ?>
        </a>
    <?php endforeach; ?>
</div>

<?php
$grouped = [];
foreach ($ads as $ad) {
    $grouped[$ad['location']][] = $ad;
}
?>

<?php if (count($grouped) === 0): ?>
    <div class="empty-box">No ads found. <a href="ad-form.php" style="color:#ef3f4d;">Add the first ad</a>.</div>
<?php endif; ?>

<?php foreach ($grouped as $location => $locationAds):
    $usedCount = $locationCounts[$location] ?? count($locationAds);
    $isFull    = $usedCount >= $MAX_ADS_PER_LOCATION;
    $usageClass = $isFull ? 'usage-full' : ($usedCount >= $MAX_ADS_PER_LOCATION - 1 ? 'usage-warn' : 'usage-ok');
?>
    <div class="location-group">
        <div class="location-title">
            <span>
                <?php echo e($locationLabels[$location] ?? ucwords(str_replace('_', ' ', $location))); ?>
                <span class="usage-badge <?php echo $usageClass; ?>">
                    <?php echo (int)$usedCount; ?> / <?php echo (int)$MAX_ADS_PER_LOCATION; ?> used
                </span>
            </span>
            <?php if ($isFull): ?>
                <span class="btn" style="font-size:13px; padding: 8px 16px; background:#9ca3af; cursor:not-allowed;" title="Location is full">+ Limit Reached</span>
            <?php else: ?>
                <a href="ad-form.php?location=<?php echo e($location); ?>" class="btn" style="font-size:13px; padding: 8px 16px;">+ Add to This Location</a>
            <?php endif; ?>
        </div>

        <div class="ad-grid">
            <?php foreach ($locationAds as $ad): ?>
                <div class="ad-card">
                    <img
                        src="<?php echo e(adImagePath($ad['image_url'])); ?>"
                        alt="<?php echo e($ad['title'] ?? 'Ad'); ?>"
                        class="ad-thumb"
                        onerror="this.src='../images/no-image.png';"
                    >
                    <div class="ad-body">
                        <div class="ad-location-badge"><?php echo e($locationLabels[$ad['location']] ?? $ad['location']); ?></div>
                        <?php if (!empty($ad['title'])): ?>
                            <div class="ad-title-text"><?php echo e($ad['title']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($ad['link_url'])): ?>
                            <div class="ad-link-text">🔗 <?php echo e($ad['link_url']); ?></div>
                        <?php endif; ?>
                        <div class="ad-order">Sort Order: <?php echo (int)$ad['sort_order']; ?></div>

                        <div class="ad-actions">
                            <span class="status-badge status-<?php echo e($ad['status']); ?>">
                                <?php echo ucfirst(e($ad['status'])); ?>
                            </span>
                            <div class="ad-btn-group">
                                <a href="ad-form.php?id=<?php echo (int)$ad['id']; ?>" class="a-btn a-btn-edit">Edit</a>
                                <?php if ($ad['status'] === 'active'): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo (int)$ad['id']; ?>">
                                        <input type="hidden" name="action" value="inactive">
                                        <button type="submit" class="a-btn a-btn-hide">Hide</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo (int)$ad['id']; ?>">
                                        <input type="hidden" name="action" value="active">
                                        <button type="submit" class="a-btn a-btn-show">Show</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo (int)$ad['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="a-btn a-btn-delete" onclick="return confirm('Delete this ad?');">Del</button>
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
