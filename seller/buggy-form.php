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

$success   = '';
$error     = '';
$activeTab = 'productInfoTab';

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

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

function normalizeSellerCategory($value)
{
    $value = trim((string)$value);
    if ($value === '2') return '2 seater';
    if ($value === '3') return '3 seater';
    if ($value === '4') return '4 seater';
    if ($value === '6') return '6 seater';
    if ($value === '8') return '8 seater';
    return $value;
}

function uploadSellerGalleryImages($pdo, $buggyId, &$error)
{
    if (!isset($_FILES['gallery_images']) || empty($_FILES['gallery_images']['name'][0])) {
        return [];
    }

    $uploadDir = __DIR__ . '/../images/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

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

        $safeName    = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
        $newFileName = 'seller-gallery-' . $safeName . '-' . time() . '-' . rand(1000, 9999) . '-' . $key . '.' . $extension;
        $targetPath  = $uploadDir . $newFileName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            $error = 'Failed to upload image.';
            return $uploadedUrls;
        }

        $imageUrl = 'images/' . $newFileName;
        $stmt = $pdo->prepare("INSERT INTO buggy_images (buggy_id, image_url) VALUES (:buggy_id, :image_url)");
        $stmt->execute([':buggy_id' => $buggyId, ':image_url' => $imageUrl]);
        $uploadedUrls[] = $imageUrl;
    }

    return $uploadedUrls;
}

try {
    $sellerStmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.contact_no, s.email, s.seller_status, s.payment_status, s.status, s.package_id,
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

    if ((int)$seller['status'] !== 1 || $seller['seller_status'] !== 'active') {
        header('Location: dashboard.php');
        exit;
    }

    // Get listing limit from package; fall back to 10 if no package
    $sellerListingLimit = !empty($seller['pkg_listing_limit']) ? (int)$seller['pkg_listing_limit'] : 10;

    if (!$isEdit) {
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM buggies
            WHERE owner_type = 'seller' AND owner_id = ?
        ");
        $countStmt->execute([$sellerId]);
        $listingCount = (int)$countStmt->fetchColumn();

        if ($listingCount >= $sellerListingLimit) {
            header('Location: dashboard.php');
            exit;
        }
    }
} catch (PDOException $e) {
    die('Failed to load seller account: ' . $e->getMessage());
}

$brandOptions = [];
try {
    $brandStmt = $pdo->prepare("
        SELECT brand_name FROM product_brands
        WHERE status = 'active' ORDER BY brand_name ASC
    ");
    $brandStmt->execute();
    $brandOptions = $brandStmt->fetchAll(PDO::FETCH_COLUMN);
    if (!is_array($brandOptions)) $brandOptions = [];
} catch (PDOException $e) {
    $brandOptions = [];
}
$brandOptions[] = 'Others';

$categoryOptions = ['2 seater', '3 seater', '4 seater', '6 seater', '8 seater'];

$defaultRemark = '';
if (!$isEdit) {
    $defaultRemarkParts = [];
    if (!empty($seller['full_name']))  $defaultRemarkParts[] = 'Seller name: ' . $seller['full_name'];
    if (!empty($seller['contact_no'])) $defaultRemarkParts[] = 'Seller phone / WhatsApp: ' . $seller['contact_no'];
    $defaultRemarkParts[] = 'Preferred contact time: Anytime';
    $defaultRemark = implode("\n", $defaultRemarkParts);
}

$formData = [
    'brand'           => '',
    'custom_brand'    => '',
    'model'           => '',
    'seats'           => '',
    'buggy_year'      => '',
    'buggy_condition' => 'used',
    'selling_price'   => '',
    'serial_number'   => '',
    'remark'          => $defaultRemark,
    'short_info'      => '',
    'description'     => '',
    'specifications'  => [],
    'image_url'       => '',
    'status'          => 'active',
];

$currentYear = (int)date('Y');
$startYear   = $currentYear;
$endYear     = 2016;

$product       = null;
$galleryImages = [];

if ($isEdit) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM buggies
            WHERE id = ? AND owner_type = 'seller' AND owner_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $sellerId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            die('Buggy listing not found or you do not have permission to edit this listing.');
        }

        $formData['brand']           = $product['brand']           ?? '';
        $formData['custom_brand']    = '';
        $formData['model']           = $product['model']           ?? '';
        $formData['seats']           = normalizeSellerCategory($product['seats'] ?? '');
        $formData['buggy_year']      = $product['buggy_year']      ?? '';
        $formData['buggy_condition'] = $product['buggy_condition'] ?? 'used';
        $formData['selling_price']   = $product['selling_price']   ?? '';
        $formData['serial_number']   = $product['serial_number']   ?? '';
        $formData['remark']          = $product['remark']          ?? '';
        $formData['short_info']      = $product['short_info']      ?? '';
        $formData['description']     = $product['description']     ?? '';
        $formData['image_url']       = $product['image_url']       ?? '';
        $formData['status']          = $product['status']          ?? 'active';

        if (!empty($product['specifications'])) {
            $decoded = json_decode($product['specifications'], true);
            $formData['specifications'] = is_array($decoded) ? $decoded : [];
        }

        if (!in_array($formData['brand'], $brandOptions, true)) {
            $formData['custom_brand'] = $formData['brand'];
            $formData['brand']        = 'Others';
        }

        $galleryStmt = $pdo->prepare("
            SELECT * FROM buggy_images WHERE buggy_id = ? ORDER BY sort_order ASC, id ASC
        ");
        $galleryStmt->execute([$id]);
        $galleryImages = $galleryStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die('Failed to load buggy listing: ' . $e->getMessage());
    }
}

if (isset($_GET['added'])   && $_GET['added']   === '1') $success = 'Buggy listing added successfully. It is now live!';
if (isset($_GET['updated']) && $_GET['updated'] === '1') $success = 'Buggy listing updated successfully.';

/* IMAGE ACTIONS */
if ($isEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image_action'])) {
    $activeTab   = 'imageTab';
    $imageAction = $_POST['image_action'];

    if ($imageAction === 'set_primary') {
        $galleryId = (int)($_POST['gallery_id'] ?? 0);
        if ($galleryId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT image_url FROM buggy_images WHERE id = ? AND buggy_id = ?");
                $stmt->execute([$galleryId, $id]);
                $galleryImage = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($galleryImage) {
                    $stmt = $pdo->prepare("
                        UPDATE buggies SET image_url = ?, updated_at = NOW()
                        WHERE id = ? AND owner_type = 'seller' AND owner_id = ?
                    ");
                    $stmt->execute([$galleryImage['image_url'], $id, $sellerId]);
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
                $stmt = $pdo->prepare("DELETE FROM buggy_images WHERE id = ? AND buggy_id = ?");
                $stmt->execute([$galleryId, $id]);
                $success = 'Gallery image removed successfully.';
            } catch (PDOException $e) {
                $error = 'Failed to remove gallery image: ' . $e->getMessage();
            }
        }
    }

    if ($imageAction === 'upload_gallery') {
        try {
            $uploadedUrls = uploadSellerGalleryImages($pdo, $id, $error);
            if ($error === '') {
                if (count($uploadedUrls) > 0) {
                    $success = count($uploadedUrls) . ' image(s) uploaded successfully.';

                    $stmt = $pdo->prepare("SELECT image_url FROM buggies WHERE id = ? AND owner_type = 'seller' AND owner_id = ?");
                    $stmt->execute([$id, $sellerId]);
                    $currentProduct = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($currentProduct && trim((string)$currentProduct['image_url']) === '') {
                        $stmt = $pdo->prepare("
                            UPDATE buggies SET image_url = ?, updated_at = NOW()
                            WHERE id = ? AND owner_type = 'seller' AND owner_id = ?
                        ");
                        $stmt->execute([$uploadedUrls[0], $id, $sellerId]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE buggies SET updated_at = NOW()
                            WHERE id = ? AND owner_type = 'seller' AND owner_id = ?
                        ");
                        $stmt->execute([$id, $sellerId]);
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

/* SAVE PRODUCT */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $activeTab      = 'productInfoTab';
    $brand          = trim($_POST['brand']           ?? '');
    $customBrand    = trim($_POST['custom_brand']    ?? '');
    $model          = trim($_POST['model']           ?? '');
    $seats          = normalizeSellerCategory($_POST['seats'] ?? '');
    $buggyCondition = trim($_POST['buggy_condition'] ?? 'used');
    $sellerStatus   = trim($_POST['status']          ?? 'active');
    $discountPrice   = (float)($_POST['selling_price'] ?? 0);
    $buggyYear      = (isset($_POST['buggy_year']) && $_POST['buggy_year'] !== '') ? (int)$_POST['buggy_year'] : null;
    if ($buggyCondition === 'new') $buggyYear = null; // year only for used
    $serialNumber   = trim($_POST['serial_number']   ?? '');
    $remark         = trim($_POST['remark']          ?? '');
    $shortInfo      = trim($_POST['short_info']      ?? '');
    $description    = trim($_POST['description']     ?? '');
    $submitAction   = $_POST['submit_action']        ?? 'insert';

    // Parse dynamic specifications
    $specLabels = $_POST['spec_label'] ?? [];
    $specValues = $_POST['spec_value'] ?? [];
    $parsedSpecs = [];
    if (is_array($specLabels) && is_array($specValues)) {
        foreach ($specLabels as $i => $label) {
            $label = trim((string)$label);
            $val   = trim((string)($specValues[$i] ?? ''));
            if ($label !== '' && $val !== '') {
                $parsedSpecs[] = ['label' => $label, 'value' => $val];
            }
        }
    }
    $specsJson = !empty($parsedSpecs) ? json_encode($parsedSpecs) : null;

    // Validate condition and status
    if (!in_array($buggyCondition, ['new', 'used'], true)) $buggyCondition = 'used';
    if (!in_array($sellerStatus, ['active', 'inactive', 'sold'], true)) $sellerStatus = 'active';

    // Auto set tag based on condition
    $tag = $buggyCondition === 'new' ? 'New' : 'Used';

    $formData['brand']           = $brand;
    $formData['custom_brand']    = $customBrand;
    $formData['model']           = $model;
    $formData['seats']           = $seats;
    $formData['buggy_condition'] = $buggyCondition;
    $formData['selling_price']   = $discountPrice;
    $formData['buggy_year']      = $buggyYear;
    $formData['serial_number']   = $serialNumber;
    $formData['remark']          = $remark;
    $formData['short_info']      = $shortInfo;
    $formData['description']     = $description;
    $formData['specifications']  = $parsedSpecs;
    $formData['status']          = $sellerStatus;

    if ($brand === 'Others' && $customBrand !== '') {
        $brand = $customBrand;
    } elseif ($brand !== '' && !in_array($brand, $brandOptions, true)) {
        $error = 'Selected brand is invalid or inactive.';
    }

    if ($error === '' && $brand        === '') $error = 'Buggy brand is required.';
    elseif ($error === '' && $model    === '') $error = 'Buggy model is required.';
    elseif ($error === '' && $seats    === '') $error = 'Please select buggy category / seats.';
    elseif ($error === '' && $discountPrice <= 0) $error = 'Selling price is required.';
    elseif ($error === '' && $shortInfo === '') $error = 'Short info is required.';
    elseif ($error === '' && $description === '') $error = 'Product description is required.';

    if ($error === '') {
        try {
            $name = trim($brand . ' ' . $model);

            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE buggies SET
                        brand           = :brand,
                        model           = :model,
                        name            = :name,
                        seats           = :seats,
                        buggy_year      = :buggy_year,
                        listing_type    = 'sale',
                        buggy_condition = :buggy_condition,
                        selling_price   = :selling_price,
                        serial_number   = :serial_number,
                        remark          = :remark,
                        short_info      = :short_info,
                        description     = :description,
                        specifications  = :specifications,
                        tag             = :tag,
                        brand_tag       = :brand_tag,
                        status          = :status,
                        updated_at      = NOW()
                    WHERE id = :id
                    AND owner_type = 'seller'
                    AND owner_id = :owner_id
                ");
                $stmt->execute([
                    ':brand'           => $brand,
                    ':model'           => $model,
                    ':name'            => $name,
                    ':seats'           => $seats,
                    ':buggy_year'      => $buggyYear,
                    ':buggy_condition' => $buggyCondition,
                    ':selling_price'   => $discountPrice,
                    ':serial_number'   => $serialNumber,
                    ':remark'          => $remark,
                    ':short_info'      => $shortInfo,
                    ':description'     => $description,
                    ':specifications'  => $specsJson,
                    ':tag'             => $tag,
                    ':brand_tag'       => $brand,
                    ':status'          => $sellerStatus,
                    ':id'              => $id,
                    ':owner_id'        => $sellerId,
                ]);

                // Also upload new images if provided in the same form
                $uploadedUrls = uploadSellerGalleryImages($pdo, $id, $error);
                if ($error === '' && count($uploadedUrls) > 0) {
                    $stmt = $pdo->prepare("SELECT image_url FROM buggies WHERE id = ? AND owner_type = 'seller' AND owner_id = ?");
                    $stmt->execute([$id, $sellerId]);
                    $currentProduct = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($currentProduct && trim((string)$currentProduct['image_url']) === '') {
                        $stmt = $pdo->prepare("UPDATE buggies SET image_url = ?, updated_at = NOW() WHERE id = ? AND owner_type = 'seller' AND owner_id = ?");
                        $stmt->execute([$uploadedUrls[0], $id, $sellerId]);
                    }
                }

                header('Location: buggy-form.php?id=' . $id . '&updated=1');
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO buggies (
                    owner_type, owner_id, brand, model, name, seats, buggy_year,
                    listing_type, buggy_condition, selling_price, serial_number, remark,
                    short_info, description, specifications, image_url, tag, brand_tag,
                    status, created_at, updated_at
                ) VALUES (
                    'seller', :owner_id, :brand, :model, :name, :seats, :buggy_year,
                    'sale', :buggy_condition, :selling_price, :serial_number, :remark,
                    :short_info, :description, :specifications, '', :tag, :brand_tag,
                    :status, NOW(), NOW()
                )
            ");
            $stmt->execute([
                ':owner_id'        => $sellerId,
                ':brand'           => $brand,
                ':model'           => $model,
                ':name'            => $name,
                ':seats'           => $seats,
                ':buggy_year'      => $buggyYear,
                ':buggy_condition' => $buggyCondition,
                ':selling_price'   => $discountPrice,
                ':serial_number'   => $serialNumber,
                ':remark'          => $remark,
                ':short_info'      => $shortInfo,
                ':description'     => $description,
                ':specifications'  => $specsJson,
                ':tag'             => $tag,
                ':brand_tag'       => $brand,
                ':status'          => $sellerStatus,
            ]);

            $newBuggyId   = (int)$pdo->lastInsertId();
            $uploadedUrls = uploadSellerGalleryImages($pdo, $newBuggyId, $error);

            if ($error === '' && count($uploadedUrls) > 0) {
                $stmt = $pdo->prepare("UPDATE buggies SET image_url = ? WHERE id = ?");
                $stmt->execute([$uploadedUrls[0], $newBuggyId]);
            }

            if ($error === '') {
                if ($submitAction === 'insert_add')  { header('Location: buggy-form.php?added=1'); exit; }
                if ($submitAction === 'insert_exit') { header('Location: buggy-list.php?added=1'); exit; }
                header('Location: buggy-list.php?added=1');
                exit;
            }

        } catch (PDOException $e) {
            $error = $isEdit
                ? 'Failed to update buggy listing: ' . $e->getMessage()
                : 'Failed to add buggy listing: ' . $e->getMessage();
        }
    }
}

/* REFRESH EDIT DATA */
if ($isEdit) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM buggies
            WHERE id = ? AND owner_type = 'seller' AND owner_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $sellerId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $formData['brand']           = $product['brand']           ?? '';
            $formData['custom_brand']    = '';
            $formData['model']           = $product['model']           ?? '';
            $formData['seats']           = normalizeSellerCategory($product['seats'] ?? '');
            $formData['buggy_year']      = $product['buggy_year']      ?? '';
            $formData['buggy_condition'] = $product['buggy_condition'] ?? 'used';
            $formData['selling_price']   = $product['selling_price']   ?? '';
            $formData['serial_number']   = $product['serial_number']   ?? '';
            $formData['remark']          = $product['remark']          ?? '';
            $formData['short_info']      = $product['short_info']      ?? '';
            $formData['description']     = $product['description']     ?? '';
            $formData['image_url']       = $product['image_url']       ?? '';
            $formData['status']          = $product['status']          ?? 'active';

            if (!empty($product['specifications'])) {
                $decoded = json_decode($product['specifications'], true);
                $formData['specifications'] = is_array($decoded) ? $decoded : [];
            }

            if (!in_array($formData['brand'], $brandOptions, true)) {
                $formData['custom_brand'] = $formData['brand'];
                $formData['brand']        = 'Others';
            }
        }

        $galleryStmt = $pdo->prepare("
            SELECT * FROM buggy_images WHERE buggy_id = ? ORDER BY sort_order ASC, id ASC
        ");
        $galleryStmt->execute([$id]);
        $galleryImages = $galleryStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = 'Failed to refresh listing: ' . $e->getMessage();
    }
}

include '../header.php';
?>

<style>
    .seller-product-page { background: #ffffff; padding: 38px 15px 76px; min-height: 75vh; }
    .seller-product-wrap { max-width: 1320px; margin: 0 auto; }

    .seller-layout { display: grid; grid-template-columns: 260px 1fr; gap: 30px; align-items: start; }

    .seller-sidebar { background: #ffffff; border: 1px solid #dddddd; border-radius: 14px; padding: 26px 18px; }
    .seller-sidebar h2 { margin: 0 0 8px; font-size: 18px; color: #222222; }
    .seller-sidebar p  { margin: 0 0 24px; font-size: 14px; color: #888888; line-height: 1.5; }

    .seller-menu { display: grid; gap: 8px; }
    .seller-menu a { width: 100%; display: flex; align-items: center; gap: 11px; padding: 12px 14px; border-radius: 999px; color: #888888; text-decoration: none; font-size: 15px; transition: 0.2s ease; }
    .seller-menu a.active, .seller-menu a:hover { background: #f7f7f7; color: #ef3f4d; font-weight: bold; }
    .menu-icon { width: 18px; text-align: center; font-size: 15px; }
    .seller-main { min-width: 0; }

    .product-page-title { margin-bottom: 18px; }
    .product-page-title h1 { margin: 0; font-size: 30px; font-weight: 500; color: #333; }

    .breadcrumb { background: #eeeeee; padding: 12px 16px; margin-bottom: 22px; color: #999; font-size: 14px; }
    .breadcrumb a { color: #777; text-decoration: none; }
    .breadcrumb span { color: #ef3f4d; }

    .form-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
    .form-header h1 { margin: 0; font-size: 30px; }
    .form-header p  { margin: 6px 0 0; color: #777; font-size: 15px; }
    .form-header-actions { display: flex; gap: 10px; flex-wrap: wrap; }

    .btn-light { background: #fff; color: #222; border: 1px solid #ddd; min-width: 130px; height: 42px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-weight: bold; font-size: 14px; }
    .btn-light:hover { border-color: #ef3f4d; color: #ef3f4d; background: #fff; }

    .product-card { background: #fff; border-radius: 4px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); border: 1px solid #eee; overflow: hidden; }

    .top-actions, .bottom-actions, .image-form-actions { background: #fff; border-bottom: 1px solid #eeeeee; padding: 18px 20px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .bottom-actions, .image-form-actions { border-top: 1px solid #eeeeee; border-bottom: 0; margin-top: 24px; }

    .action-btn { border: 0; background: #ef3f4d; color: #fff; padding: 0 22px; min-width: 145px; height: 44px; font-size: 15px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition: 0.2s ease; }
    .action-btn:hover { background: #d92e3d; color: #fff; }
    .action-btn-light { background: #2f2f2f; color: #fff; border: 0; }
    .action-btn-light:hover { background: #1f1f1f; color: #fff; }

    .response-box { position: fixed; top: 24px; right: 24px; z-index: 9999; min-width: 280px; max-width: 420px; padding: 16px 18px; border-radius: 12px; font-weight: bold; box-shadow: 0 12px 30px rgba(0,0,0,0.18); animation: slideDown 0.3s ease; }
    .response-success { background: #e8fff0; color: #167a3c; border: 1px solid #b8e8c8; }
    .response-error   { background: #fff0f2; color: #ef3f4d; border: 1px solid #ffc4cc; }
    .response-box small { display: block; margin-top: 5px; font-weight: normal; opacity: 0.8; }

    @keyframes slideDown { from { opacity: 0; transform: translateY(-12px); } to { opacity: 1; transform: translateY(0); } }

    .seller-rule-box { background: #fff7f8; border: 1px solid #ffd1d6; color: #555; padding: 14px 16px; margin-bottom: 18px; border-radius: 8px; font-size: 14px; line-height: 1.6; }
    .seller-rule-box strong { color: #ef3f4d; }

    .product-tabs { display: flex; gap: 0; padding: 20px 22px 0; border-bottom: 1px solid #eee; }
    .product-tab-btn { background: transparent; border: 1px solid transparent; color: #ef3f4d; padding: 12px 18px; font-size: 15px; cursor: pointer; border-radius: 8px 8px 0 0; margin-bottom: -1px; transition: 0.2s ease; }
    .product-tab-btn.active { color: #222; background: #fff; border-color: #222; border-bottom-color: #fff; font-weight: bold; }

    .tab-panel { display: none; padding: 22px; }
    .tab-panel.active { display: block; }

    .form-body { padding: 22px 22px 0 22px; }

    .form-grid { display: block; }
    .form-group { width: 40%; margin-bottom: 16px; }
    .form-group.full { width: 100%; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; }
    .form-group label { display: block; margin-bottom: 7px; color: #333; font-size: 14px; font-weight: bold; }
    .form-group input, .form-group select { height: 42px; border: 1px solid #ddd; border-radius: 6px; padding: 0 12px; outline: none; background: #fff; font-size: 14px; }
    .form-group textarea { min-height: 130px; border: 1px solid #ddd; border-radius: 6px; padding: 12px; outline: none; resize: vertical; font-size: 14px; font-family: inherit; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #ef3f4d; }
    .help { margin-top: 6px; color: #777; font-size: 13px; }

    .section-title { width: 100%; margin-top: 28px; margin-bottom: 16px; padding-top: 22px; border-top: 1px solid #eeeeee; font-size: 20px; font-weight: bold; }
    .section-title:first-child { margin-top: 0; padding-top: 0; border-top: 0; }

    .image-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; }
    .image-toolbar h2 { margin: 0; font-size: 22px; }
    .image-count { color: #777; font-size: 14px; }

    .main-image-box { border: 1px solid #eee; border-radius: 14px; padding: 18px; margin-bottom: 24px; background: #fafafa; }
    .main-image-title { font-weight: bold; margin-bottom: 12px; }
    .main-image-preview { width: 170px; height: 170px; border-radius: 12px; object-fit: cover; border: 1px solid #ddd; background: #f1f1f1; }

    .gallery-grid { display: grid; grid-template-columns: repeat(5, 180px); gap: 20px; margin-bottom: 30px; }
    .gallery-card { border: 1px solid #eee; border-radius: 10px; padding: 10px; background: #fff; }
    .gallery-actions { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: nowrap; align-items: center; }
    .gallery-actions form { display: inline-flex !important; margin: 0; }

    .image-action-btn { border: 0; padding: 6px 8px; border-radius: 6px; color: #fff; font-size: 11px; font-weight: bold; cursor: pointer; white-space: nowrap; height: 28px; line-height: 1; transition: 0.2s ease; }
    .set-primary-btn { background: #7dd3e8; } .set-primary-btn:hover { background: #59bfd8; }
    .remove-btn { background: #ef8585; } .remove-btn:hover { background: #ef3f4d; }

    .gallery-image { width: 158px; height: 158px; object-fit: cover; background: #fff; display: block; margin-bottom: 10px; border: 1px solid #eee; border-radius: 4px; }

    .upload-area { border: 1px solid #eee; background: #fafafa; height: 100px; display: flex; align-items: center; justify-content: center; color: #333; margin: 0 0 16px; transition: 0.2s ease; }
    .upload-area.dragover { border-color: #ef3f4d; background: #fff7f8; color: #ef3f4d; }

    .upload-preview-area { display: flex; flex-wrap: wrap; gap: 18px 30px; margin-top: 14px; align-items: flex-start; max-width: 1100px; }
    .upload-card { width: 180px; position: relative; }
    .upload-box { width: 180px; height: 180px; background: #eef3f6; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; cursor: pointer; position: relative; overflow: hidden; border-radius: 4px; transition: 0.2s ease; }
    .upload-box:hover { border-color: #ef3f4d; }
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
    .upload-icon { font-size: 48px; line-height: 1; margin-bottom: 8px; }
    .upload-label { background: #999; color: #fff; padding: 6px 10px; border-radius: 4px; display: inline-block; font-size: 13px; }
    .preview-upload-img { width: 180px; height: 180px; object-fit: cover; border: 1px solid #ddd; display: block; border-radius: 4px; }

    .remove-preview-btn { position: absolute; top: 0; right: 0; width: 24px; height: 24px; border: 0; background: #ef3f4d; color: #fff; font-weight: bold; cursor: pointer; z-index: 2; border-radius: 0 4px 0 6px; line-height: 1; font-size: 16px; transition: 0.2s ease; }
    .remove-preview-btn:hover { background: #d92e3d; }

    .add-button-holder { display: flex; align-items: flex-start; width: auto; }
    .add-image-side-btn { background: #7dd3e8; color: #fff; border: 0; padding: 7px 12px; font-weight: 700; cursor: pointer; font-size: 12px; height: 28px; line-height: 1; white-space: nowrap; border-radius: 6px; transition: 0.2s ease; }
    .add-image-side-btn:hover { background: #59bfd8; transform: translateY(-1px); }

    .image-description-input { width: 100%; height: 34px; min-height: 34px; border: 1px solid #ccc; border-radius: 4px; padding: 0 10px; font-size: 14px; font-style: italic; margin-top: 6px; }
    .image-input-hidden { display: none; }
    .form-note { color: #777; margin-top: 18px; font-size: 14px; }

    @media (max-width: 1200px) { .gallery-grid { grid-template-columns: repeat(4, 180px); } }
    @media (max-width: 1000px) { .seller-layout { grid-template-columns: 1fr; } .seller-sidebar { order: 2; } .seller-main { order: 1; } }
    @media (max-width: 900px)  { .form-group { width: 100%; } .gallery-grid { grid-template-columns: repeat(2, 180px); } .upload-preview-area { max-width: 420px; } }
    @media (max-width: 700px)  {
        .seller-product-page { padding: 28px 12px 56px; }
        .form-header { display: block; }
        .form-header-actions { margin-top: 14px; }
        .gallery-grid { grid-template-columns: 1fr; }
        .gallery-card, .upload-card, .upload-box, .preview-upload-img, .gallery-image { width: 100%; }
        .upload-box, .preview-upload-img, .gallery-image { height: 220px; }
        .response-box { left: 18px; right: 18px; top: 18px; min-width: auto; max-width: none; }
    }
</style>

<section class="seller-product-page">
    <div class="seller-product-wrap">
        <div class="seller-layout">

            <aside class="seller-sidebar">
                <h2>Seller Portal</h2>
                <p>Welcome back,<br><strong><?php echo e($seller['full_name']); ?></strong></p>
                <nav class="seller-menu">
                    <a href="dashboard.php"><span class="menu-icon">▦</span> Dashboard</a>
                    <a href="my-plan.php"><span class="menu-icon">📦</span> My Plan</a>
                    <a href="payment.php"><span class="menu-icon">□</span> Payment Verification</a>
                    <a href="buggy-list.php" class="<?php echo $isEdit ? 'active' : ''; ?>"><span class="menu-icon">☰</span> My Buggy Listings</a>
                    <a href="buggy-form.php" class="<?php echo !$isEdit ? 'active' : ''; ?>"><span class="menu-icon">+</span> Add Listing</a>
                    <a href="logout.php"><span class="menu-icon">⏻</span> Logout</a>
                </nav>
            </aside>

            <main class="seller-main">
                <div class="product-page-title">
                    <h1><?php echo $isEdit ? 'Edit Seller Buggy Listing' : '[New] Seller Buggy Listing'; ?></h1>
                </div>

                <div class="breadcrumb">
                    <a href="dashboard.php">Seller Dashboard</a> &gt;
                    <?php if ($isEdit): ?>
                        <a href="buggy-list.php">My Buggy Listings</a> &gt; <span>Edit Buggy</span>
                    <?php else: ?>
                        <span>Add Listing</span>
                    <?php endif; ?>
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
                            <h1>Edit Buggy Listing</h1>
                            <p>Update your listing information and images.</p>
                        </div>
                        <div class="form-header-actions">
                            <a href="buggy-list.php" class="btn-light">Back to List</a>
                            <?php if (($formData['status'] ?? '') === 'active'): ?>
                                <a href="../buggy-detail.php?id=<?php echo (int)$id; ?>" class="btn-light" target="_blank">View Public</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="seller-rule-box">
                    <?php if ($isEdit): ?>
                        <strong>Seller edit rule:</strong>
                        You can update your listing details, condition and status anytime. Maximum <strong><?php echo (int)$sellerListingLimit; ?> listings</strong> per seller account<?php if (!empty($seller['pkg_name'])): ?> (<?php echo htmlspecialchars($seller['pkg_name']); ?> package)<?php endif; ?>.
                    <?php else: ?>
                        <strong>Seller upload rule:</strong>
                        Your listing goes live immediately after upload. Maximum <strong><?php echo (int)$sellerListingLimit; ?> listings</strong> per seller account<?php if (!empty($seller['pkg_name'])): ?> (<?php echo htmlspecialchars($seller['pkg_name']); ?> package)<?php endif; ?>.
                    <?php endif; ?>
                </div>

                <div class="product-card">
                    <form method="post" enctype="multipart/form-data" id="productForm">
                        <input type="hidden" name="save_product" value="1">
                        <input type="hidden" name="submit_action" id="submitActionInput" value="<?php echo $isEdit ? 'update' : 'insert'; ?>">

                        <div class="top-actions">
                            <?php if ($isEdit): ?>
                                <button type="submit" class="action-btn">Save Changes</button>
                                <a href="buggy-list.php" class="action-btn action-btn-light">Cancel</a>
                                <?php if (($formData['status'] ?? '') === 'active'): ?>
                                    <a href="../buggy-detail.php?id=<?php echo (int)$id; ?>" class="action-btn action-btn-light" target="_blank">View Public</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <button type="submit" class="action-btn" data-action="insert">Save and Upload</button>
                                <a href="buggy-list.php" class="action-btn action-btn-light">Cancel</a>
                            <?php endif; ?>
                        </div>

                        <div class="form-body">
                            <div class="form-grid">
                                <div class="section-title">Buggy Information</div>

                                <div class="form-group">
                                    <label>Status *</label>
                                    <select name="status" required>
                                        <option value="active"   <?php echo $formData['status'] === 'active'   ? 'selected' : ''; ?>>Active (Live)</option>
                                        <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive (Hidden)</option>
                                        <option value="sold"     <?php echo $formData['status'] === 'sold'     ? 'selected' : ''; ?>>Sold</option>
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
                                    <input type="text" name="custom_brand" placeholder="Only fill if brand is Others" value="<?php echo e($formData['custom_brand']); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Serial Number <span style="color:#777;font-weight:normal;">(internal only)</span></label>
                                    <input type="text" name="serial_number" placeholder="Example: SN-2024-001" value="<?php echo e($formData['serial_number'] ?? ''); ?>">
                                    <div class="help">Visible to admin and you only. Not shown on public pages.</div>
                                </div>

                                <div class="form-group">
                                    <label>Buggy Model *</label>
                                    <input type="text" name="model" placeholder="Example: Tempo 4-Seater" value="<?php echo e($formData['model']); ?>" required>
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
                                    <label>New / Used *</label>
                                    <select name="buggy_condition" id="sellerBuggyCondition" required>
                                        <option value="used" <?php echo $formData['buggy_condition'] === 'used' ? 'selected' : ''; ?>>Used Buggy</option>
                                        <option value="new"  <?php echo $formData['buggy_condition'] === 'new'  ? 'selected' : ''; ?>>New Buggy</option>
                                    </select>
                                </div>

                                <div class="form-group" id="sellerYearGroup">
                                    <label>Buggy Year</label>
                                    <select name="buggy_year">
                                        <option value="">Select year</option>
                                        <?php for ($yr = $startYear; $yr >= $endYear; $yr--): ?>
                                            <option value="<?php echo (int)$yr; ?>" <?php echo (string)($formData['buggy_year'] ?? '') === (string)$yr ? 'selected' : ''; ?>>
                                                <?php echo (int)$yr; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="help">Only applicable for Used buggies.</div>
                                </div>

                                <div class="section-title">Selling Price</div>

                                <div class="form-group">
                                    <label>Selling Price (S$) *</label>
                                    <input type="number" step="0.01" name="selling_price" placeholder="Example: 8500" value="<?php echo e($formData['selling_price']); ?>" required>
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

                                <div class="form-group full">
                                    <label>Remark / Internal Information</label>
                                    <textarea name="remark" placeholder="Seller name, phone number, preferred contact time, buggy location, ownership notes. This will not be shown publicly."><?php echo e($formData['remark']); ?></textarea>
                                    <div class="help">This remark is for SGBUGGYMART/admin reference only.</div>
                                </div>

                                <div class="section-title">Product Details</div>

                                <div class="form-group full">
                                    <label>Short Info *</label>
                                    <input type="text" name="short_info" placeholder="Example: 4-seater electric buggy, well maintained" value="<?php echo e($formData['short_info']); ?>" required>
                                </div>

                                <div class="form-group full">
                                    <label>Product Description *</label>
                                    <textarea name="description" placeholder="Write buggy condition, usage, battery condition, accessories and remarks here" required><?php echo e($formData['description']); ?></textarea>
                                </div>

                                <div class="section-title">
                                    Buggy Images
                                    <?php if ($isEdit && (count($galleryImages) + (!empty($formData['image_url']) ? 1 : 0)) > 0): ?>
                                        <span style="font-size:14px;font-weight:normal;color:#777;margin-left:10px;">
                                            (<?php echo count($galleryImages) + (!empty($formData['image_url']) ? 1 : 0); ?> uploaded)
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($isEdit): ?>
                                    <div class="form-group full">
                                        <div class="main-image-box">
                                            <div class="main-image-title">Current Primary Image</div>
                                            <img src="<?php echo e(productImagePath($formData['image_url'])); ?>" alt="Primary Image" class="main-image-preview" onerror="this.src='../images/no-image.png';">
                                        </div>
                                    </div>

                                    <?php if (count($galleryImages) > 0): ?>
                                        <div class="form-group full">
                                            <div class="gallery-grid">
                                                <?php foreach ($galleryImages as $gallery): ?>
                                                    <div class="gallery-card">
                                                        <div class="gallery-actions">
                                                            <form method="post">
                                                                <input type="hidden" name="image_action" value="set_primary">
                                                                <input type="hidden" name="gallery_id" value="<?php echo (int)$gallery['id']; ?>">
                                                                <button type="submit" class="image-action-btn set-primary-btn" onclick="return confirm('Set this image as primary?');">+ Set Primary</button>
                                                            </form>
                                                            <form method="post">
                                                                <input type="hidden" name="image_action" value="remove_gallery">
                                                                <input type="hidden" name="gallery_id" value="<?php echo (int)$gallery['id']; ?>">
                                                                <button type="submit" class="image-action-btn remove-btn" onclick="return confirm('Remove this image?');">Remove</button>
                                                            </form>
                                                        </div>
                                                        <img src="<?php echo e(productImagePath($gallery['image_url'])); ?>" alt="Gallery Image" class="gallery-image" onerror="this.src='../images/no-image.png';">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <div class="form-group full">
                                    <div class="upload-area" id="dropArea">Drag & drop images here, or tap "+ Add" below.</div>
                                    <div class="upload-preview-area" id="galleryUploadArea">
                                        <div class="upload-card">
                                            <button type="button" class="remove-preview-btn">×</button>
                                            <label class="upload-box">
                                                <div class="upload-placeholder">
                                                    <div class="upload-icon">▧</div>
                                                    <div class="upload-label">click here to upload</div>
                                                </div>
                                                <input type="file" name="gallery_images[]" class="image-input-hidden single-gallery-input" accept="image/*">
                                            </label>
                                        </div>
                                        <div class="add-button-holder">
                                            <button type="button" class="add-image-side-btn" id="addGalleryBoxBtn">+ Add</button>
                                        </div>
                                    </div>
                                    <div class="form-note">
                                        <?php if ($isEdit): ?>
                                            Upload new images to add to your gallery. First uploaded image becomes primary if none is set.
                                        <?php else: ?>
                                            First uploaded image will become the main product image. Click <strong>+ Add</strong> to add more.
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="bottom-actions">
                            <?php if ($isEdit): ?>
                                <button type="submit" class="action-btn">Save Changes</button>
                                <a href="buggy-list.php" class="action-btn action-btn-light">Cancel</a>
                            <?php else: ?>
                                <button type="submit" class="action-btn" data-action="insert">Save and Upload</button>
                                <a href="buggy-list.php" class="action-btn action-btn-light">Cancel</a>
                            <?php endif; ?>
                        </div>

                    </form>
                </div>
            </main>
        </div>
    </div>
</section>

<script>
    const submitActionInput = document.getElementById('submitActionInput');

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

    function getAddButtonHolder() { return document.querySelector('.add-button-holder'); }

    const MAX_IMAGE_SIZE = 4 * 1024 * 1024; // 4MB

    function checkImageSize(file) {
        if (file.size > MAX_IMAGE_SIZE) {
            alert('Image "' + file.name + '" is too large (' + (file.size / 1024 / 1024).toFixed(1) + 'MB). Each image must be below 4MB.');
            return false;
        }
        return true;
    }

    /*
    |------------------------------------------------------------------
    | showImageInCard
    | - Replaces the <label> with a plain <div> after image is chosen
    |   so tapping the preview on mobile does NOT re-open file picker.
    | - Moves the file input outside the label (hidden, still submitted).
    | - Uses FileReader (no DataTransfer — broken on iOS/Android).
    |------------------------------------------------------------------
    */
    function showImageInCard(card, file) {
        const label = card.querySelector('.upload-box');

        // Pull the original input OUT of the label so it is no longer
        // triggered by tapping the preview image
        const existingInput = label ? label.querySelector('input[type="file"]') : null;
        if (existingInput) {
            existingInput.remove();
            existingInput.style.display = 'none';
            card.appendChild(existingInput);

            // Re-bind change so seller can re-select if needed
            existingInput.addEventListener('change', function () {
                if (this.files && this.files[0]) {
                    if (!checkImageSize(this.files[0])) { this.value = ''; return; }
                    showImageInCard(card, this.files[0]);
                }
            });
        }

        // Replace the <label> with a <div> so clicking preview does nothing
        const previewDiv = document.createElement('div');
        previewDiv.className = 'upload-box';
        previewDiv.style.cssText = 'cursor:default; padding:0; overflow:hidden; position:relative;';

        const img = document.createElement('img');
        img.className   = 'preview-upload-img';
        img.alt         = 'Preview';
        img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;pointer-events:none;';

        // Read image with FileReader — safe on all mobile browsers
        const reader = new FileReader();
        reader.onload = function (e) { img.src = e.target.result; };
        reader.readAsDataURL(file);

        previewDiv.appendChild(img);

        if (label && label.parentNode) {
            label.parentNode.replaceChild(previewDiv, label);
        }
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

    function createGalleryUploadCard(file) {
        const card      = document.createElement('div');
        card.className  = 'upload-card';

        const removeBtn     = document.createElement('button');
        removeBtn.type      = 'button';
        removeBtn.className = 'remove-preview-btn';
        removeBtn.innerHTML = '×';

        const label     = document.createElement('label');
        label.className = 'upload-box';

        const placeholder     = document.createElement('div');
        placeholder.className = 'upload-placeholder';
        placeholder.innerHTML = '<div class="upload-icon">▧</div><div class="upload-label">click here to upload</div>';

        const input     = document.createElement('input');
        input.type      = 'file';
        input.name      = 'gallery_images[]';
        input.className = 'image-input-hidden single-gallery-input';
        input.accept    = 'image/*';

        const desc         = document.createElement('input');
        desc.type          = 'text';
        desc.className     = 'image-description-input';
        desc.placeholder   = 'Description';
        desc.disabled      = true;

        label.appendChild(placeholder);
        label.appendChild(input);
        card.appendChild(removeBtn);
        card.appendChild(label);
        card.appendChild(desc);

        bindUploadCard(card);

        if (file) showImageInCard(card, file);

        return card;
    }

    if (galleryUploadArea && addGalleryBoxBtn && dropArea) {
        document.querySelectorAll('.upload-card').forEach(function (card) { bindUploadCard(card); });

        addGalleryBoxBtn.addEventListener('click', function () {
            galleryUploadArea.insertBefore(createGalleryUploadCard(), getAddButtonHolder());
        });

        // Drag & drop — desktop only, skip on touch devices
        var isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

        if (!isTouchDevice) {
            dropArea.addEventListener('dragover', function (e) {
                e.preventDefault();
                dropArea.classList.add('dragover');
            });
            dropArea.addEventListener('dragleave', function () {
                dropArea.classList.remove('dragover');
            });
            dropArea.addEventListener('drop', function (e) {
                e.preventDefault();
                dropArea.classList.remove('dragover');
                Array.from(e.dataTransfer.files).forEach(function (file) {
                    if (file.type.startsWith('image/')) {
                        galleryUploadArea.insertBefore(createGalleryUploadCard(file), getAddButtonHolder());
                    }
                });
            });
        } else {
            // On mobile: hide drag area, show tap-to-upload message instead
            dropArea.textContent = 'Tap "+ Add" below to add more images.';
        }
    }

    // Show/hide Buggy Year based on condition (only Used shows year)
    const sellerBuggyCondition = document.getElementById('sellerBuggyCondition');
    const sellerYearGroup      = document.getElementById('sellerYearGroup');

    function updateSellerYearVisibility() {
        if (!sellerBuggyCondition || !sellerYearGroup) return;
        const val = sellerBuggyCondition.value;
        sellerYearGroup.style.display = val === 'used' ? 'block' : 'none';
        if (val === 'new') {
            const yearSelect = sellerYearGroup.querySelector('select[name="buggy_year"]');
            if (yearSelect) yearSelect.value = '';
        }
    }

    if (sellerBuggyCondition) {
        sellerBuggyCondition.addEventListener('change', updateSellerYearVisibility);
        updateSellerYearVisibility();
    }
</script>

<?php include '../footer.php'; ?>