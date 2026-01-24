<?php
// api/get-product-info.php
require_once '../../includes/db.php';

header('Content-Type: application/json');

$productId = $_GET['product_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.price, p.description, p.image_url, c.name as category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");

$stmt->execute([$productId]);
$product = $stmt->fetch();

echo json_encode([
    'success' => $product ? true : false,
    'product' => $product
]);
?>