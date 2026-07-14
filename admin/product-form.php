<?php
require_once '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';
$activeTab = 'productInfoTab';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$brandOptions = [];

try {
    $brandStmt = $pdo->query("
        SELECT brand_name
        FROM product_brands
        WHERE status = 'active'
        ORDER BY brand_name ASC
    ");

    $brandOptions = $brandStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $brandOptions = [
        'Club Car',
        'Yamaha',
        'EZGO',
        'HDK',
        'Marshell',
        'RoyPow'
    ];
}

$brandOptions[] = 'Others';

$categoryOptions = [
    '2 Seater',
    '2 Seater Short Box',
    '2 Seater Long Box',
    '4 Seater',
    '4 Seater Facing Front',
    '4 Seater Short Box',
    '6 Seater',
    '6 Seater Facing Front',
    '8 Seater',
];

$statusOptions = [
    'active' => 'Open',
    'sold' => 'Sold',
    'pending' => 'Pending',
    'inactive' => 'Inactive'
];

$productTagOptions = [
    'New',
    'Used',
    'Hot Deal',
    'For Sale',
    'Promotion',
    'Featured'
];

$currentYear = (int)date('Y');
$startYear = $currentYear;
$endYear = 2016;

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

function normalizeCategory($value)
{
    $value = trim((string)$value);

    if ($value === '2') {
        return '2 seater';
    }

    if ($value === '4') {
        return '4 seater';
    }

    if ($value === '6') {
        return '6 seater';
    }

    if ($value === '8') {
        return '8 seater';
    }

    return $value;
}

function uploadDatasheetPdf(&$error)
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
    $newFileName = 'buggy-datasheet-' . $safeName . '-' . time() . '-' . rand(1000, 9999) . '.pdf';
    $targetPath  = $uploadDir . $newFileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        $error = 'move_uploaded_file failed writing to ' . $uploadDir;
        return '';
    }

    return 'uploads/datasheets/' . $newFileName;
}

function uploadGalleryImages($pdo, $buggyId, &$error)
{
    if (!isset($_FILES['gallery_images']) || empty($_FILES['gallery_images']['name'][0])) {
        return [];
    }

    $uploadDir = __DIR__ . '/../images/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    $uploadedUrls = [];

    foreach ($_FILES['gallery_images']['name'] as $key => $originalName) {
        if ($originalName === '') {
            continue;
        }

        if ($_FILES['gallery_images']['error'][$key] !== UPLOAD_ERR_OK) {
            $error = 'One of the images failed to upload.';
            return $uploadedUrls;
        }

        $tmpName = $_FILES['gallery_images']['tmp_name'][$key];
        $fileSize = $_FILES['gallery_images']['size'][$key];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!in_array($extension, $allowedExtensions, true)) {
            $error = 'Only JPG, JPEG, PNG, WEBP, and GIF images are allowed.';
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

        $safeName = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
        $newFileName = 'gallery-' . $safeName . '-' . time() . '-' . rand(1000, 9999) . '-' . $key . '.' . $extension;
        $targetPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            $error = 'Failed to upload image.';
            return $uploadedUrls;
        }

        $imageUrl = 'images/' . $newFileName;

        $stmt = $pdo->prepare("
            INSERT INTO buggy_images (buggy_id, image_url)
            VALUES (:buggy_id, :image_url)
        ");

        $stmt->execute([
            ':buggy_id' => $buggyId,
            ':image_url' => $imageUrl
        ]);

        $uploadedUrls[] = $imageUrl;
    }

    return $uploadedUrls;
}

$formData = [
    'brand' => '',
    'custom_brand' => '',
    'model' => '',
    'seats' => '',
    'buggy_year' => '',
    'buggy_condition' => 'new',
    'status' => 'active',
    'selling_price' => '',
    'sort_new'      => 0,
    'sort_used'     => 0,
    'remark' => '',
    'brand_tag' => '',
    'tag' => '',
    'serial_number'     => '',
    'short_info'        => '',
    'description'       => '',
    'specifications'    => [],
    'promo_enabled'        => 0,
    'discount_price' => '',
    'promo_end_date'       => '',
    'promo_label'          => '',
    'image_url' => '',
    'datasheet_url' => ''
];

$product = null;
$galleryImages = [];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM buggies WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        die('Product not found.');
    }

    foreach ($formData as $key => $value) {
        if (array_key_exists($key, $product)) {
            $formData[$key] = $product[$key];
        }
    }

    // Decode JSON specifications
    if (!empty($formData['specifications']) && is_string($formData['specifications'])) {
        $decoded = json_decode($formData['specifications'], true);
        $formData['specifications'] = is_array($decoded) ? $decoded : [];
    } else {
        $formData['specifications'] = [];
    }

    $formData['seats'] = normalizeCategory($formData['seats']);

    if (!in_array($formData['brand'], $brandOptions, true)) {
        $formData['custom_brand'] = $formData['brand'];
        $formData['brand'] = 'Others';
    }
}

if (isset($_GET['added']) && $_GET['added'] === '1') {
    $success = 'Product added successfully.';
}

/*
    Edit mode image actions
*/
if ($isEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image_action'])) {
    $activeTab = 'imageTab';
    $imageAction = $_POST['image_action'];

    if ($imageAction === 'set_primary') {
        $galleryId = (int)($_POST['gallery_id'] ?? 0);

        if ($galleryId > 0) {
            try {
                $stmt = $pdo->prepare("
                    SELECT image_url
                    FROM buggy_images
                    WHERE id = ? AND buggy_id = ?
                ");
                $stmt->execute([$galleryId, $id]);
                $galleryImage = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($galleryImage) {
                    $stmt = $pdo->prepare("
                        UPDATE buggies
                        SET image_url = ?
                        WHERE id = ?
                    ");
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
                $stmt = $pdo->prepare("SELECT image_url FROM buggy_images WHERE id = ? AND buggy_id = ?");
                $stmt->execute([$galleryId, $id]);
                $removedUrl = (string)$stmt->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM buggy_images WHERE id = ? AND buggy_id = ?");
                $stmt->execute([$galleryId, $id]);

                $stmt = $pdo->prepare("SELECT image_url FROM buggies WHERE id = ?");
                $stmt->execute([$id]);
                $currentPrimary = (string)$stmt->fetchColumn();

                if ($removedUrl !== '' && trim($currentPrimary) === trim($removedUrl)) {
                    $stmt = $pdo->prepare("SELECT image_url FROM buggy_images WHERE buggy_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
                    $stmt->execute([$id]);
                    $nextPrimary = (string)$stmt->fetchColumn();

                    $stmt = $pdo->prepare("UPDATE buggies SET image_url = ? WHERE id = ?");
                    $stmt->execute([$nextPrimary !== '' ? $nextPrimary : '', $id]);
                }

                $success = 'Gallery image removed successfully.';
            } catch (PDOException $e) {
                $error = 'Failed to remove gallery image: ' . $e->getMessage();
            }
        }
    }

    if ($imageAction === 'delete_datasheet') {
        try {
            $stmt = $pdo->prepare("SELECT datasheet_url FROM buggies WHERE id = ?");
            $stmt->execute([$id]);
            $oldUrl = (string)$stmt->fetchColumn();
            if ($oldUrl !== '') {
                $oldPath = __DIR__ . '/../' . $oldUrl;
                if (is_file($oldPath)) @unlink($oldPath);
            }
            $stmt = $pdo->prepare("UPDATE buggies SET datasheet_url = NULL WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Datasheet removed.';
        } catch (PDOException $e) {
            $error = 'Failed to remove datasheet: ' . $e->getMessage();
        }
    }

    if ($imageAction === 'upload_gallery') {
        try {
            $uploadedUrls = uploadGalleryImages($pdo, $id, $error);

            if ($error === '') {
                if (count($uploadedUrls) > 0) {
                    $success = count($uploadedUrls) . ' image(s) uploaded successfully.';

                    $stmt = $pdo->prepare("SELECT image_url FROM buggies WHERE id = ?");
                    $stmt->execute([$id]);
                    $currentProduct = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($currentProduct && trim((string)$currentProduct['image_url']) === '') {
                        $stmt = $pdo->prepare("
                            UPDATE buggies
                            SET image_url = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$uploadedUrls[0], $id]);
                    }
                } else {
                    $error = 'Please select at least one image to upload.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Failed to upload images: ' . $e->getMessage();
        }
    }
}

/*
    Add or edit product information
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $activeTab = 'productInfoTab';

    foreach ($formData as $key => $value) {
        if ($key === 'image_url' || $key === 'specifications' || $key === 'datasheet_url') {
            continue;
        }
        $formData[$key] = trim($_POST[$key] ?? '');
    }

    $newDatasheetUrl = uploadDatasheetPdf($error);
    if ($error === '' && $newDatasheetUrl !== '') {
        $formData['datasheet_url'] = $newDatasheetUrl;
    } elseif ($error === '' && $isEdit) {
        // Keep whatever is already in DB if no new file uploaded
        $formData['datasheet_url'] = $product['datasheet_url'] ?? '';
    }

    // Parse dynamic specifications
    $specLabels = $_POST['spec_label'] ?? [];
    $specValues = $_POST['spec_value'] ?? [];
    $parsedSpecs = [];
    if (is_array($specLabels) && is_array($specValues)) {
        foreach ($specLabels as $i => $label) {
            $label = trim((string)$label);
            $value = trim((string)($specValues[$i] ?? ''));
            if ($label !== '' && $value !== '') {
                $parsedSpecs[] = ['label' => $label, 'value' => $value];
            }
        }
    }
    $formData['specifications'] = $parsedSpecs;

    $submitAction = $_POST['submit_action'] ?? 'insert';

    $brand = $formData['brand'];
    $customBrand = $formData['custom_brand'];
    $model = $formData['model'];
    $seats = normalizeCategory($formData['seats']);
    $buggyYear = $formData['buggy_year'] !== '' ? (int)$formData['buggy_year'] : null;
    $buggyCondition = $formData['buggy_condition'] ?: 'new';
    $status = $formData['status'] ?: 'active';
    $sellingPrice = (float)($formData['selling_price'] !== '' ? $formData['selling_price'] : 0);
    $formData['sort_new']  = (int)($_POST['sort_new']  ?? 0);
    $formData['sort_used'] = (int)($_POST['sort_used'] ?? 0);
    $remark = $formData['remark'];
    $serialNumber    = $formData['serial_number'];
    $shortInfo       = $formData['short_info'];
    $description     = $formData['description'];
    $specsJson       = !empty($formData['specifications']) ? json_encode($formData['specifications']) : null;
    $tag = $formData['tag'];
    $brandTag = $formData['brand_tag'];

    // Promo fields
    $promoEnabled       = isset($_POST['promo_enabled']) ? 1 : 0;
    $discountPrice = trim($_POST['discount_price'] ?? '');
    $discountPrice = $discountPrice !== '' ? (float)$discountPrice : null;
    $promoEndDate       = trim($_POST['promo_end_date'] ?? '');
    $promoEndDate       = $promoEndDate !== '' ? $promoEndDate : null;
    $promoLabel         = trim($_POST['promo_label'] ?? '');
    $promoLabel         = $promoLabel !== '' ? $promoLabel : null;

    // Sync formData for re-display on error
    $formData['promo_enabled']        = $promoEnabled;
    $formData['discount_price'] = $discountPrice ?? '';
    $formData['promo_end_date']       = $promoEndDate ?? '';
    $formData['promo_label']          = $promoLabel ?? '';

    if ($brand === 'Others' && $customBrand !== '') {
        $brand = $customBrand;
    }

    if ($brandTag === '') {
        $brandTag = $brand;
    }

    if ($tag === '') {
        $tag = ucfirst($buggyCondition);
    }

    if ($brand === '') {
        $error = 'Buggy brand is required.';
    } elseif ($model === '') {
        $error = 'Buggy model is required.';
    } elseif ($seats === '') {
        $error = 'Please select buggy category / seats.';
    } elseif ($buggyCondition === '') {
        $error = 'Please select buggy condition (New or Used).';
    } elseif ($promoEnabled === 1 && ($discountPrice === null || $discountPrice <= 0)) {
        $error = 'Please enter a Discount Price for the promo, or turn off the Limited Time Promo.';
    } elseif ($promoEnabled === 1 && $discountPrice >= $sellingPrice) {
        $error = 'Discount Price ($' . number_format($discountPrice, 0) . ') must be LOWER than the Selling Price ($' . number_format($sellingPrice, 0) . ').';
    }

    if ($error === '') {
        try {
            $name = $brand . ' ' . $model;

            if (!$isEdit) {
                $maxSortNewStmt = $pdo->query("SELECT COALESCE(MAX(sort_new), 0) + 1 FROM buggies WHERE buggy_condition = 'new'");
                $autoSortNew    = (int)$maxSortNewStmt->fetchColumn();

                $maxSortUsedStmt = $pdo->query("SELECT COALESCE(MAX(sort_used), 0) + 1 FROM buggies WHERE buggy_condition = 'used'");
                $autoSortUsed    = (int)$maxSortUsedStmt->fetchColumn();

                if ((int)($formData['sort_new'])  === 0) $formData['sort_new']  = $autoSortNew;
                if ((int)($formData['sort_used']) === 0) $formData['sort_used'] = $autoSortUsed;
            }

            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE buggies SET
                        brand = :brand,
                        model = :model,
                        name = :name,
                        seats = :seats,
                        buggy_year = :buggy_year,
                        buggy_condition = :buggy_condition,
                        selling_price = :selling_price,
                        sort_new = :sort_new,
                        sort_used = :sort_used,
                        remark = :remark,
                        serial_number    = :serial_number,
                        short_info       = :short_info,
                        description      = :description,
                        specifications   = :specifications,
                        promo_enabled        = :promo_enabled,
                        discount_price = :discount_price,
                        promo_end_date       = :promo_end_date,
                        promo_label          = :promo_label,
                        tag = :tag,
                        brand_tag = :brand_tag,
                        status = :status,
                        datasheet_url = :datasheet_url
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':datasheet_url' => $formData['datasheet_url'] !== '' ? $formData['datasheet_url'] : null,
                    ':brand' => $brand,
                    ':model' => $model,
                    ':name' => $name,
                    ':seats' => $seats,
                    ':buggy_year' => $buggyYear,
                    ':buggy_condition' => $buggyCondition,
                    ':selling_price' => $sellingPrice,
                    ':sort_new'      => (int)($formData['sort_new'] ?? 0),
                    ':sort_used'     => (int)($formData['sort_used'] ?? 0),
                    ':remark' => $remark,
                    ':serial_number'    => $serialNumber,
                    ':short_info'       => $shortInfo,
                    ':description'      => $description,
                    ':specifications'   => $specsJson,
                    ':promo_enabled'        => $promoEnabled,
                    ':discount_price' => $discountPrice,
                    ':promo_end_date'       => $promoEndDate,
                    ':promo_label'          => $promoLabel,
                    ':tag' => $tag,
                    ':brand_tag' => $brandTag,
                    ':status' => $status,
                    ':id' => $id
                ]);

                // Also upload any newly added gallery images on the same form submit
                $uploadedUrls = uploadGalleryImages($pdo, $id, $error);
                if ($error === '' && count($uploadedUrls) > 0) {
                    $stmt = $pdo->prepare("SELECT image_url FROM buggies WHERE id = ?");
                    $stmt->execute([$id]);
                    $currentProduct = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($currentProduct && trim((string)$currentProduct['image_url']) === '') {
                        $stmt = $pdo->prepare("UPDATE buggies SET image_url = ? WHERE id = ?");
                        $stmt->execute([$uploadedUrls[0], $id]);
                    }
                }

                $success = 'Product information updated successfully.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO buggies (
                        owner_type,
                        owner_id,
                        brand,
                        model,
                        name,
                        seats,
                        buggy_year,
                        listing_type,
                        buggy_condition,
                        selling_price,
                        sort_new,
                        sort_used,
                        remark,
                        serial_number,
                        short_info,
                        description,
                        specifications,
                        promo_enabled,
                        discount_price,
                        promo_end_date,
                        promo_label,
                        image_url,
                        tag,
                        brand_tag,
                        status,
                        datasheet_url,
                        is_fleet
                    ) VALUES (
                        :owner_type,
                        :owner_id,
                        :brand,
                        :model,
                        :name,
                        :seats,
                        :buggy_year,
                        'sale',
                        :buggy_condition,
                        :selling_price,
                        :sort_new,
                        :sort_used,
                        :remark,
                        :serial_number,
                        :short_info,
                        :description,
                        :specifications,
                        :promo_enabled,
                        :discount_price,
                        :promo_end_date,
                        :promo_label,
                        '',
                        :tag,
                        :brand_tag,
                        :status,
                        :datasheet_url,
                        :is_fleet
                    )
                ");

                $isFleetListing = (($_SESSION['admin_role'] ?? 'super_admin') === 'seller') ? 1 : 0;
                $stmt->execute([
                    ':datasheet_url' => $formData['datasheet_url'] !== '' ? $formData['datasheet_url'] : null,
                    ':is_fleet'   => $isFleetListing,
                    ':owner_type' => 'admin',
                    ':owner_id' => $_SESSION['admin_id'],
                    ':brand' => $brand,
                    ':model' => $model,
                    ':name' => $name,
                    ':seats' => $seats,
                    ':buggy_year' => $buggyYear,
                    ':buggy_condition' => $buggyCondition,
                    ':selling_price' => $sellingPrice,
                    ':sort_new'      => (int)($formData['sort_new'] ?? 0),
                    ':sort_used'     => (int)($formData['sort_used'] ?? 0),
                    ':remark' => $remark,
                    ':serial_number'    => $serialNumber,
                    ':short_info'       => $shortInfo,
                    ':description'      => $description,
                    ':specifications'   => $specsJson,
                    ':promo_enabled'        => $promoEnabled,
                    ':discount_price' => $discountPrice,
                    ':promo_end_date'       => $promoEndDate,
                    ':promo_label'          => $promoLabel,
                    ':tag' => $tag,
                    ':brand_tag' => $brandTag,
                    ':status' => $status
                ]);

                $newBuggyId = (int)$pdo->lastInsertId();

                $uploadedUrls = uploadGalleryImages($pdo, $newBuggyId, $error);

                if ($error === '' && count($uploadedUrls) > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE buggies
                        SET image_url = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$uploadedUrls[0], $newBuggyId]);
                }

                if ($error === '') {
                    if ($submitAction === 'insert_add') {
                        header('Location: product-form.php?added=1');
                        exit;
                    }

                    if ($submitAction === 'insert_exit') {
                        header('Location: product-list.php?added=1');
                        exit;
                    }

                    header('Location: product-form.php?id=' . $newBuggyId . '&added=1');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = $isEdit
                ? 'Failed to update product: ' . $e->getMessage()
                : 'Failed to add product: ' . $e->getMessage();
        }
    }
}

/*
    Refresh product and gallery after actions
*/
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM buggies WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    foreach ($formData as $key => $value) {
        if ($key === 'specifications') continue; // handled below
        if (array_key_exists($key, $product)) {
            $formData[$key] = $product[$key];
        }
    }

    // Decode specifications JSON
    if (!empty($product['specifications'])) {
        $decoded = json_decode($product['specifications'], true);
        $formData['specifications'] = is_array($decoded) ? $decoded : [];
    }

    $formData['seats'] = normalizeCategory($formData['seats']);

    if (!in_array($formData['brand'], $brandOptions, true)) {
        $formData['custom_brand'] = $formData['brand'];
        $formData['brand'] = 'Others';
    }

    $galleryStmt = $pdo->prepare("
        SELECT *
        FROM buggy_images
        WHERE buggy_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $galleryStmt->execute([$id]);
    $galleryImages = $galleryStmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'header.php';
?>

<style>
    .product-page-title {
        margin-bottom: 18px;
    }

    .product-page-title h1 {
        margin: 0;
        font-size: 30px;
        font-weight: 500;
        color: #333;
    }

    .breadcrumb {
        background: #eeeeee;
        padding: 12px 16px;
        margin-bottom: 22px;
        color: #999;
        font-size: 14px;
    }

    .breadcrumb span {
        color: #ef3f4d;
    }

    .form-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 22px;
    }

    .form-header h1 {
        margin: 0;
        font-size: 30px;
    }

    .form-header p {
        margin: 6px 0 0;
        color: #777;
        font-size: 15px;
    }

    .form-header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-light {
        background: #fff;
        color: #222;
        border: 1px solid #ddd;
    }

    .btn-light:hover {
        border-color: #ef3f4d;
        color: #ef3f4d;
        background: #fff;
    }

    .product-card {
        background: #fff;
        border-radius: 4px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        border: 1px solid #eee;
        overflow: hidden;
    }

    .top-actions,
    .bottom-actions {
        background: #fff;
        border-bottom: 1px solid #eeeeee;
        padding: 18px 20px;
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .bottom-actions {
        border-top: 1px solid #eeeeee;
        border-bottom: 0;
    }

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

    .action-btn:hover {
        background: #d92e3d;
        color: #fff;
    }

    .action-btn-light {
        background: #2f2f2f;
        color: #fff;
        border: 0;
    }

    .action-btn-light:hover {
        background: #1f1f1f;
        color: #fff;
    }

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

    .response-success {
        background: #e8fff0;
        color: #167a3c;
        border: 1px solid #b8e8c8;
    }

    .response-error {
        background: #fff0f2;
        color: #ef3f4d;
        border: 1px solid #ffc4cc;
    }

    .response-box small {
        display: block;
        margin-top: 5px;
        font-weight: normal;
        color: inherit;
        opacity: 0.8;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-12px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Tabs hidden — Info and Images now display on the same page */
    .product-tabs { display: none; }
    .product-tab-btn { display: none; }
    .tab-panel { display: block; padding: 22px; }

    .form-grid {
        display: block;
    }

    .form-group {
        width: 40%;
        margin-bottom: 16px;
    }

    .form-group.full {
        width: 100%;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
    }

    .form-group textarea {
        min-height: 110px;
    }

    .section-title {
        width: 100%;
        margin-top: 28px;
        margin-bottom: 16px;
        padding-top: 22px;
        border-top: 1px solid #eeeeee;
        font-size: 20px;
        font-weight: bold;
    }

    .section-title:first-child {
        margin-top: 0;
    }

    .help {
        color: #777;
        margin-top: 6px;
        font-size: 13px;
        line-height: 1.45;
    }

    .form-actions,
    .image-form-actions {
        margin-top: 32px;
        padding-top: 22px;
        border-top: 1px solid #eeeeee;
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .image-tab-content {
        min-height: 460px;
    }

    .image-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
    }

    .image-toolbar h2 {
        margin: 0;
        font-size: 22px;
    }

    .image-count {
        color: #777;
        font-size: 14px;
    }

    .main-image-box {
        border: 1px solid #eee;
        border-radius: 14px;
        padding: 18px;
        margin-bottom: 24px;
        background: #fafafa;
    }

    .main-image-title {
        font-weight: bold;
        margin-bottom: 12px;
    }

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

    .gallery-card {
        border: 1px solid #eee;
        border-radius: 10px;
        padding: 10px;
        background: #fff;
    }

    .gallery-actions {
        display: flex;
        gap: 6px;
        margin-bottom: 10px;
        flex-wrap: nowrap;
        align-items: center;
    }

    .gallery-actions form {
        display: inline-flex !important;
        margin: 0;
    }

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

    .set-primary-btn {
        background: #7dd3e8;
    }

    .set-primary-btn:hover {
        background: #59bfd8;
    }

    .remove-btn {
        background: #ef8585;
    }

    .remove-btn:hover {
        background: #ef3f4d;
    }

    .gallery-image {
        width: 208px;
        height: 208px;
        object-fit: cover;
        background: #fff;
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

    .upload-area.dragover {
        border-color: #ef3f4d;
        background: #fff7f8;
        color: #ef3f4d;
    }

    .add-image-row {
        display: block;
    }

    .upload-preview-area {
        display: flex;
        flex-wrap: wrap;
        gap: 18px 30px;
        margin-top: 14px;
        align-items: flex-start;
        max-width: 1100px;
    }

    .upload-card {
        width: 230px;
        position: relative;
    }

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

    .upload-box:hover {
        border-color: #ef3f4d;
    }

    .upload-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #999;
        width: 100%;
        height: 100%;
    }

    .upload-icon {
        font-size: 48px;
        line-height: 1;
        margin-bottom: 8px;
    }

    .upload-label {
        background: #999;
        color: #fff;
        padding: 6px 10px;
        border-radius: 4px;
        display: inline-block;
        font-size: 13px;
    }

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
        top: 0;
        right: 0;
        width: 24px;
        height: 24px;
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

    .remove-preview-btn:hover {
        background: #d92e3d;
    }

    .add-button-holder {
        display: flex;
        align-items: flex-start;
        width: auto;
        padding-top: 0;
    }

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
        margin-top: 0;
    }

    .add-image-side-btn:hover {
        background: #59bfd8;
        transform: translateY(-1px);
    }

    .image-description-input {
        width: 100%;
        height: 34px;
        min-height: 34px;
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 0 10px;
        font-size: 14px;
        font-style: italic;
        margin-top: 6px;
    }

    .image-input-hidden {
        display: none;
    }

    .form-note {
        color: #777;
        margin-top: 18px;
        font-size: 14px;
    }

    @media (max-width: 1200px) {
        .gallery-grid {
            grid-template-columns: repeat(3, 230px);
        }
    }

    @media (max-width: 900px) {
        .form-group {
            width: 100%;
        }

        .gallery-grid {
            grid-template-columns: repeat(2, 230px);
        }

        .upload-preview-area {
            max-width: 420px;
        }
    }

    @media (max-width: 700px) {
        .form-header {
            display: block;
        }

        .form-header-actions {
            margin-top: 14px;
        }

        .gallery-grid {
            grid-template-columns: 1fr;
        }

        .upload-preview-area {
            display: grid;
            grid-template-columns: 1fr;
            max-width: none;
        }

        .gallery-card,
        .upload-card,
        .upload-box,
        .preview-upload-img {
            width: 100%;
        }

        .upload-box,
        .preview-upload-img {
            height: 220px;
        }

        .gallery-image {
            width: 100%;
            height: 220px;
        }

        .response-box {
            left: 18px;
            right: 18px;
            top: 18px;
            min-width: auto;
            max-width: none;
        }
    }
</style>

<div class="product-page-title">
    <h1><?php echo $isEdit ? 'Edit Product' : '[New] Products'; ?></h1>
</div>

<div class="breadcrumb">
    <span>Products</span> &gt; <?php echo $isEdit ? 'Edit Product' : 'Products'; ?>
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
            <h1>Edit Buggy Product</h1>
            <p>Update product information and manage product images.</p>
        </div>

        <div class="form-header-actions">
            <a href="product-list.php" class="btn btn-light">Back to Product List</a>
            <a href="product-view.php?id=<?php echo (int)$id; ?>" class="btn btn-light">View Product</a>
        </div>
    </div>
<?php endif; ?>

<div class="product-card">
    <?php if (!$isEdit): ?>
        <form method="post" enctype="multipart/form-data" id="productForm">
            <input type="hidden" name="save_product" value="1">
            <input type="hidden" name="submit_action" id="submitActionInput" value="insert">

            <div class="top-actions">
                <button type="submit" class="action-btn" data-action="insert">Save and Upload</button>
                <a href="product-list.php" class="action-btn action-btn-light">Cancel</a>
            </div>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data" id="productForm">
            <input type="hidden" name="save_product" value="1">
            <input type="hidden" name="submit_action" id="submitActionInput" value="update">
    <?php endif; ?>

    <div class="product-tabs">
        <button
            type="button"
            class="product-tab-btn <?php echo $activeTab === 'productInfoTab' ? 'active' : ''; ?>"
            data-tab="productInfoTab"
        >
            Product Info
        </button>

        <button
            type="button"
            class="product-tab-btn <?php echo $activeTab === 'imageTab' ? 'active' : ''; ?>"
            data-tab="imageTab"
        >
            Image<?php echo $isEdit ? ' (' . (count($galleryImages) + (!empty($formData['image_url']) ? 1 : 0)) . ')' : ''; ?>
        </button>
    </div>

    <div id="productInfoTab" class="tab-panel <?php echo $activeTab === 'productInfoTab' ? 'active' : ''; ?>">

        <div class="form-grid">
            <div class="section-title">Buggy Information</div>

            <div class="form-group">
                <label>Status Managed By Admin</label>
                <select name="status">
                    <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
                        <option value="<?php echo e($statusValue); ?>" <?php echo $formData['status'] === $statusValue ? 'selected' : ''; ?>>
                            <?php echo e($statusLabel); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Buggy Brand *</label>
                <select name="brand" required>
                    <option value="">Select brand</option>

                    <?php foreach ($brandOptions as $brandOption): ?>
                        <option value="<?php echo e($brandOption); ?>" <?php echo $formData['brand'] === $brandOption ? 'selected' : ''; ?>>
                            <?php echo e($brandOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Custom Brand</label>
                <input
                    type="text"
                    name="custom_brand"
                    placeholder="Only fill if brand is Others"
                    value="<?php echo e($formData['custom_brand']); ?>"
                >
            </div>

            <div class="form-group">
                <label>Serial Number <span style="color:#777;font-weight:normal;">(internal only)</span></label>
                <input
                    type="text"
                    name="serial_number"
                    placeholder="Example: SN-2024-001"
                    value="<?php echo e($formData['serial_number']); ?>"
                >
                <div class="help">Visible to admin and seller only. Not shown on public pages.</div>
            </div>

            <div class="form-group">
                <label>Product Name *</label>
                <input
                    type="text"
                    name="model"
                    placeholder="Example: Tempo 4-Seater"
                    value="<?php echo e($formData['model']); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Buggy Category / Seats *</label>
                <select name="seats" required>
                    <option value="">Select category / seats</option>

                    <?php foreach ($categoryOptions as $categoryOption): ?>
                        <option value="<?php echo e($categoryOption); ?>" <?php echo $formData['seats'] === $categoryOption ? 'selected' : ''; ?>>
                            <?php echo e($categoryOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Condition *</label>
               <select name="buggy_condition" id="buggyCondition" required>
                    <?php if (!$isEdit): ?>
                        <option value="">Select condition</option>
                    <?php endif; ?>
                    <option value="new"  <?php echo $formData['buggy_condition'] === 'new'  ? 'selected' : ''; ?>>New Buggy</option>
                    <option value="used" <?php echo $formData['buggy_condition'] === 'used' ? 'selected' : ''; ?>>Used Buggy</option>
                </select>
            </div>

            <div class="form-group" id="yearGroup">
                <label>Buggy Year</label>
                <select name="buggy_year">
                    <option value="">Select year</option>

                    <?php for ($year = $startYear; $year >= $endYear; $year--): ?>
                        <option value="<?php echo (int)$year; ?>" <?php echo (string)$formData['buggy_year'] === (string)$year ? 'selected' : ''; ?>>
                            <?php echo (int)$year; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <div class="help">Only applicable for Used buggies.</div>
            </div>

            <input type="hidden" name="listing_type" value="sale">

        <div class="section-title">Selling Price</div>

            <div class="form-group">
                <label>Selling Price ($)</label>
                <input
                    type="number"
                    step="0.01"
                    name="selling_price"
                    placeholder="Example: 8500"
                    value="<?php echo e($formData['selling_price']); ?>"
                >
                <div class="help">The normal price (e.g. 5000). During a promo this is shown crossed out.</div>
            </div>

            <?php if (false): /* Limited Time Promo hidden — flip to true to re-enable */ ?>
            <div class="section-title">🔥 Limited Time Promo</div>

            <div class="form-group full">
                <label style="display:flex;align-items:center;gap:8px;background:#fff7ed;padding:14px 16px;border:1px solid #fdba74;border-radius:8px;cursor:pointer;">
                    <input type="checkbox" name="promo_enabled" value="1" id="promoEnabled" style="width:18px;height:18px;"
                        <?php echo (int)$formData['promo_enabled'] === 1 ? 'checked' : ''; ?>>
                    <span style="font-weight:700;color:#9a3412;">Enable Limited Time Promo</span>
                </label>
            </div>

            <div id="promoFields" style="<?php echo (int)$formData['promo_enabled'] === 1 ? '' : 'display:none;'; ?>">
                <div class="form-group">
                    <label>Discount Price ($)</label>
                    <input
                        type="number"
                        step="0.01"
                        name="discount_price"
                        placeholder="Example: 15000"
                        value="<?php echo e($formData['discount_price']); ?>"
                    >
                    <div class="help">The promotional (lower) price customers pay during the promo, e.g. 3500. Must be lower than the Selling Price. Your Selling Price above is shown crossed out.</div>
                </div>

                <div class="form-group">
                    <label>Promo End Date & Time</label>
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
                        placeholder="Example: Year End Sale, Flash Deal"
                        value="<?php echo e($formData['promo_label']); ?>"
                    >
                    <div class="help">Leave empty to show default "🔥 PROMO" badge.</div>
                </div>
            </div>

            <script>
                (function () {
                    const cb = document.getElementById('promoEnabled');
                    const fields = document.getElementById('promoFields');
                    if (cb && fields) {
                        cb.addEventListener('change', function () {
                            fields.style.display = this.checked ? 'block' : 'none';
                        });
                    }
                })();
            </script>
            <?php endif; /* end Limited Time Promo block */ ?>

           <div class="section-title">Sort Order</div>

            <div class="form-group" id="sortNewGroup" style="display:none;">
                <label>Sort Order — New Buggy</label>
                <input type="number" name="sort_new" min="0" value="<?php echo (int)$formData['sort_new']; ?>">
                <div class="help">Controls display order on New Buggy page. Higher = appears first. Auto-filled if left as 0.</div>
            </div>

            <div class="form-group" id="sortUsedGroup" style="display:none;">
                <label>Sort Order — Used Buggy</label>
                <input type="number" name="sort_used" min="0" value="<?php echo (int)$formData['sort_used']; ?>">
                <div class="help">Controls display order on Used Buggy page. Higher = appears first. Auto-filled if left as 0.</div>
            </div>

            <div class="section-title">Internal Information</div>

            <div class="form-group full">
                <label>Remark / Internal Information</label>
                <textarea
                    name="remark"
                    placeholder="Seller name, phone number, preferred contact time, buggy location, ownership notes, or other internal remarks. This will not be shown publicly."
                ><?php echo e($formData['remark']); ?></textarea>

                <div class="help">
                    This remark is for SGBUGGYMART/admin reference only. Buyers will contact SGBUGGYMART for consultation.
                </div>
            </div>

            <div class="section-title">Technical Specifications</div>

            <div class="form-group full">
                <div class="help" style="margin-bottom:12px;">Add any specs you want — Power Source, Horse Power, Tyre Size, etc. Leave empty if not applicable.</div>

                <div id="specsList" class="specs-list">
                    <?php if (!empty($formData['specifications']) && is_array($formData['specifications'])): ?>
                        <?php foreach ($formData['specifications'] as $spec): ?>
                            <div class="spec-row">
                                <input type="text" name="spec_label[]" placeholder="Label (e.g. Power Source)" value="<?php echo e($spec['label'] ?? ''); ?>">
                                <input type="text" name="spec_value[]" placeholder="Value (e.g. Electrical)" value="<?php echo e($spec['value'] ?? ''); ?>">
                                <button type="button" class="spec-remove-btn" onclick="this.parentElement.remove();">×</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button type="button" id="addSpecBtn" class="spec-add-btn">+ Add Specification</button>
            </div>

            <style>
                .specs-list { display: grid; gap: 10px; margin-bottom: 12px; }
                .spec-row {
                    display: grid;
                    grid-template-columns: 1fr 1.5fr 40px;
                    gap: 10px;
                    align-items: center;
                }
                .spec-row input {
                    height: 42px;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    padding: 0 12px;
                    font-size: 14px;
                    outline: none;
                    background: #fff;
                }
                .spec-row input:focus { border-color: #ef3f4d; }
                .spec-remove-btn {
                    width: 36px; height: 36px;
                    border: 0;
                    background: #ef3f4d;
                    color: #fff;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 18px;
                    font-weight: bold;
                    line-height: 1;
                    transition: 0.2s ease;
                }
                .spec-remove-btn:hover { background: #d92e3d; }
                .spec-add-btn {
                    background: #2563eb;
                    color: #fff;
                    border: 0;
                    padding: 10px 18px;
                    border-radius: 7px;
                    cursor: pointer;
                    font-weight: 700;
                    font-size: 14px;
                    transition: 0.2s ease;
                }
                .spec-add-btn:hover { background: #1d4ed8; }
                @media (max-width: 700px) {
                    .spec-row { grid-template-columns: 1fr; }
                }
            </style>

            <script>
                document.getElementById('addSpecBtn').addEventListener('click', function () {
                    const row = document.createElement('div');
                    row.className = 'spec-row';
                    row.innerHTML = '<input type="text" name="spec_label[]" placeholder="Label (e.g. Power Source)">'
                                  + '<input type="text" name="spec_value[]" placeholder="Value (e.g. Electrical)">'
                                  + '<button type="button" class="spec-remove-btn" onclick="this.parentElement.remove();">&times;</button>';
                    document.getElementById('specsList').appendChild(row);
                });
            </script>

            <div class="form-group">
                <label>Brand Tag</label>
                <select name="brand_tag">
                    <option value="">Auto use selected brand</option>

                    <?php foreach ($brandOptions as $brandOption): ?>
                        <?php if ($brandOption !== 'Others'): ?>
                            <option value="<?php echo e($brandOption); ?>" <?php echo $formData['brand_tag'] === $brandOption ? 'selected' : ''; ?>>
                                <?php echo e($brandOption); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Product Tag</label>
                <select name="tag">
                    <option value="">Auto use New/Used</option>

                    <?php foreach ($productTagOptions as $tagOption): ?>
                        <option value="<?php echo e($tagOption); ?>" <?php echo $formData['tag'] === $tagOption ? 'selected' : ''; ?>>
                            <?php echo e($tagOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group full">
                <label>Short Info</label>
                <input
                    type="text"
                    name="short_info"
                    placeholder="Example: 4-seater electric buggy suitable for resorts and commercial use"
                    value="<?php echo e($formData['short_info']); ?>"
                >
            </div>

            <div class="form-group full">
                <label>Product Description</label>
                <textarea name="description" placeholder="Write product details here"><?php echo e($formData['description']); ?></textarea>
            </div>

            <div class="section-title">Datasheet (PDF)</div>

            <div class="form-group full">
                <label>Product Datasheet (PDF, max 5MB)</label>
                <?php if (!empty($formData['datasheet_url'])): ?>
                    <div style="margin-bottom:10px;display:flex;align-items:center;gap:14px;">
                        <a href="<?php echo e('../' . $formData['datasheet_url']); ?>" target="_blank" style="color:#ef3f4d;font-weight:600;">
                            📄 View current datasheet
                        </a>
                        <button type="button"
                                onclick="deleteDatasheet()"
                                style="background:#ef3f4d;color:#fff;border:0;padding:6px 14px;border-radius:6px;font-weight:700;cursor:pointer;">
                            Remove
                        </button>
                    </div>
                <?php endif; ?>
                <input type="file" name="datasheet_file" accept="application/pdf" style="display:flex;align-items:center;padding:10px 12px;">
                <div class="help">Upload a PDF datasheet. Leave empty to keep the existing file.</div>
            </div>
        </div>

    </div>

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
                                    <span class="primary-badge">✓ Primary</span>
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
                                src="<?php echo e(productImagePath($gallery['image_url'])); ?>"
                                alt="Gallery Image"
                                class="gallery-image"
                                onerror="this.src='../images/no-image.png';"
                            >

                            <input type="text" class="image-description-input" placeholder="Description" disabled>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="image-tab-content">
        <?php endif; ?>

            <div class="upload-area" id="dropArea">
                Drag & drop multiple images here.
            </div>

            <div class="add-image-row">
                <div class="upload-preview-area" id="galleryUploadArea">
                    <div class="upload-card">
                        <button type="button" class="remove-preview-btn">×</button>

                        <label class="upload-box">
                            <div class="upload-placeholder">
                                <div class="upload-icon">▧</div>
                                <div class="upload-label">click here to upload</div>
                            </div>

                            <input
                                type="file"
                                name="gallery_images[]"
                                class="image-input-hidden single-gallery-input"
                                accept="image/*"
                            >
                        </label>

                        <input type="text" class="image-description-input" placeholder="Description" disabled>
                    </div>

                    <div class="add-button-holder">
                        <button type="button" class="add-image-side-btn" id="addGalleryBoxBtn">
                            + Add
                        </button>
                    </div>
                </div>
            </div>

            <?php if (!$isEdit): ?>
                <div class="form-note">
                    First uploaded image will become the primary product image. Click <strong>+ Add</strong> to create more empty image boxes.
                </div>
            </div>
            <?php endif; ?>

    </div>

    <?php if ($isEdit): ?>
        <div class="bottom-actions">
            <button type="submit" class="action-btn">Save Changes</button>
            <a href="product-list.php" class="action-btn action-btn-light">Cancel</a>
        </div>
        </form>
    <?php else: ?>
        <div class="bottom-actions">
            <button type="submit" class="action-btn" data-action="insert">Save and Upload</button>
            <a href="product-list.php" class="action-btn action-btn-light">Cancel</a>
        </div>
        </form>
    <?php endif; ?>
</div>

<script>
    function deleteDatasheet() {
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

    const tabButtons = document.querySelectorAll('.product-tab-btn');
    const tabPanels = document.querySelectorAll('.tab-panel');
    const submitActionInput = document.getElementById('submitActionInput');

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const targetTab = this.getAttribute('data-tab');

            tabButtons.forEach(function (btn) {
                btn.classList.remove('active');
            });

            tabPanels.forEach(function (panel) {
                panel.classList.remove('active');
            });

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

            setTimeout(function () {
                responseBox.remove();
            }, 300);
        }, 3000);
    }

    const galleryUploadArea = document.getElementById('galleryUploadArea');
    const addGalleryBoxBtn = document.getElementById('addGalleryBoxBtn');
    const dropArea = document.getElementById('dropArea');

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

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (this.files && this.files[0]) {
                    if (!checkImageSize(this.files[0])) { this.value = ''; return; }
                    showImageInCard(card, this.files[0]);
                }
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                card.remove();
                if (galleryUploadArea.querySelectorAll('.upload-card').length === 0) {
                    galleryUploadArea.insertBefore(createGalleryUploadCard(), getAddButtonHolder());
                }
            });
        }
    }

    /*
    |------------------------------------------------------------------
    | showImageInCard — NO DataTransfer, NO createObjectURL
    | Replaces <label> with <div> after selection so tapping the
    | preview on mobile does NOT re-open the file picker (flash fix).
    | Uses FileReader — safe on iOS Safari and all Android browsers.
    |------------------------------------------------------------------
    */
    function showImageInCard(card, file) {
        const label = card.querySelector('.upload-box');

        // Move file input outside label — stops label click re-triggering picker
        const existingInput = label ? label.querySelector('input[type="file"]') : null;
        if (existingInput) {
            existingInput.remove();
            existingInput.style.display = 'none';
            card.appendChild(existingInput);
            existingInput.addEventListener('change', function () {
                if (this.files && this.files[0]) {
                    if (!checkImageSize(this.files[0])) { this.value = ''; return; }
                    showImageInCard(card, this.files[0]);
                }
            });
        }

        // Replace <label> with plain <div> so preview tap does nothing
        const previewDiv = document.createElement('div');
        previewDiv.className = 'upload-box';
        previewDiv.style.cssText = 'cursor:default; padding:0; overflow:hidden; position:relative;';

        const img = document.createElement('img');
        img.className = 'preview-upload-img';
        img.alt = 'Selected image';
        img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;pointer-events:none;';

        const reader = new FileReader();
        reader.onload = function (e) { img.src = e.target.result; };
        reader.readAsDataURL(file);

        previewDiv.appendChild(img);

        if (label && label.parentNode) {
            label.parentNode.replaceChild(previewDiv, label);
        }
    }

    function createGalleryUploadCard(file = null) {
        const card = document.createElement('div');
        card.className = 'upload-card';

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-preview-btn';
        removeBtn.innerHTML = '×';

        const label = document.createElement('label');
        label.className = 'upload-box';

        const placeholder = document.createElement('div');
        placeholder.className = 'upload-placeholder';
        placeholder.innerHTML = `
            <div class="upload-icon">▧</div>
            <div class="upload-label">click here to upload</div>
        `;

        const input = document.createElement('input');
        input.type = 'file';
        input.name = 'gallery_images[]';
        input.className = 'image-input-hidden single-gallery-input';
        input.accept = 'image/*';

        const desc = document.createElement('input');
        desc.type = 'text';
        desc.className = 'image-description-input';
        desc.placeholder = 'Description';
        desc.disabled = true;

        label.appendChild(placeholder);
        label.appendChild(input);

        card.appendChild(removeBtn);
        card.appendChild(label);
        card.appendChild(desc);

        bindUploadCard(card);

        if (file) {
            showImageInCard(card, file);
        }

        return card;
    }

    if (galleryUploadArea && addGalleryBoxBtn && dropArea) {
        document.querySelectorAll('.upload-card').forEach(function (card) {
            bindUploadCard(card);
        });

        addGalleryBoxBtn.addEventListener('click', function () {
            galleryUploadArea.insertBefore(createGalleryUploadCard(), getAddButtonHolder());
        });

        dropArea.addEventListener('dragover', function (event) {
            event.preventDefault();
            dropArea.classList.add('dragover');
        });

        dropArea.addEventListener('dragleave', function () {
            dropArea.classList.remove('dragover');
        });

        dropArea.addEventListener('drop', function (event) {
            event.preventDefault();
            dropArea.classList.remove('dragover');

            Array.from(event.dataTransfer.files).forEach(function (file) {
                if (file.type.startsWith('image/')) {
                    galleryUploadArea.insertBefore(createGalleryUploadCard(file), getAddButtonHolder());
                }
            });
        });
    }
    
    // Show/hide sort order + year fields based on condition
    const buggyCondition  = document.getElementById('buggyCondition');
    const sortNewGroup    = document.getElementById('sortNewGroup');
    const sortUsedGroup   = document.getElementById('sortUsedGroup');
    const yearGroup       = document.getElementById('yearGroup');

    function updateConditionVisibility() {
        if (!buggyCondition) return;
        const val = buggyCondition.value;

        if (sortNewGroup)  sortNewGroup.style.display  = val === 'new'  ? 'block' : 'none';
        if (sortUsedGroup) sortUsedGroup.style.display = val === 'used' ? 'block' : 'none';

        // Year only for Used buggies
        if (yearGroup) {
            yearGroup.style.display = val === 'used' ? 'block' : 'none';
            if (val === 'new') {
                const yearSelect = yearGroup.querySelector('select[name="buggy_year"]');
                if (yearSelect) yearSelect.value = '';
            }
        }
    }

    if (buggyCondition) {
        buggyCondition.addEventListener('change', updateConditionVisibility);
        updateConditionVisibility();
    }
</script>

<?php include 'footer.php'; ?>