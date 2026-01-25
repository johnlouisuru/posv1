<?php
// api/get-cart.php - UPDATED VERSION
// session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'cart' => [],
    'cartTotal' => 0,
    'cartCount' => 0
];

try {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        $response['success'] = true;
        echo json_encode($response);
        exit;
    }
    
    $cart = $_SESSION['cart'];
    $cartTotal = 0;
    $cartCount = 0;
    
    // Get all product IDs
    $productIds = [];
    foreach ($cart as $item) {
        if (isset($item['product_id'])) {
            $productIds[] = $item['product_id'];
        }
    }
    
    if (empty($productIds)) {
        $response['success'] = true;
        echo json_encode($response);
        exit;
    }
    
    // Get product details
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders)");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create product map
    $productMap = [];
    foreach ($products as $product) {
        $productMap[$product['id']] = $product;
    }
    
    // Build enhanced cart
    $enhancedCart = [];
    foreach ($cart as $uniqueKey => $item) {
        $productId = $item['product_id'] ?? 0;
        
        if (isset($productMap[$productId])) {
            $productInfo = $productMap[$productId];
            
            $itemTotal = $productInfo['price'] * $item['quantity'];
            
            // Add addons price
            if (!empty($item['addons'])) {
                foreach ($item['addons'] as $addon) {
                    $addonPrice = is_array($addon) ? ($addon['price'] ?? 0) : 0;
                    $addonQty = is_array($addon) ? ($addon['quantity'] ?? 1) : 1;
                    $itemTotal += $addonPrice * $addonQty;
                }
            }
            
            $cartTotal += $itemTotal;
            $cartCount += $item['quantity'];
            
            // Clean addons
            $cleanAddons = [];
            if (!empty($item['addons'])) {
                foreach ($item['addons'] as $addon) {
                    if (is_array($addon)) {
                        $cleanAddons[] = [
                            'addon_id' => $addon['addon_id'] ?? $addon['id'] ?? '',
                            'name' => $addon['name'] ?? '',
                            'price' => floatval($addon['price'] ?? 0),
                            'quantity' => intval($addon['quantity'] ?? 1)
                        ];
                    }
                }
            }
            
            $enhancedCart[$uniqueKey] = [
                'unique_key' => $uniqueKey,
                'product_id' => $productId,
                'name' => $productInfo['name'],
                'price' => floatval($productInfo['price']),
                'quantity' => intval($item['quantity']),
                'addons' => $cleanAddons,
                'special_request' => $item['special_request'] ?? '',
                'item_total' => floatval($itemTotal)
            ];
        }
    }
    
    $response = [
        'success' => true,
        'cart' => $enhancedCart,
        'cartTotal' => floatval($cartTotal),
        'cartCount' => intval($cartCount)
    ];
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>