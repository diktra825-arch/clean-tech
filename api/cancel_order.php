<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

header('Content-Type: application/json');

// Cek login
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit();
}

$order_id = clean_input($_GET['id']);
$user_id = $_SESSION['user_id'];
$user_role = get_user_role();

// Query order dengan cek hak akses
if ($user_role == 'admin') {
    $query = "SELECT o.*, s.name as service_name, u.name as user_name 
              FROM orders o 
              JOIN services s ON o.service_id = s.id 
              JOIN users u ON o.user_id = u.id 
              WHERE o.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
} else {
    $query = "SELECT o.*, s.name as service_name 
              FROM orders o 
              JOIN services s ON o.service_id = s.id 
              WHERE o.id = ? AND o.user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);

if ($order) {
    // Get order history
    $query = "SELECT * FROM order_history WHERE order_id = ? ORDER BY created_at ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $history_result = mysqli_stmt_get_result($stmt);
    $history = [];
    
    while ($row = mysqli_fetch_assoc($history_result)) {
        $history[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'history' => $history
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Order not found'
    ]);
}
?>