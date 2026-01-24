<?php
// api/add-to-cart.php
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

// Add or update item in cart
if (isset($_SESSION['cart'][$productId])) {
    // Update existing item
    $_SESSION['cart'][$productId]['quantity'] += $quantity;
    if (!empty($addons)) {
        // Merge addons
        $existingAddons = $_SESSION['cart'][$productId]['addons'] ?? [];
        $_SESSION['cart'][$productId]['addons'] = array_merge($existingAddons, $addons);
    }
    if ($specialRequest) {
        $_SESSION['cart'][$productId]['special_request'] = $specialRequest;
    }
} else {
    // Add new item
    $_SESSION['cart'][$productId] = [
        'product_id' => $productId,
        'name' => $product['name'],
        'price' => $product['price'],
        'quantity' => $quantity,
        'addons' => $addons,
        'special_request' => $specialRequest
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