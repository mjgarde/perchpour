<?php

session_start();
header('Content-Type: application/json');
include 'config/db_connect.php';

$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload || empty($payload['table_id']) || empty($payload['request_type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$tableId     = (int) $payload['table_id'];
$tableNumber = trim($payload['table_number'] ?? '');
$requestType = trim($payload['request_type']);
$note        = trim($payload['note'] ?? '');

$stmt = mysqli_prepare($conn, "INSERT INTO service_requests (table_id, table_number, request_type, note) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isss", $tableId, $tableNumber, $requestType, $note);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not send request.']);
}
mysqli_stmt_close($stmt);