<?php
// api/test-global-addons.php
require_once '../../includes/db.php';

header('Content-Type: application/json');

// Check all addons
$stmt = $pdo->query("SELECT * FROM addons WHERE is_available = 1");
$allAddons = $stmt->fetchAll();

// Check global addons specifically
$stmt = $pdo->query("SELECT * FROM addons WHERE is_global = 1 AND is_available = 1");
$globalAddons = $stmt->fetchAll();

// Check product_addons table
$stmt = $pdo->query("SELECT * FROM product_addons");
$productAddons = $stmt->fetchAll();

// Test with a specific product
$testProductId = 1; // Change this to test different products
$sql = "
    SELECT a.* 
    FROM addons a
    LEFT JOIN product_addons pa ON a.id = pa.addon_id AND pa.product_id = ?
    WHERE (pa.product_id = ? OR a.is_global = 1)
    AND a.is_available = 1
    ORDER BY a.is_global, a.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$testProductId, $testProductId]);
$testResult = $stmt->fetchAll();

echo json_encode([
    'debug_info' => [
        'total_addons' => count($allAddons),
        'global_addons' => count($globalAddons),
        'product_addons_entries' => count($productAddons),
        'test_product_id' => $testProductId,
        'test_result_count' => count($testResult)
    ],
    'all_addons' => $allAddons,
    'global_addons' => $globalAddons,
    'product_addons' => $productAddons,
    'test_result' => $testResult
], JSON_PRETTY_PRINT);
?>