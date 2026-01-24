<?php
// api/get-product-addons.php - FIXED VERSION
require_once '../../includes/db.php';

header('Content-Type: application/json');

$productId = $_GET['product_id'] ?? 0;

// Get product info
$stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode([
        'success' => false,
        'message' => 'Product not found'
    ]);
    exit;
}

// Get addons for this product - BOTH specific AND global
$sql = "
    SELECT DISTINCT a.* 
    FROM addons a
    WHERE (
        -- Product-specific addons
        a.id IN (
            SELECT pa.addon_id 
            FROM product_addons pa 
            WHERE pa.product_id = ?
        )
        OR
        -- Global addons (available for all products)
        a.is_global = 1
    )
    AND a.is_available = 1
    ORDER BY 
        CASE 
            WHEN a.is_global = 1 THEN 2 
            ELSE 1 
        END,
        a.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$productId]);
$addons = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'product' => $product,
    'addons' => $addons
]);
?>