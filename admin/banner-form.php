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

$success = '';
$error   = '';

$id     = isset($_GET['id'])       ? (int)$_GET['id']           : 0;
$isEdit = $id > 0;

$locationOptions = [
    'home'       => 'Home Banner',
    'sell_buggy' => 'Sell Buggy Banner',
];

$defaultLocation = $_GET['location'] ?? 'home';

$formData = [
    'location'   => $defaultLocation,
    'title'      => '',
    'subtitle'   => '',
    'link_url'   => '',
    'sort_order' => 0,
    'status'     => 'active',
    'image_url'  => '',
];

if ($isEdit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM banners WHERE id = ?");
        $stmt->execute([$id]);
        $banner = $stmt->fetch();
        if (!$banner) die('Banner not found.');
        foreach ($formData as $key => $val) {
            if (array_key_exists($key, $banner)) $formData[$key] = $banner[$key];
        }
    } catch (PDOException $e) {
        die('Failed to load banner: ' . $e->getMessage());
    }
}

if (isset($_GET['added']) && $_GET['added'] === '1') $success = 'Banner added successfully.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_banner'])) {
    $location   = trim($_POST['location']   ?? 'home');
    $title      = trim($_POST['title']      ?? '');
    $subtitle   = trim($_POST['subtitle']   ?? '');
    $linkUrl    = trim($_POST['link_url']   ?? '');
    $sortOrder  = (int)($_POST['sort_order'] ?? 0);
    $status     = trim($_POST['status']     ?? 'active');

    if (!array_key_exists($location, $locationOptions)) $location = 'home';
    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';

    $imageUrl = $formData['image_url'];

    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../images/banners/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $originalName      = $_FILES['banner_image']['name'];
        $tmpName           = $_FILES['banner_image']['tmp_name'];
        $fileSize          = $_FILES['banner_image']['size'];
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
        } elseif ($fileSize > 5 * 1024 * 1024) {
            $error = 'Image must be below 5MB.';
        } else {
            $safeName    = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
            $newFileName = 'banner-' . $location . '-' . time() . '-' . rand(1000, 9999) . '.' . $extension;
            $targetPath  = $uploadDir . $newFileName;

            if (move_uploaded_file($tmpName, $targetPath)) {
                $imageUrl = 'images/banners/' . $newFileName;
            } else {
                $error = 'Failed to upload image.';
            }
        }
    }

    if ($error === '') {
        if (!$isEdit && $imageUrl === '') {
            $error = 'Please upload a banner image.';
        }
    }

    if ($error === '') {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE banners SET
                        location   = :location,
                        title      = :title,
                        subtitle   = :subtitle,
                        link_url   = :link_url,
                        sort_order = :sort_order,
                        status     = :status,
                        image_url  = :image_url,
                        updated_at = NOW()
                    WHERE id = :id
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
                $success = 'Banner updated successfully.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO banners (location, title, subtitle, link_url, sort_order, status, image_url, created_at, updated_at)
                    VALUES (:location, :title, :subtitle, :link_url, :sort_order, :status, :image_url, NOW(), NOW())
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
                header('Location: banner-list.php?added=1');
                exit;
            }

            $formData = ['location'=>$location,'title'=>$title,'subtitle'=>$subtitle,'link_url'=>$linkUrl,'sort_order'=>$sortOrder,'status'=>$status,'image_url'=>$imageUrl];

        } catch (PDOException $e) {
            $error = 'Failed to save banner: ' . $e->getMessage();
        }
    }
}

include 'header.php';
?>

<style>
    .banner-form-header { margin-bottom: 22px; }
    .banner-form-header h1 { margin: 0; font-size: 30px; }
    .banner-form-header p  { margin: 6px 0 0; color: #777; font-size: 15px; }

    .breadcrumb { background: #eee; padding: 12px 16px; margin-bottom: 22px; color: #999; font-size: 14px; }
    .breadcrumb a { color: #777; text-decoration: none; }
    .breadcrumb span { color: #ef3f4d; }

    .form-card { background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 8px 22px rgba(0,0,0,0.04); }

    .form-group { margin-bottom: 18px; width: 40%; }
    .form-group.full { width: 100%; }
    .form-group label { display: block; margin-bottom: 7px; font-weight: bold; font-size: 14px; color: #333; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; height: 44px; border: 1px solid #ddd; border-radius: 8px; padding: 0 12px; font-size: 14px; outline: none; background: #fff; }
    .form-group textarea { min-height: 90px; padding-top: 10px; resize: vertical; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #ef3f4d; }
    .form-group input[type="file"] { height: auto; padding: 10px 12px; }

    .section-title { font-size: 18px; font-weight: bold; margin: 24px 0 14px; padding-top: 20px; border-top: 1px solid #eee; }
    .section-title:first-child { border-top: 0; margin-top: 0; padding-top: 0; }

    .help { color: #777; font-size: 13px; margin-top: 5px; }

    .current-image-box { margin-top: 10px; }
    .current-image-box img { width: 100%; max-width: 480px; height: 180px; object-fit: cover; border-radius: 10px; border: 1px solid #eee; display: block; }

    .form-actions { margin-top: 28px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 10px; }

    .message-success { background: #e8fff0; color: #167a3c; border: 1px solid #b8e8c8; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
    .message-error   { background: #fff0f2; color: #ef3f4d; border: 1px solid #ffc4cc; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }

    @media (max-width: 700px) {
        .form-group { width: 100%; }
    }
</style>

<div class="banner-form-header">
    <h1><?php echo $isEdit ? 'Edit Banner' : 'Add Banner'; ?></h1>
    <p><?php echo $isEdit ? 'Update banner details and image.' : 'Create a new banner for homepage or sell buggy page.'; ?></p>
</div>

<div class="breadcrumb">
    <a href="banner-list.php">Banner Management</a> &gt; <span><?php echo $isEdit ? 'Edit Banner' : 'Add Banner'; ?></span>
</div>

<?php if ($success): ?>
    <div class="message-success"><?php echo e($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="message-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="form-card">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="save_banner" value="1">

        <div class="section-title">Banner Settings</div>

        <div class="form-group">
            <label>Location *</label>
            <select name="location" required>
                <?php foreach ($locationOptions as $val => $label): ?>
                    <option value="<?php echo e($val); ?>" <?php echo $formData['location'] === $val ? 'selected' : ''; ?>>
                        <?php echo e($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="help">Select where this banner will appear.</div>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="active"   <?php echo $formData['status'] === 'active'   ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>

        <div class="form-group">
            <label>Sort Order</label>
            <input type="number" name="sort_order" value="<?php echo (int)$formData['sort_order']; ?>" min="0">
            <div class="help">Lower number = shown first. Use 0, 1, 2, 3...</div>
        </div>

        <div class="section-title">Banner Content (Optional)</div>

        <div class="form-group full">
            <label>Title</label>
            <input type="text" name="title" placeholder="e.g. Find Your Perfect Buggy" value="<?php echo e($formData['title']); ?>">
        </div>

        <div class="form-group full">
            <label>Subtitle</label>
            <textarea name="subtitle" placeholder="e.g. Browse new and used buggies across Singapore"><?php echo e($formData['subtitle']); ?></textarea>
        </div>

        <div class="form-group full">
            <label>Link URL</label>
            <input type="text" name="link_url" placeholder="e.g. /newbuggy.php or https://..." value="<?php echo e($formData['link_url']); ?>">
            <div class="help">Optional. If set, clicking the banner redirects here.</div>
        </div>

        <div class="section-title">Banner Image</div>

        <?php if ($isEdit && !empty($formData['image_url'])): ?>
            <div class="current-image-box">
                <div class="help" style="margin-bottom:8px;">Current image:</div>
                <img
                    src="<?php echo e(strpos($formData['image_url'], 'http') === 0 ? $formData['image_url'] : '../' . $formData['image_url']); ?>"
                    alt="Current Banner"
                    onerror="this.src='../images/no-image.png';"
                >
            </div>
            <br>
        <?php endif; ?>

        <div class="form-group full">
            <label><?php echo $isEdit ? 'Replace Image (optional)' : 'Banner Image *'; ?></label>
            <input type="file" name="banner_image" accept=".jpg,.jpeg,.png,.webp,.gif" <?php echo !$isEdit ? 'required' : ''; ?>>
            <div class="help">Recommended size: 1920 x 600px. Max 5MB. JPG, PNG, WEBP, GIF.</div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">
                <?php echo $isEdit ? 'Save Changes' : 'Add Banner'; ?>
            </button>
            <a href="banner-list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>