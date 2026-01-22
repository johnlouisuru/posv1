<?php
// session_start();
require_once '../../includes/db.php';

// Simple endpoint to ensure session is written
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Cart synced',
    'cartCount' => isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0
]);
?>