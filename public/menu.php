<?php
// session_start();
require_once '../includes/db.php';
$existing_order_handle = '';
// Initialize cart
// session_unset();
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
    $_SESSION['cart_id'] = 'CART-' . substr(md5(session_id() . time()), 0, 8);
}
if(isset($_SESSION['last_order']['order_number'])){
    $existing_order_handle = $_SESSION['last_order']['order_number'];
}

// Get all active categories with products
$sql = "
    SELECT c.*, 
           COUNT(p.id) as product_count,
           GROUP_CONCAT(DISTINCT p.id) as product_ids
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.is_available = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.display_order
";

$categories = $pdo->query($sql)->fetchAll();

// Get all products with addons (both dedicated AND global)
$productsSql = "
    SELECT p.*, c.name as category_name, c.color_code,
           GROUP_CONCAT(DISTINCT CONCAT(a.id, '::', a.name, '::', a.price, '::', a.description, '::', a.is_global)) as addons_data,
           CASE 
               WHEN EXISTS (
                   SELECT 1 FROM addons a2 
                   WHERE (a2.is_global = 1 AND a2.is_available = 1) 
                   OR a2.id IN (SELECT pa.addon_id FROM product_addons pa WHERE pa.product_id = p.id)
               ) THEN 1 
               ELSE 0 
           END as has_available_addons
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_addons pa ON p.id = pa.product_id
    LEFT JOIN addons a ON (a.id = pa.addon_id OR a.is_global = 1) AND a.is_available = 1
    WHERE p.is_available = 1
    GROUP BY p.id, c.name, c.color_code, c.display_order, p.display_order
    ORDER BY c.display_order, p.display_order
";

$products = $pdo->query($productsSql)->fetchAll();

// Organize products by category
$productsByCategory = [];
$categoryColors = [];

foreach ($categories as $category) {
    $productsByCategory[$category['id']] = array_filter($products, function($product) use ($category) {
        return $product['category_id'] == $category['id'];
    });
    $categoryColors[$category['id']] = $category['color_code'];
}

// Calculate cart totals
$cartTotal = 0;
$cartCount = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartCount += $item['quantity'];
    $cartTotal += ($item['price'] * $item['quantity']);
}

// Get best-selling products based on inventory logs (using ABS for negative values)
$bestSellersSql = "
    SELECT 
    p.*, 
    c.name AS category_name, 
    c.color_code,
    COALESCE(s.total_sold, 0) AS total_sold,
    GROUP_CONCAT(DISTINCT CONCAT(
        a.id, '::', a.name, '::', a.price, '::', a.description, '::', a.is_global
    )) AS addons_data,
    CASE 
        WHEN EXISTS (
            SELECT 1 
            FROM addons a2 
            WHERE (a2.is_global = 1 AND a2.is_available = 1) 
               OR a2.id IN (
                   SELECT pa.addon_id 
                   FROM product_addons pa 
                   WHERE pa.product_id = p.id
               )
        ) THEN 1 
        ELSE 0 
    END AS has_available_addons
FROM products p
JOIN categories c 
    ON p.category_id = c.id
LEFT JOIN (
    SELECT 
        il.product_id, 
        ABS(SUM(il.quantity_change)) AS total_sold
    FROM inventory_logs il
    WHERE il.change_type = 'sale'
    GROUP BY il.product_id
) s ON p.id = s.product_id
LEFT JOIN product_addons pa 
    ON p.id = pa.product_id
LEFT JOIN addons a 
    ON (a.id = pa.addon_id OR a.is_global = 1) 
   AND a.is_available = 1
WHERE p.is_available = 1
GROUP BY p.id, c.name, c.color_code, s.total_sold
ORDER BY total_sold DESC, p.display_order
LIMIT 10;
";

$bestSellers = $pdo->query($bestSellersSql)->fetchAll();

// Get cart items with details
$cartItems = [];
if (!empty($_SESSION['cart'])) {
    $productIds = array_column($_SESSION['cart'], 'product_id');
    if (!empty($productIds)) {
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
        $cartProductsSql = "SELECT id, name, price FROM products WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($cartProductsSql);
        $stmt->execute($productIds);
        $cartProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create a map for easy access
        $productMap = [];
        foreach ($cartProducts as $product) {
            $productMap[$product['id']] = [
                'name' => $product['name'],
                'price' => $product['price']
            ];
        }
        
        foreach ($_SESSION['cart'] as $productId => $item) {
            if (isset($productMap[$productId])) {
                $productInfo = $productMap[$productId];
                $cartItems[] = [
                    'product_id' => $productId,
                    'name' => $productInfo['name'],
                    'price' => $productInfo['price'],
                    'quantity' => $item['quantity'],
                    'addons' => $item['addons'] ?? [],
                    'special_request' => $item['special_request'] ?? ''
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../uploads/samara.jpg">
    <title>SAMARA'S CAFFEINATED DREAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/online-menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
    /* ========== BASE STYLES ========== */
    body {
        background: linear-gradient(135deg, #f8f4e9 0%, #e6dfd3 100%);
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        overflow-x: hidden;
    }

    /* ========== HEADER STYLES ========== */
    .online-header {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    /* ========== CATEGORY NAVIGATION ========== */
    .category-nav {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        position: sticky;
        top: 70px;
        z-index: 999;
    }

    .category-title {
        background: linear-gradient(90deg, #8B4513, #D2691E);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        font-weight: 700;
        letter-spacing: 0.5px;
        padding: 10px 0;
        border-bottom: 2px solid #8B4513;
        margin-bottom: 20px;
    }

    /* ========== PRODUCT CARDS ========== */
    .product-card {
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(10px);
        border-radius: 15px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
        height: 100%;
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    }

    .product-image {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        border-radius: 12px 12px 0 0;
        height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .product-image img {
        max-height: 100%;
        max-width: 100%;
        object-fit: cover;
    }

    .product-body {
        padding: 15px;
    }

    .product-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
        min-height: 40px;
    }

    .product-price {
        font-size: 1.2rem;
        font-weight: 700;
        color: #D2691E;
    }

    .product-stock {
        font-size: 0.8rem;
        padding: 3px 8px;
        border-radius: 10px;
    }

    .in-stock {
        background: #d4edda;
        color: #155724;
    }

    .out-of-stock {
        background: #f8d7da;
        color: #721c24;
    }

    /* ========== PRODUCT ACTIONS ========== */
    .product-actions {
        margin-top: 15px;
    }

    .quantity-control {
        display: flex;
        align-items: center;
        background: #f8f9fa;
        border-radius: 10px;
        padding: 5px;
        border: 1px solid #e9ecef;
        width: 120px;
    }

    .qty-btn {
        width: 32px;
        height: 32px;
        border: none;
        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        color: white;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .qty-btn:hover {
        background: linear-gradient(135deg, #5a6268 0%, #495056 100%);
    }

    .qty-display {
        flex: 1;
        text-align: center;
        font-weight: bold;
        font-size: 1rem;
        min-width: 30px;
    }

    .add-to-cart-btn {
        padding: 10px 15px;
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        color: white;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.3s;
        width: 100%;
    }

    .add-to-cart-btn:hover {
        background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
    }

    .add-to-cart-btn:disabled {
        background: #6c757d;
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
    }

    /* ========== BOTTOM ACTION BAR ========== */
    .bottom-action-bar {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(15px);
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        padding: 10px;
        box-shadow: 0 -2px 20px rgba(0, 0, 0, 0.1);
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        justify-content: space-around;
        max-width: 1200px;
        margin: 0 auto;
    }

    .action-btn {
        flex: 1;
        padding: 12px 15px;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
        font-size: 0.9rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .action-btn i {
        font-size: 1.3rem;
    }

    .view-cart-btn {
        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        color: white;
    }

    .track-order-btn {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
    }

    .checkout-btn {
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        color: white;
    }

    .checkout-btn:disabled {
        background: #6c757d;
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* ========== CART SIDEBAR ========== */
.cart-sidebar {
    position: fixed;
    top: 0;
    right: -400px;
    width: 380px;
    height: 100%;
    background: white;
    box-shadow: -5px 0 25px rgba(0, 0, 0, 0.1);
    z-index: 9999;
    transition: right 0.3s ease;
    overflow-y: auto;
}

.cart-sidebar.open {
    right: 0;
}

.cart-header {
    background: linear-gradient(135deg, #8B4513 0%, #D2691E 100%);
    color: white;
    padding: 20px;
    position: sticky;
    top: 0;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cart-header h4 {
    margin: 0;
    flex: 1;
    font-size: 1.2rem;
    font-weight: 600;
}

.cart-header .btn-close {
    width: 32px;
    height: 32px;
    border: none;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
    margin-left: 15px;
    position: relative;
    opacity: 0.9;
}

.cart-header .btn-close:hover {
    background: rgba(255, 255, 255, 0.3);
    opacity: 1;
    transform: rotate(90deg);
}

.cart-header .btn-close::before,
.cart-header .btn-close::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 2px;
    background: white;
}

.cart-header .btn-close::before {
    transform: rotate(45deg);
}

.cart-header .btn-close::after {
    transform: rotate(-45deg);
}

.cart-items {
    padding: 20px;
    max-height: calc(100vh - 250px);
    overflow-y: auto;
}

.cart-item {
    padding: 15px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
}

.cart-item-info h6 {
    font-size: 1rem;
    font-weight: 600;
    color: #333;
}

.cart-item-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.cart-total {
    padding: 20px;
    background: white;
    border-top: 2px solid #eee;
    position: sticky;
    bottom: 0;
}

.special-request {
    padding: 8px;
    background: #fff8e1;
    border-radius: 6px;
    border-left: 3px solid #ffc107;
}

.special-request small {
    display: block;
    line-height: 1.4;
}

    .products-container {
        padding-bottom: 120px !important;
    }

    /* ========== ADDONS MODAL STYLES ========== */
.addons-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.addons-modal.show {
    display: flex;
    opacity: 1;
}

.addons-content {
    background: white;
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    transform: translateY(20px);
    transition: transform 0.3s ease;
}

.addons-modal.show .addons-content {
    transform: translateY(0);
}

.addons-header {
    background: linear-gradient(135deg, #8B4513 0%, #D2691E 100%);
    color: white;
    padding: 16px 20px;
    border-bottom: 1px solid #e0e0e0;
    flex-shrink: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.addons-header h4 {
    margin: 0;
    flex: 1;
    font-weight: 600;
    font-size: 1.1rem;
}

.btn-close {
    width: 30px;
    height: 30px;
    border: none;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
    margin-left: 15px;
    position: relative;
    color: white;
    opacity: 0.9;
}

.btn-close:hover {
    background: rgba(255, 255, 255, 0.3);
    opacity: 1;
    transform: rotate(90deg);
}

.btn-close::before,
.btn-close::after {
    content: '';
    position: absolute;
    width: 14px;
    height: 2px;
    background: white;
}

.btn-close::before {
    transform: rotate(45deg);
}

.btn-close::after {
    transform: rotate(-45deg);
}

.addons-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    min-height: 0;
}

/* Addon items */
.addon-item {
    display: flex;
    align-items: center;
    padding: 12px;
    margin-bottom: 10px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 2px solid #e9ecef;
    transition: all 0.3s;
    cursor: pointer;
}

.addon-item:hover {
    border-color: #D2691E;
    background: white;
}

.addon-item.selected {
    border-color: #28a745;
    background: rgba(40, 167, 69, 0.05);
}

.addon-info {
    flex: 1;
}

.addon-info h5 {
    margin: 0 0 4px 0;
    font-size: 0.9rem;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
}

.selected-badge {
    background: #28a745;
    color: white;
    font-size: 0.65rem;
    padding: 2px 6px;
    border-radius: 8px;
    font-weight: 600;
}

.addon-description {
    font-size: 0.75rem;
    color: #6c757d;
    margin-bottom: 4px;
    line-height: 1.3;
}

.addon-price-tag {
    font-size: 0.8rem;
    color: #28a745;
    font-weight: 600;
}

.addon-quantity {
    display: flex;
    align-items: center;
    gap: 10px;
}

.addon-qty-control {
    display: flex;
    align-items: center;
    background: white;
    border-radius: 8px;
    padding: 3px;
    border: 1px solid #dee2e6;
    min-width: 90px;
}

.addon-qty-btn {
    width: 26px;
    height: 26px;
    border: none;
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    color: white;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.75rem;
}

.addon-qty-btn:hover {
    background: linear-gradient(135deg, #5a6268 0%, #495056 100%);
}

.addon-qty-display {
    min-width: 35px;
    text-align: center;
    font-weight: bold;
    font-size: 0.9rem;
    color: #495057;
}

/* Special request section */
.special-request-section {
    margin-top: 16px;
    padding: 12px;
    background: #fff8e1;
    border-radius: 10px;
    border-left: 3px solid #ffc107;
}

.special-request-section h5 {
    color: #856404;
    margin-bottom: 8px;
    font-size: 0.9rem;
    font-weight: 600;
}

.special-request-section textarea {
    width: 100%;
    border: 2px solid #ffeaa7;
    border-radius: 8px;
    padding: 10px;
    font-size: 0.85rem;
    resize: vertical;
    min-height: 70px;
    line-height: 1.4;
}

.special-request-section textarea:focus {
    outline: none;
    border-color: #ffc107;
    box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.25);
}

/* Addons footer */
.addons-footer {
    padding: 16px;
    border-top: 1px solid #e9ecef;
    background: white;
    flex-shrink: 0;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
}

.total-display {
    margin-bottom: 14px;
}

.base-price {
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 4px;
}

.total-amount {
    font-size: 1.3rem;
    font-weight: 700;
    color: #28a745;
    margin: 0;
}

.addons-action-buttons {
    display: flex;
    gap: 8px;
}

.addons-action-buttons .btn {
    flex: 1;
    padding: 10px 12px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    font-size: 0.85rem;
}

.btn-cancel {
    background: #f8f9fa;
    border: 2px solid #dee2e6 !important;
    color: #6c757d;
}

.btn-cancel:hover {
    background: #e9ecef;
    border-color: #ced4da !important;
}

.btn-add-customized {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-add-customized:hover {
    background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
}

.btn-add-customized i,
.btn-cancel i {
    font-size: 0.85rem;
}

/* Product preview in modal */
.product-preview {
    display: flex;
    align-items: center;
    padding: 12px;
    background: rgba(139, 69, 19, 0.05);
    border-radius: 10px;
    margin-bottom: 16px;
}

.product-preview-image {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
}

.product-preview-image i {
    font-size: 1.5rem;
}

.product-preview-info {
    flex: 1;
}

.product-preview-info h5 {
    margin: 0 0 4px 0;
    color: #495057;
    font-size: 0.95rem;
    font-weight: 600;
}

.product-preview-price {
    font-weight: 600;
    color: #D2691E;
    font-size: 1rem;
}

/* Empty state */
.empty-addons {
    text-align: center;
    padding: 30px 20px;
    color: #6c757d;
}

.empty-addons i {
    font-size: 2.5rem;
    margin-bottom: 12px;
    opacity: 0.5;
}

.empty-addons p {
    font-size: 0.9rem;
}

/* Loading spinner */
.loading-spinner {
    display: inline-block;
    width: 50px;
    height: 50px;
    border: 3px solid rgba(0,0,0,.1);
    border-radius: 50%;
    border-top-color: #3498db;
    animation: spin 1s ease-in-out infinite;
    margin-bottom: 15px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ========== RESPONSIVE STYLES ========== */
@media (max-width: 768px) {
    .addons-content {
        width: 95%;
        max-height: 85vh;
        border-radius: 15px 15px 0 0;
        align-self: flex-end;
    }
    
    .addons-header {
        padding: 14px 16px;
    }

    .addons-header h4 {
        font-size: 1rem;
    }
    
    .addons-body {
        padding: 14px;
    }
    
    .addons-footer {
        padding: 14px;
    }

    .btn-close {
        width: 28px;
        height: 28px;
    }
    
    .btn-close::before,
    .btn-close::after {
        width: 12px;
    }

    .addon-item {
        padding: 10px;
    }

    .addon-info h5 {
        font-size: 0.85rem;
    }

    .addon-description {
        font-size: 0.7rem;
    }

    .product-preview {
        padding: 10px;
    }

    .product-preview-image {
        width: 45px;
        height: 45px;
    }

    .product-preview-info h5 {
        font-size: 0.9rem;
    }

    .total-amount {
        font-size: 1.2rem;
    }

    .cart-sidebar {
        width: 100%;
        right: -100%;
    }

    .cart-header {
        padding: 16px;
    }

    .cart-header h4 {
        font-size: 1.1rem;
    }

    .cart-header .btn-close {
        width: 30px;
        height: 30px;
    }

    .cart-header .btn-close::before,
    .cart-header .btn-close::after {
        width: 14px;
    }

    .cart-items {
        padding: 16px;
    }

    .cart-total {
        padding: 16px;
    }
}

@media (max-width: 576px) {
    .addon-item {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    
    .addon-quantity {
        justify-content: space-between;
        width: 100%;
    }
    
    .addons-action-buttons {
        flex-direction: column;
    }
    
    .addons-action-buttons .btn {
        width: 100%;
    }
}
/* ========== CAROUSEL STYLES ========== */
.carousel-section {
    background: linear-gradient(135deg, rgba(139, 69, 19, 0.05) 0%, rgba(210, 105, 30, 0.05) 100%);
    padding: 25px 0;
    margin-bottom: 20px;
    border-radius: 15px;
    position: relative;
    overflow: hidden;
    /* display: none !important; Hidden by default, enable when needed */
}

.carousel-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #8B4513, #D2691E, #8B4513);
}

.carousel-header {
    text-align: center;
    margin-bottom: 20px;
}

.carousel-header h3 {
    background: linear-gradient(90deg, #8B4513, #D2691E);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    font-weight: 700;
    font-size: 1.5rem;
    margin-bottom: 5px;
}

.carousel-header p {
    color: #6c757d;
    font-size: 0.9rem;
    margin: 0;
}

/* SIMPLIFIED CAROUSEL FIX */
.bestseller-carousel {
    position: relative;
    width: 100%;
    overflow: hidden;
    padding: 0 50px;
    margin: 0;
}

/* Add to your existing carousel CSS */
.carousel-track {
    display: flex;
    margin: 0;
    padding: 0;
    list-style: none;
    position: relative;
    transition: transform 0.5s ease;
    width: 100000px;
    /* Improve touch experience */
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    touch-action: pan-y pinch-zoom;
}

.carousel-track.dragging {
    cursor: grabbing;
    transition: none;
}

.carousel-item {
    width: 280px;
    min-width: 280px;
    max-width: 280px;
    flex: 0 0 280px;
    margin: 0;
    margin-right: 15px;
    box-sizing: border-box;
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    position: relative;
    float: none;
    display: block;
    /* Improve touch experience */
    cursor: grab;
    -webkit-tap-highlight-color: transparent;
}

.carousel-item:active {
    cursor: grabbing;
}

/* Add visual feedback during drag */
.carousel-track:active {
    cursor: grabbing;
}

/* Improve touch targets on mobile */
@media (max-width: 768px) {
    .carousel-nav {
        width: 44px;
        height: 44px;
        min-width: 44px; /* Minimum touch target size */
        min-height: 44px;
    }
    
    .carousel-item {
        cursor: pointer;
    }
}

/* Prevent text selection during drag */
.carousel-track,
.carousel-item {
    -webkit-user-drag: none;
    -khtml-user-drag: none;
    -moz-user-drag: none;
    -o-user-drag: none;
    user-drag: none;
}

.carousel-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.carousel-item-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #8B4513;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    z-index: 1;
    box-shadow: 0 2px 10px rgba(255, 215, 0, 0.3);
}

.carousel-item-badge.rank-1 {
    background: linear-gradient(135deg, #FFD700, #FFA500);
}

.carousel-item-badge.rank-2 {
    background: linear-gradient(135deg, #C0C0C0, #A8A8A8);
}

.carousel-item-badge.rank-3 {
    background: linear-gradient(135deg, #CD7F32, #B87333);
}

.carousel-item-image {
    height: 180px;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.carousel-item-image img {
    max-height: 100%;
    max-width: 100%;
    object-fit: cover;
}

.carousel-item-image i {
    font-size: 3rem;
    color: #8B4513;
}

.carousel-item-content {
    padding: 15px;
}

.carousel-item-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    min-height: 45px;
    line-height: 1.3;
}

.carousel-item-category {
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.carousel-item-price {
    font-size: 1.3rem;
    font-weight: 700;
    color: #D2691E;
    margin-bottom: 10px;
}

.carousel-item-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid #e9ecef;
}

.carousel-item-sold {
    font-size: 0.75rem;
    color: #ccc91a;
    font-weight: 600;
}

.carousel-item-customizable {
    font-size: 0.75rem;
    color: #D2691E;
}

.carousel-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.95);
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    z-index: 2;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.carousel-nav:hover {
    background: white;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.carousel-nav-prev {
    left: 5px;
}

.carousel-nav-next {
    right: 5px;
}

.carousel-nav i {
    color: #8B4513;
    font-size: 1.2rem;
}

.carousel-indicators {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 15px;
}

.carousel-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #dee2e6;
    cursor: pointer;
    transition: all 0.3s;
}

.carousel-indicator.active {
    background: #D2691E;
    width: 24px;
    border-radius: 4px;
}

@media (max-width: 768px) {
    .bestseller-carousel {
        padding: 0 40px;
    }

    .carousel-item {
        min-width: 240px;
    }

    .carousel-item-image {
        height: 150px;
    }

    .carousel-nav {
        width: 35px;
        height: 35px;
    }

    .carousel-header h3 {
        font-size: 1.3rem;
    }
}

@media (max-width: 576px) {
    .bestseller-carousel {
        padding: 0 35px;
    }

    .carousel-item {
        min-width: 200px;
    }

    .carousel-item-image {
        height: 130px;
    }

    .carousel-nav {
        width: 30px;
        height: 30px;
    }

    .carousel-nav i {
        font-size: 1rem;
    }
}

@keyframes highlightPulse {
    0%, 100% {
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        transform: translateY(0);
    }
    50% {
        box-shadow: 0 15px 40px rgba(210, 105, 30, 0.3);
        transform: translateY(-5px);
    }
}

.highlight-product .product-card {
    animation: highlightPulse 1s ease-in-out;
}
/* Add swipe hint for mobile */
@media (max-width: 768px) {
    .bestseller-carousel::before {
        content: '← Swipe →';
        position: absolute;
        top: -25px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 12px;
        color: #8B4513;
        opacity: 0.7;
        font-weight: 600;
        z-index: 2;
        animation: swipeHint 2s infinite;
    }
}

@keyframes swipeHint {
    0%, 100% {
        opacity: 0.3;
        transform: translateX(-50%) scale(1);
    }
    50% {
        opacity: 0.7;
        transform: translateX(-50%) scale(1.05);
    }
}

/* Quick Add Modal Styles */
.quick-add-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

.quick-add-content {
    background: white;
    border-radius: 15px;
    width: 90%;
    max-width: 400px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.3s ease;
}

.quick-add-header {
    background: linear-gradient(135deg, #8B4513 0%, #D2691E 100%);
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.quick-add-header h5 {
    margin: 0;
    font-size: 1.1rem;
}

.quick-add-body {
    padding: 20px;
}

.product-price-lg {
    font-size: 1.8rem;
    font-weight: 700;
    color: #D2691E;
}

.total-price {
    font-size: 1.2rem;
    font-weight: 600;
    color: #28a745;
}

.quick-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Disabled/out of stock product cards */
.product-card.disabled {
    cursor: not-allowed;
    opacity: 0.6;
    pointer-events: none;
}

.product-card.disabled::after {
    display: none;
}

.product-card.disabled:hover {
    transform: none;
}

/* Loading state */
.product-card.loading {
    position: relative;
    overflow: hidden;
}

.product-card.loading::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    animation: loadingShimmer 1.5s infinite;
    z-index: 1;
}

@keyframes loadingShimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

/* Make product cards look clickable */
.product-card {
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.product-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 15px;
    transition: all 0.3s ease;
    pointer-events: none;
}

.product-card:hover::after {
    box-shadow: 0 0 0 2px rgba(139, 69, 19, 0.3);
}

.product-card:hover {
    transform: translateY(-5px);
}

/* Prevent buttons from looking like they're part of the clickable card */
.product-actions {
    position: relative;
    z-index: 2;
}

.add-to-cart-btn,
.quantity-control {
    position: relative;
    z-index: 3;
}

/* Add click animation */
.product-card.clicked {
    animation: cardClickPulse 0.5s ease;
}

@keyframes cardClickPulse {
    0% {
        transform: scale(1);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }
    50% {
        transform: scale(0.98);
        box-shadow: 0 15px 40px rgba(139, 69, 19, 0.2);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }
}

/* Quick Add Modal Image */
.quick-add-image {
    width: 80px;
    height: 80px;
    border-radius: 10px;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    overflow: hidden;
}

.quick-add-image img {
    max-height: 100%;
    max-width: 100%;
    object-fit: cover;
}

.quick-add-image i {
    font-size: 2.5rem;
    color: #8B4513;
}
/* Carousel item click animation */
.carousel-item {
    cursor: pointer;
    transition: all 0.3s ease;
}

.carousel-item.clicked {
    animation: itemAdded 0.6s ease;
}

@keyframes itemAdded {
    0% {
        transform: scale(1);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }
    50% {
        transform: scale(0.95);
        box-shadow: 0 0 0 5px rgba(40, 167, 69, 0.3);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }
}
/* Responsive product actions for mobile */
@media (max-width: 768px) {
    .product-actions {
        flex-direction: column;
        gap: 8px;
    }
    
    .product-actions .quantity-control {
        width: 100%;
        margin-right: 0 !important;
        justify-content: center;
    }
    
    .product-actions .quantity-control .qty-btn {
        width: 36px;
        height: 36px;
    }
    
    .product-actions .quantity-control .qty-display {
        min-width: 40px;
        font-size: 1.1rem;
    }
    
    .product-actions .add-to-cart-btn {
        width: 100%;
        padding: 12px;
        font-size: 0.95rem;
    }
    
    /* Only apply this to non-customizable products */
    .product-actions:has(.quantity-control) {
        flex-direction: column;
    }
    
    /* For customizable products */
    .product-actions:not(:has(.quantity-control)) .add-to-cart-btn {
        width: 100%;
    }
}
</style>
</head>
<body>
    <!-- Header -->
    <header class="online-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-6">
                    <div class="logo">
                        <img src="../uploads/samara.jpg" height="30px" width="30px" alt="Icon" class="header-icon">
                        <span style="color:#3d2d31">SAMARA</span>
                    </div>
                </div>
                <div class="col-6 text-end text-dark">
                    <div class="cart-summary alert alert-info py-2 px-3 mb-0" onclick="return toggleCart(event)" style="cursor: pointer;">
                        <i class="fas fa-shopping-cart"></i>
                        <span id="cart-count"><?php echo $cartCount; ?></span> items
                        <span class="ms-2">₱<span id="cart-total"><?php echo number_format($cartTotal, 2); ?></span></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

<!-- Best Sellers Carousel -->
<?php if (!empty($bestSellers)): ?>
<section class="carousel-section">
    <div class="container">
        <div class="carousel-header">
            <h3><i class="fas fa-fire me-2"></i>Best Sellers</h3>
            <p>Our most popular items loved by customers</p>
        </div>
        
        <div class="bestseller-carousel" id="bestsellerCarousel">
            <button class="carousel-nav carousel-nav-prev" onclick="carouselPrev()">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <div class="carousel-track" id="carouselTrack">
                <?php foreach ($bestSellers as $index => $product): ?>
                <?php 
                // Fix: Use has_available_addons instead of has_addons
                $hasAddons = $product['has_available_addons'] == 1 || !empty($product['addons_data']);
                $isAvailable = $product['stock'] > 0 || $product['stock'] === null;
                $rank = $index + 1;
                $rankClass = $rank <= 3 ? "rank-{$rank}" : "";
                ?>
                <div class="carousel-item" onclick="handleCarouselItemClick(<?php echo $product['id']; ?>, <?php echo $hasAddons ? 'true' : 'false'; ?>)" style="cursor: pointer;">
                    <?php if ($rank <= 3): ?>
                    <div class="carousel-item-badge <?php echo $rankClass; ?>">
                        <i class="fas fa-trophy"></i> #<?php echo $rank; ?>
                    </div>
                    <?php else: ?>
                    <div class="carousel-item-badge">
                        #<?php echo $rank; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="carousel-item-image">
                        <?php if(empty($product['image_url'])): ?>
                            <!-- <i class="fas fa-<?php echo getProductIcon($product['name']); ?>"></i>  -->
                            <img src="../uploads/samara.jpg" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <img src="../<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="carousel-item-content">
                        <h5 class="carousel-item-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <div class="carousel-item-category">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </div>
                        <div class="carousel-item-price">
                            ₱<?php echo number_format($product['price'], 2); ?>
                        </div>
                        <div class="carousel-item-stats">
                            <span class="carousel-item-sold">
                                <!-- <i class="fas fa-shopping-bag"></i> -->
                                <i class="fas fa-star"></i>
                                <!-- <?php echo abs($product['total_sold']); ?> sold -->
                                Popular
                            </span>
                            <?php if ($hasAddons): ?>
                            <span class="carousel-item-customizable">
                                <i class="fas fa-cog"></i> Customizable
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button class="carousel-nav carousel-nav-next" onclick="carouselNext()">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <div class="carousel-indicators" id="carouselIndicators"></div>
    </div>
</section>
<?php else: ?>
<!-- Fallback message or hide section -->
<style>
.carousel-section {
    display: none !important;
}
</style>
<?php endif; ?>
<!-- END OF CAROUSEL BEST SELLERS -->

<!-- Category Navigation -->
    <nav class="category-nav py-2">
        <div class="container">
            <div class="category-tabs d-flex flex-nowrap overflow-auto" id="categoryTabs" style="gap: 3px; font-size: 10px;">
                <button class="category-tab active px-3 py-2 rounded" data-category="all" style="white-space: nowrap;">
                    <i class="fas fa-th-large me-1"></i> All
                </button>
                <?php foreach ($categories as $category): ?>
                <button class="category-tab px-3 py-2 rounded" 
                        data-category="<?php echo $category['id']; ?>"
                        style="white-space: nowrap; border-left: 4px solid <?php echo $category['color_code']; ?>">
                    <i class="fas fa-<?php echo getCategoryIcon($category['name']); ?> me-1"></i>
                    <?php echo htmlspecialchars($category['name']); ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>
    

    <!-- Products Grid -->
    <main class="container products-container py-3">
        <div id="productsGrid">
            <?php foreach ($categories as $category): ?>
            <?php if (!empty($productsByCategory[$category['id']])): ?>
            <div class="category-section mb-4" data-category="<?php echo $category['id']; ?>">
                <h4 class="category-title">
                    <?php echo htmlspecialchars($category['name']); ?>
                </h4>
                
                <div class="row g-3">
                    <?php foreach ($productsByCategory[$category['id']] as $product): ?>
                    <?php 
                    // Use consistent field naming
                    $hasAddons = ($product['has_available_addons'] ?? $product['has_addons'] ?? false) || !empty($product['addons_data']);
                    $isAvailable = $product['stock'] > 0 || $product['stock'] === null;
                    ?>
                    <div class="col-6 col-md-4 col-lg-3 product-item" 
     data-category="<?php echo $product['category_id']; ?>"
     data-id="<?php echo $product['id']; ?>">
    
    <div class="product-card h-100 d-flex flex-column" 
     onclick="handleProductCardClick(<?php echo $product['id']; ?>, <?php echo $hasAddons ? 'true' : 'false'; ?>, event)"
     tabindex="0"
     role="button"
     aria-label="Select <?php echo htmlspecialchars($product['name']); ?>, Price: ₱<?php echo number_format($product['price'], 2); ?><?php echo $hasAddons ? ', Customizable' : ''; ?>">
        
        <div class="product-image">
            <?php if(empty($product['image_url'])): ?>
            <img src="../uploads/samara.jpg" alt="<?php echo htmlspecialchars($product['name']); ?>">
            <?php else: ?>
                <img src="../<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-fluid">
            <?php endif; ?>
        </div>
        
        <div class="product-body flex-grow-1 d-flex flex-column">
            <h5 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
            
            <?php if (!empty($product['description'])): ?>
            <p class="product-description small text-muted mb-2">
                <?php echo htmlspecialchars($product['description']); ?>
            </p>
            <?php endif; ?>
            
            <div class="mt-auto">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="product-price">
                        ₱<?php echo number_format($product['price'], 2); ?>
                    </span>
                    
                    <?php if ($product['stock'] !== null): ?>
                    <span class="product-stock <?php echo $isAvailable ? 'in-stock' : 'out-of-stock'; ?>">
                        <?php echo $isAvailable ? 'In Stock' : 'Out of Stock'; ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($hasAddons): ?>
                <div class="mb-2">
                    <small class="text-muted">
                        <i class="fas fa-plus-circle"></i> Customizable
                    </small>
                </div>
                <?php endif; ?>
                
                <div class="product-actions d-flex">
                    <?php if ($hasAddons): ?>
                    <button class="add-to-cart-btn" 
                            onclick="showAddonsModal(<?php echo $product['id']; ?>)"
                            <?php echo !$isAvailable ? 'disabled' : ''; ?>>
                        <i class="fas fa-cog me-1"></i> Customize
                    </button>
                    <?php else: ?>
                    <div class="quantity-control me-2">
                        <button class="qty-btn" onclick="changeQuantity(<?php echo $product['id']; ?>, -1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <span class="qty-display" id="qty-<?php echo $product['id']; ?>">1</span>
                        <button class="qty-btn" onclick="changeQuantity(<?php echo $product['id']; ?>, 1)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <button class="add-to-cart-btn" 
                            onclick="addProductToCart(<?php echo $product['id']; ?>)"
                            <?php echo !$isAvailable ? 'disabled' : ''; ?>>
                        <i class="fas fa-cart-plus me-1"></i> Add
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($products)): ?>
        <div class="text-center py-5">
            <i class="fas fa-utensils fa-4x text-muted mb-3"></i>
            <h4 class="text-muted">Menu Coming Soon</h4>
            <p class="text-muted">We're preparing our delicious offerings!</p>
        </div>
        <?php endif; ?>
    </main>

    <!-- Addons Modal -->
    <div class="addons-modal" id="addonsModal">
        <div class="addons-content">
            <div class="addons-header">
                <h4 id="addonProductName">Customize Your Order</h4>
                <button class="btn-close" onclick="hideAddonsModal()"></button>
            </div>
            <div class="addons-body" id="addonsList">
                <!-- Addons will be loaded here dynamically -->
            </div>
            <div class="addons-footer">
                <div class="total-display">
                    <div class="base-price">Base price: ₱<span id="basePrice">0.00</span></div>
                    <h4 class="total-amount">₱<span id="addonTotalPrice">0.00</span></h4>
                </div>
                <div class="addons-action-buttons">
                    <button class="btn btn-cancel" onclick="hideAddonsModal()">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <button class="btn btn-add-customized" onclick="addCustomizedToCart()">
                        <i class="fas fa-check me-2"></i> Add to Cart
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart Sidebar -->
    <div class="cart-sidebar" id="cartSidebar">
        <div class="cart-header">
            <h4 class="mb-0">
                <i class="fas fa-shopping-cart me-2"></i>
                Your Order
            </h4>
            <button class="btn-close btn-close-white" onclick="toggleCart()"></button>
        </div>
        
        <div class="cart-items">
            <?php if (empty($cartItems)): ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Your cart is empty</h5>
                <p class="text-muted">Add some delicious items!</p>
            </div>
            <?php else: ?>
            <?php foreach ($cartItems as $item): ?>
            <div class="cart-item" data-id="<?php echo $item['product_id']; ?>">
                <div class="cart-item-info">
                    <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                    <p class="text-muted mb-1">₱<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?></p>
                    <?php if (!empty($item['addons'])): ?>
                    <small class="text-success">
                        <i class="fas fa-plus-circle"></i>
                        <?php echo count($item['addons']); ?> addon(s)
                    </small>
                    <?php endif; ?>
                    <?php if (!empty($item['special_request'])): ?>
                    <div class="special-request mt-1">
                        <small class="text-warning">
                            <i class="fas fa-sticky-note"></i>
                            <?php echo htmlspecialchars($item['special_request']); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="cart-item-actions">
                    <button class="btn btn-sm btn-outline-secondary" onclick="updateCartItem(<?php echo $item['product_id']; ?>, -1)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="mx-2"><?php echo $item['quantity']; ?></span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="updateCartItem(<?php echo $item['product_id']; ?>, 1)">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="btn btn-sm btn-danger ms-2" onclick="removeCartItem(<?php echo $item['product_id']; ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="cart-total">
            <div class="row mb-3">
                <div class="col-6">
                    <h5 class="mb-0">Total:</h5>
                </div>
                <div class="col-6 text-end">
                    <h4 class="mb-0 text-primary">₱<span id="sidebar-cart-total"><?php echo number_format($cartTotal, 2); ?></span></h4>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button class="btn btn-success btn-lg" onclick="proceedToCheckout()" id="sidebar-checkout-btn">
                    <i class="fas fa-credit-card me-2"></i> Proceed to Checkout
                </button>
                <button class="btn btn-outline-secondary" onclick="toggleCart()">
                    Continue Shopping
                </button>
            </div>
        </div>
    </div>

    <!-- Bottom Action Bar (Mobile) -->
    <div class="bottom-action-bar">
        <div class="action-buttons">
            <button class="action-btn view-cart-btn" onclick="return toggleCart(event)">
                <i class="fas fa-shopping-cart"></i>
                <span>Cart (<span id="mobile-cart-count"><?php echo $cartCount; ?></span>)</span>
            </button>
            <button class="action-btn track-order-btn" onclick="trackOrder()">
                <i class="fas fa-map-pin"></i>
                <span>Track Order</span>
            </button>
            <button class="action-btn checkout-btn" onclick="proceedToCheckout()" id="bottom-checkout-btn">
                <i class="fas fa-credit-card"></i>
                <span>Checkout</span>
            </button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    // ========== GLOBAL VARIABLES ==========
    let addonsData = [];
    let currentProduct = null;
    let specialRequest = '';
    let currentProductId = null;
    let selectedAddons = {};
    let cartRefreshTimeout = null;
    let isRefreshing = false;
    
    
    // ========== UTILITY FUNCTIONS ==========
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize toastr
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "timeOut": "3000"
    };
    
 // ========== CAROUSEL FUNCTIONS ==========
let currentCarouselIndex = 0;
let carouselAutoplayInterval = null;
let carouselItemsPerView = 3;
let carouselItems = [];
let carouselTrack = null;
let carouselContainer = null;

// Touch/swipe variables
let touchStartX = 0;
let touchEndX = 0;
let isDragging = false;
let dragStartX = 0;
let dragCurrentX = 0;

function initCarousel() {
    console.log('Initializing carousel...');
    
    carouselContainer = document.getElementById('bestsellerCarousel');
    carouselTrack = document.getElementById('carouselTrack');
    
    if (!carouselTrack || !carouselContainer) {
        console.error('Carousel elements not found!');
        return;
    }
    
    carouselItems = Array.from(carouselTrack.querySelectorAll('.carousel-item'));
    if (carouselItems.length === 0) {
        console.error('No carousel items found!');
        return;
    }
    
    console.log(`Found ${carouselItems.length} carousel items`);
    
    updateCarouselItemsPerView();
    
    // Create indicators
    const indicators = document.getElementById('carouselIndicators');
    const totalPages = Math.ceil(carouselItems.length / carouselItemsPerView);
    
    if (indicators) {
        indicators.innerHTML = '';
        for (let i = 0; i < totalPages; i++) {
            const indicator = document.createElement('div');
            indicator.className = 'carousel-indicator';
            if (i === 0) indicator.classList.add('active');
            indicator.onclick = () => goToCarouselPage(i);
            indicators.appendChild(indicator);
        }
    }
    
    // Set initial position
    currentCarouselIndex = 0;
    updateCarousel();
    
    // Add touch/swipe support
    addTouchSupport();
    
    // Start autoplay
    startCarouselAutoplay();
    
    // Pause on hover
    carouselContainer.addEventListener('mouseenter', stopCarouselAutoplay);
    carouselContainer.addEventListener('mouseleave', startCarouselAutoplay);
    
    console.log('Carousel initialized successfully');
}

function addTouchSupport() {
    if (!carouselTrack) return;
    
    // Touch events for mobile
    carouselTrack.addEventListener('touchstart', handleTouchStart, { passive: true });
    carouselTrack.addEventListener('touchmove', handleTouchMove, { passive: false });
    carouselTrack.addEventListener('touchend', handleTouchEnd);
    
    // Mouse events for desktop dragging
    carouselTrack.addEventListener('mousedown', handleMouseDown);
    carouselTrack.addEventListener('mousemove', handleMouseMove);
    carouselTrack.addEventListener('mouseup', handleMouseUp);
    carouselTrack.addEventListener('mouseleave', handleMouseLeave);
    
    // Prevent default behavior for arrow buttons
    const prevBtn = document.querySelector('.carousel-nav-prev');
    const nextBtn = document.querySelector('.carousel-nav-next');
    
    if (prevBtn) {
        prevBtn.addEventListener('touchstart', (e) => e.stopPropagation());
        prevBtn.addEventListener('mousedown', (e) => e.stopPropagation());
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('touchstart', (e) => e.stopPropagation());
        nextBtn.addEventListener('mousedown', (e) => e.stopPropagation());
    }
}

function handleTouchStart(e) {
    touchStartX = e.touches[0].clientX;
    touchEndX = touchStartX;
    stopCarouselAutoplay();
}

function handleTouchMove(e) {
    if (!touchStartX) return;
    
    touchEndX = e.touches[0].clientX;
    e.preventDefault(); // Prevent scrolling while swiping
    
    const diff = touchStartX - touchEndX;
    applyDragTransform(diff);
}

function handleTouchEnd() {
    if (!touchStartX) return;
    
    const diff = touchStartX - touchEndX;
    const threshold = 50; // Minimum swipe distance
    
    if (Math.abs(diff) > threshold) {
        if (diff > 0) {
            // Swiped left - go to next
            carouselNext();
        } else {
            // Swiped right - go to previous
            carouselPrev();
        }
    } else {
        // Reset position if not a significant swipe
        updateCarousel();
    }
    
    // Reset
    touchStartX = 0;
    touchEndX = 0;
    
    // Restart autoplay
    setTimeout(startCarouselAutoplay, 3000);
}

function handleMouseDown(e) {
    isDragging = true;
    dragStartX = e.clientX;
    dragCurrentX = dragStartX;
    stopCarouselAutoplay();
    
    carouselTrack.style.cursor = 'grabbing';
}

function handleMouseMove(e) {
    if (!isDragging) return;
    
    dragCurrentX = e.clientX;
    const diff = dragStartX - dragCurrentX;
    
    applyDragTransform(diff);
}

function handleMouseUp() {
    if (!isDragging) return;
    
    const diff = dragStartX - dragCurrentX;
    const threshold = 50; // Minimum drag distance
    
    if (Math.abs(diff) > threshold) {
        if (diff > 0) {
            // Dragged left - go to next
            carouselNext();
        } else {
            // Dragged right - go to previous
            carouselPrev();
        }
    } else {
        // Reset position if not a significant drag
        updateCarousel();
    }
    
    // Reset
    isDragging = false;
    dragStartX = 0;
    dragCurrentX = 0;
    carouselTrack.style.cursor = 'grab';
    
    // Restart autoplay
    setTimeout(startCarouselAutoplay, 3000);
}

function handleMouseLeave() {
    if (isDragging) {
        handleMouseUp();
    }
}

function applyDragTransform(diff) {
    if (!carouselTrack || carouselItems.length === 0) return;
    
    const itemWidth = carouselItems[0].offsetWidth;
    const gap = 15;
    const baseOffset = currentCarouselIndex * (itemWidth + gap) * carouselItemsPerView;
    
    // Apply transform with drag offset
    carouselTrack.style.transform = `translateX(calc(-${baseOffset}px - ${diff}px))`;
    carouselTrack.style.transition = 'none';
}

function updateCarouselItemsPerView() {
    const width = window.innerWidth;
    if (width < 576) {
        carouselItemsPerView = 1;
    } else if (width < 768) {
        carouselItemsPerView = 2;
    } else if (width < 992) {
        carouselItemsPerView = 3;
    } else {
        carouselItemsPerView = 4;
    }
    
    console.log(`Carousel items per view: ${carouselItemsPerView} (screen width: ${width}px)`);
}

function updateCarousel() {
    if (!carouselTrack || carouselItems.length === 0) return;
    
    const itemWidth = carouselItems[0].offsetWidth;
    const gap = 15;
    const totalPages = Math.ceil(carouselItems.length / carouselItemsPerView);
    
    // Ensure currentCarouselIndex stays within bounds
    if (currentCarouselIndex >= totalPages) {
        currentCarouselIndex = totalPages - 1;
    }
    if (currentCarouselIndex < 0) {
        currentCarouselIndex = 0;
    }
    
    const offset = currentCarouselIndex * (itemWidth + gap) * carouselItemsPerView;
    
    // Apply transition
    carouselTrack.style.transition = 'transform 0.5s ease';
    carouselTrack.style.transform = `translateX(-${offset}px)`;
    
    // Update indicators
    updateIndicators();
}

function updateIndicators() {
    const indicators = document.querySelectorAll('.carousel-indicator');
    if (indicators.length === 0) return;
    
    const totalPages = Math.ceil(carouselItems.length / carouselItemsPerView);
    const currentPage = Math.min(currentCarouselIndex, totalPages - 1);
    
    indicators.forEach((indicator, index) => {
        indicator.classList.toggle('active', index === currentPage);
    });
}

function carouselNext() {
    console.log('Carousel next called');
    
    const totalPages = Math.ceil(carouselItems.length / carouselItemsPerView);
    
    if (currentCarouselIndex < totalPages - 1) {
        currentCarouselIndex++;
    } else {
        currentCarouselIndex = 0; // Loop back to start
    }
    
    updateCarousel();
}

function carouselPrev() {
    console.log('Carousel prev called');
    
    const totalPages = Math.ceil(carouselItems.length / carouselItemsPerView);
    
    if (currentCarouselIndex > 0) {
        currentCarouselIndex--;
    } else {
        currentCarouselIndex = totalPages - 1; // Loop to end
    }
    
    updateCarousel();
}

function goToCarouselPage(pageIndex) {
    const totalPages = Math.ceil(carouselItems.length / carouselItemsPerView);
    
    if (pageIndex < 0) pageIndex = 0;
    if (pageIndex >= totalPages) pageIndex = totalPages - 1;
    
    currentCarouselIndex = pageIndex;
    updateCarousel();
}

function startCarouselAutoplay() {
    stopCarouselAutoplay();
    carouselAutoplayInterval = setInterval(carouselNext, 4000);
}

function stopCarouselAutoplay() {
    if (carouselAutoplayInterval) {
        clearInterval(carouselAutoplayInterval);
        carouselAutoplayInterval = null;
    }
}

// ========== BEST SOLUTION: QUICK ADD MODAL ==========
function handleCarouselItemClick(productId, hasAddons) {
    // Don't trigger click if user was dragging/swiping
    if (isDragging || Math.abs(touchStartX - touchEndX) > 10) {
        return;
    }
    
    if (hasAddons) {
        showAddonsModal(productId);
    } else {
        showQuickAddModal(productId);
    }
}

// Update the showQuickAddModal to accept optional product data
function showQuickAddModal(productId, productData = null) {
    if (productData) {
        // Use provided product data
        displayQuickAddModal(productData, productId);
    } else {
        // Fetch product data
        fetch('api/get-product-addons.php?product_id=' + productId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.product) {
                    displayQuickAddModal(data.product, productId);
                } else {
                    toastr.error('Failed to load product details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastr.error('Network error. Please try again.');
            });
    }
}
function displayQuickAddModal(product, productId) {
    // Get product image from DOM if available
    const productElement = document.querySelector(`[data-id="${productId}"]`);
    let imageHtml = `<i class="fas fa-coffee fa-2x" style="color: #8B4513;"></i>`;
    
    if (productElement) {
        const imgElement = productElement.querySelector('.product-image img, .carousel-item-image img');
        if (imgElement) {
            imageHtml = `<img src="${imgElement.src}" alt="${escapeHtml(product.name)}" style="max-height: 80px; max-width: 80px;">`;
        }
    }
    
    // Create modal
    const quickAddHtml = `
        <div class="quick-add-modal">
            <div class="quick-add-content">
                <div class="quick-add-header">
                    <h5>${escapeHtml(product.name)}</h5>
                    <button class="btn-close" onclick="closeQuickAddModal()"></button>
                </div>
                <div class="quick-add-body">
                    <div class="text-center mb-4">
                        <div class="quick-add-image mb-3">
                            ${imageHtml}
                        </div>
                        <div class="product-price-lg mb-3">₱${parseFloat(product.price).toFixed(2)}</div>
                        <div class="d-flex justify-content-center align-items-center mb-3">
                            <button class="qty-btn" onclick="updateQuickAddQty('minus')">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="mx-4" id="quickQty" style="font-size: 1.5rem; font-weight: bold;">1</span>
                            <button class="qty-btn" onclick="updateQuickAddQty('plus')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="total-price mb-4" id="quickTotal">Total: ₱${parseFloat(product.price).toFixed(2)}</div>
                    </div>
                    <div class="quick-actions">
                        <button class="btn btn-secondary btn-sm" onclick="closeQuickAddModal()">
                            Cancel
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="confirmQuickAdd(${productId}, '${escapeHtml(product.name)}')">
                            <i class="fas fa-cart-plus me-1"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add to body
    const existingModal = document.querySelector('.quick-add-modal');
    if (existingModal) existingModal.remove();
    
    document.body.insertAdjacentHTML('beforeend', quickAddHtml);
    
    // Store product price for calculations
    window.quickAddPrice = parseFloat(product.price);
    window.quickAddQty = 1;
}

function updateQuickAddQty(action) {
    if (action === 'minus' && window.quickAddQty > 1) {
        window.quickAddQty--;
    } else if (action === 'plus' && window.quickAddQty < 99) {
        window.quickAddQty++;
    }
    
    document.getElementById('quickQty').textContent = window.quickAddQty;
    const total = window.quickAddPrice * window.quickAddQty;
    document.getElementById('quickTotal').textContent = `Total: ₱${total.toFixed(2)}`;
}

function confirmQuickAdd(productId, productName) {
    addToCart(productId, window.quickAddQty, [], '');
    
    const message = window.quickAddQty > 1 ? 
        `Added ${window.quickAddQty} x ${productName} to cart!` : 
        `Added ${productName} to cart!`;
    
    toastr.success(message);
    closeQuickAddModal();
}

function closeQuickAddModal() {
    const modal = document.querySelector('.quick-add-modal');
    if (modal) modal.remove();
    window.quickAddPrice = null;
    window.quickAddQty = null;
}
    
// ========== PRODUCT CARD CLICK HANDLER ==========
function handleProductCardClick(productId, hasAddons, event) {
    // Prevent click from bubbling to parent elements
    event.stopPropagation();
    
    // Don't trigger if click was on buttons (Customize/Add)
    if (event.target.closest('.add-to-cart-btn') || 
        event.target.closest('.qty-btn') ||
        event.target.closest('.quantity-control')) {
        return;
    }
    
    if (hasAddons) {
        showAddonsModal(productId);
    } else {
        showQuickAddModal(productId);
    }
}

    // ========== MODAL FUNCTIONS ==========
    function showAddonsModal(productId) {
        const modal = document.getElementById('addonsModal');
        
        document.getElementById('addonsList').innerHTML = `
            <div class="text-center py-5">
                <div class="loading-spinner"></div>
                <p class="mt-3">Loading customizations...</p>
            </div>
        `;
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        fetch('api/get-product-addons.php?product_id=' + productId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.product) {
                    currentProductId = productId;
                    currentProduct = data.product;
                    selectedAddons = {};
                    specialRequest = '';
                    addonsData = data.addons || [];
                    
                    document.getElementById('addonProductName').textContent = currentProduct.name;
                    document.getElementById('basePrice').textContent = parseFloat(currentProduct.price).toFixed(2);
                    
                    let addonsHtml = '';
                    
                    if (addonsData.length === 0) {
                        addonsHtml = `
                            <div class="product-preview">
                                <div class="product-preview-image">
                                    <i class="fas fa-coffee fa-2x" style="color: #8B4513;"></i>
                                </div>
                                <div class="product-preview-info">
                                    <h5>${escapeHtml(currentProduct.name)}</h5>
                                    <div class="product-preview-price">₱${parseFloat(currentProduct.price).toFixed(2)}</div>
                                </div>
                            </div>
                            <h5 class="mb-3">Select Quantity:</h5>
                            <div class="text-center py-4">
                                <div class="d-flex justify-content-center align-items-center mb-4">
                                    <button class="qty-btn" style="width: 50px; height: 50px;" onclick="updateSimpleQuantity(-1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <span class="mx-4" id="simpleQtyDisplay" style="font-size: 1.5rem; font-weight: bold;">1</span>
                                    <button class="qty-btn" style="width: 50px; height: 50px;" onclick="updateSimpleQuantity(1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="special-request-section">
                                <h5><i class="fas fa-sticky-note me-2"></i>Special Instructions</h5>
                                <textarea class="form-control" id="specialRequestText" rows="3" 
                                          placeholder="Any special requests? (e.g., extra sauce, no onions, less ice, etc.)"></textarea>
                            </div>
                        `;
                        window.simpleQuantity = 1;
                    } else {
                        addonsHtml += `
                            <div class="product-preview">
                                <div class="product-preview-image">
                                    <i class="fas fa-coffee fa-2x" style="color: #8B4513;"></i>
                                </div>
                                <div class="product-preview-info">
                                    <h5>${escapeHtml(currentProduct.name)}</h5>
                                    <div class="product-preview-price">₱${parseFloat(currentProduct.price).toFixed(2)}</div>
                                </div>
                            </div>
                            <h5 class="mb-3">Customize Your Order:</h5>
                        `;
                        
                        addonsData.forEach(addon => {
                            const addonPrice = parseFloat(addon.price).toFixed(2);
                            const isGlobal = addon.is_global == 1 ? ' <span class="badge bg-info">GLOBAL</span>' : '';
                            
                            addonsHtml += `
                                <div class="addon-item" data-addon-id="${addon.id}" onclick="toggleAddonSelection(${addon.id})">
                                    <div class="addon-info">
                                        <h5>
                                            ${escapeHtml(addon.name)}
                                            ${isGlobal}
                                            <span class="selected-badge" id="badge-${addon.id}" style="display: none;">Selected</span>
                                        </h5>
                                        ${addon.description ? `<div class="addon-description">${escapeHtml(addon.description)}</div>` : ''}
                                        <div class="addon-price-tag">+₱${addonPrice} each</div>
                                    </div>
                                    <div class="addon-quantity">
                                        <span class="addon-price-tag" id="addon-price-${addon.id}">₱0.00</span>
                                        <div class="addon-qty-control" onclick="event.stopPropagation();">
                                            <button class="addon-qty-btn" onclick="updateAddonQuantity(${addon.id}, -1, event)">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <span class="addon-qty-display" id="addon-qty-${addon.id}">0</span>
                                            <button class="addon-qty-btn" onclick="updateAddonQuantity(${addon.id}, 1, event)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        addonsHtml += `
                            <div class="special-request-section">
                                <h5><i class="fas fa-sticky-note me-2"></i>Special Instructions</h5>
                                <textarea class="form-control" id="specialRequestText" rows="3" 
                                          placeholder="Any special requests? (e.g., extra sauce, no onions, less ice, etc.)"></textarea>
                            </div>
                        `;
                    }
                    
                    document.getElementById('addonsList').innerHTML = addonsHtml;
                    updateAddonTotal();
                    
                } else {
                    throw new Error(data.message || 'Failed to load product details');
                }
            })
            .catch(error => {
                console.error('Error loading addons:', error);
                document.getElementById('addonsList').innerHTML = `
                    <div class="text-center py-5 text-danger">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                        <h5>Error Loading Customizations</h5>
                        <p>${escapeHtml(error.message)}</p>
                        <button class="btn btn-outline-secondary mt-3" onclick="hideAddonsModal()">
                            Close
                        </button>
                    </div>
                `;
                toastr.error('Failed to load customizations');
            });
    }
    
    function hideAddonsModal() {
        const modal = document.getElementById('addonsModal');
        modal.classList.remove('show');
        document.body.style.overflow = '';
        currentProductId = null;
        currentProduct = null;
        selectedAddons = {};
        specialRequest = '';
        addonsData = [];
    }
    
    function toggleAddonSelection(addonId) {
        const addonItem = document.querySelector(`[data-addon-id="${addonId}"]`);
        const badge = document.getElementById(`badge-${addonId}`);
        const qtyDisplay = document.getElementById(`addon-qty-${addonId}`);
        
        if (!selectedAddons[addonId] || selectedAddons[addonId] === 0) {
            selectedAddons[addonId] = 1;
            addonItem.classList.add('selected');
            badge.style.display = 'inline-block';
            qtyDisplay.textContent = '1';
        } else {
            selectedAddons[addonId] = 0;
            addonItem.classList.remove('selected');
            badge.style.display = 'none';
            qtyDisplay.textContent = '0';
        }
        updateAddonItemPrice(addonId);
        updateAddonTotal();
    }
    
    function updateAddonQuantity(addonId, change, event) {
        if (event) event.stopPropagation();
        
        const currentQty = selectedAddons[addonId] || 0;
        const addonInfo = addonsData.find(a => a.id == addonId);
        const maxQty = addonInfo?.max_quantity || 5;
        
        let newQty = currentQty + change;
        newQty = Math.max(0, Math.min(maxQty, newQty));
        
        selectedAddons[addonId] = newQty;
        
        const qtyDisplay = document.getElementById(`addon-qty-${addonId}`);
        const badge = document.getElementById(`badge-${addonId}`);
        const addonItem = document.querySelector(`[data-addon-id="${addonId}"]`);
        
        qtyDisplay.textContent = newQty;
        
        if (newQty > 0) {
            addonItem.classList.add('selected');
            badge.style.display = 'inline-block';
        } else {
            addonItem.classList.remove('selected');
            badge.style.display = 'none';
        }
        
        updateAddonItemPrice(addonId);
        updateAddonTotal();
    }
    
    function updateAddonItemPrice(addonId) {
        const addonInfo = addonsData.find(a => a.id == addonId);
        if (!addonInfo) return;
        
        const qty = selectedAddons[addonId] || 0;
        const price = parseFloat(addonInfo.price);
        const total = price * qty;
        
        const priceElement = document.getElementById(`addon-price-${addonId}`);
        priceElement.textContent = `₱${total.toFixed(2)}`;
    }
    
    function updateAddonTotal() {
        if (!currentProduct) return;
        
        let total = parseFloat(currentProduct.price);
        
        if (addonsData.length > 0) {
            Object.keys(selectedAddons).forEach(addonId => {
                const qty = selectedAddons[addonId] || 0;
                if (qty > 0) {
                    const addonInfo = addonsData.find(a => a.id == addonId);
                    if (addonInfo) {
                        total += (parseFloat(addonInfo.price) * qty);
                    }
                }
            });
        } else {
            total *= (window.simpleQuantity || 1);
        }
        
        document.getElementById('addonTotalPrice').textContent = total.toFixed(2);
    }
    
    function updateSimpleQuantity(change) {
        if (!window.simpleQuantity) window.simpleQuantity = 1;
        
        let newQty = window.simpleQuantity + change;
        newQty = Math.max(1, newQty);
        newQty = Math.min(99, newQty);
        
        window.simpleQuantity = newQty;
        document.getElementById('simpleQtyDisplay').textContent = newQty;
        updateAddonTotal();
    }
    
    function addCustomizedToCart() {
        const specialRequestTextarea = document.getElementById('specialRequestText');
        const specialRequest = specialRequestTextarea ? specialRequestTextarea.value : '';
        
        let quantity = 1;
        let addons = [];
        
        if (addonsData.length === 0) {
            quantity = window.simpleQuantity || 1;
        } else {
            Object.keys(selectedAddons).forEach(addonId => {
                const qty = selectedAddons[addonId] || 0;
                if (qty > 0) {
                    const addonInfo = addonsData.find(a => a.id == addonId);
                    if (addonInfo) {
                        addons.push({
                            addon_id: addonId,
                            name: addonInfo.name,
                            price: parseFloat(addonInfo.price),
                            quantity: qty
                        });
                    }
                }
            });
        }
        
        addToCart(currentProductId, quantity, addons, specialRequest);
        
        let message = `Added ${currentProduct.name} to cart!`;
        if (quantity > 1) {
            message = `Added ${quantity} x ${currentProduct.name} to cart!`;
        }
        if (addons.length > 0) {
            const totalAddons = addons.reduce((sum, addon) => sum + addon.quantity, 0);
            message += ` (+${totalAddons} customization${totalAddons > 1 ? 's' : ''})`;
        }
        
        toastr.success(message);
        hideAddonsModal();
    }
    
    // ========== CART FUNCTIONS ==========
    function addToCart(productId, quantity = 1, addons = [], specialRequest = '') {
        fetch('api/add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity,
                addons: addons,
                special_request: specialRequest
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartSummary(data);
                const qtyElement = document.getElementById(`qty-${productId}`);
                if (qtyElement) {
                    qtyElement.textContent = '0';
                }
                if (document.getElementById('cartSidebar').classList.contains('open')) {
                    refreshCartItems();
                }
            } else {
                toastr.error(data.message || 'Failed to add to cart');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastr.error('Network error. Please try again.');
        });
    }
    
    function addProductToCart(productId) {
        const qtyElement = document.getElementById('qty-' + productId);
        const quantity = parseInt(qtyElement.textContent) || 1;
        
        if (quantity <= 0) {
            toastr.warning('Please select at least 1 quantity');
            return;
        }
        
        addToCart(productId, quantity);
        qtyElement.textContent = '0';
    }
    
    function changeQuantity(productId, change) {
        const element = document.getElementById('qty-' + productId);
        let currentQty = parseInt(element.textContent) || 0;
        let newQty = Math.max(0, currentQty + change);
        element.textContent = newQty;
    }
    
    function toggleCart(event) {
    // Stop event propagation
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    const sidebar = document.getElementById('cartSidebar');
    const isOpening = !sidebar.classList.contains('open');
    
    sidebar.classList.toggle('open');
    
    if (isOpening) {
        refreshCartItems();
    } else {
        if (cartRefreshTimeout) {
            clearTimeout(cartRefreshTimeout);
            cartRefreshTimeout = null;
        }
    }
    
    return false;
}
    
    function updateCartSummary(data) {
        const total = data.cartTotal || 0;
        const count = data.cartCount || 0;
        
        document.getElementById('cart-count').textContent = count;
        document.getElementById('mobile-cart-count').textContent = count;
        document.getElementById('cart-total').textContent = total.toFixed(2);
        document.getElementById('sidebar-cart-total').textContent = total.toFixed(2);
        
        updateCheckoutButtons(count);
    }
    
    function updateCheckoutButtons(cartCount) {
        const isCartEmpty = cartCount === 0;
        const sidebarBtn = document.getElementById('sidebar-checkout-btn');
        const bottomBtn = document.getElementById('bottom-checkout-btn');
        
        if (sidebarBtn) sidebarBtn.disabled = isCartEmpty;
        if (bottomBtn) bottomBtn.disabled = isCartEmpty;
    }
    
    function refreshCartItems() {
        if (isRefreshing) return;
        
        if (cartRefreshTimeout) {
            clearTimeout(cartRefreshTimeout);
        }
        
        cartRefreshTimeout = setTimeout(function() {
            isRefreshing = true;
            
            fetch('api/get-cart.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateCartDisplay(data.cart);
                        updateCartSummary(data);
                    }
                })
                .catch(error => {
                    console.error('Error refreshing cart:', error);
                })
                .finally(() => {
                    isRefreshing = false;
                    cartRefreshTimeout = null;
                });
        }, 300);
    }
    
    // Update the cart display to show each item separately
function updateCartDisplay(cart) {
    const cartItemsContainer = document.querySelector('.cart-items');
    
    if (!cart || Object.keys(cart).length === 0) {
        cartItemsContainer.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Your cart is empty</h5>
                <p class="text-muted">Add some delicious items!</p>
            </div>
        `;
        return;
    }
    
    let cartHtml = '';
    
    Object.values(cart).forEach(item => {
        let itemDescription = escapeHtml(item.name);
        
        // Show addons if any
        if (item.addons && item.addons.length > 0) {
            itemDescription += ' (with addons)';
        }
        
        cartHtml += `
            <div class="cart-item" data-unique-key="${item.unique_key}">
                <div class="cart-item-info">
                    <h6 class="mb-1">${itemDescription}</h6>
                    <p class="text-muted mb-1">₱${parseFloat(item.price).toFixed(2)} × ${item.quantity}</p>
                    
                    ${item.addons && item.addons.length > 0 ? `
                    <div class="mt-2">
                        <small class="text-success fw-bold">Addons:</small>
                        <div class="ps-3 mt-1">
                    ${item.addons.map(addon => `
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted">
                                    • ${escapeHtml(addon.name)} (x${addon.quantity})
                                </small>
                                <small class="text-success">
                                    +₱${(addon.price * addon.quantity).toFixed(2)}
                                </small>
                            </div>
                    `).join('')}
                        </div>
                    </div>
                    ` : ''}
                    
                    ${item.special_request ? `
                    <div class="special-request mt-2">
                        <small class="text-warning">
                            <i class="fas fa-sticky-note"></i>
                            ${escapeHtml(item.special_request)}
                        </small>
                    </div>
                    ` : ''}
                    
                    <div class="mt-2">
                        <strong>₱${(item.item_total || (item.price * item.quantity)).toFixed(2)}</strong>
                    </div>
                </div>
                <div class="cart-item-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="updateCartItem('${item.unique_key}', -1)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="mx-2">${item.quantity}</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="updateCartItem('${item.unique_key}', 1)">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger ms-2" onclick="removeCartItem('${item.unique_key}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    cartItemsContainer.innerHTML = cartHtml;
}
    
    function updateCartItem(uniqueKey, change) {
    if (isRefreshing) return;
    
    fetch('api/update-cart-item.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            unique_key: uniqueKey,
            change: parseInt(change)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartSummary(data);
            if (document.getElementById('cartSidebar').classList.contains('open')) {
                refreshCartItems();
            }
            toastr.info('Cart updated');
        } else {
            toastr.error(data.message || 'Failed to update cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toastr.error('Network error. Please try again.');
    });
}

function removeCartItem(uniqueKey) {
    if (isRefreshing) return;
    
    if (!confirm('Remove this item from cart?')) return;
    
    fetch('api/remove-cart-item.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            unique_key: uniqueKey
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartSummary(data);
            if (document.getElementById('cartSidebar').classList.contains('open')) {
                refreshCartItems();
            }
            toastr.success('Removed from cart');
        } else {
            toastr.error(data.message || 'Failed to remove item');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toastr.error('Network error. Please try again.');
    });
}
    
    function proceedToCheckout() {
        const count = parseInt(document.getElementById('cart-count').textContent) || 0;
        
        if (count === 0) {
            toastr.warning('Your cart is empty');
            return;
        }
        
        window.location.href = 'checkout.php';
    }
    
    function trackOrder() {
        const holder = "<?php echo $existing_order_handle; ?>";
        if (holder) {
            window.location.href = `order-status.php?order=${holder}`;
        } else {
            const orderNumber = prompt('Enter your order number:');
            if (orderNumber) {
                window.location.href = `order-status.php?order=${orderNumber}`;
            }
        }
    }
    
    // ========== EVENT LISTENERS ==========
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize cart
        const cartCount = <?php echo $cartCount; ?>;
        updateCheckoutButtons(cartCount);
        
            // Initialize carousel after a short delay to ensure DOM is ready
    setTimeout(function() {
        if (document.querySelector('#carouselTrack')) {
            initCarousel();
        } else {
            console.log('Carousel track not found on initial load');
        }
    }, 500);
        
        // Close cart when clicking outside
        document.addEventListener('click', function(event) {
            const cartSidebar = document.getElementById('cartSidebar');
            const cartSummary = document.querySelector('.cart-summary');
            
            if (cartSidebar.classList.contains('open') && 
                !cartSidebar.contains(event.target) && 
                !cartSummary.contains(event.target)) {
                toggleCart();
            }
        });
        
        // Category filtering
        document.querySelectorAll('.category-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const categoryId = this.dataset.category;
                document.querySelectorAll('.category-section').forEach(section => {
                    if (categoryId === 'all' || section.dataset.category === categoryId) {
                        section.style.display = 'block';
                    } else {
                        section.style.display = 'none';
                    }
                });
            });
        });
        
        // Cart item actions delegation
        document.addEventListener('click', function(event) {
            if (isRefreshing) return;
            
            const deleteBtn = event.target.closest('.cart-item-actions .btn-danger');
            if (deleteBtn) {
                const cartItem = deleteBtn.closest('.cart-item');
                if (cartItem) {
                    const productId = cartItem.dataset.id;
                    if (productId) {
                        event.stopPropagation();
                        event.preventDefault();
                        removeCartItem(productId);
                        return;
                    }
                }
            }
            
            const minusBtn = event.target.closest('.cart-item-actions .btn-outline-secondary:first-child');
            if (minusBtn) {
                const cartItem = minusBtn.closest('.cart-item');
                if (cartItem) {
                    const productId = cartItem.dataset.id;
                    if (productId) {
                        event.stopPropagation();
                        event.preventDefault();
                        updateCartItem(productId, -1);
                        return;
                    }
                }
            }
            
            const plusBtn = event.target.closest('.cart-item-actions .btn-outline-secondary:nth-child(2)');
            if (plusBtn) {
                const cartItem = plusBtn.closest('.cart-item');
                if (cartItem) {
                    const productId = cartItem.dataset.id;
                    if (productId) {
                        event.stopPropagation();
                        event.preventDefault();
                        updateCartItem(productId, 1);
                        return;
                    }
                }
            }
        });
        
        // Modal close handlers
        document.getElementById('addonsModal').addEventListener('click', function(event) {
            if (event.target === this) {
                hideAddonsModal();
            }
        });
        
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('addonsModal');
                if (modal.classList.contains('show')) {
                    hideAddonsModal();
                }
            }
        });

    });
    
  // Handle window resize for carousel
let resizeTimeout;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(function() {
        console.log('Window resized, updating carousel...');
        
        const oldItemsPerView = carouselItemsPerView;
        updateCarouselItemsPerView();
        
        // Reinitialize if items per view changed
        if (oldItemsPerView !== carouselItemsPerView) {
            console.log(`Carousel items per view changed from ${oldItemsPerView} to ${carouselItemsPerView}`);
            stopCarouselAutoplay();
            updateCarousel();
            startCarouselAutoplay();
        }
    }, 250);
});
    
    // Clean up intervals on page unload
    window.addEventListener('beforeunload', function() {
        if (cartRefreshTimeout) {
            clearTimeout(cartRefreshTimeout);
        }
        stopCarouselAutoplay();
    });
</script>
</body>
</html>

<?php
function getCategoryIcon($categoryName) {
    $icons = [
        'Burgers' => 'hamburger',
        'Sides' => 'bacon',
        'Drinks' => 'glass-whiskey',
        'Pizza' => 'pizza-slice',
        'Desserts' => 'ice-cream',
        'Coffee' => 'coffee',
        'Tea' => 'mug-hot',
        'Milk' => 'glass-martini',
        'Shake' => 'blender',
        'default' => 'utensils'
    ];
    
    foreach ($icons as $key => $icon) {
        if (stripos($categoryName, $key) !== false) {
            return $icon;
        }
    }
    
    return $icons['default'];
}

function getProductIcon($productName) {
    $icons = [
        'Burger' => 'hamburger',
        'Fries' => 'bacon',
        'Drink' => 'glass-whiskey',
        'Pizza' => 'pizza-slice',
        'Cake' => 'birthday-cake',
        'Coffee' => 'coffee',
        'Tea' => 'mug-hot',
        'Milk' => 'glass-martini',
        'Shake' => 'blender',
        'Americano' => 'coffee',
        'Macchiato' => 'coffee',
        'Vanilla' => 'ice-cream',
        'Mocha' => 'coffee',
        'Sugar' => 'candy-cane',
        'default' => 'utensils'
    ];
    
    foreach ($icons as $key => $icon) {
        if (stripos($productName, $key) !== false) {
            return $icon;
        }
    }
    
    return $icons['default'];
}
?>