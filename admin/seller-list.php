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

function receiptPath($path)
{
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('/^https?:\/\//i', $path)) return $path;
    if (strpos($path, '../') === 0) return $path;
    return '../' . $path;
}

function sendSellerEmail($toEmail, $toName, $subject, $body)
{
    $headers  = "From: noreply@sgbuggymart.com\r\n";
    $headers .= "Reply-To: noreply@sgbuggymart.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($toEmail, $subject, $body, $headers);
}

$message = '';
$error   = '';

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = 'Seller account updated successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seller_id'], $_POST['action'])) {
    $sellerId       = (int)$_POST['seller_id'];
    $action         = $_POST['action'];
    $rejectedReason = trim($_POST['rejected_reason'] ?? '');

    if ($sellerId <= 0) {
        $error = 'Invalid seller ID.';
    } else {
        try {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("
                    UPDATE sellers SET
                        seller_status  = 'active',
                        payment_status = 'verified',
                        approved_at    = NOW(),
                        rejected_reason = NULL,
                        updated_at     = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$sellerId]);

                $sellerStmt = $pdo->prepare("SELECT full_name, email FROM sellers WHERE id = ? LIMIT 1");
                $sellerStmt->execute([$sellerId]);
                $approvedSeller = $sellerStmt->fetch(PDO::FETCH_ASSOC);

                if ($approvedSeller && !empty($approvedSeller['email'])) {
                    $subject = 'Your SGBUGGYMART Seller Account is Approved!';
                    $body    = "Dear " . $approvedSeller['full_name'] . ",\n\n"
                        . "Congratulations! Your seller account on SGBUGGYMART has been approved.\n\n"
                        . "Your payment has been verified and your seller account is now active.\n\n"
                        . "You can now log in to your seller dashboard and start uploading your buggy listings:\n"
                        . "https://sgbuggymart.com/seller/login.php\n\n"
                        . "Important reminders:\n"
                        . "- You can upload up to 10 buggy listings\n"
                        . "- Your listings will go live immediately after upload\n"
                        . "- SGBUGGYMART admin may hide or remove listings that violate our policies\n\n"
                        . "If you have any questions, please contact us.\n\n"
                        . "Thank you for choosing SGBUGGYMART!\n\n"
                        . "Best regards,\n"
                        . "SGBUGGYMART Team";

                    sendSellerEmail($approvedSeller['email'], $approvedSeller['full_name'], $subject, $body);
                }

                header('Location: seller-list.php?updated=1');
                exit;
            }

            if ($action === 'reject') {
                if ($rejectedReason === '') {
                    $rejectedReason = 'Payment verification rejected by admin.';
                }

                $stmt = $pdo->prepare("
                    UPDATE sellers SET
                        seller_status  = 'rejected',
                        payment_status = 'rejected',
                        approved_at    = NULL,
                        rejected_reason = ?,
                        updated_at     = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$rejectedReason, $sellerId]);

                $sellerStmt = $pdo->prepare("SELECT full_name, email FROM sellers WHERE id = ? LIMIT 1");
                $sellerStmt->execute([$sellerId]);
                $rejectedSeller = $sellerStmt->fetch(PDO::FETCH_ASSOC);

                if ($rejectedSeller && !empty($rejectedSeller['email'])) {
                    $subject = 'Your SGBUGGYMART Seller Account Payment Verification';
                    $body    = "Dear " . $rejectedSeller['full_name'] . ",\n\n"
                        . "We regret to inform you that your payment verification has been rejected.\n\n"
                        . "Reason: " . $rejectedReason . "\n\n"
                        . "Please log in to your seller account and resubmit your payment details:\n"
                        . "https://sgbuggymart.com/seller/login.php\n\n"
                        . "If you believe this is an error or need assistance, please contact us.\n\n"
                        . "Best regards,\n"
                        . "SGBUGGYMART Team";

                    sendSellerEmail($rejectedSeller['email'], $rejectedSeller['full_name'], $subject, $body);
                }

                header('Location: seller-list.php?updated=1');
                exit;
            }

            if ($action === 'suspend') {
                $stmt = $pdo->prepare("
                    UPDATE sellers SET
                        seller_status = 'suspended',
                        updated_at    = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$sellerId]);

                header('Location: seller-list.php?updated=1');
                exit;
            }

            if ($action === 'reactivate') {
                $stmt = $pdo->prepare("
                    UPDATE sellers SET
                        seller_status  = 'active',
                        payment_status = 'verified',
                        approved_at    = IFNULL(approved_at, NOW()),
                        rejected_reason = NULL,
                        updated_at     = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$sellerId]);

                header('Location: seller-list.php?updated=1');
                exit;
            }

            if ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM buggies WHERE owner_type = 'seller' AND owner_id = ?");
                $stmt->execute([$sellerId]);
                $stmt = $pdo->prepare("DELETE FROM sellers WHERE id = ?");
                $stmt->execute([$sellerId]);

                header('Location: seller-list.php?updated=1');
                exit;
            }

        } catch (PDOException $e) {
            $error = 'Failed to update seller account: ' . $e->getMessage();
        }
    }
}

$keyword       = trim($_GET['keyword']        ?? '');
$sellerStatus  = $_GET['seller_status']        ?? 'all';
$paymentStatus = $_GET['payment_status']       ?? 'all';

$sql    = "
    SELECT
        id, full_name, contact_no, email, address,
        seller_status, payment_status, payment_reference,
        payment_receipt, approved_at, rejected_reason,
        status, created_at, updated_at
    FROM sellers
    WHERE 1 = 1
";
$params = [];

if ($keyword !== '') {
    $sql .= " AND (full_name LIKE ? OR email LIKE ? OR contact_no LIKE ? OR payment_reference LIKE ?)";
    $like     = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($sellerStatus !== 'all') {
    $sql .= " AND seller_status = ?";
    $params[] = $sellerStatus;
}

if ($paymentStatus !== 'all') {
    $sql .= " AND payment_status = ?";
    $params[] = $paymentStatus;
}

/* ==== CSV export ==== */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportSql = $sql . " ORDER BY created_at DESC, id DESC";
    try {
        $exportStmt = $pdo->prepare($exportSql);
        $exportStmt->execute($params);
        $rows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $rows = [];
    }

    $filename = 'sellers-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Full Name', 'Email', 'Contact No', 'Address', 'Seller Status', 'Payment Status', 'Payment Reference', 'Approved At', 'Created At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            (string)($r['full_name']         ?? ''),
            (string)($r['email']             ?? ''),
            (string)($r['contact_no']        ?? ''),
            (string)($r['address']           ?? ''),
            ucwords(str_replace('_', ' ', (string)($r['seller_status']  ?? ''))),
            ucwords(str_replace('_', ' ', (string)($r['payment_status'] ?? ''))),
            (string)($r['payment_reference'] ?? ''),
            !empty($r['approved_at']) ? date('Y-m-d H:i', strtotime($r['approved_at'])) : '',
            !empty($r['created_at'])  ? date('Y-m-d H:i', strtotime($r['created_at']))  : '',
        ]);
    }
    fclose($out);
    exit;
}

/* Pagination */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$countSql = preg_replace('/^\s*SELECT[\s\S]*?FROM\s/', 'SELECT COUNT(*) FROM ', $sql, 1);
try {
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();
} catch (PDOException $e) {
    $totalCount = 0;
}
$totalPages = max(1, (int)ceil($totalCount / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql .= "
    ORDER BY
        CASE
            WHEN seller_status = 'pending_verification' THEN 1
            WHEN seller_status = 'pending_payment'      THEN 2
            WHEN seller_status = 'active'               THEN 3
            WHEN seller_status = 'rejected'             THEN 4
            WHEN seller_status = 'suspended'            THEN 5
            ELSE 6
        END,
        created_at DESC, id DESC
    LIMIT $perPage OFFSET $offset
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sellers = [];
    $error = 'Failed to load sellers: ' . $e->getMessage();
}

function pageUrl($pageNum) {
    $qs = array_filter($_GET, function ($v) { return $v !== '' && $v !== null; });
    $qs['page'] = $pageNum;
    return '?' . http_build_query($qs);
}

include 'header.php';
?>

<style>
    .seller-list-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
    .seller-list-header h1 { margin: 0; font-size: 30px; }
    .seller-list-header p  { margin: 6px 0 0; color: #777; font-size: 15px; }

    .filter-card { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 8px 22px rgba(0,0,0,0.04); margin-bottom: 28px; }

    .filter-form { display: grid; grid-template-columns: 1fr 220px 220px auto; gap: 12px; align-items: center; }
    .filter-form input, .filter-form select { height: 52px; border: 1px solid #ddd; border-radius: 10px; padding: 0 16px; font-size: 15px; background: #fff; }
    .filter-form button { height: 52px; padding-left: 22px; padding-right: 22px; }

    .table-card { background: #fff; border-radius: 16px; box-shadow: 0 8px 22px rgba(0,0,0,0.04); overflow: hidden; }
    .table-top  { display: flex; align-items: center; justify-content: space-between; padding: 22px; border-bottom: 1px solid #e5e5e5; }
    .table-top strong { font-size: 20px; }
    .table-top span   { color: #777; font-size: 14px; }

    .table-scroll { width: 100%; overflow-x: auto; }
    table { width: 100%; min-width: 1260px; border-collapse: collapse; }

    th, td { padding: 20px 18px; text-align: left; border-bottom: 1px solid #e5e5e5; vertical-align: top; font-size: 14px; }
    th { background: #fafafa; color: #444; font-size: 13px; text-transform: uppercase; letter-spacing: 0.3px; font-weight: 700; }
    tbody tr:hover { background: #fff7f8; }

    .seller-name { font-weight: 800; font-size: 16px; color: #111; margin-bottom: 6px; }
    .seller-meta { color: #777; font-size: 13px; line-height: 1.45; max-width: 240px; word-break: break-word; }

    .badge { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 7px 12px; font-size: 12px; font-weight: 700; text-transform: capitalize; white-space: nowrap; }
    .badge-pending-payment, .badge-pending-verification, .badge-unpaid, .badge-submitted { background: #fef3c7; color: #92400e; }
    .badge-active, .badge-verified { background: #dcfce7; color: #166534; }
    .badge-rejected  { background: #fee2e2; color: #991b1b; }
    .badge-suspended { background: #e5e7eb; color: #374151; }

    .receipt-link { display: inline-flex; color: #ef3f4d; font-weight: bold; text-decoration: none; margin-top: 6px; }
    .receipt-link:hover { text-decoration: underline; }

    .action-group { display: grid; gap: 8px; width: 120px; }

    .btn-small { width: 120px; height: 36px; border: none; outline: none; border-radius: 8px; padding: 0; font-size: 13px; font-weight: 700; cursor: pointer; transition: 0.2s ease; font-family: inherit; }

    .btn-approve { background: #dcfce7; color: #166534; }
    .btn-approve:hover { background: #bbf7d0; }
    .btn-reject  { background: #fee2e2; color: #991b1b; }
    .btn-reject:hover { background: #fecaca; }
    .btn-suspend { background: #e5e7eb; color: #374151; }
    .btn-suspend:hover { background: #d1d5db; }
    .btn-delete  { background: #ef3f4d; color: #ffffff; }
    .btn-delete:hover { background: #d92e3d; }

    .reject-reason { width: 120px; min-height: 58px; border: 1px solid #ddd; border-radius: 8px; padding: 8px; font-size: 12px; resize: vertical; font-family: inherit; }
    .inline-form { margin: 0; }

    .message-success { background: #e8fff0; color: #167a3c; border: 1px solid #b8e8c8; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
    .message-error   { background: #fff0f2; color: #ef3f4d; border: 1px solid #ffc4cc; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
    .empty { padding: 55px 20px; text-align: center; color: #777; }

    @media (max-width: 900px) {
        .seller-list-header { display: block; }
        .filter-form { grid-template-columns: 1fr; }
    }
</style>

<div class="seller-list-header">
    <div>
        <h1>Seller Approval</h1>
        <p>Review seller accounts, payment references, receipts and approval status.</p>
    </div>
    <?php
        $exportQs = array_filter($_GET, function ($v) { return $v !== '' && $v !== null; });
        $exportQs['export'] = 'csv';
        unset($exportQs['page']);
    ?>
    <a href="?<?php echo e(http_build_query($exportQs)); ?>"
       class="btn"
       style="background:#16a34a;"
       title="Download the currently filtered sellers as an Excel-compatible CSV file">
        &#128190; Export to Excel
    </a>
</div>

<?php if ($message !== ''): ?>
    <div class="message-success"><?php echo e($message); ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="message-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="filter-card">
    <form method="GET" class="filter-form">
        <input type="text" name="keyword" placeholder="Search seller name, email, contact, reference..." value="<?php echo e($keyword); ?>">
        <select name="seller_status">
            <option value="all"                  <?php echo $sellerStatus === 'all'                  ? 'selected' : ''; ?>>All Seller Status</option>
            <option value="pending_payment"      <?php echo $sellerStatus === 'pending_payment'      ? 'selected' : ''; ?>>Pending Payment</option>
            <option value="pending_verification" <?php echo $sellerStatus === 'pending_verification' ? 'selected' : ''; ?>>Pending Verification</option>
            <option value="active"               <?php echo $sellerStatus === 'active'               ? 'selected' : ''; ?>>Active</option>
            <option value="rejected"             <?php echo $sellerStatus === 'rejected'             ? 'selected' : ''; ?>>Rejected</option>
            <option value="suspended"            <?php echo $sellerStatus === 'suspended'            ? 'selected' : ''; ?>>Suspended</option>
        </select>
        <select name="payment_status">
            <option value="all"       <?php echo $paymentStatus === 'all'       ? 'selected' : ''; ?>>All Payment Status</option>
            <option value="unpaid"    <?php echo $paymentStatus === 'unpaid'    ? 'selected' : ''; ?>>Unpaid</option>
            <option value="submitted" <?php echo $paymentStatus === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
            <option value="verified"  <?php echo $paymentStatus === 'verified'  ? 'selected' : ''; ?>>Verified</option>
            <option value="rejected"  <?php echo $paymentStatus === 'rejected'  ? 'selected' : ''; ?>>Rejected</option>
        </select>
        <button type="submit" class="btn">Search</button>
    </form>
</div>

<div class="table-card">
    <div class="table-top">
        <strong>Sellers</strong>
        <span>
            <?php echo (int)$totalCount; ?> seller(s) found
            <?php if ($totalPages > 1): ?>
                &middot; Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?>
            <?php endif; ?>
        </span>
    </div>

    <?php if (count($sellers) > 0): ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Seller</th>
                        <th>Contact</th>
                        <th>Seller Status</th>
                        <th>Payment Status</th>
                        <th>Payment Reference</th>
                        <th>Receipt</th>
                        <th>Rejected Reason</th>
                        <th>Created</th>
                        <th width="150">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sellers as $seller): ?>
                        <?php
                            $sellerStatusValue  = strtolower((string)$seller['seller_status']);
                            $paymentStatusValue = strtolower((string)$seller['payment_status']);
                            $sellerStatusLabel  = ucwords(str_replace('_', ' ', $sellerStatusValue));
                            $paymentStatusLabel = ucwords(str_replace('_', ' ', $paymentStatusValue));
                            $sellerBadgeClass   = 'badge-' . str_replace('_', '-', $sellerStatusValue);
                            $paymentBadgeClass  = 'badge-' . str_replace('_', '-', $paymentStatusValue);
                            $receiptUrl         = receiptPath($seller['payment_receipt'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <div class="seller-name"><?php echo e($seller['full_name']); ?></div>
                                <div class="seller-meta"><?php echo e($seller['email']); ?></div>
                                <?php if (!empty($seller['address'])): ?>
                                    <div class="seller-meta"><?php echo e($seller['address']); ?></div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="seller-meta"><?php echo e($seller['contact_no']); ?></div>
                            </td>

                            <td>
                                <span class="badge <?php echo e($sellerBadgeClass); ?>">
                                    <?php echo e($sellerStatusLabel); ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?php echo e($paymentBadgeClass); ?>">
                                    <?php echo e($paymentStatusLabel); ?>
                                </span>
                            </td>

                            <td>
                                <?php if (!empty($seller['payment_reference'])): ?>
                                    <div class="seller-meta"><?php echo e($seller['payment_reference']); ?></div>
                                <?php else: ?>
                                    <span class="seller-meta">No reference submitted</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($receiptUrl)): ?>
                                    <a href="<?php echo e($receiptUrl); ?>" class="receipt-link" target="_blank">View Receipt</a>
                                <?php else: ?>
                                    <span class="seller-meta">No receipt</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($seller['rejected_reason'])): ?>
                                    <div class="seller-meta"><?php echo e($seller['rejected_reason']); ?></div>
                                <?php else: ?>
                                    <span class="seller-meta">-</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="seller-meta">
                                    <?php echo !empty($seller['created_at']) ? e(date('Y-m-d', strtotime($seller['created_at']))) : '-'; ?>
                                </div>
                                <?php if (!empty($seller['approved_at'])): ?>
                                    <div class="seller-meta">
                                        Approved: <?php echo e(date('Y-m-d', strtotime($seller['approved_at']))); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="action-group">
                                    <?php if ($sellerStatusValue === 'pending_verification'): ?>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Approve this seller account?');">
                                            <input type="hidden" name="seller_id" value="<?php echo (int)$seller['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn-small btn-approve">Approve</button>
                                        </form>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Reject this seller account?');">
                                            <input type="hidden" name="seller_id" value="<?php echo (int)$seller['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <textarea name="rejected_reason" class="reject-reason" placeholder="Reject reason"></textarea>
                                            <button type="submit" class="btn-small btn-reject">Reject</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($sellerStatusValue === 'pending_payment'): ?>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Reject this seller account?');">
                                            <input type="hidden" name="seller_id" value="<?php echo (int)$seller['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <textarea name="rejected_reason" class="reject-reason" placeholder="Reject reason"></textarea>
                                            <button type="submit" class="btn-small btn-reject">Reject</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($sellerStatusValue === 'active'): ?>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Suspend this seller account?');">
                                            <input type="hidden" name="seller_id" value="<?php echo (int)$seller['id']; ?>">
                                            <input type="hidden" name="action" value="suspend">
                                            <button type="submit" class="btn-small btn-suspend">Suspend</button>
                                        </form>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Permanently delete this seller account? This cannot be undone.');">
                                            <input type="hidden" name="seller_id" value="<?php echo (int)$seller['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn-small btn-delete">Delete</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($sellerStatusValue === 'rejected' || $sellerStatusValue === 'suspended'): ?>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Reactivate this seller account?');">
                                            <input type="hidden" name="seller_id" value="<?php echo (int)$seller['id']; ?>">
                                            <input type="hidden" name="action" value="reactivate">
                                            <button type="submit" class="btn-small btn-approve">Reactivate</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (
                                        $sellerStatusValue !== 'pending_verification' &&
                                        $sellerStatusValue !== 'pending_payment' &&
                                        $sellerStatusValue !== 'active' &&
                                        $sellerStatusValue !== 'rejected' &&
                                        $sellerStatusValue !== 'suspended'
                                    ): ?>
                                        <span class="seller-meta">No action</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="admin-pagination">
                <?php if ($page > 1): ?>
                    <a class="page-link" href="<?php echo e(pageUrl($page - 1)); ?>">&laquo; Prev</a>
                <?php else: ?>
                    <span class="page-link is-disabled">&laquo; Prev</span>
                <?php endif; ?>

                <?php
                $windowStart = max(1, $page - 2);
                $windowEnd   = min($totalPages, $page + 2);
                if ($windowStart > 1): ?>
                    <a class="page-link" href="<?php echo e(pageUrl(1)); ?>">1</a>
                    <?php if ($windowStart > 2): ?><span class="page-ellipsis">&hellip;</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                    <?php if ($p === $page): ?>
                        <span class="page-link is-active"><?php echo (int)$p; ?></span>
                    <?php else: ?>
                        <a class="page-link" href="<?php echo e(pageUrl($p)); ?>"><?php echo (int)$p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($windowEnd < $totalPages): ?>
                    <?php if ($windowEnd < $totalPages - 1): ?><span class="page-ellipsis">&hellip;</span><?php endif; ?>
                    <a class="page-link" href="<?php echo e(pageUrl($totalPages)); ?>"><?php echo (int)$totalPages; ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a class="page-link" href="<?php echo e(pageUrl($page + 1)); ?>">Next &raquo;</a>
                <?php else: ?>
                    <span class="page-link is-disabled">Next &raquo;</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty">No sellers found.</div>
    <?php endif; ?>
</div>

<style>
    .admin-pagination { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 8px; padding: 22px; border-top: 1px solid #e5e5e5; }
    .admin-pagination .page-link { min-width: 40px; height: 40px; padding: 0 14px; border-radius: 999px; border: 1px solid #d8dde4; background: #ffffff; color: #333; font-size: 14px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: 0.2s ease; }
    .admin-pagination .page-link:hover { border-color: #ef3f4d; color: #ef3f4d; }
    .admin-pagination .page-link.is-active { background: #ef3f4d; border-color: #ef3f4d; color: #ffffff; }
    .admin-pagination .page-link.is-disabled { opacity: 0.4; pointer-events: none; }
    .admin-pagination .page-ellipsis { color: #999; padding: 0 4px; font-weight: 700; }
</style>

<?php include 'footer.php'; ?>