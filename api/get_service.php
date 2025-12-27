<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Service ID required']);
    exit();
}

$service_id = clean_input($_GET['id']);

$query = "SELECT * FROM services WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $service_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$service = mysqli_fetch_assoc($result);

if ($service) {
    echo json_encode([
        'success' => true,
        'service' => $service
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Service not found'
    ]);
}
?>