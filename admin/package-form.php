<?php
require_once '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$success = '';
$error   = '';

$formData = [
    'name'           => '',
    'tagline'        => '',
    'amount'         => '',
    'listing_limit'  => 10,
    'benefits'       => [],
    'is_highlighted' => 0,
    'color_theme'    => 'blue',
    'status'         => 'active',
    'sort_order'     => 0,
];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM seller_packages WHERE id = ?");
    $stmt->execute([$id]);
    $pkg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pkg) die('Package not found.');

    foreach ($formData as $key => $val) {
        if ($key === 'benefits') continue;
        if (array_key_exists($key, $pkg)) $formData[$key] = $pkg[$key];
    }

    if (!empty($pkg['benefits'])) {
        $decoded = json_decode($pkg['benefits'], true);
        $formData['benefits'] = is_array($decoded) ? $decoded : [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_package'])) {
    $name          = trim($_POST['name'] ?? '');
    $tagline       = trim($_POST['tagline'] ?? '');
    $amount        = (float)($_POST['amount'] ?? 0);
    $listingLimit  = (int)($_POST['listing_limit'] ?? 0);
    $isHighlighted = isset($_POST['is_highlighted']) ? 1 : 0;
    $colorTheme    = trim($_POST['color_theme'] ?? 'blue');
    $status        = trim($_POST['status'] ?? 'active');
    $sortOrder     = (int)($_POST['sort_order'] ?? 0);

    // Parse benefits
    $benefitInputs = $_POST['benefit_item'] ?? [];
    $parsedBenefits = [];
    if (is_array($benefitInputs)) {
        foreach ($benefitInputs as $b) {
            $b = trim((string)$b);
            if ($b !== '') $parsedBenefits[] = $b;
        }
    }
    $benefitsJson = !empty($parsedBenefits) ? json_encode($parsedBenefits) : null;

    $formData['name']           = $name;
    $formData['tagline']        = $tagline;
    $formData['amount']         = $amount;
    $formData['listing_limit']  = $listingLimit;
    $formData['benefits']       = $parsedBenefits;
    $formData['is_highlighted'] = $isHighlighted;
    $formData['color_theme']    = $colorTheme;
    $formData['status']         = $status;
    $formData['sort_order']     = $sortOrder;

    if ($name === '')         $error = 'Package name is required.';
    elseif ($amount <= 0)     $error = 'Amount must be greater than 0.';
    elseif ($listingLimit <= 0) $error = 'Listing limit must be at least 1.';

    if ($error === '') {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE seller_packages SET
                        name = :name, tagline = :tagline, amount = :amount,
                        listing_limit = :listing_limit, benefits = :benefits,
                        is_highlighted = :is_highlighted, color_theme = :color_theme,
                        status = :status, sort_order = :sort_order
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':name' => $name, ':tagline' => $tagline, ':amount' => $amount,
                    ':listing_limit' => $listingLimit, ':benefits' => $benefitsJson,
                    ':is_highlighted' => $isHighlighted, ':color_theme' => $colorTheme,
                    ':status' => $status, ':sort_order' => $sortOrder,
                    ':id' => $id,
                ]);
                header('Location: package-list.php?updated=1');
                exit;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO seller_packages
                        (name, tagline, amount, listing_limit, benefits, is_highlighted, color_theme, status, sort_order)
                    VALUES
                        (:name, :tagline, :amount, :listing_limit, :benefits, :is_highlighted, :color_theme, :status, :sort_order)
                ");
                $stmt->execute([
                    ':name' => $name, ':tagline' => $tagline, ':amount' => $amount,
                    ':listing_limit' => $listingLimit, ':benefits' => $benefitsJson,
                    ':is_highlighted' => $isHighlighted, ':color_theme' => $colorTheme,
                    ':status' => $status, ':sort_order' => $sortOrder,
                ]);
                header('Location: package-list.php?added=1');
                exit;
            }
        } catch (PDOException $e) {
            $error = ($isEdit ? 'Failed to update package: ' : 'Failed to add package: ') . $e->getMessage();
        }
    }
}

include 'header.php';
?>

<style>
    .pf-header { margin-bottom: 20px; }
    .pf-header h1 { margin: 0; font-size: 26px; }
    .pf-header p  { margin: 6px 0 0; color: #777; font-size: 14px; }

    .pf-card {
        background: #fff;
        border: 1px solid #eee;
        border-radius: 14px;
        padding: 28px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.05);
    }

    .pf-row { display: grid; grid-template-columns: 1fr; gap: 18px; margin-bottom: 18px; }
    .pf-group label { display: block; font-weight: bold; font-size: 14px; margin-bottom: 7px; color: #333; }
    .pf-group input, .pf-group select, .pf-group textarea {
        width: 100%; height: 44px;
        border: 1px solid #ddd; border-radius: 8px;
        padding: 0 12px; font-size: 14px; outline: none;
        background: #fff;
    }
    .pf-group input:focus, .pf-group select:focus { border-color: #ef3f4d; }
    .pf-group .help { color: #777; font-size: 12px; margin-top: 5px; }

    .pf-section-title {
        margin: 28px 0 14px;
        padding-top: 22px;
        border-top: 1px solid #eee;
        font-size: 17px;
        font-weight: bold;
    }

    .benefit-row {
        display: grid;
        grid-template-columns: 1fr 40px;
        gap: 10px;
        margin-bottom: 10px;
        align-items: center;
    }
    .benefit-row input {
        height: 42px;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 0 12px;
    }
    .benefit-remove {
        width: 36px; height: 36px; border: 0;
        background: #ef3f4d; color: #fff;
        border-radius: 6px; cursor: pointer;
        font-size: 18px; font-weight: bold;
        line-height: 1;
    }
    .benefit-add-btn {
        background: #2563eb; color: #fff;
        border: 0; padding: 10px 18px;
        border-radius: 7px; cursor: pointer;
        font-weight: 700; font-size: 14px;
    }
    .benefit-add-btn:hover { background: #1d4ed8; }

    .pf-checkbox-row {
        display: flex; align-items: center; gap: 8px;
        background: #fff8e6; padding: 12px 14px;
        border: 1px solid #ffe3a3; border-radius: 8px;
        font-size: 14px; color: #7a5700;
    }
    .pf-checkbox-row input[type=checkbox] { width: 18px; height: 18px; }

    .pf-actions { margin-top: 28px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 10px; }

    .message-error { background: #fff0f2; color: #ef3f4d; border: 1px solid #ffc4cc; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
</style>

<div class="pf-header">
    <h1><?php echo $isEdit ? 'Edit Package' : 'Add New Package'; ?></h1>
    <p>Configure package details, pricing, and benefits shown to sellers.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="message-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="pf-card">
    <form method="post">
        <input type="hidden" name="save_package" value="1">

        <div class="pf-row">
            <div class="pf-group">
                <label>Package Name *</label>
                <input type="text" name="name" placeholder="e.g. Basic Seller" value="<?php echo e($formData['name']); ?>" required>
            </div>
            <div class="pf-group">
                <label>Tagline</label>
                <input type="text" name="tagline" placeholder="e.g. Perfect for getting started" value="<?php echo e($formData['tagline']); ?>">
            </div>
        </div>

        <div class="pf-row">
            <div class="pf-group">
                <label>Amount ($) *</label>
                <input type="number" step="0.01" name="amount" placeholder="e.g. 98" value="<?php echo e($formData['amount']); ?>" required>
                <div class="help">One-time payment amount in SGD.</div>
            </div>
            <div class="pf-group">
                <label>Listing Limit *</label>
                <input type="number" name="listing_limit" placeholder="e.g. 10" value="<?php echo e($formData['listing_limit']); ?>" required min="1">
                <div class="help">Max number of buggy listings the seller can post.</div>
            </div>
        </div>

        <div class="pf-section-title">Benefits / Features</div>

        <div class="help" style="margin-bottom:12px;color:#777;font-size:13px;">
            Add bullet points shown on the seller's package card. Click + Add to add more.
        </div>

        <div id="benefitsList">
            <?php if (!empty($formData['benefits'])): ?>
                <?php foreach ($formData['benefits'] as $benefit): ?>
                    <div class="benefit-row">
                        <input type="text" name="benefit_item[]" placeholder="e.g. Up to 10 buggy listings" value="<?php echo e($benefit); ?>">
                        <button type="button" class="benefit-remove" onclick="this.parentElement.remove();">×</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <button type="button" class="benefit-add-btn" id="addBenefitBtn">+ Add Benefit</button>

        <div class="pf-section-title">Settings</div>

        <div class="pf-row">
            <div class="pf-group">
                <label>Status</label>
                <select name="status">
                    <option value="active"   <?php echo $formData['status'] === 'active'   ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive (Hidden)</option>
                </select>
            </div>
            <div class="pf-group">
                <label>Sort Order</label>
                <input type="number" name="sort_order" value="<?php echo (int)$formData['sort_order']; ?>">
                <div class="help">Lower number shows first.</div>
            </div>
        </div>

        <div class="pf-row">
            <div class="pf-group">
                <label>Color Theme</label>
                <select name="color_theme">
                    <option value="blue" <?php echo $formData['color_theme'] === 'blue' ? 'selected' : ''; ?>>Blue (Default)</option>
                    <option value="red"  <?php echo $formData['color_theme'] === 'red'  ? 'selected' : ''; ?>>Red</option>
                    <option value="gold" <?php echo $formData['color_theme'] === 'gold' ? 'selected' : ''; ?>>Gold</option>
                </select>
            </div>
            <div class="pf-group" style="display:flex;align-items:flex-end;">
                <label class="pf-checkbox-row" style="margin:0;cursor:pointer;width:100%;">
                    <input type="checkbox" name="is_highlighted" value="1" <?php echo (int)$formData['is_highlighted'] === 1 ? 'checked' : ''; ?>>
                    <span>Mark as "⭐ Most Popular"</span>
                </label>
            </div>
        </div>

        <div class="pf-actions">
            <button type="submit" class="btn"><?php echo $isEdit ? 'Save Changes' : 'Add Package'; ?></button>
            <a href="package-list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
    document.getElementById('addBenefitBtn').addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'benefit-row';
        row.innerHTML = '<input type="text" name="benefit_item[]" placeholder="e.g. Priority support">'
                      + '<button type="button" class="benefit-remove" onclick="this.parentElement.remove();">&times;</button>';
        document.getElementById('benefitsList').appendChild(row);
    });
</script>

<?php include 'footer.php'; ?>
