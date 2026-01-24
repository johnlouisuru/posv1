<?php
// api/update-cart-item.php - FIXED VERSION
// session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$productId = $data['product_id'] ?? 0;
$change = $data['change'] ?? 0;

if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'No product selected']);
    exit;
}

if (!isset($_SESSION['cart'][$productId])) {
    echo json_encode(['success' => false, 'message' => 'Item not in cart']);
    exit;
}

// Update quantity
$newQuantity = $_SESSION['cart'][$productId]['quantity'] + $change;
$newQuantity = max(0, $newQuantity);

if ($newQuantity > 0) {
    $_SESSION['cart'][$productId]['quantity'] = $newQuantity;
} else {
    // Remove if quantity is 0
    unset($_SESSION['cart'][$productId]);
}

// Calculate cart totals
$cartCount = 0;
$cartTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartCount += $item['quantity'];
    $itemTotal = $item['price'] * $item['quantity'];
    
    // Add addons price if exists
    if (!empty($item['addons'])) {
        foreach ($item['addons'] as $addon) {
            if (is_array($addon) && isset($addon['price']) && isset($addon['quantity'])) {
                $itemTotal += ($addon['price'] * $addon['quantity']);
            }
        }
    }
    
    $cartTotal += $itemTotal;
}

// Return clean data
$cleanCart = [];
foreach ($_SESSION['cart'] as $id => $item) {
    $cleanCart[$id] = [
        'product_id' => $id,
        'name' => $item['name'] ?? '',
        'price' => floatval($item['price'] ?? 0),
        'quantity' => intval($item['quantity'] ?? 0),
        'addons' => $item['addons'] ?? [],
        'special_request' => $item['special_request'] ?? ''
    ];
}

echo json_encode([
    'success' => true,
    'cart' => $cleanCart,
    'cartCount' => $cartCount,
    'cartTotal' => $cartTotal
]);
?>