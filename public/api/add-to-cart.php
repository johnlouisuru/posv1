<?php
// api/add-to-cart.php - UPDATED VERSION
// session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$productId = $data['product_id'] ?? 0;
$quantity = $data['quantity'] ?? 1;
$addons = $data['addons'] ?? [];
$specialRequest = $data['special_request'] ?? '';

// Get product info
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Sort addons by ID to ensure consistent comparison
usort($addons, function($a, $b) {
    return ($a['addon_id'] ?? $a['id'] ?? 0) - ($b['addon_id'] ?? $b['id'] ?? 0);
});

// Create a unique key based on product + addons configuration + special request
// Convert addons to a string for comparison
$addonsKey = '';
if (!empty($addons)) {
    $addonParts = [];
    foreach ($addons as $addon) {
        $addonId = $addon['addon_id'] ?? $addon['id'] ?? 0;
        $addonQty = $addon['quantity'] ?? 1;
        $addonParts[] = "{$addonId}:{$addonQty}";
    }
    $addonsKey = implode(',', $addonParts);
}

$specialRequestKey = md5(trim($specialRequest));

// Create unique cart item ID
$cartItemId = "{$productId}_{$addonsKey}_{$specialRequestKey}";

// Check if this exact configuration already exists in cart
$itemExists = false;
foreach ($_SESSION['cart'] as $existingItemId => $item) {
    // Extract components from existing item ID
    $parts = explode('_', $existingItemId);
    if ($parts[0] == $productId && 
        ($parts[1] ?? '') == $addonsKey && 
        ($parts[2] ?? '') == $specialRequestKey) {
        
        // Same configuration exists - update quantity
        $_SESSION['cart'][$existingItemId]['quantity'] += $quantity;
        $itemExists = true;
        break;
    }
}

// If not exists, add as new item
if (!$itemExists) {
    $_SESSION['cart'][$cartItemId] = [
        'product_id' => $productId,
        'name' => $product['name'],
        'price' => $product['price'],
        'quantity' => $quantity,
        'addons' => $addons,
        'special_request' => $specialRequest,
        'unique_key' => $cartItemId // Store for reference
    ];
}

// Calculate totals for immediate response
$cartTotal = 0;
$cartCount = 0;

foreach ($_SESSION['cart'] as $item) {
    $itemTotal = $item['price'] * $item['quantity'];
    
    // Add addons price
    foreach ($item['addons'] ?? [] as $addon) {
        $itemTotal += ($addon['price'] ?? 0) * ($addon['quantity'] ?? 1);
    }
    
    $cartTotal += $itemTotal;
    $cartCount += $item['quantity'];
}

echo json_encode([
    'success' => true,
    'message' => 'Added to cart successfully',
    'cart' => $_SESSION['cart'],
    'cartTotal' => $cartTotal,
    'cartCount' => $cartCount
]);
?>