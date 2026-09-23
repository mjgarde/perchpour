<?php
session_start();
include '../config/db_connect.php';

if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

$staff_id = (int) $_SESSION['staff_id'];
$fullName = $_SESSION['full_name'] ?? 'Staff';
$initial  = strtoupper(substr($fullName, 0, 1));

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $item_id  = (int) ($_POST['item_id'] ?? 0);
    $action   = $_POST['action'] ?? '';
    $quantity = (float) ($_POST['quantity'] ?? 0);
    $reason   = trim($_POST['reason'] ?? '');
    $notes    = trim($_POST['notes'] ?? '');

    if ($item_id <= 0) {
        $error = 'Please select an item.';
    } elseif (!in_array($action, ['add', 'deduct'], true)) {
        $error = 'Invalid action.';
    } elseif ($quantity <= 0) {
        $error = 'Quantity must be greater than zero.';
    } else {
        $stmt = mysqli_prepare($conn,
            "SELECT item_name, current_stock, low_stock_level FROM inventory_items WHERE item_id = ? AND is_active = 1");
        mysqli_stmt_bind_param($stmt, "i", $item_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $item = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$item) {
            $error = 'Item not found.';
        } else {
            $before = (float) $item['current_stock'];
            $after  = $action === 'add' ? $before + $quantity : $before - $quantity;

            if ($after < 0) {
                $error = 'Not enough stock. Current stock is ' . $before . '.';
            } else {
                mysqli_begin_transaction($conn);

                try {
                    $stmt = mysqli_prepare($conn,
                        "UPDATE inventory_items SET current_stock = ? WHERE item_id = ?");
                    mysqli_stmt_bind_param($stmt, "di", $after, $item_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    $stmt = mysqli_prepare($conn,
                        "INSERT INTO inventory_transactions
                         (item_id, type, quantity, before_stock, after_stock, reason, notes, staff_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "isdddssi",
                        $item_id, $action, $quantity, $before, $after, $reason, $notes, $staff_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    mysqli_commit($conn);

                    $label = $action === 'add' ? 'added to' : 'deducted from';
                    $success = number_format($quantity, 2) . ' ' . $label . ' ' . $item['item_name'] . '. New stock: ' . number_format($after, 2) . '.';
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = 'Failed to update stock.';
                }
            }
        }
    }
}

$items = [];
$res = mysqli_query($conn,
    "SELECT * FROM inventory_items WHERE is_active = 1 ORDER BY item_name ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $items[] = $row;
}

$lowStockCount = 0;
foreach ($items as $it) {
    if ((float) $it['current_stock'] <= (float) $it['low_stock_level']) {
        $lowStockCount++;
    }
}

$transactions = [];
$res = mysqli_query($conn,
    "SELECT t.*, i.item_name, i.unit, s.full_name AS staff_name
     FROM inventory_transactions t
     INNER JOIN inventory_items i ON i.item_id = t.item_id
     LEFT JOIN staff s ON s.staff_id = t.staff_id
     ORDER BY t.created_at DESC
     LIMIT 30");
while ($row = mysqli_fetch_assoc($res)) {
    $transactions[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory — Perch &amp; Pour Staff</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<style>
    body {
        font-family: Georgia, 'Times New Roman', serif;
        background-color: #f8f9fa;
        color: #111;
    }

    .stock-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 3px 10px;
        border-radius: 20px;
        white-space: nowrap;
    }
    .stock-pill.ok  { background: #eaf6ec; color: #1a7f37; }
    .stock-pill.low { background: #fdf3e0; color: #a86b00; }
    .stock-pill.out { background: #fdecec; color: #b91c1c; }

    .item-row td { vertical-align: middle; }
    .item-row.low-stock { background: #fffbf2; }
    .item-row.out-stock { background: #fef6f6; }

    .stock-value {
        font-size: 18px;
        font-weight: 700;
        line-height: 1;
    }
    .stock-unit {
        font-size: 12px;
        color: #6b7280;
        margin-left: 4px;
    }

    .type-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 2px 8px;
        border-radius: 4px;
    }
    .type-badge.add    { background: #eaf6ec; color: #1a7f37; }
    .type-badge.deduct { background: #fdecec; color: #b91c1c; }

    .history-table td { vertical-align: middle; font-size: 13.5px; }
    .history-table th {
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
        font-weight: 700;
        border-bottom: 2px solid #111;
        white-space: nowrap;
    }

    .stat-warn .stat-value { color: #a86b00; }
    .stat-danger .stat-value { color: #b91c1c; }
</style>
</head>
<body>

<div class="d-flex min-vh-100">

<?php include '../include/staff_sidebar.php'; ?>

<div class="flex-grow-1 min-w-0">

    <div class="d-flex align-items-center gap-3 bg-white border-bottom px-4 py-3">
        <button type="button" class="btn btn-outline-dark d-lg-none" onclick="openSidebar()">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div>
            <h1 class="h4 fw-bold mb-0">Inventory</h1>
            <div class="small text-secondary"><?= date("l, F j, Y") ?></div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">

        <?php if ($success): ?>
            <div class="alert alert-success rounded-0 border-0 small py-2">
                <i class="fa-solid fa-circle-check me-1"></i><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger rounded-0 border-0 small py-2">
                <i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-dark-subtle rounded-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                            <i class="fa-solid fa-boxes-stacked"></i>
                            <span>Total Items</span>
                        </div>
                        <div class="fs-3 fw-bold lh-1"><?= count($items) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-dark-subtle rounded-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                            <i class="fa-solid fa-triangle-exclamation text-warning"></i>
                            <span>Low Stock</span>
                        </div>
                        <div class="fs-3 fw-bold lh-1 <?= $lowStockCount > 0 ? 'text-warning' : '' ?>"><?= $lowStockCount ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-dark-subtle rounded-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <span>Recent Transactions</span>
                        </div>
                        <div class="fs-3 fw-bold lh-1"><?= count($transactions) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            <div class="col-12 col-lg-7">

                <h3 class="h6 fw-bold border-bottom pb-2 mb-3">Stock Levels</h3>

                <?php if (empty($items)): ?>

                    <div class="border border-dark-subtle text-center text-secondary py-5 px-3">
                        <i class="fa-solid fa-box d-block mb-2 fs-4"></i>
                        No inventory items yet.
                    </div>

                <?php else: ?>

                    <div class="card border-dark-subtle rounded-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Item</th>
                                        <th>Stock</th>
                                        <th>Low Level</th>
                                        <th class="pe-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $it):
                                        $stock = (float) $it['current_stock'];
                                        $low   = (float) $it['low_stock_level'];

                                        if ($stock <= 0) {
                                            $statusClass = 'out-stock';
                                            $pillClass   = 'out';
                                            $pillLabel   = 'Out of Stock';
                                            $pillIcon    = 'fa-circle-xmark';
                                        } elseif ($stock <= $low) {
                                            $statusClass = 'low-stock';
                                            $pillClass   = 'low';
                                            $pillLabel   = 'Low Stock';
                                            $pillIcon    = 'fa-triangle-exclamation';
                                        } else {
                                            $statusClass = '';
                                            $pillClass   = 'ok';
                                            $pillLabel   = 'In Stock';
                                            $pillIcon    = 'fa-circle-check';
                                        }
                                    ?>
                                        <tr class="item-row <?= $statusClass ?>">
                                            <td class="ps-3 fw-semibold"><?= htmlspecialchars($it['item_name']) ?></td>
                                            <td>
                                                <span class="stock-value"><?= number_format($stock, 0) ?></span>
                                                <span class="stock-unit"><?= htmlspecialchars($it['unit']) ?></span>
                                            </td>
                                            <td class="small text-secondary">
                                                <?= number_format($low, 0) ?> <?= htmlspecialchars($it['unit']) ?>
                                            </td>
                                            <td class="pe-3">
                                                <span class="stock-pill <?= $pillClass ?>">
                                                    <i class="fa-solid <?= $pillIcon ?>"></i> <?= $pillLabel ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php endif; ?>

            </div>

            <div class="col-12 col-lg-5">

                <h3 class="h6 fw-bold border-bottom pb-2 mb-3">Adjust Stock</h3>

                <div class="card border-dark-subtle rounded-0 mb-4">
                    <div class="card-body p-4">

                        <?php if (empty($items)): ?>

                            <div class="text-secondary small text-center py-3">
                                No items available to adjust.
                            </div>

                        <?php else: ?>

                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Item</label>
                                    <select name="item_id" class="form-select form-select-sm rounded-0 border-dark-subtle" required>
                                        <option value="">— Select item —</option>
                                        <?php foreach ($items as $it): ?>
                                            <option value="<?= $it['item_id'] ?>">
                                                <?= htmlspecialchars($it['item_name']) ?>
                                                (current: <?= number_format((float) $it['current_stock'], 0) ?> <?= htmlspecialchars($it['unit']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold d-block">Action</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="action"
                                                   id="actionAdd" value="add" checked>
                                            <label class="form-check-label small" for="actionAdd">
                                                <i class="fa-solid fa-plus text-success me-1"></i> Add Stock
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="action"
                                                   id="actionDeduct" value="deduct">
                                            <label class="form-check-label small" for="actionDeduct">
                                                <i class="fa-solid fa-minus text-danger me-1"></i> Deduct Stock
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Quantity</label>
                                    <input type="number" name="quantity" step="1" min="1"
                                           class="form-control form-control-sm rounded-0 border-dark-subtle"
                                           placeholder="e.g. 100" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Reason</label>
                                    <select name="reason" class="form-select form-select-sm rounded-0 border-dark-subtle">
                                        <option value="Delivery">Delivery</option>
                                        <option value="Manual use">Manual use</option>
                                        <option value="Spoilage / Damage">Spoilage / Damage</option>
                                        <option value="Wastage">Wastage</option>
                                        <option value="Correction">Correction</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Notes (optional)</label>
                                    <textarea name="notes" rows="2"
                                              class="form-control form-control-sm rounded-0 border-dark-subtle"
                                              placeholder="Optional remarks..."></textarea>
                                </div>

                                <button type="submit" name="adjust_stock"
                                        class="btn btn-dark btn-sm rounded-0 w-100">
                                    <i class="fa-solid fa-check me-1"></i> Submit Adjustment
                                </button>
                            </form>

                        <?php endif; ?>

                    </div>
                </div>

            </div>

        </div>

        <h3 class="h6 fw-bold border-bottom pb-2 mb-3 mt-2">Recent Transactions</h3>

        <?php if (empty($transactions)): ?>

            <div class="border border-dark-subtle text-center text-secondary py-5 px-3">
                <i class="fa-solid fa-clock-rotate-left d-block mb-2 fs-4"></i>
                No transactions yet.
            </div>

        <?php else: ?>

            <div class="card border-dark-subtle rounded-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 history-table">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Date &amp; Time</th>
                                <th>Item</th>
                                <th>Type</th>
                                <th>Qty</th>
                                <th>Before</th>
                                <th>After</th>
                                <th>Reason</th>
                                <th class="pe-3">Staff</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t):
                                $typeClass = $t['type'] === 'add' ? 'add' : 'deduct';
                                $typeIcon  = $t['type'] === 'add' ? 'fa-plus' : 'fa-minus';
                                $sign      = $t['type'] === 'add' ? '+' : '−';
                            ?>
                                <tr>
                                    <td class="ps-3 small text-secondary">
                                        <?= date('M d, Y g:i A', strtotime($t['created_at'])) ?>
                                    </td>
                                    <td class="fw-semibold"><?= htmlspecialchars($t['item_name']) ?></td>
                                    <td>
                                        <span class="type-badge <?= $typeClass ?>">
                                            <i class="fa-solid <?= $typeIcon ?>"></i> <?= ucfirst($t['type']) ?>
                                        </span>
                                    </td>
                                    <td class="fw-semibold">
                                        <?= $sign ?><?= number_format((float) $t['quantity'], 0) ?>
                                        <span class="text-secondary small"><?= htmlspecialchars($t['unit']) ?></span>
                                    </td>
                                    <td class="small text-secondary"><?= number_format((float) $t['before_stock'], 0) ?></td>
                                    <td class="small text-secondary"><?= number_format((float) $t['after_stock'], 0) ?></td>
                                    <td class="small text-secondary"><?= htmlspecialchars($t['reason'] ?: '—') ?></td>
                                    <td class="pe-3 small text-secondary">
                                        <?= htmlspecialchars($t['staff_name'] ?? 'System') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

    </div>
</div>

</div>

<script src="../vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>

</body>
</html>