<?php
require_once '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$success   = '';
$error     = '';
$activeTab = 'infoTab';

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$currentYear = (int)date('Y');
$startYear   = $currentYear;
$endYear     = $currentYear - 10;

$statusOptions = [
    'active'   => 'Active',
    'inactive' => 'Inactive',
    'sold'     => 'Sold',
    'pending'  => 'Pending',
];

$tagOptions = [
    'New',
    'Hot Deal',
    'Promotion',
    'Featured',
    'Limited Stock',
];

$accCategories = [
    ''                 => 'Select Category',
    'Batteries'        => 'Batteries',
    'Tyres'            => 'Tyres',
    'Mechanical Parts' => 'Mechanical Parts',
    'Electrical Parts' => 'Electrical Parts',
    'Others'           => 'Others',
];

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

function uploadAccDatasheetPdf(&$error)
{
    if (!isset($_FILES['datasheet_file']) || $_FILES['datasheet_file']['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    $errCode = $_FILES['datasheet_file']['error'];
    if ($errCode !== UPLOAD_ERR_OK) {
        $map = [
            UPLOAD_ERR_INI_SIZE   => 'PDF exceeds server upload_max_filesize limit (' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_FORM_SIZE  => 'PDF exceeds form MAX_FILE_SIZE limit.',
            UPLOAD_ERR_PARTIAL    => 'PDF was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No PDF was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write the PDF to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
        ];
        $error = 'Datasheet upload failed: ' . ($map[$errCode] ?? ('error code ' . $errCode));
        return '';
    }

    $tmpName  = $_FILES['datasheet_file']['tmp_name'];
    $original = $_FILES['datasheet_file']['name'];
    $fileSize = $_FILES['datasheet_file']['size'];
    $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    if ($ext !== 'pdf') {
        $error = 'Datasheet must be a PDF file.';
        return '';
    }

    if ($fileSize > 5 * 1024 * 1024) {
        $error = 'Datasheet must be below 5MB.';
        return '';
    }

    if (function_exists('finfo_open')) {
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $tmpName) : '';
        if ($finfo) finfo_close($finfo);
        if ($mimeType && stripos($mimeType, 'pdf') === false) {
            $error = 'Datasheet is not a valid PDF (detected: ' . htmlspecialchars($mimeType) . ').';
            return '';
        }
    }

    $uploadDir = __DIR__ . '/../uploads/datasheets/';
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $error = 'Server could not create the uploads/datasheets folder. Please create it manually with write permission.';
            return '';
        }
    }
    if (!is_writable($uploadDir)) {
        $error = 'uploads/datasheets folder is not writable on the server.';
        return '';
    }

    $safeName    = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($original, PATHINFO_FILENAME));
    $newFileName = 'acc-datasheet-' . $safeName . '-' . time() . '-' . rand(1000, 9999) . '.pdf';
    $targetPath  = $uploadDir . $newFileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        $error = 'move_uploaded_file failed writing to ' . $uploadDir;
        return '';
    }

    return 'uploads/datasheets/' . $newFileName;
}

function uploadAccImages($pdo, $automotiveId, &$error)
{
    if (!isset($_FILES['gallery_images']) || empty($_FILES['gallery_images']['name'][0])) {
        return [];
    }

    $uploadDir = __DIR__ . '/../images/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedMimeTypes  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $uploadedUrls      = [];

    foreach ($_FILES['gallery_images']['name'] as $key => $originalName) {
        if ($originalName === '') continue;

        if ($_FILES['gallery_images']['error'][$key] !== UPLOAD_ERR_OK) {
            $error = 'One of the images failed to upload.';
            return $uploadedUrls;
        }

        $tmpName   = $_FILES['gallery_images']['tmp_name'][$key];
        $fileSize  = $_FILES['gallery_images']['size'][$key];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!in_array($extension, $allowedExtensions, true)) {
            $error = 'Only JPG, PNG, WEBP, and GIF images are allowed.';
            return $uploadedUrls;
        }

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $error = 'One of the uploaded files is not a valid image.';
            return $uploadedUrls;
        }

        if ($fileSize > 4 * 1024 * 1024) {
            $error = 'Each image must be below 4MB.';
            return $uploadedUrls;
        }

        $safeName    = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
        $newFileName = 'acc-' . $safeName . '-' . time() . '-' . rand(1000, 9999) . '-' . $key . '.' . $extension;
        $targetPath  = $uploadDir . $newFileName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            $error = 'Failed to upload image.';
            return $uploadedUrls;
        }

        $imageUrl = 'images/' . $newFileName;

        $stmt = $pdo->prepare("INSERT INTO automotive_images (automotive_id, image_url) VALUES (:automotive_id, :image_url)");
        $stmt->execute([':automotive_id' => $automotiveId, ':image_url' => $imageUrl]);

        $uploadedUrls[] = $imageUrl;
    }

    return $uploadedUrls;
}

$formData = [
    'brand'           => '',
    'model'           => '',
    'name'            => '',
    'category'        => '',
    'manufacture_year'=> '',
    'serial_number'   => '',
    'lead_time'       => '',
    'selling_price'   => '',
    'promo_enabled'   => 0,
    'discount_price'  => '',
    'promo_end_date'  => '',
    'promo_label'     => '',
    'short_info'      => '',
    'description'     => '',
    'tag'             => '',
    'brand_tag'       => '',
    'remark'          => '',
    'status'          => 'active',
    'image_url'       => '',
    'datasheet_url'   => '',
];

$product       = null;
$galleryImages = [];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM automotive WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        die('Automotive not found.');
    }

    foreach ($formData as $key => $value) {
        if (array_key_exists($key, $product)) {
            $formData[$key] = $product[$key];
        }
    }
}

if (isset($_GET['added']) && $_GET['added'] === '1') {
    $success = 'Automotive added successfully.';
}

/* â”€â”€ Image actions (edit mode) â”€â”€ */
if ($isEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image_action'])) {
    $activeTab   = 'imageTab';
    $imageAction = $_POST['image_action'];

    if ($imageAction === 'set_primary') {
        $galleryId = (int)($_POST['gallery_id'] ?? 0);

        if ($galleryId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT image_url FROM automotive_images WHERE id = ? AND automotive_id = ?");
                $stmt->execute([$galleryId, $id]);
                $galleryImage = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($galleryImage) {
                    $stmt = $pdo->prepare("UPDATE automotive SET image_url = ? WHERE id = ?");
                    $stmt->execute([$galleryImage['image_url'], $id]);
                    $success = 'Primary image updated successfully.';
                } else {
                    $error = 'Gallery image not found.';
                }
            } catch (PDOException $e) {
                $error = 'Failed to set primary image: ' . $e->getMessage();
            }
        }
    }

    if ($imageAction === 'remove_gallery') {
        $galleryId = (int)($_POST['gallery_id'] ?? 0);

        if ($galleryId > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM automotive_images WHERE id = ? AND automotive_id = ?");
                $stmt->execute([$galleryId, $id]);
                $success = 'Image removed successfully.';
            } catch (PDOException $e) {
                $error = 'Failed to remove image: ' . $e->getMessage();
            }
        }
    }

    if ($imageAction === 'delete_datasheet') {
        try {
            $stmt = $pdo->prepare("SELECT datasheet_url FROM automotive WHERE id = ?");
            $stmt->execute([$id]);
            $oldUrl = (string)$stmt->fetchColumn();
            if ($oldUrl !== '') {
                $oldPath = __DIR__ . '/../' . $oldUrl;
                if (is_file($oldPath)) @unlink($oldPath);
            }
            $stmt = $pdo->prepare("UPDATE automotive SET datasheet_url = NULL WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Datasheet removed.';
        } catch (PDOException $e) {
            $error = 'Failed to remove datasheet: ' . $e->getMessage();
        }
    }

    if ($imageAction === 'upload_gallery') {
        try {
            $uploadedUrls = uploadAccImages($pdo, $id, $error);

            if ($error === '') {
                if (count($uploadedUrls) > 0) {
                    $success = count($uploadedUrls) . ' image(s) uploaded successfully.';

                    $stmt = $pdo->prepare("SELECT image_url FROM automotive WHERE id = ?");
                    $stmt->execute([$id]);
                    $current = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($current && trim((string)$current['image_url']) === '') {
                        $stmt = $pdo->prepare("UPDATE automotive SET image_url = ? WHERE id = ?");
                        $stmt->execute([$uploadedUrls[0], $id]);
                    }
                } else {
                    $error = 'Please select at least one image.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Failed to upload images: ' . $e->getMessage();
        }
    }
}

/* â”€â”€ Save automotive info â”€â”€ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_automotive'])) {
    $activeTab = 'infoTab';

    foreach ($formData as $key => $value) {
        if ($key !== 'image_url' && $key !== 'datasheet_url') {
            $formData[$key] = trim($_POST[$key] ?? '');
        }
    }

    $newDatasheetUrl = uploadAccDatasheetPdf($error);
    if ($error === '' && $newDatasheetUrl !== '') {
        $formData['datasheet_url'] = $newDatasheetUrl;
    } elseif ($error === '' && $isEdit) {
        $formData['datasheet_url'] = $product['datasheet_url'] ?? '';
    }

    $submitAction   = $_POST['submit_action'] ?? 'insert';
    $brand          = $formData['brand'];
    $model          = $formData['model'];
    $name           = $formData['name'] !== '' ? $formData['name'] : ($brand . ' ' . $model);
    $manufactureYear= $formData['manufacture_year'] !== '' ? (int)$formData['manufacture_year'] : null;
    $serialNumber   = $formData['serial_number'];
    $leadTime       = $formData['lead_time'];
    $sellingPrice   = (float)($formData['selling_price'] !== '' ? $formData['selling_price'] : 0);
    $promoEnabled   = isset($_POST['promo_enabled']) ? 1 : 0;
    $discountPrice  = (float)($formData['discount_price'] !== '' ? $formData['discount_price'] : 0);
    $promoEndDate   = trim($_POST['promo_end_date'] ?? '');
    $promoEndDate   = $promoEndDate !== '' ? $promoEndDate : null;
    $promoLabel     = trim($_POST['promo_label'] ?? '');
    $promoLabel     = $promoLabel !== '' ? $promoLabel : null;
    $formData['promo_enabled']  = $promoEnabled;
    $formData['discount_price'] = $discountPrice ?: '';
    $formData['promo_end_date'] = $promoEndDate ?? '';
    $formData['promo_label']    = $promoLabel ?? '';
    $shortInfo      = $formData['short_info'];
    $description    = $formData['description'];
    $tag            = $formData['tag'] !== '' ? $formData['tag'] : 'New';
    $brandTag       = $formData['brand_tag'] !== '' ? $formData['brand_tag'] : $brand;
    $remark         = $formData['remark'];
    $status         = $formData['status'] ?: 'active';

    if ($brand === '') {
        $error = 'Brand is required.';
    } elseif ($model === '') {
        $error = 'Model is required.';
    } elseif ($promoEnabled === 1 && $discountPrice <= 0) {
        $error = 'Please enter a Discount Price for the promo, or turn off the Limited Time Promo.';
    } elseif ($promoEnabled === 1 && $discountPrice >= $sellingPrice) {
        $error = 'Discount Price ($' . number_format($discountPrice, 0) . ') must be LOWER than the Selling Price ($' . number_format($sellingPrice, 0) . ').';
    }

    if ($error === '') {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE automotive SET
                        brand            = :brand,
                        model            = :model,
                        name             = :name,
                        category         = :category,
                        manufacture_year = :manufacture_year,
                        serial_number    = :serial_number,
                        lead_time        = :lead_time,
                        selling_price    = :selling_price,
                        promo_enabled    = :promo_enabled,
                        discount_price   = :discount_price,
                        promo_end_date   = :promo_end_date,
                        promo_label      = :promo_label,
                        short_info       = :short_info,
                        description      = :description,
                        tag              = :tag,
                        brand_tag        = :brand_tag,
                        remark           = :remark,
                        status           = :status,
                        datasheet_url    = :datasheet_url
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':datasheet_url'    => $formData['datasheet_url'] !== '' ? $formData['datasheet_url'] : null,
                    ':brand'            => $brand,
                    ':model'            => $model,
                    ':name'             => $name,
                    ':category'         => $formData['category'],
                    ':manufacture_year' => $manufactureYear,
                    ':serial_number'    => $serialNumber,
                    ':lead_time'        => $leadTime,
                    ':selling_price'    => $sellingPrice,
                    ':promo_enabled'    => $promoEnabled,
                    ':discount_price'   => $discountPrice ?: null,
                    ':promo_end_date'   => $promoEndDate,
                    ':promo_label'      => $promoLabel,
                    ':short_info'       => $shortInfo,
                    ':description'      => $description,
                    ':tag'              => $tag,
                    ':brand_tag'        => $brandTag,
                    ':remark'           => $remark,
                    ':status'           => $status,
                    ':id'               => $id,
                ]);

                /* Upload any newly added gallery images on the same form submit */
                $uploadedUrls = uploadAccImages($pdo, $id, $error);
                if ($error === '' && count($uploadedUrls) > 0) {
                    $stmt = $pdo->prepare("SELECT image_url FROM automotive WHERE id = ?");
                    $stmt->execute([$id]);
                    $current = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($current && trim((string)$current['image_url']) === '') {
                        $stmt = $pdo->prepare("UPDATE automotive SET image_url = ? WHERE id = ?");
                        $stmt->execute([$uploadedUrls[0], $id]);
                    }
                }

                $success = 'Automotive updated successfully.';

            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO automotive (
                        owner_type, owner_id, brand, model, name,
                        category, manufacture_year, serial_number, lead_time,
                        selling_price, promo_enabled, discount_price, promo_end_date, promo_label,
                        short_info, description,
                        image_url, tag, brand_tag, remark, status, datasheet_url
                    ) VALUES (
                        'admin', :owner_id, :brand, :model, :name,
                        :category, :manufacture_year, :serial_number, :lead_time,
                        :selling_price, :promo_enabled, :discount_price, :promo_end_date, :promo_label,
                        :short_info, :description,
                        '', :tag, :brand_tag, :remark, :status, :datasheet_url
                    )
                ");

                $stmt->execute([
                    ':datasheet_url'    => $formData['datasheet_url'] !== '' ? $formData['datasheet_url'] : null,
                    ':owner_id'         => $_SESSION['admin_id'],
                    ':brand'            => $brand,
                    ':model'            => $model,
                    ':name'             => $name,
                    ':category'         => $formData['category'],
                    ':manufacture_year' => $manufactureYear,
                    ':serial_number'    => $serialNumber,
                    ':lead_time'        => $leadTime,
                    ':selling_price'    => $sellingPrice,
                    ':promo_enabled'    => $promoEnabled,
                    ':discount_price'   => $discountPrice ?: null,
                    ':promo_end_date'   => $promoEndDate,
                    ':promo_label'      => $promoLabel,
                    ':short_info'       => $shortInfo,
                    ':description'      => $description,
                    ':tag'              => $tag,
                    ':brand_tag'        => $brandTag,
                    ':remark'           => $remark,
                    ':status'           => $status,
                ]);

                $newId = (int)$pdo->lastInsertId();

                $uploadedUrls = uploadAccImages($pdo, $newId, $error);

                if ($error === '' && count($uploadedUrls) > 0) {
                    $stmt = $pdo->prepare("UPDATE automotive SET image_url = ? WHERE id = ?");
                    $stmt->execute([$uploadedUrls[0], $newId]);
                }

                if ($error === '') {
                    if ($submitAction === 'insert_add') {
                        header('Location: automotive-form.php?added=1');
                        exit;
                    }
                    if ($submitAction === 'insert_exit') {
                        header('Location: automotive-list.php?added=1');
                        exit;
                    }
                    header('Location: automotive-form.php?id=' . $newId . '&added=1');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = ($isEdit ? 'Failed to update: ' : 'Failed to add: ') . $e->getMessage();
        }
    }
}

/* â”€â”€ Refresh after actions â”€â”€ */
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM automotive WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    foreach ($formData as $key => $value) {
        if (array_key_exists($key, $product)) {
            $formData[$key] = $product[$key];
        }
    }

    $galleryStmt = $pdo->prepare("SELECT * FROM automotive_images WHERE automotive_id = ? ORDER BY sort_order ASC, id ASC");
    $galleryStmt->execute([$id]);
    $galleryImages = $galleryStmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'header.php';
?>

<style>
    .product-page-title { margin-bottom: 18px; }
    .product-page-title h1 { margin: 0; font-size: 30px; font-weight: 500; color: #333; }

    .breadcrumb {
        background: #eeeeee;
        padding: 12px 16px;
        margin-bottom: 22px;
        color: #999;
        font-size: 14px;
    }

    .breadcrumb span { color: #ef3f4d; }

    .form-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 22px;
    }

    .form-header h1 { margin: 0; font-size: 30px; }
    .form-header p  { margin: 6px 0 0; color: #777; font-size: 15px; }

    .form-header-actions { display: flex; gap: 10px; flex-wrap: wrap; }

    .btn-light { background: #fff; color: #222; border: 1px solid #ddd; }
    .btn-light:hover { border-color: #ef3f4d; color: #ef3f4d; background: #fff; }

    .product-card {
        background: #fff;
        border-radius: 4px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        border: 1px solid #eee;
        overflow: hidden;
    }

    .top-actions, .bottom-actions {
        background: #fff;
        border-bottom: 1px solid #eeeeee;
        padding: 18px 20px;
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .bottom-actions { border-top: 1px solid #eeeeee; border-bottom: 0; }

    .action-btn {
        border: 0;
        background: #ef3f4d;
        color: #fff;
        padding: 0 22px;
        min-width: 145px;
        height: 44px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: 0.2s ease;
    }

    .action-btn:hover { background: #d92e3d; color: #fff; }
    .action-btn-light { background: #2f2f2f; color: #fff; border: 0; }
    .action-btn-light:hover { background: #1f1f1f; color: #fff; }

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
    .response-box small { display: block; margin-top: 5px; font-weight: normal; opacity: 0.8; }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-12px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* Tabs hidden &mdash; Info and Images now display on the same page */
    .product-tabs { display: none; }
    .product-tab-btn { display: none; }
    .tab-panel { display: block; padding: 22px; }

    .form-grid { display: block; }
    .form-group { display: flex; flex-direction: column; gap: 7px; width: 40%; margin-bottom: 16px; }
    .form-group.full { width: 100%; }
    .section-title { width: 100%; margin-top: 28px; margin-bottom: 16px; padding-top: 22px; border-top: 1px solid #eeeeee; font-size: 20px; font-weight: bold; }
    .section-title:first-child { margin-top: 0; border-top: 0; padding-top: 0; }

    .section-title {
        width: 100%;
        margin-top: 28px;
        margin-bottom: 16px;
        padding-top: 22px;
        border-top: 1px solid #eeeeee;
        font-size: 20px;
        font-weight: bold;
    }
    .section-title:first-child { margin-top: 0; border-top: 0; padding-top: 0; }

    .help { color: #777; font-size: 13px; line-height: 1.45; }

    .form-actions, .image-form-actions {
        margin-top: 32px;
        padding-top: 22px;
        border-top: 1px solid #eee;
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .image-tab-content { min-height: 460px; }

    .image-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
    }

    .image-toolbar h2 { margin: 0; font-size: 22px; }
    .image-count { color: #777; font-size: 14px; }

    .main-image-box {
        border: 1px solid #eee;
        border-radius: 14px;
        padding: 18px;
        margin-bottom: 24px;
        background: #fafafa;
    }

    .main-image-title { font-weight: bold; margin-bottom: 12px; }

    .main-image-preview {
        width: 170px;
        height: 170px;
        border-radius: 12px;
        object-fit: cover;
        border: 1px solid #ddd;
        background: #f1f1f1;
    }

    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(4, 230px);
        gap: 20px;
        margin-bottom: 30px;
    }

    .gallery-card { border: 1px solid #eee; border-radius: 10px; padding: 10px; background: #fff; }

    .gallery-actions {
        display: flex;
        gap: 6px;
        margin-bottom: 10px;
        flex-wrap: nowrap;
        align-items: center;
    }

    .gallery-actions form { display: inline-flex !important; margin: 0; }

    .image-action-btn {
        border: 0;
        padding: 6px 8px;
        border-radius: 6px;
        color: #fff;
        font-size: 11px;
        font-weight: bold;
        cursor: pointer;
        white-space: nowrap;
        height: 28px;
        line-height: 1;
        transition: 0.2s ease;
    }

    .gallery-card.is-primary {
        border-color: #16a34a;
        box-shadow: 0 0 0 2px #16a34a inset;
    }
    .primary-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #16a34a;
        color: #fff;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: bold;
        height: 28px;
        line-height: 1;
    }

    .set-primary-btn { background: #7dd3e8; }
    .set-primary-btn:hover { background: #59bfd8; }
    .remove-btn { background: #ef8585; }
    .remove-btn:hover { background: #ef3f4d; }

    .gallery-image {
        width: 208px;
        height: 208px;
        object-fit: cover;
        display: block;
        margin-bottom: 10px;
        border: 1px solid #eee;
        border-radius: 4px;
    }

    .upload-area {
        border: 1px solid #eee;
        background: #fafafa;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #333;
        margin: 0 0 16px;
        transition: 0.2s ease;
    }

    .upload-area.dragover { border-color: #ef3f4d; background: #fff7f8; color: #ef3f4d; }

    .upload-preview-area {
        display: flex;
        flex-wrap: wrap;
        gap: 18px 30px;
        margin-top: 14px;
        align-items: flex-start;
        max-width: 1100px;
    }

    .upload-card { width: 230px; position: relative; }

    .upload-box {
        width: 230px;
        height: 230px;
        background: #eef3f6;
        border: 1px solid #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        border-radius: 4px;
        transition: 0.2s ease;
    }

    .upload-box:hover { border-color: #ef3f4d; }

    .upload-placeholder { text-align: center; color: #999; }
    .upload-icon { font-size: 48px; line-height: 1; margin-bottom: 8px; }
    .upload-label { background: #999; color: #fff; padding: 6px 10px; border-radius: 4px; display: inline-block; font-size: 13px; }

    .preview-upload-img {
        width: 230px;
        height: 230px;
        object-fit: cover;
        border: 1px solid #ddd;
        display: block;
        border-radius: 4px;
    }

    .remove-preview-btn {
        position: absolute;
        top: 0; right: 0;
        width: 24px; height: 24px;
        border: 0;
        background: #ef3f4d;
        color: #fff;
        font-weight: bold;
        cursor: pointer;
        z-index: 2;
        border-radius: 0 4px 0 6px;
        line-height: 1;
        font-size: 16px;
        transition: 0.2s ease;
    }

    .remove-preview-btn:hover { background: #d92e3d; }

    .add-button-holder { display: flex; align-items: flex-start; width: auto; }

    .add-image-side-btn {
        background: #7dd3e8;
        color: #fff;
        border: 0;
        padding: 7px 12px;
        font-weight: 700;
        cursor: pointer;
        font-size: 12px;
        height: 28px;
        line-height: 1;
        white-space: nowrap;
        border-radius: 6px;
        transition: 0.2s ease;
    }

    .add-image-side-btn:hover { background: #59bfd8; }

    .image-input-hidden { display: none; }
    .form-note { color: #777; margin-top: 18px; font-size: 14px; }

    @media (max-width: 1200px) {
        .gallery-grid { grid-template-columns: repeat(3, 230px); }
    }
    @media (max-width: 900px) {
        .form-group { width: 100%; }
        .gallery-grid { grid-template-columns: repeat(2, 230px); }
    }

    @media (max-width: 700px) {
        .form-header { display: block; }
        .form-header-actions { margin-top: 14px; }
        .gallery-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="product-page-title">
    <h1><?php echo $isEdit ? 'Edit Automotive' : '[New] Automotive'; ?></h1>
</div>

<div class="breadcrumb">
    <a href="automotive-list.php">Automotive</a> &gt; <span><?php echo $isEdit ? 'Edit Automotive' : 'Add Automotive'; ?></span>
</div>

<?php if ($success): ?>
    <div class="response-box response-success" id="responseBox">
        <?php echo e($success); ?>
        <small>Your changes have been saved.</small>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="response-box response-error" id="responseBox">
        <?php echo e($error); ?>
        <small>Please check the form and try again.</small>
    </div>
<?php endif; ?>

<?php if ($isEdit): ?>
    <div class="form-header">
        <div>
            <h1>Edit Automotive</h1>
            <p>Update automotive information and manage images.</p>
        </div>
        <div class="form-header-actions">
            <a href="automotive-list.php" class="btn btn-light">Back to List</a>
        </div>
    </div>
<?php endif; ?>

<div class="product-card">

    <?php if (!$isEdit): ?>
        <form method="post" enctype="multipart/form-data" id="accForm">
            <input type="hidden" name="save_automotive" value="1">
            <input type="hidden" name="submit_action" id="submitActionInput" value="insert">

            <div class="top-actions">
                <button type="submit" class="action-btn" data-action="insert">Insert</button>
                <button type="submit" class="action-btn" data-action="insert_add">Insert and Add</button>
                <button type="submit" class="action-btn" data-action="insert_exit">Insert and Exit</button>
                <a href="automotive-list.php" class="action-btn action-btn-light">Cancel</a>
            </div>
    <?php endif; ?>

    <?php if ($isEdit): ?>
        <form method="post" enctype="multipart/form-data" id="accForm">
            <input type="hidden" name="save_automotive" value="1">
            <input type="hidden" name="submit_action" id="submitActionInput" value="update">
    <?php endif; ?>

    <div class="product-tabs">
        <button type="button" class="product-tab-btn <?php echo $activeTab === 'infoTab' ? 'active' : ''; ?>" data-tab="infoTab">
            Automotive Info
        </button>
        <button type="button" class="product-tab-btn <?php echo $activeTab === 'imageTab' ? 'active' : ''; ?>" data-tab="imageTab">
            Images<?php echo $isEdit ? ' (' . (count($galleryImages) + (!empty($formData['image_url']) ? 1 : 0)) . ')' : ''; ?>
        </button>
    </div>

    <!-- INFO TAB -->
    <div id="infoTab" class="tab-panel <?php echo $activeTab === 'infoTab' ? 'active' : ''; ?>">

        <div class="form-grid">

            <div class="section-title">Product Information</div>

            <div class="form-group">
                <label>Brand *</label>
                <input type="text" name="brand" placeholder="e.g. Rocket, Club Car" value="<?php echo e($formData['brand']); ?>" required>
            </div>

            <div class="form-group">
                <label>Model *</label>
                <input type="text" name="model" placeholder="e.g. L3, Tempo" value="<?php echo e($formData['model']); ?>" required>
            </div>

            <div class="form-group full">
                <label>Product Name</label>
                <input type="text" name="name" placeholder="Leave blank to auto-combine Brand + Model" value="<?php echo e($formData['name']); ?>">
                <div class="help">If left blank, name will be set as Brand + Model automatically.</div>
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category">
                    <?php foreach ($accCategories as $catVal => $catLabel): ?>
                        <option value="<?php echo e($catVal); ?>" <?php echo $formData['category'] === $catVal ? 'selected' : ''; ?>>
                            <?php echo e($catLabel); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help">Used to filter automotive by category on the public automotive page.</div>
            </div>

            <div class="section-title">Equipment Details</div>

            <div class="form-group">
                <label>Manufacture Year</label>
                <select name="manufacture_year">
                    <option value="">Select year</option>
                    <?php for ($y = $startYear; $y >= $endYear; $y--): ?>
                        <option value="<?php echo (int)$y; ?>" <?php echo (string)$formData['manufacture_year'] === (string)$y ? 'selected' : ''; ?>>
                            <?php echo (int)$y; ?>
                        </option>
                    <?php endfor; ?>
                    <option value="Brand New" <?php echo $formData['manufacture_year'] === 'Brand New' ? 'selected' : ''; ?>>Brand New</option>
                </select>
            </div>

            <div class="form-group">
                <label>Serial Number</label>
                <input type="text" name="serial_number" placeholder="e.g. SN-20240001" value="<?php echo e($formData['serial_number']); ?>">
            </div>

            <div class="form-group">
                <label>Lead Time</label>
                <input type="text" name="lead_time" placeholder="e.g. Ready For Delivery, 2-3 Weeks" value="<?php echo e($formData['lead_time']); ?>">
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <?php foreach ($statusOptions as $val => $label): ?>
                        <option value="<?php echo e($val); ?>" <?php echo $formData['status'] === $val ? 'selected' : ''; ?>>
                            <?php echo e($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="section-title">Pricing</div>

            <div class="form-group">
                <label>Selling Price ($)</label>
                <input type="number" step="0.01" name="selling_price" placeholder="e.g. 250" value="<?php echo e($formData['selling_price']); ?>">
                <div class="help">The normal price. During a promo this is shown crossed out.</div>
            </div>

            <?php if (false): /* Limited Time Promo hidden — flip to true to re-enable */ ?>
            <div class="section-title">🔥 Limited Time Promo</div>

            <div class="form-group full">
                <label style="display:flex;align-items:center;gap:8px;background:#fff7ed;padding:14px 16px;border:1px solid #fdba74;border-radius:8px;cursor:pointer;">
                    <input type="checkbox" name="promo_enabled" value="1" id="accPromoEnabled" style="width:18px;height:18px;"
                        <?php echo (int)$formData['promo_enabled'] === 1 ? 'checked' : ''; ?>>
                    <span style="font-weight:700;color:#9a3412;">Enable Limited Time Promo</span>
                </label>
            </div>

            <div id="accPromoFields" style="<?php echo (int)$formData['promo_enabled'] === 1 ? '' : 'display:none;'; ?>">
                <div class="form-group">
                    <label>Discount Price ($)</label>
                    <input
                        type="number"
                        step="0.01"
                        name="discount_price"
                        placeholder="e.g. 350"
                        value="<?php echo e($formData['discount_price']); ?>"
                    >
                    <div class="help">The promotional (lower) price customers pay during the promo. Must be lower than the Selling Price. Your Selling Price will be shown crossed out.</div>
                </div>

                <div class="form-group">
                    <label>Promo End Date &amp; Time</label>
                    <input
                        type="datetime-local"
                        name="promo_end_date"
                        value="<?php echo e($formData['promo_end_date'] ? date('Y-m-d\TH:i', strtotime($formData['promo_end_date'])) : ''); ?>"
                    >
                    <div class="help">Promo will automatically end after this date/time.</div>
                </div>

                <div class="form-group">
                    <label>Custom Promo Label (optional)</label>
                    <input
                        type="text"
                        name="promo_label"
                        placeholder="e.g. Year End Sale, Flash Deal"
                        value="<?php echo e($formData['promo_label']); ?>"
                    >
                    <div class="help">Leave empty to show default "&#128293; SALE" badge.</div>
                </div>
            </div>

            <script>
                (function () {
                    const cb = document.getElementById('accPromoEnabled');
                    const fields = document.getElementById('accPromoFields');
                    if (cb && fields) {
                        cb.addEventListener('change', function () {
                            fields.style.display = this.checked ? 'block' : 'none';
                        });
                    }
                })();
            </script>
            <?php endif; /* end Limited Time Promo block */ ?>

            <div class="section-title">Tags & Details</div>

            <div class="form-group">
                <label>Product Tag</label>
                <select name="tag">
                    <option value="">Auto (New)</option>
                    <?php foreach ($tagOptions as $tagOpt): ?>
                        <option value="<?php echo e($tagOpt); ?>" <?php echo $formData['tag'] === $tagOpt ? 'selected' : ''; ?>>
                            <?php echo e($tagOpt); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Brand Tag</label>
                <input type="text" name="brand_tag" placeholder="Auto use Brand if blank" value="<?php echo e($formData['brand_tag']); ?>">
            </div>

            <div class="form-group full">
                <label>Short Info</label>
                <input type="text" name="short_info" placeholder="e.g. Universal seat cover for 2-4 seater buggies" value="<?php echo e($formData['short_info']); ?>">
            </div>

            <div class="form-group full">
                <label>Description</label>
                <textarea name="description" placeholder="Write product details here"><?php echo e($formData['description']); ?></textarea>
            </div>

            <div class="section-title">Datasheet (PDF)</div>

            <div class="form-group full">
                <label>Automotive Datasheet (PDF, max 5MB)</label>
                <?php if (!empty($formData['datasheet_url'])): ?>
                    <div style="margin-bottom:10px;display:flex;align-items:center;gap:14px;">
                        <a href="<?php echo e('../' . $formData['datasheet_url']); ?>" target="_blank" style="color:#ef3f4d;font-weight:600;">
                            ðŸ“„ View current datasheet
                        </a>
                        <button type="button"
                                onclick="deleteAccDatasheet()"
                                style="background:#ef3f4d;color:#fff;border:0;padding:6px 14px;border-radius:6px;font-weight:700;cursor:pointer;">
                            Remove
                        </button>
                    </div>
                <?php endif; ?>
                <input type="file" name="datasheet_file" accept="application/pdf" style="display:flex;align-items:center;padding:10px 12px;">
                <div class="help">Upload a PDF datasheet. Leave empty to keep the existing file.</div>
            </div>

            <div class="section-title">Internal Remark</div>

            <div class="form-group full">
                <label>Remark (Admin Only)</label>
                <textarea name="remark" placeholder="Internal notes, supplier info, stock location, etc. Not shown publicly."><?php echo e($formData['remark']); ?></textarea>
                <div class="help">This is for admin reference only and will not be shown to the public.</div>
            </div>

        </div>

    </div>

    <!-- IMAGE TAB -->
    <div id="imageTab" class="tab-panel <?php echo $activeTab === 'imageTab' ? 'active' : ''; ?>">

        <?php if ($isEdit): ?>
            <div class="image-toolbar">
                <div>
                    <h2>Product Images</h2>
                    <div class="image-count">Manage primary image and gallery images.</div>
                </div>
            </div>

            <?php if (count($galleryImages) > 0): ?>
                <div class="gallery-grid">
                    <?php foreach ($galleryImages as $gallery): ?>
                        <?php $isPrimary = trim((string)$gallery['image_url']) !== '' && trim((string)$gallery['image_url']) === trim((string)$formData['image_url']); ?>
                        <div class="gallery-card<?php echo $isPrimary ? ' is-primary' : ''; ?>">
                            <div class="gallery-actions">
                                <?php if ($isPrimary): ?>
                                    <span class="primary-badge">&#10003; Primary</span>
                                <?php else: ?>
                                    <button
                                        type="button"
                                        class="image-action-btn set-primary-btn"
                                        onclick="postGalleryAction('set_primary', <?php echo (int)$gallery['id']; ?>, 'Set this image as primary image?')"
                                    >
                                        + Set Primary
                                    </button>
                                <?php endif; ?>
                                <button
                                    type="button"
                                    class="image-action-btn remove-btn"
                                    onclick="postGalleryAction('remove_gallery', <?php echo (int)$gallery['id']; ?>, 'Remove this image?')"
                                >
                                    Remove
                                </button>
                            </div>
                            <img
                                src="<?php echo e(accImagePath($gallery['image_url'])); ?>"
                                alt="Gallery Image"
                                class="gallery-image"
                                onerror="this.src='../images/no-image.png';"
                            >
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="image-tab-content">
        <?php endif; ?>

            <div class="upload-area" id="dropArea">Drag & drop images here.</div>

            <div class="upload-preview-area" id="galleryUploadArea">
                <div class="upload-card">
                    <button type="button" class="remove-preview-btn">&times;</button>
                    <label class="upload-box">
                        <div class="upload-placeholder">
                            <div class="upload-icon">&#9639;</div>
                            <div class="upload-label">click here to upload</div>
                        </div>
                        <input type="file" name="gallery_images[]" class="image-input-hidden single-gallery-input" accept="image/*">
                    </label>
                </div>
                <div class="add-button-holder">
                    <button type="button" class="add-image-side-btn" id="addGalleryBoxBtn">+ Add</button>
                </div>
            </div>

            <?php if (!$isEdit): ?>
                <div class="form-note">
                    First uploaded image will become the primary image. Click <strong>+ Add</strong> to add more image boxes.
                </div>
            </div>
            <?php endif; ?>

    </div>

    <?php if ($isEdit): ?>
        <div class="bottom-actions">
            <button type="submit" class="action-btn" name="save_automotive" value="1">Save Changes</button>
            <a href="automotive-list.php" class="action-btn action-btn-light">Cancel</a>
        </div>
        </form>
    <?php endif; ?>

    <?php if (!$isEdit): ?>
        <div class="bottom-actions">
            <button type="submit" class="action-btn" data-action="insert">Insert</button>
            <button type="submit" class="action-btn" data-action="insert_add">Insert and Add</button>
            <button type="submit" class="action-btn" data-action="insert_exit">Insert and Exit</button>
            <a href="automotive-list.php" class="action-btn action-btn-light">Cancel</a>
        </div>
        </form>
    <?php endif; ?>

</div>

<script>
    function postGalleryAction(action, galleryId, confirmMsg) {
        if (confirmMsg && !confirm(confirmMsg)) return;
        const f = document.createElement('form');
        f.method = 'post';
        f.action = window.location.href;
        const a = document.createElement('input');
        a.type = 'hidden'; a.name = 'image_action'; a.value = action;
        const g = document.createElement('input');
        g.type = 'hidden'; g.name = 'gallery_id'; g.value = galleryId;
        f.appendChild(a); f.appendChild(g);
        document.body.appendChild(f);
        f.submit();
    }

    function deleteAccDatasheet() {
        if (!confirm('Remove the current datasheet PDF?')) return;
        const f = document.createElement('form');
        f.method = 'post';
        f.action = window.location.href;
        const a = document.createElement('input');
        a.type = 'hidden'; a.name = 'image_action'; a.value = 'delete_datasheet';
        f.appendChild(a);
        document.body.appendChild(f);
        f.submit();
    }

    const tabButtons       = document.querySelectorAll('.product-tab-btn');
    const tabPanels        = document.querySelectorAll('.tab-panel');
    const submitActionInput = document.getElementById('submitActionInput');

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const targetTab = this.getAttribute('data-tab');
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanels.forEach(panel => panel.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');
        });
    });

    if (submitActionInput) {
        document.querySelectorAll('button[data-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                submitActionInput.value = this.getAttribute('data-action');
            });
        });
    }

    const responseBox = document.getElementById('responseBox');
    if (responseBox) {
        setTimeout(function () {
            responseBox.style.opacity = '0';
            responseBox.style.transform = 'translateY(-12px)';
            responseBox.style.transition = '0.3s ease';
            setTimeout(function () { responseBox.remove(); }, 300);
        }, 3000);
    }

    const galleryUploadArea = document.getElementById('galleryUploadArea');
    const addGalleryBoxBtn  = document.getElementById('addGalleryBoxBtn');
    const dropArea          = document.getElementById('dropArea');

    function getAddButtonHolder() {
        return document.querySelector('.add-button-holder');
    }

    const MAX_IMAGE_SIZE = 4 * 1024 * 1024; // 4MB

    function checkImageSize(file) {
        if (file.size > MAX_IMAGE_SIZE) {
            alert('Image "' + file.name + '" is too large (' + (file.size / 1024 / 1024).toFixed(1) + 'MB). Each image must be below 4MB.');
            return false;
        }
        return true;
    }

    function bindUploadCard(card) {
        const fileInput = card.querySelector('input[type="file"]');
        const removeBtn = card.querySelector('.remove-preview-btn');

        fileInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                if (!checkImageSize(this.files[0])) { this.value = ''; return; }
                showImageInCard(card, this.files[0]);
            }
        });

        removeBtn.addEventListener('click', function () {
            card.remove();
            if (galleryUploadArea.querySelectorAll('.upload-card').length === 0) {
                galleryUploadArea.insertBefore(createGalleryUploadCard(), getAddButtonHolder());
            }
        });
    }

    function showImageInCard(card, file) {
        const label = card.querySelector('.upload-box');
        label.innerHTML = '';

        const img = document.createElement('img');
        img.className = 'preview-upload-img';
        img.src = URL.createObjectURL(file);
        img.alt = 'Selected image';

        const input = document.createElement('input');
        input.type = 'file';
        input.name = 'gallery_images[]';
        input.className = 'image-input-hidden single-gallery-input';
        input.accept = 'image/*';

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        input.files = dataTransfer.files;

        label.appendChild(img);
        label.appendChild(input);

        input.addEventListener('change', function () {
            if (this.files && this.files[0]) showImageInCard(card, this.files[0]);
        });
    }

    function createGalleryUploadCard(file = null) {
        const card      = document.createElement('div');
        card.className  = 'upload-card';

        const removeBtn = document.createElement('button');
        removeBtn.type  = 'button';
        removeBtn.className = 'remove-preview-btn';
        removeBtn.innerHTML = '&times;';

        const label     = document.createElement('label');
        label.className = 'upload-box';

        const placeholder = document.createElement('div');
        placeholder.className = 'upload-placeholder';
        placeholder.innerHTML = `<div class="upload-icon">&#9639;</div><div class="upload-label">click here to upload</div>`;

        const input     = document.createElement('input');
        input.type      = 'file';
        input.name      = 'gallery_images[]';
        input.className = 'image-input-hidden single-gallery-input';
        input.accept    = 'image/*';

        label.appendChild(placeholder);
        label.appendChild(input);
        card.appendChild(removeBtn);
        card.appendChild(label);

        bindUploadCard(card);

        if (file) showImageInCard(card, file);

        return card;
    }

    if (galleryUploadArea && addGalleryBoxBtn && dropArea) {
        document.querySelectorAll('.upload-card').forEach(card => bindUploadCard(card));

        addGalleryBoxBtn.addEventListener('click', function () {
            galleryUploadArea.insertBefore(createGalleryUploadCard(), getAddButtonHolder());
        });

        dropArea.addEventListener('dragover',  function (e) { e.preventDefault(); dropArea.classList.add('dragover'); });
        dropArea.addEventListener('dragleave', function ()  { dropArea.classList.remove('dragover'); });
        dropArea.addEventListener('drop', function (e) {
            e.preventDefault();
            dropArea.classList.remove('dragover');
            Array.from(e.dataTransfer.files).forEach(function (file) {
                if (file.type.startsWith('image/')) {
                    galleryUploadArea.insertBefore(createGalleryUploadCard(file), getAddButtonHolder());
                }
            });
        });
    }
</script>

<?php include 'footer.php'; ?>