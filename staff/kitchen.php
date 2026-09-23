<?php
session_start();

$isAjax = isset($_GET['ajax']);

if ($isAjax) {
    header('Content-Type: application/json');
    error_reporting(E_ALL);
    ini_set('display_errors', '0');

    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
                http_response_code(500);
            }
            echo json_encode([
                'success' => false,
                'error' => 'DEBUG FATAL: ' . $err['message'] . ' in ' . $err['file'] . ' on line ' . $err['line'],
            ]);
        }
    });
}

if (!isset($_SESSION['staff_id'])) {
    if ($isAjax) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authorized. Session staff_id is not set.']);
        exit;
    }
    header("Location: login.php");
    exit();
}

$fullName = $_SESSION['full_name'] ?? 'Staff';

if (!file_exists('../config/db_connect.php')) {
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DEBUG: db_connect.php not found at ../config/db_connect.php from ' . __DIR__]);
        exit;
    }
    die('db_connect.php not found.');
}
include '../config/db_connect.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DEBUG: $conn is not a valid mysqli connection after include.']);
        exit;
    }
    die('Database connection not available.');
}

if ($isAjax && $_GET['ajax'] === 'orders') {
    try {
        $result = mysqli_query($conn,
            "SELECT o.order_id AS id, o.table_id, o.items_json, o.total, o.status,
                    o.created_at, t.table_number
             FROM orders o
             LEFT JOIN tables t ON t.table_id = o.table_id
             ORDER BY o.order_id DESC"
        );

        if (!$result) {
            throw new Exception('Query failed: ' . mysqli_error($conn));
        }

        $orders = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $decoded = json_decode($row['items_json'], true);
            $items = [];
            $notes = '';
            $tableNumberFallback = $row['table_number'] ?? ('#' . $row['table_id']);

            if (is_array($decoded)) {
                $notes = $decoded['notes'] ?? '';
                if (!empty($decoded['items']) && is_array($decoded['items'])) {
                    foreach ($decoded['items'] as $it) {
                        $customization = array_filter([
                            $it['size'] ?? null,
                            $it['sweetness'] ?? null,
                            $it['milk_type'] ?? null,
                            !empty($it['addons']) ? $it['addons'] : null,
                        ]);
                        $items[] = [
                            'name' => $it['item_name'] ?? 'Item',
                            'qty'  => (int) ($it['quantity'] ?? 1),
                            'note' => implode(', ', $customization),
                        ];
                    }
                }
            }

            $orders[] = [
                'id'      => (int) $row['id'],
                'label'   => 'Order ' . str_pad((string) $row['id'], 3, '0', STR_PAD_LEFT),
                'table'   => 'Table ' . $tableNumberFallback,
                'status'  => $row['status'],
                'total'   => (float) $row['total'],
                'notes'   => $notes,
                'time'    => !empty($row['created_at']) ? date('h:i A', strtotime($row['created_at'])) : '',
                'items'   => $items,
            ];
        }

        echo json_encode(['success' => true, 'orders' => $orders]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DEBUG: ' . $e->getMessage()]);
    }
    exit;
}

if ($isAjax && $_GET['ajax'] === 'update') {
    try {
        $payload = json_decode(file_get_contents('php://input'), true);
        $allowedStatuses = ['pending', 'preparing', 'ready', 'completed'];

        $orderId = (int) ($payload['order_id'] ?? 0);
        $status  = trim($payload['status'] ?? '');

        if ($orderId <= 0) {
            throw new Exception('Missing order_id.');
        }
        if (!in_array($status, $allowedStatuses, true)) {
            throw new Exception('Invalid status value.');
        }

        $stmt = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE order_id = ?");
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "si", $status, $orderId);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Execute failed: ' . mysqli_stmt_error($stmt));
        }

        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected === 0) {
            throw new Exception('Order not found or status already set to that value.');
        }

        echo json_encode(['success' => true, 'order_id' => $orderId, 'status' => $status]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Kitchen Display — Perch &amp; Pour</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #f4f5f7;
        color: #1f2937;
    }
    .page-header {
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        padding: 18px 28px;
    }
    .page-header h1 {
        font-size: 18px;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.2px;
    }
    .page-header .sub {
        font-size: 13px;
        color: #6b7280;
        margin-top: 2px;
    }

    .filter-bar {
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        padding: 0 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
    .filter-tabs {
        display: flex;
        gap: 4px;
    }
    .filter-tab {
        background: none;
        border: none;
        padding: 16px 18px;
        font-size: 13.5px;
        font-weight: 500;
        color: #6b7280;
        border-bottom: 2px solid transparent;
        cursor: pointer;
        transition: color .15s, border-color .15s;
        white-space: nowrap;
    }
    .filter-tab:hover { color: #111827; }
    .filter-tab.active {
        color: #111827;
        border-bottom-color: #111827;
        font-weight: 600;
    }
    .filter-tab .count {
        display: inline-block;
        margin-left: 6px;
        padding: 1px 7px;
        font-size: 11.5px;
        font-weight: 600;
        background: #f3f4f6;
        color: #6b7280;
        border-radius: 10px;
    }
    .filter-tab.active .count {
        background: #111827;
        color: #fff;
    }

    .search-wrap {
        position: relative;
        margin: 10px 0;
    }
    .search-wrap i {
        position: absolute;
        top: 50%;
        left: 12px;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 13px;
    }
    #orderSearch {
        width: 260px;
        padding: 8px 12px 8px 34px;
        font-size: 13.5px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #fafafa;
        outline: none;
        transition: border-color .15s, background .15s;
    }
    #orderSearch:focus {
        border-color: #111827;
        background: #fff;
    }

    .order-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px 18px;
        height: 100%;
        display: flex;
        flex-direction: column;
        transition: border-color .15s;
    }
    .order-card:hover {
        border-color: #d1d5db;
    }

    .order-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .order-id {
        font-size: 15px;
        font-weight: 700;
        color: #111827;
    }

    .status-badge {
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        padding: 3px 9px;
        border-radius: 4px;
    }
    .status-badge.pending    { background: #fef3c7; color: #92400e; }
    .status-badge.preparing  { background: #dbeafe; color: #1e40af; }
    .status-badge.ready      { background: #d1fae5; color: #065f46; }
    .status-badge.completed  { background: #f3f4f6; color: #4b5563; }

    .order-meta {
        font-size: 12.5px;
        color: #6b7280;
        display: flex;
        flex-wrap: wrap;
        gap: 4px 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f3f4f6;
        margin-bottom: 12px;
    }
    .order-meta i {
        color: #9ca3af;
        margin-right: 4px;
        font-size: 11.5px;
    }

    .items-list {
        flex: 1;
        margin-bottom: 12px;
    }
    .item-row {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 5px 0;
        font-size: 13.5px;
    }
    .item-qty {
        display: inline-block;
        min-width: 22px;
        font-weight: 700;
        color: #111827;
    }
    .item-name {
        color: #1f2937;
        flex: 1;
    }
    .item-note {
        font-size: 11.5px;
        color: #9ca3af;
        font-style: italic;
        padding-left: 30px;
        margin-top: -2px;
        margin-bottom: 4px;
    }

    .order-note {
        font-size: 12px;
        color: #4b5563;
        background: #f9fafb;
        border-left: 2px solid #d1d5db;
        padding: 8px 10px;
        border-radius: 0 4px 4px 0;
        margin-bottom: 12px;
    }
    .order-note i {
        color: #9ca3af;
        margin-right: 5px;
    }

    .btn-advance {
        width: 100%;
        padding: 9px;
        font-size: 13px;
        font-weight: 600;
        background: #111827;
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: background .15s;
    }
    .btn-advance:hover:not(:disabled) {
        background: #1f2937;
    }
    .btn-advance:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .completed-note {
        text-align: center;
        font-size: 12.5px;
        color: #9ca3af;
        padding: 9px 0;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #9ca3af;
        font-size: 13.5px;
    }
    .empty-state i {
        font-size: 28px;
        display: block;
        margin-bottom: 10px;
        opacity: 0.5;
    }

    #debugAlert {
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #991b1b;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 20px;
        font-size: 13px;
    }
    #debugBox {
        font-family: ui-monospace, Menlo, monospace;
        font-size: 12px;
        white-space: pre-wrap;
        word-break: break-word;
        margin-top: 6px;
    }

    .content-area {
        padding: 24px 28px;
        max-width: 1280px;
    }
</style>
</head>
<body>

<div class="d-flex min-vh-100">

<?php include '../include/staff_sidebar.php'; ?>

<div class="flex-grow-1 min-w-0 d-flex flex-column">

    <div class="page-header">
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none" onclick="openSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h1>Kitchen Display</h1>
                <div class="sub"><?php echo date("l, F j, Y"); ?> &middot; <?php echo htmlspecialchars($fullName); ?></div>
            </div>
        </div>
    </div>

    <div class="filter-bar">
        <div class="filter-tabs" id="statusFilters">
            <button type="button" class="filter-tab active" data-filter="all">All <span class="count" id="countAll">0</span></button>
            <button type="button" class="filter-tab" data-filter="pending">Pending <span class="count" id="countPending">0</span></button>
            <button type="button" class="filter-tab" data-filter="preparing">Preparing <span class="count" id="countPreparing">0</span></button>
            <button type="button" class="filter-tab" data-filter="ready">Ready <span class="count" id="countReady">0</span></button>
            <button type="button" class="filter-tab" data-filter="completed">Completed <span class="count" id="countCompleted">0</span></button>
        </div>
        <div class="search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="orderSearch" placeholder="Search order, table, item...">
        </div>
    </div>

    <div class="content-area flex-grow-1">

        <div id="debugAlert" class="d-none">
            <strong>Error:</strong>
            <div id="debugBox"></div>
        </div>

        <div class="row g-3" id="orderGrid"></div>

        <div class="empty-state d-none" id="emptyState">
            <i class="fa-solid fa-inbox"></i>
            <span id="emptyStateText">No orders match your search or filter.</span>
        </div>

    </div>
</div>

</div>

<script src="../vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
const statusFlow = { pending: 'preparing', preparing: 'ready', ready: 'completed', completed: null };
const statusLabel = { pending: 'Pending', preparing: 'Preparing', ready: 'Ready', completed: 'Completed' };
const nextActionLabel = { pending: 'Start Preparing', preparing: 'Mark as Ready', ready: 'Mark as Completed', completed: null };

let orders = [];
let activeFilter = 'all';
let searchTerm = '';
let busyOrderIds = new Set();

function showDebug(msg) {
    document.getElementById('debugBox').textContent = msg;
    document.getElementById('debugAlert').classList.remove('d-none');
}
function hideDebug() {
    document.getElementById('debugAlert').classList.add('d-none');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}

function renderCounts() {
    document.getElementById('countAll').textContent = orders.length;
    document.getElementById('countPending').textContent = orders.filter(o => o.status === 'pending').length;
    document.getElementById('countPreparing').textContent = orders.filter(o => o.status === 'preparing').length;
    document.getElementById('countReady').textContent = orders.filter(o => o.status === 'ready').length;
    document.getElementById('countCompleted').textContent = orders.filter(o => o.status === 'completed').length;
}

function orderMatchesSearch(order, term) {
    if (!term) return true;
    const haystack = [order.label, order.table, ...order.items.map(i => i.name)].join(' ').toLowerCase();
    return haystack.includes(term);
}

function renderOrders() {
    const grid = document.getElementById('orderGrid');
    const empty = document.getElementById('emptyState');
    grid.innerHTML = '';

    if (orders.length === 0) {
        document.getElementById('emptyStateText').textContent = 'No orders yet. New orders will appear here automatically.';
        empty.classList.remove('d-none');
        renderCounts();
        return;
    }

    const filtered = orders.filter(o => {
        const statusOk = activeFilter === 'all' || o.status === activeFilter;
        const searchOk = orderMatchesSearch(o, searchTerm.toLowerCase().trim());
        return statusOk && searchOk;
    });

    if (filtered.length === 0) {
        document.getElementById('emptyStateText').textContent = 'No orders match your search or filter.';
        empty.classList.remove('d-none');
    } else {
        empty.classList.add('d-none');
    }

    filtered.forEach(order => {
        const col = document.createElement('div');
        col.className = 'col-12 col-md-6 col-xl-4';

        const itemsHtml = order.items.length
            ? order.items.map(item => `
                <div>
                    <div class="item-row">
                        <span class="item-qty">${escapeHtml(item.qty)}&times;</span>
                        <span class="item-name">${escapeHtml(item.name)}</span>
                    </div>
                    ${item.note ? `<div class="item-note">${escapeHtml(item.note)}</div>` : ''}
                </div>
            `).join('')
            : '<div class="item-note">No item details.</div>';

        const nextLabel = nextActionLabel[order.status];
        const isBusy = busyOrderIds.has(order.id);
        const actionBtn = nextLabel
            ? `<button type="button" class="btn-advance kds-advance-btn" data-id="${order.id}" data-next="${statusFlow[order.status]}" ${isBusy ? 'disabled' : ''}>
                   ${isBusy ? '<span class="spinner-border spinner-border-sm me-1"></span>Updating...' : escapeHtml(nextLabel)}
               </button>`
            : `<div class="completed-note"><i class="fa-solid fa-check me-1"></i>Completed</div>`;

        col.innerHTML = `
            <div class="order-card">
                <div class="order-head">
                    <div class="order-id">${escapeHtml(order.label)}</div>
                    <span class="status-badge ${order.status}">${escapeHtml(statusLabel[order.status])}</span>
                </div>
                <div class="order-meta">
                    <span><i class="fa-solid fa-chair"></i>${escapeHtml(order.table)}</span>
                    ${order.time ? `<span><i class="fa-regular fa-clock"></i>${escapeHtml(order.time)}</span>` : ''}
                    <span><i class="fa-solid fa-peso-sign"></i>${Number(order.total).toFixed(2)}</span>
                </div>
                <div class="items-list">${itemsHtml}</div>
                ${order.notes ? `<div class="order-note"><i class="fa-solid fa-note-sticky"></i>${escapeHtml(order.notes)}</div>` : ''}
                ${actionBtn}
            </div>
        `;

        grid.appendChild(col);
    });

    renderCounts();
}

function advanceOrder(id, nextStatus) {
    busyOrderIds.add(id);
    renderOrders();

    fetch('kitchen.php?ajax=update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: id, status: nextStatus })
    })
    .then(r => r.text())
    .then(text => {
        let data;
        try { data = JSON.parse(text); }
        catch (e) { showDebug('Non-JSON response from update:\n' + text); busyOrderIds.delete(id); renderOrders(); return; }

        busyOrderIds.delete(id);
        if (data.success) {
            hideDebug();
            const order = orders.find(o => o.id === id);
            if (order) order.status = data.status;
        } else {
            showDebug(data.error || 'Could not update order status.');
        }
        renderOrders();
    })
    .catch(err => {
        busyOrderIds.delete(id);
        showDebug('Network error: ' + err.message);
        renderOrders();
    });
}

document.getElementById('orderGrid').addEventListener('click', function (e) {
    const btn = e.target.closest('.kds-advance-btn');
    if (!btn || btn.disabled) return;
    advanceOrder(parseInt(btn.dataset.id, 10), btn.dataset.next);
});

document.getElementById('statusFilters').addEventListener('click', function (e) {
    const tab = e.target.closest('.filter-tab');
    if (!tab) return;
    activeFilter = tab.dataset.filter;
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    renderOrders();
});

document.getElementById('orderSearch').addEventListener('input', function (e) {
    searchTerm = e.target.value;
    renderOrders();
});

function fetchOrders() {
    fetch('kitchen.php?ajax=orders')
        .then(r => r.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                showDebug('Server did not return valid JSON. Raw response:\n\n' + text);
                return;
            }
            if (data.success) {
                hideDebug();
                orders = data.orders;
                renderOrders();
            } else {
                showDebug(data.error || 'Unknown error loading orders.');
            }
        })
        .catch(err => {
            showDebug('Network error: ' + err.message);
        });
}

fetchOrders();
setInterval(fetchOrders, 6000);
</script>

</body>
</html>