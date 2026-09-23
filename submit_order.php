<?php

session_start();
header('Content-Type: application/json');
include 'config/db_connect.php';

$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON received.']);
    exit;
}
if (empty($payload['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Your cart is empty.']);
    exit;
}
if (empty($payload['table_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing table_id — table_id received: ' . var_export($payload['table_id'] ?? null, true)]);
    exit;
}

$tableId     = (int) $payload['table_id'];
$tableNumber = trim($payload['table_number'] ?? '');
$notes       = trim($payload['notes'] ?? '');
$items       = $payload['items'];

$total = 0;
$cleanItems = [];
foreach ($items as $it) {
    $qty       = max(1, (int) ($it['quantity'] ?? 1));
    $price     = (float) ($it['unit_price'] ?? 0);
    $subtotal  = $qty * $price;
    $total    += $subtotal;

    $cleanItems[] = [
        'menu_item_id' => (int) ($it['menu_item_id'] ?? 0),
        'item_name'    => trim($it['item_name'] ?? ''),
        'quantity'     => $qty,
        'unit_price'   => $price,
        'size'         => $it['size'] ?? null,
        'sweetness'    => $it['sweetness'] ?? null,
        'milk_type'    => $it['milk_type'] ?? null,
        'addons'       => $it['addons'] ?? null,
        'subtotal'     => $subtotal,
    ];
}

$itemsJson = json_encode([
    'table_number' => $tableNumber,
    'notes'        => $notes,
    'items'        => $cleanItems,
], JSON_UNESCAPED_UNICODE);

$stmt = mysqli_prepare($conn, "INSERT INTO orders (table_id, items_json, total, status) VALUES (?, ?, ?, 'pending')");
mysqli_stmt_bind_param($stmt, "isd", $tableId, $itemsJson, $total);

if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save order. Please tell a staff member.']);
    exit;
}

$orderId = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

$orderNumber = 'Order ' . str_pad($orderId, 3, '0', STR_PAD_LEFT);

echo json_encode([
    'success'      => true,
    'order_id'     => $orderId,
    'order_number' => $orderNumber,
    'total'        => $total,
]);