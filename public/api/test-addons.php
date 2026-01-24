<?php
// api/test-addons.php
require_once '../../includes/db.php';

header('Content-Type: application/json');

// Test endpoint to verify addons exist
$stmt = $pdo->query("SELECT COUNT(*) as count FROM addons WHERE is_available = 1");
$addonCount = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_available = 1");
$productCount = $stmt->fetch()['count'];

// Get some sample data
$stmt = $pdo->query("SELECT id, name, price FROM products WHERE is_available = 1 LIMIT 3");
$sampleProducts = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM addons WHERE is_available = 1");
$allAddons = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'stats' => [
        'addons_count' => $addonCount,
        'products_count' => $productCount
    ],
    'sample_products' => $sampleProducts,
    'all_addons' => $allAddons
]);
?>