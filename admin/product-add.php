<!--<?php-->
<!--require_once '../includes/db.php';-->

<!--if (session_status() === PHP_SESSION_NONE) {-->
<!--    session_start();-->
<!--}-->

<!--if (!isset($_SESSION['admin_id'])) {-->
<!--    header('Location: login.php');-->
<!--    exit;-->
<!--}-->

<!--ini_set('display_errors', 1);-->
<!--ini_set('display_startup_errors', 1);-->
<!--error_reporting(E_ALL);-->

<!--$success = '';-->
<!--$error = '';-->

<!--$brandOptions = [-->
<!--    'Club Car',-->
<!--    'Yamaha',-->
<!--    'EZGO',-->
<!--    'HDK',-->
<!--    'Marshell',-->
<!--    'RoyPow',-->
<!--    'Others'-->
<!--];-->

<!--$productTagOptions = [-->
<!--    'New',-->
<!--    'Used',-->
<!--    'Hot Deal',-->
<!--    'Event Rental',-->
<!--    'For Sale',-->
<!--    'For Rent',-->
<!--    'Sale + Rent',-->
<!--    'Promotion',-->
<!--    'Featured'-->
<!--];-->

<!--function e($value)-->
<!--{-->
<!--    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');-->
<!--}-->

<!--function uploadGalleryImages($pdo, $buggyId, &$error)-->
<!--{-->
<!--    if (!isset($_FILES['gallery_images']) || empty($_FILES['gallery_images']['name'][0])) {-->
<!--        return [];-->
<!--    }-->

<!--    $uploadDir = __DIR__ . '/../images/';-->

<!--    if (!is_dir($uploadDir)) {-->
<!--        mkdir($uploadDir, 0755, true);-->
<!--    }-->

<!--    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];-->
<!--    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];-->

<!--    $uploadedUrls = [];-->

<!--    foreach ($_FILES['gallery_images']['name'] as $key => $originalName) {-->
<!--        if ($originalName === '') {-->
<!--            continue;-->
<!--        }-->

<!--        if ($_FILES['gallery_images']['error'][$key] !== UPLOAD_ERR_OK) {-->
<!--            $error = 'One of the images failed to upload.';-->
<!--            return $uploadedUrls;-->
<!--        }-->

<!--        $tmpName = $_FILES['gallery_images']['tmp_name'][$key];-->
<!--        $fileSize = $_FILES['gallery_images']['size'][$key];-->
<!--        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));-->

<!--        $finfo = finfo_open(FILEINFO_MIME_TYPE);-->
<!--        $mimeType = finfo_file($finfo, $tmpName);-->
<!--        finfo_close($finfo);-->

<!--        if (!in_array($extension, $allowedExtensions, true)) {-->
<!--            $error = 'Only JPG, JPEG, PNG, WEBP, and GIF images are allowed.';-->
<!--            return $uploadedUrls;-->
<!--        }-->

<!--        if (!in_array($mimeType, $allowedMimeTypes, true)) {-->
<!--            $error = 'One of the uploaded files is not a valid image.';-->
<!--            return $uploadedUrls;-->
<!--        }-->

<!--        if ($fileSize > 5 * 1024 * 1024) {-->
<!--            $error = 'Each image must be below 5MB.';-->
<!--            return $uploadedUrls;-->
<!--        }-->

<!--        $safeName = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($originalName, PATHINFO_FILENAME));-->
<!--        $newFileName = 'gallery-' . $safeName . '-' . time() . '-' . rand(1000, 9999) . '-' . $key . '.' . $extension;-->
<!--        $targetPath = $uploadDir . $newFileName;-->

<!--        if (!move_uploaded_file($tmpName, $targetPath)) {-->
<!--            $error = 'Failed to upload image.';-->
<!--            return $uploadedUrls;-->
<!--        }-->

<!--        $imageUrl = 'images/' . $newFileName;-->

<!--        $stmt = $pdo->prepare("-->
<!--            INSERT INTO buggy_images (buggy_id, image_url)-->
<!--            VALUES (:buggy_id, :image_url)-->
<!--        ");-->

<!--        $stmt->execute([-->
<!--            ':buggy_id' => $buggyId,-->
<!--            ':image_url' => $imageUrl-->
<!--        ]);-->

<!--        $uploadedUrls[] = $imageUrl;-->
<!--    }-->

<!--    return $uploadedUrls;-->
<!--}-->

<!--$formData = [-->
<!--    'brand' => '',-->
<!--    'custom_brand' => '',-->
<!--    'model' => '',-->
<!--    'seats' => '',-->
<!--    'buggy_condition' => 'new',-->
<!--    'listing_type' => 'sale',-->
<!--    'status' => 'active',-->
<!--    'original_price' => '',-->
<!--    'rent_price_1_day' => '',-->
<!--    'rent_price_3_days' => '',-->
<!--    'rent_price_1_week' => '',-->
<!--    'rent_price_1_month' => '',-->
<!--    'brand_tag' => '',-->
<!--    'tag' => '',-->
<!--    'short_info' => '',-->
<!--    'description' => ''-->
<!--];-->

<!--if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {-->
<!--    foreach ($formData as $key => $value) {-->
<!--        $formData[$key] = trim($_POST[$key] ?? '');-->
<!--    }-->

<!--    $submitAction = $_POST['submit_action'] ?? 'insert';-->

<!--    $brand = $formData['brand'];-->
<!--    $customBrand = $formData['custom_brand'];-->
<!--    $model = $formData['model'];-->
<!--    $seats = (int)$formData['seats'];-->
<!--    $listingType = $formData['listing_type'] ?: 'sale';-->
<!--    $buggyCondition = $formData['buggy_condition'] ?: 'new';-->

<!--    $sellingPrice = (float)($formData['original_price'] ?: 0);-->
<!--    $rentPrice1Day = (float)($formData['rent_price_1_day'] ?: 0);-->
<!--    $rentPrice3Days = (float)($formData['rent_price_3_days'] ?: 0);-->
<!--    $rentPrice1Week = (float)($formData['rent_price_1_week'] ?: 0);-->
<!--    $rentPrice1Month = (float)($formData['rent_price_1_month'] ?: 0);-->

<!--    $shortInfo = $formData['short_info'];-->
<!--    $description = $formData['description'];-->
<!--    $tag = $formData['tag'];-->
<!--    $brandTag = $formData['brand_tag'];-->
<!--    $status = $formData['status'] ?: 'active';-->

<!--    if ($brand === 'Others' && $customBrand !== '') {-->
<!--        $brand = $customBrand;-->
<!--    }-->

<!--    if ($brandTag === '') {-->
<!--        $brandTag = $brand;-->
<!--    }-->

<!--    if ($tag === '') {-->
<!--        $tag = ucfirst($buggyCondition);-->
<!--    }-->

<!--    if ($brand === '') {-->
<!--        $error = 'Buggy brand is required.';-->
<!--    } elseif ($model === '') {-->
<!--        $error = 'Buggy model is required.';-->
<!--    } elseif ($seats <= 0) {-->
<!--        $error = 'Please select buggy seats.';-->
<!--    }-->

<!--    if ($error === '') {-->
<!--        try {-->
<!--            $name = $brand . ' ' . $model;-->

<!--            $stmt = $pdo->prepare("-->
<!--                INSERT INTO buggies (-->
<!--                    owner_type,-->
<!--                    owner_id,-->
<!--                    brand,-->
<!--                    model,-->
<!--                    name,-->
<!--                    seats,-->
<!--                    listing_type,-->
<!--                    buggy_condition,-->
<!--                    original_price,-->
<!--                    rent_price_1_day,-->
<!--                    rent_price_3_days,-->
<!--                    rent_price_1_week,-->
<!--                    rent_price_1_month,-->
<!--                    short_info,-->
<!--                    description,-->
<!--                    image_url,-->
<!--                    tag,-->
<!--                    brand_tag,-->
<!--                    status-->
<!--                ) VALUES (-->
<!--                    :owner_type,-->
<!--                    :owner_id,-->
<!--                    :brand,-->
<!--                    :model,-->
<!--                    :name,-->
<!--                    :seats,-->
<!--                    :listing_type,-->
<!--                    :buggy_condition,-->
<!--                    :original_price,-->
<!--                    :rent_price_1_day,-->
<!--                    :rent_price_3_days,-->
<!--                    :rent_price_1_week,-->
<!--                    :rent_price_1_month,-->
<!--                    :short_info,-->
<!--                    :description,-->
<!--                    '',-->
<!--                    :tag,-->
<!--                    :brand_tag,-->
<!--                    :status-->
<!--                )-->
<!--            ");-->

<!--            $stmt->execute([-->
<!--                ':owner_type' => 'admin',-->
<!--                ':owner_id' => $_SESSION['admin_id'],-->
<!--                ':brand' => $brand,-->
<!--                ':model' => $model,-->
<!--                ':name' => $name,-->
<!--                ':seats' => $seats,-->
<!--                ':listing_type' => $listingType,-->
<!--                ':buggy_condition' => $buggyCondition,-->
<!--                ':original_price' => $sellingPrice,-->
<!--                ':rent_price_1_day' => $rentPrice1Day,-->
<!--                ':rent_price_3_days' => $rentPrice3Days,-->
<!--                ':rent_price_1_week' => $rentPrice1Week,-->
<!--                ':rent_price_1_month' => $rentPrice1Month,-->
<!--                ':short_info' => $shortInfo,-->
<!--                ':description' => $description,-->
<!--                ':tag' => $tag,-->
<!--                ':brand_tag' => $brandTag,-->
<!--                ':status' => $status-->
<!--            ]);-->

<!--            $newBuggyId = (int)$pdo->lastInsertId();-->

<!--            $galleryUrls = uploadGalleryImages($pdo, $newBuggyId, $error);-->

<!--            if ($error === '' && count($galleryUrls) > 0) {-->
<!--                $stmt = $pdo->prepare("UPDATE buggies SET image_url = ? WHERE id = ?");-->
<!--                $stmt->execute([$galleryUrls[0], $newBuggyId]);-->
<!--            }-->

<!--            if ($error === '') {-->
<!--                if ($submitAction === 'insert_add') {-->
<!--                    header('Location: product-add.php?added=1');-->
<!--                    exit;-->
<!--                }-->

<!--                if ($submitAction === 'insert_exit') {-->
<!--                    header('Location: product-list.php?added=1');-->
<!--                    exit;-->
<!--                }-->

<!--                header('Location: product-edit.php?id=' . $newBuggyId . '&added=1');-->
<!--                exit;-->
<!--            }-->
<!--        } catch (PDOException $e) {-->
<!--            $error = 'Failed to add product: ' . $e->getMessage();-->
<!--        }-->
<!--    }-->
<!--}-->

<!--if (isset($_GET['added']) && $_GET['added'] === '1') {-->
<!--    $success = 'Product added successfully.';-->
<!--}-->

<!--include 'header.php';-->
<!--?>-->

<!--<style>-->
<!--    .add-page-title {-->
<!--    margin-bottom: 18px;-->
<!--    }-->
    
<!--    .add-page-title h1 {-->
<!--        margin: 0;-->
<!--        font-size: 30px;-->
<!--        font-weight: 500;-->
<!--        color: #333;-->
<!--    }-->
    
<!--    .breadcrumb {-->
<!--        background: #eeeeee;-->
<!--        padding: 12px 16px;-->
<!--        margin-bottom: 22px;-->
<!--        color: #999;-->
<!--        font-size: 14px;-->
<!--    }-->
    
<!--    .breadcrumb span {-->
<!--        color: #ef3f4d;-->
<!--    }-->
    
<!--    .add-card {-->
<!--        background: #fff;-->
<!--        border-radius: 4px;-->
<!--        box-shadow: 0 4px 14px rgba(0,0,0,0.08);-->
<!--        border: 1px solid #eee;-->
<!--        overflow: hidden;-->
<!--    }-->
    
<!--    .top-actions,-->
<!--    .bottom-actions {-->
<!--        background: #fff;-->
<!--        border-bottom: 1px solid #eeeeee;-->
<!--        padding: 18px 20px;-->
<!--        display: flex;-->
<!--        gap: 12px;-->
<!--        align-items: center;-->
<!--        flex-wrap: wrap;-->
<!--    }-->
    
<!--    .bottom-actions {-->
<!--        border-top: 1px solid #eeeeee;-->
<!--        border-bottom: 0;-->
<!--    }-->
    
<!--    .action-btn {-->
<!--        border: 0;-->
<!--        background: #ef3f4d;-->
<!--        color: #fff;-->
<!--        padding: 0 22px;-->
<!--        min-width: 145px;-->
<!--        height: 44px;-->
<!--        font-size: 15px;-->
<!--        font-weight: 700;-->
<!--        cursor: pointer;-->
<!--        text-decoration: none;-->
<!--        display: inline-flex;-->
<!--        align-items: center;-->
<!--        justify-content: center;-->
<!--        border-radius: 8px;-->
<!--        transition: 0.2s ease;-->
<!--    }-->
    
<!--    .action-btn:hover {-->
<!--        background: #d92e3d;-->
<!--        color: #fff;-->
<!--    }-->
    
<!--    .action-btn-light {-->
<!--        background: #2f2f2f;-->
<!--        color: #fff;-->
<!--        border: 0;-->
<!--    }-->
    
<!--    .action-btn-light:hover {-->
<!--        background: #1f1f1f;-->
<!--        color: #fff;-->
<!--    }-->
    
<!--    .response-box {-->
<!--        position: fixed;-->
<!--        top: 24px;-->
<!--        right: 24px;-->
<!--        z-index: 9999;-->
<!--        min-width: 280px;-->
<!--        max-width: 420px;-->
<!--        padding: 16px 18px;-->
<!--        border-radius: 12px;-->
<!--        font-weight: bold;-->
<!--        box-shadow: 0 12px 30px rgba(0,0,0,0.18);-->
<!--        animation: slideDown 0.3s ease;-->
<!--    }-->
    
<!--    .response-success {-->
<!--        background: #e8fff0;-->
<!--        color: #167a3c;-->
<!--        border: 1px solid #b8e8c8;-->
<!--    }-->
    
<!--    .response-error {-->
<!--        background: #fff0f2;-->
<!--        color: #ef3f4d;-->
<!--        border: 1px solid #ffc4cc;-->
<!--    }-->
    
<!--    .response-box small {-->
<!--        display: block;-->
<!--        margin-top: 5px;-->
<!--        font-weight: normal;-->
<!--        color: inherit;-->
<!--        opacity: 0.8;-->
<!--    }-->
    
<!--    @keyframes slideDown {-->
<!--        from {-->
<!--            opacity: 0;-->
<!--            transform: translateY(-12px);-->
<!--        }-->
    
<!--        to {-->
<!--            opacity: 1;-->
<!--            transform: translateY(0);-->
<!--        }-->
<!--    }-->
    
<!--    .add-tabs {-->
<!--        display: flex;-->
<!--        gap: 0;-->
<!--        padding: 20px 22px 0;-->
<!--        border-bottom: 1px solid #eee;-->
<!--    }-->
    
<!--    .add-tab-btn {-->
<!--        background: transparent;-->
<!--        border: 1px solid transparent;-->
<!--        color: #ef3f4d;-->
<!--        padding: 12px 18px;-->
<!--        font-size: 15px;-->
<!--        cursor: pointer;-->
<!--        border-radius: 8px 8px 0 0;-->
<!--        margin-bottom: -1px;-->
<!--        transition: 0.2s ease;-->
<!--    }-->
    
<!--    .add-tab-btn.active {-->
<!--        color: #222;-->
<!--        background: #fff;-->
<!--        border-color: #222;-->
<!--        border-bottom-color: #fff;-->
<!--        font-weight: bold;-->
<!--    }-->
    
<!--    .tab-panel {-->
<!--        display: none;-->
<!--        padding: 22px;-->
<!--    }-->
    
<!--    .tab-panel.active {-->
<!--        display: block;-->
<!--    }-->
    
<!--    .form-grid {-->
<!--        display: block;-->
<!--    }-->
    
<!--    .form-group {-->
<!--        width: 50%;-->
<!--        margin-bottom: 16px;-->
<!--    }-->
    
<!--    .form-group.full {-->
<!--        width: 100%;-->
<!--    }-->
    
<!--    .form-group input,-->
<!--    .form-group select,-->
<!--    .form-group textarea {-->
<!--        width: 100%;-->
<!--    }-->
    
<!--    .section-title {-->
<!--        width: 100%;-->
<!--        margin-top: 28px;-->
<!--        margin-bottom: 16px;-->
<!--        padding-top: 22px;-->
<!--        border-top: 1px solid #eeeeee;-->
<!--        font-size: 20px;-->
<!--        font-weight: bold;-->
<!--    }-->
    
<!--    .section-title:first-child {-->
<!--        margin-top: 0;-->
<!--    }-->
    
<!--    .image-tab-content {-->
<!--        min-height: 460px;-->
<!--    }-->
    
<!--    .upload-area {-->
<!--        border: 1px solid #eee;-->
<!--        background: #fafafa;-->
<!--        height: 100px;-->
<!--        display: flex;-->
<!--        align-items: center;-->
<!--        justify-content: center;-->
<!--        color: #333;-->
<!--        margin: 0 0 16px;-->
<!--        transition: 0.2s ease;-->
<!--    }-->
    
<!--    .upload-area.dragover {-->
<!--        border-color: #ef3f4d;-->
<!--        background: #fff7f8;-->
<!--        color: #ef3f4d;-->
<!--    }-->
    
<!--    .add-image-row {-->
<!--        display: block;-->
<!--    }-->
    
<!--    .upload-preview-area {-->
<!--        display: flex;-->
<!--        flex-wrap: wrap;-->
<!--        gap: 18px 30px;-->
<!--        margin-top: 14px;-->
<!--        align-items: flex-start;-->
<!--        max-width: 1100px;-->
<!--    }-->
    
<!--    .upload-card {-->
<!--        width: 180px;-->
<!--        position: relative;-->
<!--    }-->
    
<!--    .upload-box {-->
<!--        width: 180px;-->
<!--        height: 180px;-->
<!--        background: #eef3f6;-->
<!--        border: 1px solid #ddd;-->
<!--        display: flex;-->
<!--        align-items: center;-->
<!--        justify-content: center;-->
<!--        cursor: pointer;-->
<!--        position: relative;-->
<!--        overflow: hidden;-->
<!--        border-radius: 4px;-->
<!--        transition: 0.2s ease;-->
<!--    }-->
    
<!--    .upload-box:hover {-->
<!--        border-color: #ef3f4d;-->
<!--    }-->
    
<!--    .upload-placeholder {-->
<!--        text-align: center;-->
<!--        color: #999;-->
<!--    }-->
    
<!--    .upload-icon {-->
<!--        font-size: 48px;-->
<!--        line-height: 1;-->
<!--        margin-bottom: 8px;-->
<!--    }-->
    
<!--    .upload-label {-->
<!--        background: #999;-->
<!--        color: #fff;-->
<!--        padding: 6px 10px;-->
<!--        border-radius: 4px;-->
<!--        display: inline-block;-->
<!--        font-size: 13px;-->
<!--    }-->
    
<!--    .preview-upload-img {-->
<!--        width: 180px;-->
<!--        height: 180px;-->
<!--        object-fit: cover;-->
<!--        border: 1px solid #ddd;-->
<!--        display: block;-->
<!--        border-radius: 4px;-->
<!--    }-->
    
<!--    .remove-preview-btn {-->
<!--        position: absolute;-->
<!--        top: 0;-->
<!--        right: 0;-->
<!--        width: 24px;-->
<!--        height: 24px;-->
<!--        border: 0;-->
<!--        background: #ef3f4d;-->
<!--        color: #fff;-->
<!--        font-weight: bold;-->
<!--        cursor: pointer;-->
<!--        z-index: 2;-->
<!--        border-radius: 0 4px 0 6px;-->
<!--        line-height: 1;-->
<!--        font-size: 16px;-->
<!--        transition: 0.2s ease;-->
<!--    }-->
    
<!--    .remove-preview-btn:hover {-->
<!--        background: #d92e3d;-->
<!--    }-->
    
<!--    .add-button-holder {-->
<!--        display: flex;-->
<!--        align-items: flex-start;-->
<!--        width: auto;-->
<!--        padding-top: 0;-->
<!--    }-->
    
<!--    .add-image-side-btn {-->
<!--        background: #7dd3e8;-->
<!--        color: #fff;-->
<!--        border: 0;-->
<!--        padding: 7px 12px;-->
<!--        font-weight: 700;-->
<!--        cursor: pointer;-->
<!--        font-size: 12px;-->
<!--        height: 28px;-->
<!--        line-height: 1;-->
<!--        white-space: nowrap;-->
<!--        border-radius: 6px;-->
<!--        transition: 0.2s ease;-->
<!--        margin-top: 0;-->
<!--    }-->
    
<!--    .add-image-side-btn:hover {-->
<!--        background: #59bfd8;-->
<!--        transform: translateY(-1px);-->
<!--    }-->
    
<!--    .image-description-input {-->
<!--        width: 100%;-->
<!--        height: 34px;-->
<!--        min-height: 34px;-->
<!--        border: 1px solid #ccc;-->
<!--        border-radius: 4px;-->
<!--        padding: 0 10px;-->
<!--        font-size: 14px;-->
<!--        font-style: italic;-->
<!--        margin-top: 6px;-->
<!--    }-->
    
<!--    .image-input-hidden {-->
<!--        display: none;-->
<!--    }-->
    
<!--    .form-note {-->
<!--        color: #777;-->
<!--        margin-top: 18px;-->
<!--        font-size: 14px;-->
<!--    }-->
    
<!--    @media (max-width: 900px) {-->
<!--        .form-group {-->
<!--            width: 100%;-->
<!--        }-->
<!--    }-->
    
<!--    @media (max-width: 650px) {-->
<!--        .upload-preview-area {-->
<!--            display: grid;-->
<!--            grid-template-columns: 1fr;-->
<!--            max-width: none;-->
<!--        }-->
    
<!--        .upload-card,-->
<!--        .upload-box,-->
<!--        .preview-upload-img {-->
<!--            width: 100%;-->
<!--        }-->
    
<!--        .upload-box,-->
<!--        .preview-upload-img {-->
<!--            height: 220px;-->
<!--        }-->
    
<!--        .response-box {-->
<!--            left: 18px;-->
<!--            right: 18px;-->
<!--            top: 18px;-->
<!--            min-width: auto;-->
<!--            max-width: none;-->
<!--        }-->
<!--    }-->
<!--</style>-->

<!--<div class="add-page-title">-->
<!--    <h1>[New] Products</h1>-->
<!--</div>-->

<!--<div class="breadcrumb">-->
<!--    <span>Products</span> › Products-->
<!--</div>-->

<!--<?php if ($success): ?>-->
<!--    <div class="response-box response-success" id="responseBox">-->
<!--        <?php echo e($success); ?>-->
<!--        <small>Your product has been added.</small>-->
<!--    </div>-->
<!--<?php endif; ?>-->

<!--<?php if ($error): ?>-->
<!--    <div class="response-box response-error" id="responseBox">-->
<!--        <?php echo e($error); ?>-->
<!--        <small>Please check the form and try again.</small>-->
<!--    </div>-->
<!--<?php endif; ?>-->

<!--<form method="post" enctype="multipart/form-data" id="productAddForm">-->
<!--    <input type="hidden" name="save_product" value="1">-->
<!--    <input type="hidden" name="submit_action" id="submitActionInput" value="insert">-->

<!--    <div class="add-card">-->
<!--        <div class="top-actions">-->
<!--            <button type="submit" class="action-btn" data-action="insert">Insert</button>-->
<!--            <button type="submit" class="action-btn" data-action="insert_add">Insert and Add</button>-->
<!--            <button type="submit" class="action-btn" data-action="insert_exit">Insert and Exit</button>-->
<!--            <a href="product-list.php" class="action-btn action-btn-light">Cancel</a>-->
<!--        </div>-->

<!--        <div class="add-tabs">-->
<!--            <button type="button" class="add-tab-btn active" data-tab="productInfoTab">-->
<!--                Product Info-->
<!--            </button>-->

<!--            <button type="button" class="add-tab-btn" data-tab="imageTab">-->
<!--                Image-->
<!--            </button>-->
<!--        </div>-->

<!--        <div id="productInfoTab" class="tab-panel active">-->
<!--            <div class="form-grid">-->
<!--                <div class="section-title">Buggy Information</div>-->

<!--                <div class="form-group">-->
<!--                    <label>Buggy Brand *</label>-->
<!--                    <select name="brand" required>-->
<!--                        <option value="">Select brand</option>-->

<!--                        <?php foreach ($brandOptions as $brandOption): ?>-->
<!--                            <option value="<?php echo e($brandOption); ?>" <?php echo $formData['brand'] === $brandOption ? 'selected' : ''; ?>>-->
<!--                                <?php echo e($brandOption); ?>-->
<!--                            </option>-->
<!--                        <?php endforeach; ?>-->
<!--                    </select>-->
<!--                </div>-->

<!--                <div class="form-group">-->
<!--                    <label>Custom Brand</label>-->
<!--                    <input-->
<!--                        type="text"-->
<!--                        name="custom_brand"-->
<!--                        placeholder="Only fill if brand is Others"-->
<!--                        value="<?php echo e($formData['custom_brand']); ?>"-->
<!--                    >-->
<!--                </div>-->

<!--                <div class="form-group">-->
<!--                    <label>Buggy Model *</label>-->
<!--                    <input-->
<!--                        type="text"-->
<!--                        name="model"-->
<!--                        placeholder="Example: Tempo 4-Seater"-->
<!--                        value="<?php echo e($formData['model']); ?>"-->
<!--                        required-->
<!--                    >-->
<!--                </div>-->

<!--                <div class="form-group">-->
<!--                    <label>Buggy Category / Seats *</label>-->
<!--                    <select name="seats" required>-->
<!--                        <option value="">Select seats</option>-->
<!--                        <option value="2" <?php echo $formData['seats'] === '2' ? 'selected' : ''; ?>>2 Seater</option>-->
<!--                        <option value="3" <?php echo $formData['seats'] === '3' ? 'selected' : ''; ?>>3 Seater</option>-->
<!--                        <option value="4" <?php echo $formData['seats'] === '4' ? 'selected' : ''; ?>>4 Seater</option>-->
<!--                        <option value="6" <?php echo $formData['seats'] === '6' ? 'selected' : ''; ?>>6 Seater</option>-->
<!--                        <option value="8" <?php echo $formData['seats'] === '8' ? 'selected' : ''; ?>>8 Seater</option>-->
<!--                    </select>-->
<!--                </div>-->

<!--                <div class="form-group">-->
<!--                    <label>New / Used</label>-->
<!--                    <select name="buggy_condition">-->
<!--                        <option value="new" <?php echo $formData['buggy_condition'] === 'new' ? 'selected' : ''; ?>>New Buggy</option>-->
<!--                        <option value="used" <?php echo $formData['buggy_condition'] === 'used' ? 'selected' : ''; ?>>Used Buggy</option>-->
<!--                    </select>-->
<!--                </div>-->

<!--                <div class="form-group">-->
<!--                    <label>Listing Type *</label>-->
<!--                    <select name="listing_type" required>-->
<!--                        <option value="sale" <?php echo $formData['listing_type'] === 'sale' ? 'selected' : ''; ?>>For Sale Only</option>-->
<!--                        <option value="rent" <?php echo $formData['listing_type'] === 'rent' ? 'selected' : ''; ?>>For Rent Only</option>-->
<!--                        <option value="sale_rent" <?php echo $formData['listing_type'] === 'sale_rent' ? 'selected' : ''; ?>>For Sale + Rent</option>-->
<!--                        <option value="both" <?php echo $formData['listing_type'] === 'both' ? 'selected' : ''; ?>>Sale & Rent</option>-->
<!--                    </select>-->
<!--                </div>-->

<!--                <div class="form-group">-->
<!--                    <label>Status Managed By Admin</label>-->
<!--                    <select name="status">-->
<!--                        <option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>Open / Active</option>-->
<!--                        <option value="sold" <?php echo $formData['status'] === 'sold' ? 'selected' : ''; ?>>Sold</option>-->
<!--                        <option value="rented" <?php echo $formData['status'] === 'rented' ? 'selected' : ''; ?>>Rented</option>-->
<!--                        <option value="pending" <?php echo $formData['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>-->
<!--                        <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>-->
<!--                    </select>-->
<!--                </div>-->

<!--                <div class="section-title">Selling Price</div>-->

<!--                <div class="form-group">-->
<!--                    <label>Selling Price</label>-->
<!--                    <input-->
<!--                        type="number"-->
<!--                        step="0.01"-->
<!--                        name="original_price"-->
<!--                        placeholder="Example: 8500"-->
<!--                        value="<?php echo e($formData['original_price']); ?>"-->
<!--                    >-->
<!--                    <div class="help">Use 0 if this buggy is rental only.</div>-->
<!--                </div>-->

<!--                <div class="section-title">Rental Prices</div>-->

<!--                <div class="form-group">-->
<!--                    <label>1 Day Rental Price</label>-->
<!--                    <input type="number" step="0.01" name="rent_price_1_day" value="<?php echo e($formData['rent_price_1_day']); ?>">-->
<!--                </div>-->

<!--                <div class="form-group">-->
<!--                    <label>3 Days Rental Price</label>-->
<!--                    <input type="number" step="0.01" name="rent_price_3_days" value="<?php echo e($formData['rent_price_3_days']); ?>">-->
<!--                </div>-->

<!--                <div class="form-group">-->
<!--                    <label>1 Week Rental Price</label>-->
<!--                    <input type="number" step="0.01" name="rent_price_1_week" value="<?php echo e($formData['rent_price_1_week']); ?>">-->
<!--                </div>-->

<!--                <div class="form-group">-->
<!--                    <label>1 Month Rental Price</label>-->
<!--                    <input type="number" step="0.01" name="rent_price_1_month" value="<?php echo e($formData['rent_price_1_month']); ?>">-->
<!--                </div>-->

<!--                <div class="section-title">Tags & Product Details</div>-->

<!--                <div class="form-group">-->
<!--                    <label>Brand Tag</label>-->
<!--                    <select name="brand_tag">-->
<!--                        <option value="">Auto use selected brand</option>-->

<!--                        <?php foreach ($brandOptions as $brandOption): ?>-->
<!--                            <?php if ($brandOption !== 'Others'): ?>-->
<!--                                <option value="<?php echo e($brandOption); ?>" <?php echo $formData['brand_tag'] === $brandOption ? 'selected' : ''; ?>>-->
<!--                                    <?php echo e($brandOption); ?>-->
<!--                                </option>-->
<!--                            <?php endif; ?>-->
<!--                        <?php endforeach; ?>-->
<!--                    </select>-->
<!--                </div>-->

<!--                <div class="form-group">-->
<!--                    <label>Product Tag</label>-->
<!--                    <select name="tag">-->
<!--                        <option value="">Auto use New/Used</option>-->

<!--                        <?php foreach ($productTagOptions as $tagOption): ?>-->
<!--                            <option value="<?php echo e($tagOption); ?>" <?php echo $formData['tag'] === $tagOption ? 'selected' : ''; ?>>-->
<!--                                <?php echo e($tagOption); ?>-->
<!--                            </option>-->
<!--                        <?php endforeach; ?>-->
<!--                    </select>-->
<!--                </div>-->

<!--                <div class="form-group full">-->
<!--                    <label>Short Info</label>-->
<!--                    <input-->
<!--                        type="text"-->
<!--                        name="short_info"-->
<!--                        placeholder="Example: 4-seater electric buggy suitable for resorts and events"-->
<!--                        value="<?php echo e($formData['short_info']); ?>"-->
<!--                    >-->
<!--                </div>-->

<!--                <div class="form-group full">-->
<!--                    <label>Product Description</label>-->
<!--                    <textarea name="description" placeholder="Write product details here"><?php echo e($formData['description']); ?></textarea>-->
<!--                </div>-->
<!--            </div>-->
<!--        </div>-->

<!--        <div id="imageTab" class="tab-panel">-->
<!--            <div class="image-tab-content">-->
<!--                <div class="upload-area" id="dropArea">-->
<!--                    Drag & drop multiple images here.-->
<!--                </div>-->

<!--                <div class="add-image-row">-->
<!--                    <div class="upload-preview-area" id="galleryUploadArea">-->
<!--                        <div class="upload-card">-->
<!--                            <button type="button" class="remove-preview-btn">×</button>-->

<!--                            <label class="upload-box">-->
<!--                                <div class="upload-placeholder">-->
<!--                                    <div class="upload-icon">▧</div>-->
<!--                                    <div class="upload-label">click here to upload</div>-->
<!--                                </div>-->

<!--                                <input-->
<!--                                    type="file"-->
<!--                                    name="gallery_images[]"-->
<!--                                    class="image-input-hidden single-gallery-input"-->
<!--                                    accept="image/*"-->
<!--                                >-->
<!--                            </label>-->

<!--                            <input-->
<!--                                type="text"-->
<!--                                class="image-description-input"-->
<!--                                placeholder="Description"-->
<!--                                disabled-->
<!--                            >-->
<!--                        </div>-->

<!--                        <div class="add-button-holder">-->
<!--                            <button type="button" class="add-image-side-btn" id="addGalleryBoxBtn">-->
<!--                                + Add-->
<!--                            </button>-->
<!--                        </div>-->
<!--                    </div>-->
<!--                </div>-->

<!--                <div class="form-note">-->
<!--                    First uploaded image will become the primary product image. Click <strong>+ Add</strong> to create more empty image boxes.-->
<!--                </div>-->
<!--            </div>-->
<!--        </div>-->

<!--        <div class="bottom-actions">-->
<!--            <button type="submit" class="action-btn" data-action="insert">Insert</button>-->
<!--            <button type="submit" class="action-btn" data-action="insert_add">Insert and Add</button>-->
<!--            <button type="submit" class="action-btn" data-action="insert_exit">Insert and Exit</button>-->
<!--            <a href="product-list.php" class="action-btn action-btn-light">Cancel</a>-->
<!--        </div>-->
<!--    </div>-->
<!--</form>-->

<!--<script>-->
<!--    const tabButtons = document.querySelectorAll('.add-tab-btn');-->
<!--    const tabPanels = document.querySelectorAll('.tab-panel');-->
<!--    const submitActionInput = document.getElementById('submitActionInput');-->

<!--    tabButtons.forEach(function (button) {-->
<!--        button.addEventListener('click', function () {-->
<!--            const targetTab = this.getAttribute('data-tab');-->

<!--            tabButtons.forEach(function (btn) {-->
<!--                btn.classList.remove('active');-->
<!--            });-->

<!--            tabPanels.forEach(function (panel) {-->
<!--                panel.classList.remove('active');-->
<!--            });-->

<!--            this.classList.add('active');-->
<!--            document.getElementById(targetTab).classList.add('active');-->
<!--        });-->
<!--    });-->

<!--    document.querySelectorAll('button[data-action]').forEach(function (button) {-->
<!--        button.addEventListener('click', function () {-->
<!--            submitActionInput.value = this.getAttribute('data-action');-->
<!--        });-->
<!--    });-->

<!--    const responseBox = document.getElementById('responseBox');-->

<!--    if (responseBox) {-->
<!--        setTimeout(function () {-->
<!--            responseBox.style.opacity = '0';-->
<!--            responseBox.style.transform = 'translateY(-12px)';-->
<!--            responseBox.style.transition = '0.3s ease';-->

<!--            setTimeout(function () {-->
<!--                responseBox.remove();-->
<!--            }, 300);-->
<!--        }, 3000);-->
<!--    }-->

<!--    const galleryUploadArea = document.getElementById('galleryUploadArea');-->
<!--    const addGalleryBoxBtn = document.getElementById('addGalleryBoxBtn');-->
<!--    const dropArea = document.getElementById('dropArea');-->

<!--    function getAddButtonHolder() {-->
<!--        return document.querySelector('.add-button-holder');-->
<!--    }-->

<!--    function bindUploadCard(card) {-->
<!--        const fileInput = card.querySelector('input[type="file"]');-->
<!--        const removeBtn = card.querySelector('.remove-preview-btn');-->

<!--        fileInput.addEventListener('change', function () {-->
<!--            if (this.files && this.files[0]) {-->
<!--                showImageInCard(card, this.files[0]);-->
<!--            }-->
<!--        });-->

<!--        removeBtn.addEventListener('click', function () {-->
<!--            card.remove();-->

<!--            if (galleryUploadArea.querySelectorAll('.upload-card').length === 0) {-->
<!--                galleryUploadArea.insertBefore(createGalleryUploadCard(), getAddButtonHolder());-->
<!--            }-->
<!--        });-->
<!--    }-->

<!--    function showImageInCard(card, file) {-->
<!--        const label = card.querySelector('.upload-box');-->

<!--        label.innerHTML = '';-->

<!--        const img = document.createElement('img');-->
<!--        img.className = 'preview-upload-img';-->
<!--        img.src = URL.createObjectURL(file);-->
<!--        img.alt = 'Selected image';-->

<!--        const input = document.createElement('input');-->
<!--        input.type = 'file';-->
<!--        input.name = 'gallery_images[]';-->
<!--        input.className = 'image-input-hidden single-gallery-input';-->
<!--        input.accept = 'image/*';-->

<!--        const dataTransfer = new DataTransfer();-->
<!--        dataTransfer.items.add(file);-->
<!--        input.files = dataTransfer.files;-->

<!--        label.appendChild(img);-->
<!--        label.appendChild(input);-->

<!--        input.addEventListener('change', function () {-->
<!--            if (this.files && this.files[0]) {-->
<!--                showImageInCard(card, this.files[0]);-->
<!--            }-->
<!--        });-->
<!--    }-->

<!--    function createGalleryUploadCard(file = null) {-->
<!--        const card = document.createElement('div');-->
<!--        card.className = 'upload-card';-->

<!--        const removeBtn = document.createElement('button');-->
<!--        removeBtn.type = 'button';-->
<!--        removeBtn.className = 'remove-preview-btn';-->
<!--        removeBtn.innerHTML = '×';-->

<!--        const label = document.createElement('label');-->
<!--        label.className = 'upload-box';-->

<!--        const placeholder = document.createElement('div');-->
<!--        placeholder.className = 'upload-placeholder';-->
<!--        placeholder.innerHTML = `-->
<!--            <div class="upload-icon">▧</div>-->
<!--            <div class="upload-label">click here to upload</div>-->
<!--        `;-->

<!--        const input = document.createElement('input');-->
<!--        input.type = 'file';-->
<!--        input.name = 'gallery_images[]';-->
<!--        input.className = 'image-input-hidden single-gallery-input';-->
<!--        input.accept = 'image/*';-->

<!--        const desc = document.createElement('input');-->
<!--        desc.type = 'text';-->
<!--        desc.className = 'image-description-input';-->
<!--        desc.placeholder = 'Description';-->
<!--        desc.disabled = true;-->

<!--        label.appendChild(placeholder);-->
<!--        label.appendChild(input);-->

<!--        card.appendChild(removeBtn);-->
<!--        card.appendChild(label);-->
<!--        card.appendChild(desc);-->

<!--        bindUploadCard(card);-->

<!--        if (file) {-->
<!--            showImageInCard(card, file);-->
<!--        }-->

<!--        return card;-->
<!--    }-->

<!--    document.querySelectorAll('.upload-card').forEach(function (card) {-->
<!--        bindUploadCard(card);-->
<!--    });-->

<!--    addGalleryBoxBtn.addEventListener('click', function () {-->
<!--        galleryUploadArea.insertBefore(createGalleryUploadCard(), getAddButtonHolder());-->
<!--    });-->

<!--    dropArea.addEventListener('dragover', function (event) {-->
<!--        event.preventDefault();-->
<!--        dropArea.classList.add('dragover');-->
<!--    });-->

<!--    dropArea.addEventListener('dragleave', function () {-->
<!--        dropArea.classList.remove('dragover');-->
<!--    });-->

<!--    dropArea.addEventListener('drop', function (event) {-->
<!--        event.preventDefault();-->
<!--        dropArea.classList.remove('dragover');-->

<!--        Array.from(event.dataTransfer.files).forEach(function (file) {-->
<!--            if (file.type.startsWith('image/')) {-->
<!--                galleryUploadArea.insertBefore(createGalleryUploadCard(file), getAddButtonHolder());-->
<!--            }-->
<!--        });-->
<!--    });-->
<!--</script>-->

<!--<?php include 'footer.php'; ?>-->