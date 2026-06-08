<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'includes/db.php';

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $keyword      = trim($_GET['keyword'] ?? '');
    $condition    = $_GET['buggy_condition'] ?? 'all';
    $listing_type = trim($_GET['listing_type'] ?? '');
    $seats        = $_GET['seats'] ?? '';
    $brand        = trim($_GET['brand'] ?? '');

    /* Accessories are in a separate table */
    if ($listing_type === 'accessory') {
        $accSql    = "SELECT id, brand, model, name, selling_price, promo_enabled, discount_price, promo_end_date, promo_label, image_url, tag FROM accessories WHERE status = 'active'";
        $accParams = [];

        if ($keyword !== '') {
            $accSql .= " AND (brand LIKE :kw1 OR model LIKE :kw2 OR name LIKE :kw3)";
            $accParams[':kw1'] = '%' . $keyword . '%';
            $accParams[':kw2'] = '%' . $keyword . '%';
            $accParams[':kw3'] = '%' . $keyword . '%';
        }

        if ($brand !== '') {
            $accSql .= " AND brand = :brand";
            $accParams[':brand'] = $brand;
        }

        $accSql .= " ORDER BY created_at DESC LIMIT 20";

        try {
            $accStmt = $pdo->prepare($accSql);
            $accStmt->execute($accParams);
            $items = $accStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => 'Success',
                'data'    => ['count' => count($items), 'items' => $items]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to load accessories', 'error' => $e->getMessage()]);
            exit;
        }
    }

    $sql = "
        SELECT
            b.id,
            b.owner_type,
            b.owner_id,
            b.brand,
            b.model,
            b.name,
            b.seats,
            b.buggy_condition,
            b.selling_price,
            b.promo_enabled,
            b.discount_price,
            b.promo_end_date,
            b.promo_label,
            b.short_info,
            b.description,
            b.image_url,
            b.tag,
            b.brand_tag,
            b.status,
            b.created_at
        FROM buggies b
        LEFT JOIN product_brands pb
            ON pb.brand_name = b.brand
        WHERE b.status IN ('active', 'sold')
    ";

    $params = [];

    /*
        Only show products if:
        1. Their brand exists in product_brands and is active
        OR
        2. product_brands table has no matching brand because old products may have custom brand
    */
    $sql .= " AND (
        pb.status = 'active'
        OR pb.id IS NULL
    )";

    if ($condition !== '' && $condition !== 'all') {
        $sql .= " AND b.buggy_condition = :buggy_condition";
        $params[':buggy_condition'] = $condition;
    }

    if ($brand !== '') {
        $sql .= " AND b.brand = :brand";
        $params[':brand'] = $brand;
    }

    if ($seats !== '' && $seats !== 'All' && $seats !== 'all') {
        $sql .= " AND (
            b.seats = :seats_text
            OR b.seats = :seats_number
            OR b.seats = :seats_seater_lower
            OR b.seats = :seats_seater_title
        )";

        $seatNumber = preg_replace('/[^0-9]/', '', $seats);

        $params[':seats_text']         = $seats;
        $params[':seats_number']       = $seatNumber;
        $params[':seats_seater_lower'] = $seatNumber . ' seater';
        $params[':seats_seater_title'] = $seatNumber . ' Seater';
    }

    if ($keyword !== '') {
        $sql .= " AND (
            b.brand LIKE :keyword
            OR b.model LIKE :keyword
            OR b.name LIKE :keyword
            OR b.short_info LIKE :keyword
            OR b.description LIKE :keyword
            OR b.tag LIKE :keyword
            OR b.brand_tag LIKE :keyword
        )";

        $params[':keyword'] = '%' . $keyword . '%';
    }

    $sql .= " ORDER BY
        (b.status = 'sold') ASC,
        CASE WHEN b.buggy_condition = 'new'  THEN b.sort_new  ELSE 0 END DESC,
        CASE WHEN b.buggy_condition = 'used' THEN b.sort_used ELSE 0 END DESC,
        b.created_at DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Success',
            'data'    => ['count' => count($items), 'items' => $items]
        ]);
        exit;
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to load products',
            'error'   => $e->getMessage(),
            'sql'     => $sql
        ]);
        exit;
    }
}

echo json_encode([
    'success' => false,
    'message' => 'Invalid action'
]);
exit;