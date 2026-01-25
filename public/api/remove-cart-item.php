<?php
// api/remove-cart-item.php - UPDATED VERSION
// session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$uniqueKey = $data['unique_key'] ?? '';

if (!$uniqueKey || !isset($_SESSION['cart'][$uniqueKey])) {
    echo json_encode(['success' => false, 'message' => 'Item not in cart']);
    exit;
}

// Remove the item
unset($_SESSION['cart'][$uniqueKey]);

// Calculate cart totals
$cartCount = 0;
$cartTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartCount += $item['quantity'];
    $itemTotal = $item['price'] * $item['quantity'];
    
    if (!empty($item['addons'])) {
        foreach ($item['addons'] as $addon) {
            if (is_array($addon) && isset($addon['price']) && isset($addon['quantity'])) {
                $itemTotal += ($addon['price'] * $addon['quantity']);
            }
        }
    }
    
    $cartTotal += $itemTotal;
}

echo json_encode([
    'success' => true,
    'message' => 'Item removed from cart',
    'cartCount' => $cartCount,
    'cartTotal' => $cartTotal
]);
?>