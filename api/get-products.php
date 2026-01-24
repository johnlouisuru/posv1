<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$category_id = $_GET['category_id'] ?? null;
$search = $_GET['search'] ?? '';

try {
    // Get products with addon check in a single query
    $sql = "
        SELECT 
            p.*, 
            c.name as category_name, 
            k.name as station_name,
            CASE 
                WHEN p.has_addons = 1 THEN 1
                WHEN EXISTS (
                    SELECT 1 FROM addons a 
                    LEFT JOIN product_addons pa ON a.id = pa.addon_id
                    WHERE a.is_available = 1 
                    AND (
                        a.is_global = 1 
                        OR pa.product_id = p.id
                        OR a.product_id = p.id
                    )
                ) THEN 1
                ELSE 0
            END as has_any_addons
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN kitchen_stations k ON p.station_id = k.id 
        WHERE p.is_available = 1
    ";
    
    $params = [];
    $conditions = [];
    
    if ($category_id) {
        $conditions[] = "p.category_id = ?";
        $params[] = $category_id;
    }
    
    if ($search) {
        $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY p.display_order, p.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For debugging - check specific product
    foreach ($products as $product) {
        if ($product['id'] == 13) {
            error_log("Product 13 - has_addons: {$product['has_addons']}, has_any_addons: {$product['has_any_addons']}");
        }
    }
    
    echo json_encode($products);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>