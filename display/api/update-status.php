<?php
require_once '../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$orderId = $_POST['order_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$orderId || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Validate status - ADD 'confirmed' to valid statuses
$validStatuses = ['confirmed', 'preparing', 'ready', 'completed', 'cancelled'];
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status: ' . $status]);
    exit;
}

try {
    // Map display status to order status - UPDATE with all statuses
    $statusMap = [
        'confirmed' => 'confirmed',
        'preparing' => 'preparing',
        'ready' => 'ready',
        'completed' => 'completed',
        'cancelled' => 'cancelled'
    ];
    
    $orderStatus = $statusMap[$status] ?? 'confirmed';
    
    // Update main order status
    $sql = "UPDATE orders SET status = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderStatus, $orderId]);
    
    // Update display status
    $sql2 = "UPDATE order_display_status SET status = ? WHERE order_id = ?";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([$status, $orderId]);
    
    // Also update all order items status based on order status
    $itemStatusMap = [
        'confirmed' => 'pending',
        'preparing' => 'preparing',
        'ready' => 'ready',
        'completed' => 'ready', // Items marked as ready when order completed
        'cancelled' => 'cancelled' // Items marked as cancelled when order cancelled
    ];
    
    $itemStatus = $itemStatusMap[$status] ?? 'pending';
    $sql3 = "UPDATE order_items SET status = ? WHERE order_id = ?";
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute([$itemStatus, $orderId]);
    
    // If marking as completed, update display_until
    if ($status === 'completed') {
        $sql4 = "UPDATE order_display_status SET display_until = NOW() WHERE order_id = ?";
        $stmt4 = $pdo->prepare($sql4);
        $stmt4->execute([$orderId]);
    }
    
    // Log the status change
    $sql5 = "INSERT INTO status_change_logs (order_id, old_status, new_status, notes) 
             SELECT ?, o.status, ?, 'Status updated from display' 
             FROM orders o WHERE o.id = ?";
    $stmt5 = $pdo->prepare($sql5);
    $stmt5->execute([$orderId, $orderStatus, $orderId]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Status updated successfully',
        'new_status' => $status
    ]);
    
} catch (Exception $e) {
    error_log("Status update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>