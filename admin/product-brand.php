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

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
    Add brand
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_brand'])) {
    $brandName = trim($_POST['brand_name'] ?? '');

    if ($brandName === '') {
        $error = 'Brand name is required.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO product_brands (brand_name, status)
                VALUES (:brand_name, 'active')
            ");

            $stmt->execute([
                ':brand_name' => $brandName
            ]);

            $success = 'Brand added successfully.';
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) {
                $error = 'This brand already exists.';
            } else {
                $error = 'Failed to add brand: ' . $e->getMessage();
            }
        }
    }
}

/*
    Update brand
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_brand'])) {
    $brandId = (int)($_POST['brand_id'] ?? 0);
    $brandName = trim($_POST['brand_name'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($brandId <= 0) {
        $error = 'Invalid brand ID.';
    } elseif ($brandName === '') {
        $error = 'Brand name is required.';
    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        $error = 'Invalid brand status.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE product_brands
                SET brand_name = :brand_name,
                    status = :status
                WHERE id = :id
            ");

            $stmt->execute([
                ':brand_name' => $brandName,
                ':status' => $status,
                ':id' => $brandId
            ]);

            $success = 'Brand updated successfully.';
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) {
                $error = 'This brand already exists.';
            } else {
                $error = 'Failed to update brand: ' . $e->getMessage();
            }
        }
    }
}

/*
    Delete brand
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_brand'])) {
    $brandId = (int)($_POST['brand_id'] ?? 0);

    if ($brandId <= 0) {
        $error = 'Invalid brand ID.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT brand_name FROM product_brands WHERE id = ?");
            $stmt->execute([$brandId]);
            $brand = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$brand) {
                $error = 'Brand not found.';
            } else {
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM buggies
                    WHERE brand = ?
                ");
                $checkStmt->execute([$brand['brand_name']]);
                $productCount = (int)$checkStmt->fetchColumn();

                if ($productCount > 0) {
                    $error = 'Cannot delete this brand because it is already used by product(s). You can set it to inactive instead.';
                } else {
                    $deleteStmt = $pdo->prepare("DELETE FROM product_brands WHERE id = ?");
                    $deleteStmt->execute([$brandId]);

                    $success = 'Brand deleted successfully.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Failed to delete brand: ' . $e->getMessage();
        }
    }
}

$stmt = $pdo->query("
    SELECT *
    FROM product_brands
    ORDER BY brand_name ASC
");
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<style>
    .brand-page-title {
        margin-bottom: 18px;
    }

    .brand-page-title h1 {
        margin: 0;
        font-size: 30px;
        font-weight: 500;
        color: #333;
    }

    .brand-page-title p {
        margin: 6px 0 0;
        color: #777;
        font-size: 15px;
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

    .brand-card {
        background: #fff;
        border-radius: 4px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        border: 1px solid #eee;
        padding: 22px;
        margin-bottom: 22px;
    }

    .brand-card h2 {
        margin: 0 0 18px;
        font-size: 22px;
        color: #333;
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

    .add-brand-form {
        display: flex;
        gap: 12px;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 7px;
        min-width: 320px;
    }

    .form-group label {
        font-weight: bold;
        font-size: 14px;
    }

    .form-group input,
    .form-group select {
        height: 42px;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        padding: 0 12px;
        font-size: 14px;
    }

    .brand-table-wrap {
        overflow-x: auto;
    }

    .brand-table {
        width: 100%;
        border-collapse: collapse;
    }

    .brand-table th,
    .brand-table td {
        border-bottom: 1px solid #eeeeee;
        padding: 14px 12px;
        text-align: left;
        vertical-align: middle;
    }

    .brand-table th {
        background: #fafafa;
        font-size: 14px;
        color: #333;
    }

    .brand-table td {
        font-size: 14px;
    }

    .brand-edit-form {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .brand-name-input {
        width: 260px;
        height: 40px;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        padding: 0 10px;
    }

    .brand-status-select {
        width: 130px;
        height: 40px;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        padding: 0 10px;
    }

    .btn-small {
        height: 40px;
        padding: 0 14px;
        border: 0;
        border-radius: 8px;
        font-weight: bold;
        cursor: pointer;
        color: #fff;
        background: #ef3f4d;
        transition: 0.2s ease;
    }

    .btn-small:hover {
        background: #d92e3d;
    }

    .btn-delete {
        background: #333;
    }

    .btn-delete:hover {
        background: #111;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 78px;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: bold;
    }

    .status-active {
        background: #e8fff0;
        color: #167a3c;
    }

    .status-inactive {
        background: #fff0f2;
        color: #ef3f4d;
    }

    .empty-message {
        padding: 24px;
        background: #fafafa;
        border: 1px dashed #ddd;
        color: #777;
        border-radius: 10px;
    }

    @media (max-width: 800px) {
        .form-group {
            min-width: 100%;
        }

        .add-brand-form {
            display: block;
        }

        .add-brand-form .btn {
            margin-top: 12px;
        }

        .brand-edit-form {
            display: block;
        }

        .brand-name-input,
        .brand-status-select {
            width: 100%;
            margin-bottom: 8px;
        }

        .btn-small {
            width: 100%;
            margin-bottom: 8px;
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

<div class="brand-page-title">
    <h1>Product Brand</h1>
    <p>Add, update, activate, deactivate or delete product brands.</p>
</div>

<div class="breadcrumb">
    <span>Products</span> &gt; Product Brand
</div>

<?php if ($success): ?>
    <div class="response-box response-success" id="responseBox">
        <?php echo e($success); ?>
        <small>Your brand changes have been saved.</small>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="response-box response-error" id="responseBox">
        <?php echo e($error); ?>
        <small>Please check and try again.</small>
    </div>
<?php endif; ?>

<div class="brand-card">
    <h2>Add New Brand</h2>

    <form method="post" class="add-brand-form">
        <input type="hidden" name="add_brand" value="1">

        <div class="form-group">
            <label>Brand Name *</label>
            <input type="text" name="brand_name" placeholder="Example: Toyota" required>
        </div>

        <button type="submit" class="btn">Add Brand</button>
    </form>
</div>

<div class="brand-card">
    <h2>Brand List</h2>

    <?php if (count($brands) > 0): ?>
        <div class="brand-table-wrap">
            <table class="brand-table">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Brand</th>
                        <th style="width:130px;">Status</th>
                        <th style="width:520px;">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($brands as $brand): ?>
                        <tr>
                            <td><?php echo (int)$brand['id']; ?></td>

                            <td>
                                <strong><?php echo e($brand['brand_name']); ?></strong>
                            </td>

                            <td>
                                <span class="status-badge <?php echo $brand['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo e(ucfirst($brand['status'])); ?>
                                </span>
                            </td>

                            <td>
                                <form method="post" class="brand-edit-form">
                                    <input type="hidden" name="brand_id" value="<?php echo (int)$brand['id']; ?>">

                                    <input
                                        type="text"
                                        name="brand_name"
                                        class="brand-name-input"
                                        value="<?php echo e($brand['brand_name']); ?>"
                                        required
                                    >

                                    <select name="status" class="brand-status-select">
                                        <option value="active" <?php echo $brand['status'] === 'active' ? 'selected' : ''; ?>>
                                            Active
                                        </option>
                                        <option value="inactive" <?php echo $brand['status'] === 'inactive' ? 'selected' : ''; ?>>
                                            Inactive
                                        </option>
                                    </select>

                                    <button type="submit" name="update_brand" value="1" class="btn-small">
                                        Update
                                    </button>

                                    <button
                                        type="submit"
                                        name="delete_brand"
                                        value="1"
                                        class="btn-small btn-delete"
                                        onclick="return confirm('Delete this brand? This cannot be undone.');"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-message">
            No brands found. Add your first brand above.
        </div>
    <?php endif; ?>
</div>

<script>
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
</script>

<?php include 'footer.php'; ?>