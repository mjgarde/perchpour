<?php
session_start();
include '../config/db_connect.php';

if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

$staff_id = (int) $_SESSION['staff_id'];
$fullName = $_SESSION['full_name'] ?? 'Staff';

$action = $_GET['action'] ?? '';

if ($action === 'debug') {
    header('Content-Type: text/plain');

    echo "=== orders (last 20) ===\n";
    $res = mysqli_query($conn, "SELECT * FROM orders ORDER BY id DESC LIMIT 20");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            echo json_encode($r) . "\n";
        }
    } else {
        echo "Query failed: " . mysqli_error($conn) . "\n";
    }

    echo "\n=== order_items (last 20) ===\n";
    $res = mysqli_query($conn, "SELECT * FROM order_items ORDER BY id DESC LIMIT 20");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            echo json_encode($r) . "\n";
        }
    } else {
        echo "Query failed: " . mysqli_error($conn) . "\n";
    }

    echo "\n=== columns in orders ===\n";
    $res = mysqli_query($conn, "SHOW COLUMNS FROM orders");
    while ($r = mysqli_fetch_assoc($res)) echo $r['Field'] . " (" . $r['Type'] . ")\n";

    echo "\n=== columns in order_items ===\n";
    $res = mysqli_query($conn, "SHOW COLUMNS FROM order_items");
    while ($r = mysqli_fetch_assoc($res)) echo $r['Field'] . " (" . $r['Type'] . ")\n";

    exit();
}

if ($action === 'fetch') {
    header('Content-Type: application/json');

    $orders = [];

    $sql = "SELECT * FROM orders WHERE status IN ('pending','preparing','ready') ORDER BY created_at ASC, id ASC LIMIT 200";
    $res = mysqli_query($conn, $sql);

    if (!$res) {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn), 'sql' => $sql]);
        exit();
    }

    $orderRows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $orderRows[] = $row;
    }

    $itemsByOrder = [];
    if (!empty($orderRows)) {
        $ids = array_map(function ($r) { return (int)$r['id']; }, $orderRows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $stmt = mysqli_prepare($conn,
            "SELECT * FROM order_items WHERE order_id IN ($placeholders) ORDER BY id ASC");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$ids);
            mysqli_stmt_execute($stmt);
            $ires = mysqli_stmt_get_result($stmt);
            while ($it = mysqli_fetch_assoc($ires)) {
                $itemsByOrder[(int)$it['order_id']][] = [
                    'item_name'  => $it['item_name'] ?? $it['name'] ?? 'Item',
                    'quantity'   => (int)($it['quantity'] ?? $it['qty'] ?? 1),
                    'unit_price' => (float)($it['unit_price'] ?? $it['price'] ?? 0),
                    'subtotal'   => (float)($it['subtotal'] ?? (($it['unit_price'] ?? $it['price'] ?? 0) * ($it['quantity'] ?? $it['qty'] ?? 1))),
                    'size'       => $it['size'] ?? ($it['item_size'] ?? null),
                    'sweetness'  => $it['sweetness'] ?? null,
                    'milk_type'  => $it['milk_type'] ?? ($it['milk'] ?? null),
                    'addons'     => $it['addons'] ?? null,
                ];
            }
            mysqli_stmt_close($stmt);
        }
    }

    $nowTs = time();

    foreach ($orderRows as $o) {
        $createdRaw = $o['created_at'] ?? $o['created'] ?? $o['order_date'] ?? date('Y-m-d H:i:s');
        $createdTs  = strtotime($createdRaw);
        if ($createdTs === false) $createdTs = $nowTs;

        $orders[] = [
            'id'                 => (int)$o['id'],
            'order_number'       => $o['order_number'] ?? $o['order_no'] ?? ('ORD-' . str_pad($o['id'], 5, '0', STR_PAD_LEFT)),
            'table_id'           => (int)($o['table_id'] ?? 0),
            'table_number'       => (string)($o['table_number'] ?? $o['table_no'] ?? ''),
            'status'             => $o['status'] ?? 'pending',
            'notes'              => $o['notes'] ?? ($o['note'] ?? ''),
            'total'              => (float)($o['total'] ?? $o['total_amount'] ?? 0),
            'created_at'         => $createdRaw,
            'created_at_ts'      => $createdTs,
            'created_at_display' => date('M j, Y g:i A', $createdTs),
            'prepared_at'        => !empty($o['prepared_at']) ? date('g:i A', strtotime($o['prepared_at'])) : null,
            'ready_at'           => !empty($o['ready_at'])    ? date('g:i A', strtotime($o['ready_at']))    : null,
            'served_at'          => !empty($o['served_at'])   ? date('g:i A', strtotime($o['served_at']))   : null,
            'elapsed_seconds'    => $nowTs - $createdTs,
            'items'              => $itemsByOrder[(int)$o['id']] ?? [],
        ];
    }

    $stats = ['pending' => 0, 'preparing' => 0, 'ready' => 0, 'served' => 0];

    $res = mysqli_query($conn, "SELECT status, COUNT(*) c FROM orders GROUP BY status");
    while ($row = mysqli_fetch_assoc($res)) {
        if (isset($stats[$row['status']])) $stats[$row['status']] = (int)$row['c'];
    }

    echo json_encode([
        'success' => true,
        'orders'  => $orders,
        'stats'   => $stats,
    ]);
    exit();
}

if ($action === 'update') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $order_id  = (int)($input['order_id'] ?? 0);
    $newStatus = trim($input['status'] ?? '');

    $allowed = ['pending', 'preparing', 'ready', 'served', 'cancelled'];

    if ($order_id <= 0 || !in_array($newStatus, $allowed, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid request.']);
        exit();
    }

    $stmt = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $newStatus, $order_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        exit();
    }

    echo json_encode(['success' => true, 'order_id' => $order_id, 'status' => $newStatus]);
    exit();
}

$stats = ['pending' => 0, 'preparing' => 0, 'ready' => 0, 'served' => 0];

$res = mysqli_query($conn, "SELECT status, COUNT(*) c FROM orders GROUP BY status");
while ($row = mysqli_fetch_assoc($res)) {
    if (isset($stats[$row['status']])) $stats[$row['status']] = (int)$row['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Kitchen Display — Perch &amp; Pour Staff</title>
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

    .kds-board {
        display: grid;
        grid-template-columns: repeat(3, minmax(280px, 1fr));
        gap: 16px;
    }
    @media (max-width: 1100px) { .kds-board { grid-template-columns: repeat(2, minmax(260px, 1fr)); } }
    @media (max-width: 720px)  { .kds-board { grid-template-columns: 1fr; } }

    .kds-col {
        background: #fff;
        border: 1px solid #dee2e6;
        display: flex;
        flex-direction: column;
        min-height: 300px;
    }
    .kds-col-header {
        padding: 12px 16px;
        border-bottom: 2px solid #111;
        display: flex; justify-content: space-between; align-items: center;
        background: #fafafa;
    }
    .kds-col-header .title {
        font-size: 13px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.06em;
    }
    .kds-col-header .count {
        background: #111; color: #fff;
        font-size: 11px; font-weight: 700;
        min-width: 22px; height: 22px;
        display: inline-flex; align-items: center; justify-content: center;
        padding: 0 6px;
    }
    .kds-col.pending   .kds-col-header { border-bottom-color: #b91c1c; }
    .kds-col.preparing .kds-col-header { border-bottom-color: #a86b00; }
    .kds-col.ready     .kds-col-header { border-bottom-color: #1a7f37; }
    .kds-col.pending   .kds-col-header .count { background: #b91c1c; }
    .kds-col.preparing .kds-col-header .count { background: #a86b00; }
    .kds-col.ready     .kds-col-header .count { background: #1a7f37; }

    .kds-col-body {
        padding: 12px;
        display: flex; flex-direction: column; gap: 12px;
        flex: 1;
        max-height: calc(100vh - 260px);
        overflow-y: auto;
    }

    .order-card {
        background: #fff;
        border: 1px solid #dee2e6;
        border-left: 4px solid #111;
        padding: 12px 14px;
        position: relative;
    }
    .order-card.is-new { animation: pulseNew 1.4s ease-in-out 3; }
    @keyframes pulseNew {
        0%, 100% { background: #fff; }
        50%      { background: #fff8e1; }
    }
    .order-card.pending   { border-left-color: #b91c1c; }
    .order-card.preparing { border-left-color: #a86b00; }
    .order-card.ready     { border-left-color: #1a7f37; }

    .order-head {
        display: flex; justify-content: space-between; align-items: flex-start;
        gap: 8px; margin-bottom: 8px;
    }
    .order-num {
        font-size: 15px; font-weight: 700;
        letter-spacing: 0.02em; margin-bottom: 2px;
    }
    .order-table { font-size: 12px; color: #6b7280; font-weight: 600; }
    .order-table strong { color: #111; font-size: 13px; }

    .order-elapsed {
        font-size: 12px; font-weight: 700;
        color: #111;
        background: #f1f3f5;
        padding: 3px 8px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
        white-space: nowrap;
    }
    .order-elapsed.warn   { background: #fdf3e0; color: #a86b00; }
    .order-elapsed.danger { background: #fdecec; color: #b91c1c; }

    .order-items {
        border-top: 1px dashed #dee2e6;
        padding-top: 8px;
        margin-bottom: 10px;
    }
    .order-item {
        display: flex; gap: 8px;
        padding: 5px 0;
        font-size: 13px;
        line-height: 1.35;
    }
    .order-item + .order-item { border-top: 1px dotted #eee; }
    .order-item .qty { font-weight: 700; min-width: 26px; color: #111; flex-shrink: 0; }
    .order-item .detail { flex: 1; min-width: 0; }
    .order-item .name { font-weight: 600; }
    .order-item .cust { font-size: 11px; color: #6b7280; margin-top: 1px; line-height: 1.4; }

    .order-notes {
        font-size: 11.5px;
        background: #fffbea;
        border: 1px solid #f5e6a8;
        padding: 6px 9px;
        margin-bottom: 10px;
        color: #6b5714;
    }
    .order-notes i { margin-right: 4px; }

    .order-actions {
        display: flex; gap: 6px;
        border-top: 1px solid #eee;
        padding-top: 10px;
        margin-top: 4px;
    }
    .order-actions .btn {
        flex: 1;
        font-size: 11.5px;
        padding: 6px 8px;
        border-radius: 0;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .kds-empty {
        border: 1px dashed #dee2e6;
        color: #a0a0a0;
        text-align: center;
        padding: 32px 16px;
        font-size: 12.5px;
        font-style: italic;
    }

    .detail-sheet {
        background: #fafafa;
        border: 1px solid #dee2e6;
        padding: 12px 14px;
        font-size: 12.5px;
    }
    .detail-sheet .row-line {
        display: flex; justify-content: space-between;
        padding: 4px 0;
        border-bottom: 1px dotted #e5e5e5;
    }
    .detail-sheet .row-line:last-child { border-bottom: none; }
    .detail-sheet .row-line .lbl {
        color: #6b7280;
        text-transform: uppercase;
        font-size: 10.5px;
        letter-spacing: 0.05em;
    }
    .detail-sheet .row-line .val { font-weight: 600; }
</style>
</head>
<body>

<div class="d-flex min-vh-100">

<?php include '../include/staff_sidebar.php'; ?>

<div class="flex-grow-1 min-w-0">

    <div class="d-flex align-items-center bg-white border-bottom px-4 py-3">
        <button type="button" class="btn btn-outline-dark d-lg-none me-3" onclick="openSidebar()">
            <i class="fa-solid fa-bars"></i>
        </button>
        <h1 class="h4 fw-bold mb-0">Kitchen Display</h1>
    </div>

    <div class="container-fluid px-4 py-4">

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                        <i class="fa-solid fa-bell text-danger"></i><span>New Orders</span>
                    </div>
                    <div class="fs-3 fw-bold lh-1" id="statPending"><?= $stats['pending'] ?></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                        <i class="fa-solid fa-fire text-warning"></i><span>Preparing</span>
                    </div>
                    <div class="fs-3 fw-bold lh-1" id="statPreparing"><?= $stats['preparing'] ?></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                        <i class="fa-solid fa-bell-concierge text-success"></i><span>Ready</span>
                    </div>
                    <div class="fs-3 fw-bold lh-1" id="statReady"><?= $stats['ready'] ?></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                        <i class="fa-solid fa-circle-check text-secondary"></i><span>Served Today</span>
                    </div>
                    <div class="fs-3 fw-bold lh-1" id="statServed"><?= $stats['served'] ?></div>
                </div></div>
            </div>
        </div>

        <div class="kds-board">

            <div class="kds-col pending" data-col="pending">
                <div class="kds-col-header">
                    <span class="title"><i class="fa-solid fa-bell text-danger me-1"></i> New</span>
                    <span class="count" id="countPending">0</span>
                </div>
                <div class="kds-col-body" id="colPending"></div>
            </div>

            <div class="kds-col preparing" data-col="preparing">
                <div class="kds-col-header">
                    <span class="title"><i class="fa-solid fa-fire text-warning me-1"></i> Preparing</span>
                    <span class="count" id="countPreparing">0</span>
                </div>
                <div class="kds-col-body" id="colPreparing"></div>
            </div>

            <div class="kds-col ready" data-col="ready">
                <div class="kds-col-header">
                    <span class="title"><i class="fa-solid fa-bell-concierge text-success me-1"></i> Ready for Pickup</span>
                    <span class="count" id="countReady">0</span>
                </div>
                <div class="kds-col-body" id="colReady"></div>
            </div>

        </div>

    </div>
</div>

</div>

<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title h6 fw-bold mb-0">
                    <i class="fa-solid fa-receipt me-1"></i>
                    <span id="mdOrderNum">Order Details</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="mdBody"></div>
            <div class="modal-footer border-top" id="mdFooter"></div>
        </div>
    </div>
</div>

<script src="../vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
    const API = 'kitchen.php';

    const STATUS_LABEL = {
        pending:   'New',
        preparing: 'Preparing',
        ready:     'Ready',
        served:    'Served',
        cancelled: 'Cancelled'
    };

    const NEXT_STATUS = {
        pending:   { next: 'preparing', label: 'Start Preparing', icon: 'fa-fire',  cls: 'btn-warning' },
        preparing: { next: 'ready',     label: 'Mark Ready',      icon: 'fa-bell',  cls: 'btn-success' },
        ready:     { next: 'served',    label: 'Mark Served',     icon: 'fa-check', cls: 'btn-dark'    }
    };

    const PREV_STATUS = {
        preparing: { prev: 'pending',   label: 'Undo' },
        ready:     { prev: 'preparing', label: 'Undo' },
        served:    { prev: 'ready',     label: 'Undo' }
    };

    let knownOrderIds = new Set();
    let currentOrders = [];
    let audioUnlocked = false;

    function unlockAudio() {
        if (audioUnlocked) return;
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            ctx.resume();
            audioUnlocked = true;
        } catch (e) {}
    }
    document.addEventListener('click', unlockAudio, { once: true });
    document.addEventListener('touchstart', unlockAudio, { once: true });

    function playBeep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.18].forEach(function (offset) {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, ctx.currentTime + offset);
                gain.gain.setValueAtTime(0.22, ctx.currentTime + offset);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + offset + 0.16);
                osc.start(ctx.currentTime + offset);
                osc.stop(ctx.currentTime + offset + 0.2);
            });
        } catch (e) {}
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function itemCustLine(it) {
        const parts = [];
        if (it.size) parts.push('Size: ' + it.size);
        if (it.sweetness) parts.push('Sweet: ' + it.sweetness);
        if (it.milk_type) parts.push('Milk: ' + it.milk_type);
        if (it.addons) parts.push('Add: ' + it.addons);
        return parts.join(' • ');
    }

    function elapsedClass(secs, status) {
        if (status === 'ready') return '';
        if (secs >= 900) return 'danger';
        if (secs >= 480) return 'warn';
        return '';
    }

    function fmtElapsed(secs) {
        secs = Math.max(0, parseInt(secs, 10) || 0);
        const m = Math.floor(secs / 60);
        const s = secs % 60;
        return m + ':' + String(s).padStart(2, '0');
    }

    function updateElapsedDisplays() {
        document.querySelectorAll('[data-elapsed-for]').forEach(function (el) {
            const created = parseInt(el.dataset.created, 10) || 0;
            const status  = el.dataset.status || '';
            const secs    = Math.floor((Date.now() / 1000) - created);
            el.textContent = fmtElapsed(secs);
            el.className = 'order-elapsed ' + elapsedClass(secs, status);
        });
    }

    function buildCard(o) {
        const custLine = o.items.map(function (it) {
            return '<div class="order-item">'
                 + '<span class="qty">' + it.quantity + '×</span>'
                 + '<div class="detail">'
                 + '<div class="name">' + escapeHtml(it.item_name) + '</div>'
                 + (itemCustLine(it) ? '<div class="cust">' + escapeHtml(itemCustLine(it)) + '</div>' : '')
                 + '</div>'
                 + '</div>';
        }).join('');

        const notes = o.notes
            ? '<div class="order-notes"><i class="fa-solid fa-note-sticky"></i>' + escapeHtml(o.notes) + '</div>'
            : '';

        const action = NEXT_STATUS[o.status];
        const undo   = PREV_STATUS[o.status];

        let buttons = '';
        if (undo) {
            buttons += '<button type="button" class="btn btn-outline-secondary" '
                     + 'data-action="status" data-order="' + o.id + '" data-status="' + undo.prev + '">'
                     + '<i class="fa-solid fa-rotate-left me-1"></i>' + undo.label + '</button>';
        }
        if (action) {
            buttons += '<button type="button" class="btn ' + action.cls + '" '
                     + 'data-action="status" data-order="' + o.id + '" data-status="' + action.next + '">'
                     + '<i class="fa-solid ' + action.icon + ' me-1"></i>' + action.label + '</button>';
        }
        buttons += '<button type="button" class="btn btn-outline-dark" '
                 + 'data-action="detail" data-order="' + o.id + '" title="View details">'
                 + '<i class="fa-solid fa-expand"></i></button>';

        const isNew = o.is_new ? ' is-new' : '';

        return ''
            + '<div class="order-card ' + o.status + isNew + '" data-order-id="' + o.id + '">'
            +   '<div class="order-head">'
            +     '<div>'
            +       '<div class="order-num">' + escapeHtml(o.order_number) + '</div>'
            +       '<div class="order-table">Table <strong>' + escapeHtml(o.table_number || '—') + '</strong></div>'
            +     '</div>'
            +     '<div class="order-elapsed" data-elapsed-for="' + o.id + '" data-created="' + o.created_at_ts + '" data-status="' + o.status + '">'
            +       fmtElapsed(o.elapsed_seconds)
            +     '</div>'
            +   '</div>'
            +   '<div class="order-items">' + (custLine || '<div class="text-secondary small">No items</div>') + '</div>'
            +   notes
            +   '<div class="order-actions">' + buttons + '</div>'
            + '</div>';
    }

    function renderColumn(status, orders) {
        const key = status.charAt(0).toUpperCase() + status.slice(1);
        const colEl   = document.getElementById('col' + key);
        const countEl = document.getElementById('count' + key);
        if (!colEl) return;

        if (orders.length === 0) {
            colEl.innerHTML = '<div class="kds-empty">No orders in this column.</div>';
            if (countEl) countEl.textContent = '0';
            return;
        }

        colEl.innerHTML = orders.map(buildCard).join('');
        if (countEl) countEl.textContent = orders.length;
    }

    function renderOrders(data) {
        const byStatus = { pending: [], preparing: [], ready: [] };

        data.orders.forEach(function (o) {
            if (byStatus[o.status]) byStatus[o.status].push(o);
        });

        renderColumn('pending',   byStatus.pending);
        renderColumn('preparing', byStatus.preparing);
        renderColumn('ready',     byStatus.ready);

        document.getElementById('statPending').textContent   = data.stats.pending;
        document.getElementById('statPreparing').textContent = data.stats.preparing;
        document.getElementById('statReady').textContent     = data.stats.ready;
        document.getElementById('statServed').textContent    = data.stats.served;

        currentOrders = data.orders;
        updateElapsedDisplays();
    }

    async function fetchOrders() {
        try {
            const res  = await fetch(API + '?action=fetch', { cache: 'no-store' });
            const data = await res.json();

            if (!data.success) {
                console.error('Fetch error:', data.error, data.sql || '');
                return;
            }

            const newIds = data.orders
                .filter(function (o) { return o.status === 'pending'; })
                .map(function (o) { return o.id; });

            let hasNew = false;
            newIds.forEach(function (id) {
                if (!knownOrderIds.has(id)) hasNew = true;
            });

            data.orders.forEach(function (o) {
                o.is_new = !knownOrderIds.has(o.id) && o.status === 'pending';
            });

            if (hasNew && knownOrderIds.size > 0) {
                playBeep();
            }

            knownOrderIds = new Set(data.orders.map(function (o) { return o.id; }));
            renderOrders(data);

        } catch (err) {
            console.error('Fetch failed:', err);
        }
    }

    async function updateStatus(orderId, newStatus) {
        try {
            const res = await fetch(API + '?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId, status: newStatus })
            });
            const data = await res.json();
            if (data.success) {
                fetchOrders();
            } else {
                alert(data.error || 'Could not update order.');
            }
        } catch (err) {
            alert('Network error. Please try again.');
        }
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        const action  = btn.dataset.action;
        const orderId = parseInt(btn.dataset.order, 10);

        if (action === 'status') {
            updateStatus(orderId, btn.dataset.status);
        } else if (action === 'detail') {
            openDetail(orderId);
        }
    });

    function openDetail(orderId) {
        const o = currentOrders.find(function (x) { return x.id === orderId; });
        if (!o) return;

        document.getElementById('mdOrderNum').textContent = o.order_number + ' · Table ' + (o.table_number || '—');

        let itemsHtml = '<table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr>'
                      + '<th style="width:60px;">Qty</th><th>Item</th><th class="text-end" style="width:110px;">Subtotal</th>'
                      + '</tr></thead><tbody>';
        o.items.forEach(function (it) {
            itemsHtml += '<tr>'
                       + '<td class="fw-bold">' + it.quantity + '×</td>'
                       + '<td>'
                       +   '<div class="fw-semibold">' + escapeHtml(it.item_name) + '</div>'
                       +   (itemCustLine(it) ? '<div class="small text-secondary">' + escapeHtml(itemCustLine(it)) + '</div>' : '')
                       + '</td>'
                       + '<td class="text-end">₱' + Number(it.subtotal).toFixed(2) + '</td>'
                       + '</tr>';
        });
        itemsHtml += '</tbody></table>';

        const metaHtml = '<div class="detail-sheet mb-3">'
                       + '<div class="row-line"><span class="lbl">Status</span><span class="val">' + STATUS_LABEL[o.status] + '</span></div>'
                       + '<div class="row-line"><span class="lbl">Created</span><span class="val">' + o.created_at_display + '</span></div>'
                       + (o.prepared_at ? '<div class="row-line"><span class="lbl">Started Preparing</span><span class="val">' + o.prepared_at + '</span></div>' : '')
                       + (o.ready_at    ? '<div class="row-line"><span class="lbl">Ready At</span><span class="val">' + o.ready_at + '</span></div>' : '')
                       + (o.served_at   ? '<div class="row-line"><span class="lbl">Served At</span><span class="val">' + o.served_at + '</span></div>' : '')
                       + '<div class="row-line"><span class="lbl">Total</span><span class="val">₱' + Number(o.total).toFixed(2) + '</span></div>'
                       + '</div>';

        const notesHtml = o.notes
            ? '<div class="order-notes mb-3"><i class="fa-solid fa-note-sticky"></i><strong>Note:</strong> ' + escapeHtml(o.notes) + '</div>'
            : '';

        document.getElementById('mdBody').innerHTML = metaHtml + notesHtml + itemsHtml;

        const next = NEXT_STATUS[o.status];
        const undo = PREV_STATUS[o.status];
        let footHtml = '<button type="button" class="btn btn-outline-dark btn-sm rounded-0" data-bs-dismiss="modal">Close</button>';
        if (undo) {
            footHtml += '<button type="button" class="btn btn-outline-secondary btn-sm rounded-0" '
                      + 'data-action="status" data-order="' + o.id + '" data-status="' + undo.prev + '" data-bs-dismiss="modal">'
                      + '<i class="fa-solid fa-rotate-left me-1"></i>' + undo.label + '</button>';
        }
        if (next) {
            footHtml += '<button type="button" class="btn ' + next.cls + ' btn-sm rounded-0" '
                      + 'data-action="status" data-order="' + o.id + '" data-status="' + next.next + '" data-bs-dismiss="modal">'
                      + '<i class="fa-solid ' + next.icon + ' me-1"></i>' + next.label + '</button>';
        }
        document.getElementById('mdFooter').innerHTML = footHtml;

        new bootstrap.Modal(document.getElementById('orderModal')).show();
    }

    fetchOrders();
    setInterval(fetchOrders, 5000);
    setInterval(updateElapsedDisplays, 1000);
</script>

</body>
</html>