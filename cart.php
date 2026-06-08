<?php
// cart.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$action = $_GET['action'] ?? '';
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'add' && $productId > 0) {
    if (!isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] = 1;
    } else {
        $_SESSION['cart'][$productId]++;
    }

    try {
        $logStmt = $pdo->prepare("
            INSERT INTO add_to_cart_logs (
                buggy_id,
                quantity,
                session_id,
                member_id,
                ip_address,
                user_agent,
                added_at
            ) VALUES (
                :buggy_id,
                :quantity,
                :session_id,
                :member_id,
                :ip_address,
                :user_agent,
                NOW()
            )
        ");

        $logStmt->execute([
            ':buggy_id' => $productId,
            ':quantity' => 1,
            ':session_id' => session_id(),
            ':member_id' => isset($_SESSION['member_id']) ? (int)$_SESSION['member_id'] : null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (PDOException $e) {
        /*
            Do not stop cart function if logging fails.
            The buggy is still added to cart normally.
        */
    }

    header('Location: cart.php?added=1');
    exit;
}

if ($action === 'remove' && $productId > 0) {
    unset($_SESSION['cart'][$productId]);

    header('Location: cart.php?removed=1');
    exit;
}

if ($action === 'clear') {
    $_SESSION['cart'] = [];

    header('Location: cart.php?cleared=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantities']) && is_array($_POST['quantities'])) {
    foreach ($_POST['quantities'] as $id => $qty) {
        $id = (int)$id;
        $qty = (int)$qty;

        if ($id <= 0) {
            continue;
        }

        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
        } else {
            $_SESSION['cart'][$id] = $qty;
        }
    }

    header('Location: cart.php?updated=1');
    exit;
}

$cartItems = [];
$subtotal = 0;

$cartIds = array_keys($_SESSION['cart']);
$cartIds = array_filter($cartIds, function ($id) {
    return (int)$id > 0;
});

if (!empty($cartIds)) {
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));

    $stmt = $pdo->prepare("
        SELECT 
            id,
            brand,
            model,
            name,
            seats,
            buggy_condition,
            selling_price,
            image_url,
            status
        FROM buggies
        WHERE id IN ($placeholders)
        AND status = 'active'
    ");

    $stmt->execute(array_values($cartIds));
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $product) {
        $id = (int)$product['id'];
        $qty = isset($_SESSION['cart'][$id]) ? (int)$_SESSION['cart'][$id] : 1;
        $price = (float)($product['selling_price'] ?? 0);
        $lineTotal = $price * $qty;

        $subtotal += $lineTotal;

        $cartItems[] = [
            'id' => $id,
            'name' => !empty($product['name']) ? $product['name'] : trim(($product['brand'] ?? '') . ' ' . ($product['model'] ?? '')),
            'brand' => $product['brand'] ?? '',
            'model' => $product['model'] ?? '',
            'seats' => $product['seats'] ?? '',
            'condition' => $product['buggy_condition'] ?? '',
            'price' => $price,
            'qty' => $qty,
            'line_total' => $lineTotal,
            'image_url' => !empty($product['image_url']) ? $product['image_url'] : 'images/no-image.jpg'
        ];
    }
}

$total = $subtotal;
?>

<?php include 'header.php'; ?>

<style>
    .cart-page {
        max-width: 1180px;
        margin: 0 auto;
        padding: 30px 20px 70px;
    }

    .cart-breadcrumb {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #8b95a1;
        font-size: 13px;
        margin-bottom: 26px;
    }

    .cart-breadcrumb a {
        color: #8b95a1;
        text-decoration: none;
    }

    .cart-layout {
        display: grid;
        grid-template-columns: 1fr 355px;
        gap: 36px;
        align-items: start;
    }

    .cart-title {
        margin: 0 0 22px;
        color: #111827;
        font-size: 28px;
        font-weight: 900;
    }

    .cart-alert {
        margin-bottom: 18px;
        border: 1px solid #c7e7d0;
        background: #effff3;
        color: #167a3c;
        padding: 12px 15px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 14px;
    }

    .cart-empty {
        min-height: 360px;
        border: 1px solid #eeeeee;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        background: #ffffff;
        padding: 40px 20px;
    }

    .empty-icon {
        width: 110px;
        height: 90px;
        margin: 0 auto 18px;
        opacity: 0.35;
        font-size: 74px;
        line-height: 1;
    }

    .cart-empty h2 {
        margin: 0 0 18px;
        color: #111827;
        font-size: 20px;
        font-weight: 900;
    }

    .start-shopping-btn {
        width: 270px;
        height: 42px;
        border: 1px solid #ef3f4d;
        border-radius: 6px;
        background: #ffffff;
        color: #ef3f4d;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        text-decoration: none;
        transition: 0.2s ease;
    }

    .start-shopping-btn:hover {
        background: #ef3f4d;
        color: #ffffff;
    }

    .cart-items {
        display: grid;
        gap: 16px;
    }

    .cart-item {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        background: #ffffff;
        padding: 18px;
        display: grid;
        grid-template-columns: 135px 1fr auto;
        gap: 18px;
        align-items: center;
    }

    .cart-item-image {
        width: 135px;
        height: 100px;
        border-radius: 10px;
        overflow: hidden;
        background: #f3f4f6;
    }

    .cart-item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .cart-item-name {
        margin: 0 0 8px;
        color: #111827;
        font-size: 17px;
        font-weight: 900;
    }

    .cart-item-meta {
        color: #6b7280;
        font-size: 13px;
        line-height: 1.6;
    }

    .cart-item-price {
        margin-top: 8px;
        color: #ef3f4d;
        font-size: 18px;
        font-weight: 900;
    }

    .cart-item-actions {
        min-width: 150px;
        display: grid;
        gap: 10px;
        justify-items: end;
    }

    .qty-input {
        width: 80px;
        height: 38px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 0 10px;
        text-align: center;
        font-weight: 800;
    }

    .remove-link {
        color: #ef3f4d;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
    }

    .cart-update-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-top: 18px;
        flex-wrap: wrap;
    }

    .cart-left-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .update-cart-btn,
    .clear-cart-btn,
    .continue-shopping-btn {
        height: 42px;
        border-radius: 8px;
        padding: 0 20px;
        font-weight: 800;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .update-cart-btn {
        border: 0;
        background: #0066cc;
        color: #ffffff;
    }

    .update-cart-btn:hover {
        background: #004f99;
    }

    .clear-cart-btn {
        border: 1px solid #ef3f4d;
        background: #ffffff;
        color: #ef3f4d;
    }

    .clear-cart-btn:hover {
        background: #ef3f4d;
        color: #ffffff;
    }

    .continue-shopping-btn {
        border: 1px solid #0066cc;
        background: #ffffff;
        color: #0066cc;
    }

    .continue-shopping-btn:hover {
        background: #0066cc;
        color: #ffffff;
    }

    .cart-summary {
        border: 1px solid #dcdfe4;
        border-radius: 10px;
        background: #ffffff;
        padding: 22px 20px;
        position: sticky;
        top: 92px;
    }

    .summary-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 10px 0;
        color: #111827;
        font-size: 14px;
        font-weight: 700;
    }

    .summary-row.total {
        border-top: 1px solid #e5e7eb;
        margin-top: 12px;
        padding-top: 18px;
        font-size: 16px;
        font-weight: 900;
    }

    .summary-price {
        color: #111827;
        font-weight: 900;
    }

    .summary-total-price {
        color: #ef3f4d;
        font-size: 20px;
        font-weight: 900;
    }

    .voucher-row {
        border-top: 1px solid #e5e7eb;
        border-bottom: 1px solid #e5e7eb;
        margin: 12px 0;
        padding: 14px 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: #111827;
        font-weight: 800;
        font-size: 14px;
    }

    .voucher-icon {
        color: #ef3f4d;
        margin-right: 8px;
    }

    .checkout-btn {
        width: 100%;
        height: 46px;
        border: 0;
        border-radius: 8px;
        background: #ef3f4d;
        color: #ffffff;
        font-size: 16px;
        font-weight: 900;
        margin-top: 18px;
        cursor: pointer;
    }

    .checkout-btn:hover {
        background: #d92e3d;
    }

    .gst-note {
        margin-top: 8px;
        color: #6b7280;
        font-size: 12px;
        text-align: right;
    }

    @media (max-width: 900px) {
        .cart-layout {
            grid-template-columns: 1fr;
        }

        .cart-summary {
            position: static;
        }
    }

    @media (max-width: 620px) {
        .cart-item {
            grid-template-columns: 1fr;
        }

        .cart-item-image {
            width: 100%;
            height: 190px;
        }

        .cart-item-actions {
            justify-items: stretch;
        }

        .qty-input {
            width: 100%;
        }

        .start-shopping-btn,
        .clear-cart-btn,
        .continue-shopping-btn,
        .update-cart-btn {
            width: 100%;
        }

        .cart-update-row,
        .cart-left-actions {
            display: grid;
            grid-template-columns: 1fr;
            width: 100%;
        }
    }
</style>

<main class="cart-page">
    <div class="cart-breadcrumb">
        <a href="index.php">Home</a>
        <span>›</span>
        <a href="allbuggy.php">Shop</a>
        <span>›</span>
        <strong>Cart</strong>
    </div>

    <div class="cart-layout">
        <section>
            <h1 class="cart-title">Shopping Cart</h1>

            <?php if (isset($_GET['added'])): ?>
                <div class="cart-alert">Buggy added to cart successfully.</div>
            <?php elseif (isset($_GET['updated'])): ?>
                <div class="cart-alert">Cart updated successfully.</div>
            <?php elseif (isset($_GET['removed'])): ?>
                <div class="cart-alert">Item removed from cart.</div>
            <?php elseif (isset($_GET['cleared'])): ?>
                <div class="cart-alert">Cart cleared successfully.</div>
            <?php endif; ?>

            <?php if (empty($cartItems)): ?>
                <div class="cart-empty">
                    <div>
                        <div class="empty-icon">🛒</div>
                        <h2>You Have No Items In Your Cart</h2>
                        <a href="allbuggy.php" class="start-shopping-btn">Start Shopping</a>
                    </div>
                </div>
            <?php else: ?>
                <form method="post">
                    <div class="cart-items">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item">
                                <a href="buggy-detail.php?id=<?php echo (int)$item['id']; ?>" class="cart-item-image">
                                    <img 
                                        src="<?php echo e($item['image_url']); ?>" 
                                        alt="<?php echo e($item['name']); ?>"
                                        onerror="this.src='images/no-image.jpg';"
                                    >
                                </a>

                                <div>
                                    <h2 class="cart-item-name">
                                        <?php echo e($item['name']); ?>
                                    </h2>

                                    <div class="cart-item-meta">
                                        Brand: <?php echo e($item['brand'] ?: 'N/A'); ?><br>
                                        Model: <?php echo e($item['model'] ?: 'N/A'); ?><br>
                                        Seats: <?php echo e($item['seats'] ?: 'N/A'); ?> Seater<br>
                                        Condition: <?php echo e(ucfirst($item['condition'] ?: 'N/A')); ?>
                                    </div>

                                    <div class="cart-item-price">
                                        $ <?php echo number_format((float)$item['price'], 0); ?>
                                    </div>
                                </div>

                                <div class="cart-item-actions">
                                    <input 
                                        type="number" 
                                        class="qty-input" 
                                        name="quantities[<?php echo (int)$item['id']; ?>]" 
                                        value="<?php echo (int)$item['qty']; ?>" 
                                        min="1"
                                    >

                                    <strong>
                                        $ <?php echo number_format((float)$item['line_total'], 0); ?>
                                    </strong>

                                    <a 
                                        href="cart.php?action=remove&id=<?php echo (int)$item['id']; ?>" 
                                        class="remove-link"
                                        onclick="return confirm('Remove this buggy from cart?');"
                                    >
                                        Remove
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cart-update-row">
                        <div class="cart-left-actions">
                            <a 
                                href="cart.php?action=clear" 
                                class="clear-cart-btn"
                                onclick="return confirm('Clear all cart items?');"
                            >
                                Clear Cart
                            </a>

                            <a href="allbuggy.php" class="continue-shopping-btn">
                                Continue Shopping
                            </a>
                        </div>

                        <button type="submit" class="update-cart-btn">
                            Update Cart
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <aside class="cart-summary">
            <div class="summary-row">
                <span>Subtotal</span>
                <span class="summary-price">$ <?php echo number_format((float)$subtotal, 2); ?></span>
            </div>

            <div class="summary-row">
                <span>Shipping</span>
                <span class="summary-price">FREE</span>
            </div>

            <div class="voucher-row">
                <span><span class="voucher-icon">%</span> Vouchers</span>
                <span>›</span>
            </div>

            <div class="summary-row total">
                <span>Total</span>
                <span class="summary-total-price">$ <?php echo number_format((float)$total, 2); ?></span>
            </div>

            <div class="gst-note">
                *Prices shown are inclusive of GST where applicable.
            </div>

            <?php if (!empty($cartItems)): ?>
                <button type="button" class="checkout-btn" onclick="window.location.href='checkout.php';">
                    Checkout
                </button>
            <?php endif; ?>
        </aside>
    </div>
</main>

<?php include 'footer.php'; ?>