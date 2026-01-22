<?php
// session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$productId = $data['product_id'] ?? 0;
$quantity = $data['quantity'] ?? 1;
$addons = $data['addons'] ?? [];
$specialRequest = $data['special_request'] ?? '';

if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'No product selected']);
    exit;
}

// Get product info
$stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = ? AND is_available = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not available']);
    exit;
}

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check current quantity in cart
$currentQuantity = $_SESSION['cart'][$productId]['quantity'] ?? 0;

// Calculate new quantity
if (isset($_SESSION['cart'][$productId])) {
    // If quantity is provided, set it directly
    $_SESSION['cart'][$productId]['quantity'] = $quantity;
    $_SESSION['cart'][$productId]['addons'] = $addons;
    $_SESSION['cart'][$productId]['special_request'] = $specialRequest;
} else {
    // Add new item
    $_SESSION['cart'][$productId] = [
        'product_id' => $productId,
        'name' => $product['name'],
        'price' => floatval($product['price']),
        'quantity' => $quantity,
        'addons' => $addons,
        'special_request' => $specialRequest
    ];
}

// If quantity is 0, remove from cart
if ($quantity <= 0) {
    unset($_SESSION['cart'][$productId]);
}

// Calculate cart totals
$cartCount = 0;
$cartTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartCount += $item['quantity'];
    $cartTotal += ($item['price'] * $item['quantity']);
    
    // Add addons price
    if (!empty($item['addons'])) {
        foreach ($item['addons'] as $addon) {
            // Assuming each addon has price information
            if (isset($addon['price'])) {
                $cartTotal += ($addon['price'] * $addon['quantity']);
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Item added to cart',
    'cart' => $_SESSION['cart'],
    'cartCount' => $cartCount,
    'cartTotal' => $cartTotal,
    'cartQuantity' => isset($_SESSION['cart'][$productId]) ? $_SESSION['cart'][$productId]['quantity'] : 0
]);
?>