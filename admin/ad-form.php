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

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$success = '';
$error   = '';

$locationOptions = [
    'side_brands'      => 'Side — Buggy Brands',
    'side_new_buggy'   => 'Side — New Buggy',
    'side_used_buggy'  => 'Side — Used Buggy',
    'side_allbuggy'    => 'Side — All Buggy',
    'side_accessories' => 'Side — Accessories',
];

$defaultLocation = $_GET['location'] ?? 'side_brands';

$formData = [
    'location'   => array_key_exists($defaultLocation, $locationOptions) ? $defaultLocation : 'side_brands',
    'title'      => '',
    'subtitle'   => '',
    'link_url'   => '',
    'sort_order' => 0,
    'status'     => 'active',
    'image_url'  => '',
];

if ($isEdit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM banners WHERE id = ? AND type = 'ad'");
        $stmt->execute([$id]);
        $ad = $stmt->fetch();
        if (!$ad) die('Ad not found.');
        foreach ($formData as $key => $val) {
            if (array_key_exists($key, $ad)) $formData[$key] = $ad[$key];
        }
    } catch (PDOException $e) {
        die('Failed to load ad: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ad'])) {
    $location   = trim($_POST['location']   ?? 'side_brands');
    $title      = trim($_POST['title']      ?? '');
    $subtitle   = trim($_POST['subtitle']   ?? '');
    $linkUrl    = trim($_POST['link_url']   ?? '');
    $sortOrder  = (int)($_POST['sort_order'] ?? 0);
    $status     = trim($_POST['status']     ?? 'active');

    if (!array_key_exists($location, $locationOptions)) $location = 'side_brands';
    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';

    $imageUrl = $formData['image_url'];

    if (isset($_FILES['ad_image']) && $_FILES['ad_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../images/ads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $originalName      = $_FILES['ad_image']['name'];
        $tmpName           = $_FILES['ad_image']['tmp_name'];
        $fileSize          = $_FILES['ad_image']['size'];
        $extension         = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $allowedMimeTypes  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!in_array($extension, $allowedExtensions, true)) {
            $error = 'Only JPG, PNG, WEBP, and GIF images are allowed.';
        } elseif (!in_array($mimeType, $allowedMimeTypes, true)) {
            $error = 'Uploaded file is not a valid image.';
        } elseif ($fileSize > 10 * 1024 * 1024) {
            $error = 'Image must be below 10MB.';
        } else {
            $safeName    = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
            $newFileName = 'ad-' . $location . '-' . time() . '-' . rand(1000, 9999) . '.' . $extension;
            $targetPath  = $uploadDir . $newFileName;

            if (move_uploaded_file($tmpName, $targetPath)) {
                $imageUrl = 'images/ads/' . $newFileName;
            } else {
                $error = 'Failed to upload image.';
            }
        }
    }

    if ($error === '') {
        if (!$isEdit && $imageUrl === '') {
            $error = 'Please upload an ad image.';
        }
    }

    // Enforce maximum of 5 ads per location (only when adding new)
    if ($error === '' && !$isEdit) {
        try {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM banners WHERE type = 'ad' AND location = ?");
            $countStmt->execute([$location]);
            $existingCount = (int)$countStmt->fetchColumn();
            if ($existingCount >= 5) {
                $error = 'Maximum of 5 ads allowed per location. Please delete or hide an existing ad first.';
            }
        } catch (PDOException $e) {
            $error = 'Failed to verify ad count: ' . $e->getMessage();
        }
    }

    if ($error === '') {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE banners SET location = :location, title = :title, subtitle = :subtitle,
                        link_url = :link_url, sort_order = :sort_order, status = :status,
                        image_url = :image_url, updated_at = NOW()
                    WHERE id = :id AND type = 'ad'
                ");
                $stmt->execute([
                    ':location'   => $location,
                    ':title'      => $title,
                    ':subtitle'   => $subtitle,
                    ':link_url'   => $linkUrl,
                    ':sort_order' => $sortOrder,
                    ':status'     => $status,
                    ':image_url'  => $imageUrl,
                    ':id'         => $id,
                ]);
                header('Location: ad-list.php?updated=1');
                exit;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO banners (type, location, title, subtitle, link_url, sort_order, status, image_url, created_at, updated_at)
                    VALUES ('ad', :location, :title, :subtitle, :link_url, :sort_order, :status, :image_url, NOW(), NOW())
                ");
                $stmt->execute([
                    ':location'   => $location,
                    ':title'      => $title,
                    ':subtitle'   => $subtitle,
                    ':link_url'   => $linkUrl,
                    ':sort_order' => $sortOrder,
                    ':status'     => $status,
                    ':image_url'  => $imageUrl,
                ]);
                header('Location: ad-list.php?added=1');
                exit;
            }

            $formData = ['location'=>$location,'title'=>$title,'subtitle'=>$subtitle,'link_url'=>$linkUrl,'sort_order'=>$sortOrder,'status'=>$status,'image_url'=>$imageUrl];
        } catch (PDOException $e) {
            $error = 'Failed to save ad: ' . $e->getMessage();
        }
    }
}

include 'header.php';
?>

<style>
    .af-header { margin-bottom: 22px; }
    .af-header h1 { margin: 0; font-size: 28px; }
    .af-header p  { margin: 6px 0 0; color: #777; font-size: 14px; }

    .af-card { background: #fff; border: 1px solid #eee; border-radius: 14px; padding: 28px; box-shadow: 0 4px 14px rgba(0,0,0,0.05); }

    .af-row { display: grid; grid-template-columns: 1fr; gap: 18px; margin-bottom: 18px; }
    .af-group label { display: block; font-weight: bold; font-size: 14px; margin-bottom: 7px; color: #333; }
    .af-group input, .af-group select, .af-group textarea {
        width: 100%; height: 44px; border: 1px solid #ddd; border-radius: 8px;
        padding: 0 12px; font-size: 14px; outline: none; background: #fff;
    }
    .af-group input:focus, .af-group select:focus { border-color: #ef3f4d; }
    .af-group .help { color: #777; font-size: 12px; margin-top: 5px; }

    .af-group input[type="file"] {
        height: auto;
        padding: 10px 12px;
    }

    .current-preview { margin-bottom: 16px; }
    .current-preview img { max-width: 280px; max-height: 180px; border: 1px solid #eee; border-radius: 8px; }

    .af-actions { margin-top: 28px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 10px; }

    .message-error { background: #fff0f2; color: #ef3f4d; border: 1px solid #ffc4cc; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
</style>

<div class="af-header">
    <h1><?php echo $isEdit ? 'Edit Ad' : 'Add New Ad'; ?></h1>
    <p>Side promotional ads shown beside home page sections.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="message-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="af-card">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="save_ad" value="1">

        <div class="af-row">
            <div class="af-group">
                <label>Location *</label>
                <select name="location" required>
                    <?php foreach ($locationOptions as $locKey => $locLabel): ?>
                        <option value="<?php echo e($locKey); ?>" <?php echo $formData['location'] === $locKey ? 'selected' : ''; ?>>
                            <?php echo e($locLabel); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help">Where the ad will be displayed on the home page.</div>
            </div>
        </div>

        <div class="af-row">
            <div class="af-group">
                <label>Title (optional)</label>
                <input type="text" name="title" placeholder="Internal name for admin reference" value="<?php echo e($formData['title']); ?>">
                <div class="help">For your reference only — not shown publicly.</div>
            </div>
        </div>

        <div class="af-row">
            <div class="af-group">
                <label>Link URL (optional)</label>
                <input type="url" name="link_url" placeholder="https://example.com" value="<?php echo e($formData['link_url']); ?>">
                <div class="help">When user clicks the ad, opens this URL in a new tab. Leave empty for non-clickable ads.</div>
            </div>
        </div>

        <?php if (!empty($formData['image_url'])): ?>
            <div class="current-preview">
                <strong>Current Image:</strong><br>
                <img src="../<?php echo e($formData['image_url']); ?>" alt="Current ad">
            </div>
        <?php endif; ?>

        <div class="af-row">
            <div class="af-group">
                <label><?php echo $isEdit ? 'Replace Image (optional)' : 'Ad Image *'; ?></label>
                <input type="file" name="ad_image" id="adImageInput" accept=".jpg,.jpeg,.png,.webp,.gif" <?php echo !$isEdit ? 'required' : ''; ?>>
                <div class="help">Recommended portrait/square ratio. Max 10MB. JPG, PNG, WEBP, GIF.</div>
            </div>
        </div>

        <div class="af-row">
            <div class="af-group">
                <label>Sort Order</label>
                <input type="number" name="sort_order" value="<?php echo (int)$formData['sort_order']; ?>">
                <div class="help">Lower number shows first when multiple ads share the same location.</div>
            </div>
        </div>

        <div class="af-row">
            <div class="af-group">
                <label>Status</label>
                <select name="status">
                    <option value="active"   <?php echo $formData['status'] === 'active'   ? 'selected' : ''; ?>>Active (Visible)</option>
                    <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive (Hidden)</option>
                </select>
            </div>
        </div>

        <div class="af-actions">
            <button type="submit" class="btn"><?php echo $isEdit ? 'Save Changes' : 'Add Ad'; ?></button>
            <a href="ad-list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
    (function () {
        const input = document.getElementById('adImageInput');
        if (!input) return;
        const MAX = 10 * 1024 * 1024;
        input.addEventListener('change', function () {
            if (this.files && this.files[0] && this.files[0].size > MAX) {
                alert('Image is too large (' + (this.files[0].size / 1024 / 1024).toFixed(1) + 'MB). Max size is 10MB.');
                this.value = '';
            }
        });
    })();
</script>

<?php include 'footer.php'; ?>
