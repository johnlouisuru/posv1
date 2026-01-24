<?php
// session_start();
require_once '../includes/db.php';
$existing_order_handle = '';
// Initialize cart
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
        /* Entire page gradient background */
        body {
            background: linear-gradient(135deg, #f8f4e9 0%, #e6dfd3 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* Header with glass effect */
        .online-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Category navigation */
        .category-nav {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 70px;
            z-index: 999;
        }

        /* Product cards with glass effect */
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

        /* Product actions */
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

        /* Category title */
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

        /* Bottom action bar */
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

        /* Cart sidebar */
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

        /* Add padding to prevent content from being hidden */
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
            padding: 20px;
            position: relative;
        }

        .addons-header h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.3rem;
            padding-right: 40px;
        }

        .addons-header .btn-close {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0.9;
            transition: all 0.3s;
        }

        .addons-header .btn-close:hover {
            background: rgba(255, 255, 255, 0.3);
            opacity: 1;
        }

        .addons-body {
            padding: 20px;
            overflow-y: auto;
            max-height: 60vh;
        }

        /* Addon items */
        .addon-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 12px;
            background: #f8f9fa;
            border-radius: 12px;
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
            margin: 0 0 5px 0;
            font-size: 1rem;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .selected-badge {
            background: #28a745;
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
        }

        .addon-description {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .addon-price-tag {
            font-size: 0.9rem;
            color: #28a745;
            font-weight: 600;
        }

        .addon-quantity {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .addon-qty-control {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 8px;
            padding: 4px;
            border: 1px solid #dee2e6;
            min-width: 100px;
        }

        .addon-qty-btn {
            width: 28px;
            height: 28px;
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

        .addon-qty-btn:hover {
            background: linear-gradient(135deg, #5a6268 0%, #495056 100%);
        }

        .addon-qty-display {
            min-width: 40px;
            text-align: center;
            font-weight: bold;
            font-size: 1rem;
            color: #495057;
        }

        /* Special request section */
        .special-request-section {
            margin-top: 20px;
            padding: 15px;
            background: #fff8e1;
            border-radius: 12px;
            border-left: 4px solid #ffc107;
        }

        .special-request-section h5 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .special-request-section textarea {
            width: 100%;
            border: 2px solid #ffeaa7;
            border-radius: 8px;
            padding: 12px;
            font-size: 0.9rem;
            resize: vertical;
            min-height: 80px;
        }

        .special-request-section textarea:focus {
            outline: none;
            border-color: #ffc107;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.25);
        }

        /* Addons footer */
        .addons-footer {
            background: white;
            border-top: 1px solid #e9ecef;
            padding: 20px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
        }

        .total-display {
            margin-bottom: 20px;
        }

        .base-price {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .total-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #28a745;
        }

        .addons-action-buttons {
            display: flex;
            gap: 10px;
        }

        .addons-action-buttons .btn {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
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
            gap: 8px;
        }

        .btn-add-customized:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
        }

        /* Product preview in modal */
        .product-preview {
            display: flex;
            align-items: center;
            padding: 15px;
            background: rgba(139, 69, 19, 0.05);
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .product-preview-image {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .product-preview-info {
            flex: 1;
        }

        .product-preview-info h5 {
            margin: 0 0 5px 0;
            color: #495057;
        }

        .product-preview-price {
            font-weight: 600;
            color: #D2691E;
            font-size: 1.1rem;
        }

        /* Empty state */
        .empty-addons {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-addons i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 60px;
            height: 60px;
            border: 3px solid rgba(0,0,0,.1);
            border-radius: 50%;
            border-top-color: #3498db;
            animation: spin 1s ease-in-out infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .addons-content {
                width: 95%;
                max-height: 85vh;
            }
            
            .cart-sidebar {
                width: 100%;
                right: -100%;
            }
            
            .action-btn {
                flex-direction: column;
            }
            
            .product-actions {
                flex-direction: column;
            }
            
            .quantity-control {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .addon-item {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
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
                    <div class="cart-summary alert alert-info py-2 px-3 mb-0" onclick="toggleCart()" style="cursor: pointer;">
                        <i class="fas fa-shopping-cart"></i>
                        <span id="cart-count"><?php echo $cartCount; ?></span> items
                        <span class="ms-2">₱<span id="cart-total"><?php echo number_format($cartTotal, 2); ?></span></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Category Navigation -->
    <nav class="category-nav py-2">
        <div class="container">
            <div class="category-tabs d-flex flex-nowrap overflow-auto" id="categoryTabs" style="gap: 5px;">
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
                    $hasAddons = $product['has_addons'] || !empty($product['addons_data']);
                    $isAvailable = $product['stock'] > 0 || $product['stock'] === null;
                    ?>
                    <div class="col-6 col-md-4 col-lg-3 product-item" 
                         data-category="<?php echo $product['category_id']; ?>"
                         data-id="<?php echo $product['id']; ?>">
                        
                        <div class="product-card h-100 d-flex flex-column">
                            <div class="product-image">
                                <?php if(empty($product['image_url'])): ?>
                                    <i class="fas fa-<?php echo getProductIcon($product['name']); ?> fa-3x" style="color: #8B4513;"></i>
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
            <button class="action-btn view-cart-btn" onclick="toggleCart()">
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
    // Utility: Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Store addons data globally
    let addonsData = [];
    let currentProduct = null;
    let specialRequest = '';
    let currentProductId = null;
    let selectedAddons = {};

    // Initialize toastr
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "timeOut": "3000"
    };

    // ========== MODAL FUNCTIONS ==========
    function showAddonsModal(productId) {
    console.log('Opening addons modal for product:', productId);
    
    const modal = document.getElementById('addonsModal');
    
    // Show loading state
    document.getElementById('addonsList').innerHTML = `
        <div class="text-center py-5">
            <div class="loading-spinner"></div>
            <p class="mt-3">Loading customizations...</p>
        </div>
    `;
    
    // Show modal with animation
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Fetch product addons
    fetch('api/get-product-addons.php?product_id=' + productId)
        .then(response => response.json())
        .then(data => {
            console.log('DEBUG: Full API response:', data);
            
            if (data.success && data.product) {
                currentProductId = productId;
                currentProduct = data.product;
                selectedAddons = {};
                specialRequest = '';
                addonsData = data.addons || [];
                
                console.log('DEBUG: Number of addons returned:', addonsData.length);
                
                // Update product info
                document.getElementById('addonProductName').textContent = currentProduct.name;
                document.getElementById('basePrice').textContent = parseFloat(currentProduct.price).toFixed(2);
                
                // Build addons list
                let addonsHtml = '';
                
                if (addonsData.length === 0) {
                    // If no addons available, show simple quantity selector
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
                } else {
                    // Add product preview
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
                    
                    // Add special request section
                    addonsHtml += `
                        <div class="special-request-section">
                            <h5><i class="fas fa-sticky-note me-2"></i>Special Instructions</h5>
                            <textarea class="form-control" id="specialRequestText" rows="3" 
                                      placeholder="Any special requests? (e.g., extra sauce, no onions, less ice, etc.)"></textarea>
                        </div>
                    `;
                }
                
                document.getElementById('addonsList').innerHTML = addonsHtml;
                
                // Initialize simple quantity if needed
                if (addonsData.length === 0) {
                    window.simpleQuantity = 1;
                    updateAddonTotal();
                } else {
                    updateAddonTotal();
                }
                
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
        console.log('Closing addons modal');
        const modal = document.getElementById('addonsModal');
        
        // Hide modal with animation
        modal.classList.remove('show');
        document.body.style.overflow = '';
        
        // Reset data
        currentProductId = null;
        currentProduct = null;
        selectedAddons = {};
        specialRequest = '';
        addonsData = [];
    }

    // Toggle addon selection
    function toggleAddonSelection(addonId) {
        const addonItem = document.querySelector(`[data-addon-id="${addonId}"]`);
        const badge = document.getElementById(`badge-${addonId}`);
        const qtyDisplay = document.getElementById(`addon-qty-${addonId}`);
        
        if (!selectedAddons[addonId] || selectedAddons[addonId] === 0) {
            // Select addon
            selectedAddons[addonId] = 1;
            addonItem.classList.add('selected');
            badge.style.display = 'inline-block';
            qtyDisplay.textContent = '1';
            updateAddonItemPrice(addonId);
        } else {
            // Deselect addon
            selectedAddons[addonId] = 0;
            addonItem.classList.remove('selected');
            badge.style.display = 'none';
            qtyDisplay.textContent = '0';
            updateAddonItemPrice(addonId);
        }
        
        updateAddonTotal();
    }

    // Update addon quantity
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

    // Update individual addon price display
    function updateAddonItemPrice(addonId) {
        const addonInfo = addonsData.find(a => a.id == addonId);
        if (!addonInfo) return;
        
        const qty = selectedAddons[addonId] || 0;
        const price = parseFloat(addonInfo.price);
        const total = price * qty;
        
        const priceElement = document.getElementById(`addon-price-${addonId}`);
        priceElement.textContent = `₱${total.toFixed(2)}`;
    }

    // Update total price
    function updateAddonTotal() {
        if (!currentProduct) return;
        
        let total = parseFloat(currentProduct.price);
        
        Object.keys(selectedAddons).forEach(addonId => {
            const qty = selectedAddons[addonId] || 0;
            if (qty > 0) {
                const addonInfo = addonsData.find(a => a.id == addonId);
                if (addonInfo) {
                    total += (parseFloat(addonInfo.price) * qty);
                }
            }
        });
        
        document.getElementById('addonTotalPrice').textContent = total.toFixed(2);
    }

 function addCustomizedToCart() {
    // Get special request
    const specialRequestTextarea = document.getElementById('specialRequestText');
    const specialRequest = specialRequestTextarea ? specialRequestTextarea.value : '';
    
    let quantity = 1;
    let addons = [];
    
    if (addonsData.length === 0) {
        // For products with no addons
        quantity = window.simpleQuantity || 1;
    } else {
        // Filter addons with quantity > 0
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
    
    // Add to cart
    addToCart(currentProductId, quantity, addons, specialRequest);
    
    // Show confirmation
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
        console.log('Adding to cart:', { productId, quantity, addons, specialRequest });
        
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
            console.log('Cart response:', data);
            
            if (data.success) {
                updateCartSummary(data);
                
                // Reset product quantity display
                const qtyElement = document.getElementById(`qty-${productId}`);
                if (qtyElement) {
                    qtyElement.textContent = '0';
                }
                
                // Refresh cart display IMMEDIATELY
                refreshCartItems();
                
                // toastr.success('Added to cart!');
                
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
        
        // Reset quantity display
        qtyElement.textContent = '0';
    }

    function changeQuantity(productId, change) {
        const element = document.getElementById('qty-' + productId);
        let currentQty = parseInt(element.textContent) || 0;
        let newQty = Math.max(0, currentQty + change);
        
        // Update display
        element.textContent = newQty;
    }

function toggleCart() {
    const sidebar = document.getElementById('cartSidebar');
    const isOpening = !sidebar.classList.contains('open');
    
    sidebar.classList.toggle('open');
    
    // Only refresh cart when opening
    if (isOpening) {
        refreshCartItems();
    }
}

    // FIXED: This function updates cart summary AND refreshes cart display
    function updateCartSummary(data) {
        const total = data.cartTotal || 0;
        const count = data.cartCount || 0;
        
        console.log('Updating cart summary:', { total, count });
        
        // Update all cart displays
        document.getElementById('cart-count').textContent = count;
        document.getElementById('mobile-cart-count').textContent = count;
        document.getElementById('cart-total').textContent = total.toFixed(2);
        document.getElementById('sidebar-cart-total').textContent = total.toFixed(2);
        
        // Update checkout buttons
        updateCheckoutButtons(count);
        
        // Refresh cart display if sidebar is open
        if (document.getElementById('cartSidebar').classList.contains('open')) {
            refreshCartItems();
        }
    }

    function updateCheckoutButtons(cartCount) {
        const isCartEmpty = cartCount === 0;
        
        // Update sidebar checkout button
        const sidebarBtn = document.getElementById('sidebar-checkout-btn');
        if (sidebarBtn) {
            sidebarBtn.disabled = isCartEmpty;
        }
        
        // Update bottom checkout button
        const bottomBtn = document.getElementById('bottom-checkout-btn');
        if (bottomBtn) {
            bottomBtn.disabled = isCartEmpty;
        }
    }

// FIXED: Refresh cart items with debouncing
let cartRefreshTimeout = null;
function refreshCartItems() {
    console.log('Refreshing cart items...');
    
    // Clear any existing timeout
    if (cartRefreshTimeout) {
        clearTimeout(cartRefreshTimeout);
    }
    
    // Set a timeout to prevent rapid successive calls
    cartRefreshTimeout = setTimeout(function() {
        fetch('api/get-cart.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
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
                cartRefreshTimeout = null;
            });
    }, 300); // 300ms debounce delay
}


    // FIXED: Update cart display - SIMPLIFIED VERSION
function updateCartDisplay(cart) {
    const cartItemsContainer = document.querySelector('.cart-items');
    
    console.log('Updating cart display with:', cart);
    
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
    
    // Build cart items HTML
    Object.values(cart).forEach(item => {
        // Calculate item total including addons
        let itemTotal = parseFloat(item.price) * item.quantity;
        let addonsHtml = '';
        
        if (item.addons && item.addons.length > 0) {
            addonsHtml = `
                <div class="mt-2">
                    <small class="text-success fw-bold">Addons:</small>
                    <div class="ps-3 mt-1">
            `;
            
            item.addons.forEach(addon => {
                const addonTotal = parseFloat(addon.price) * addon.quantity;
                itemTotal += addonTotal;
                
                addonsHtml += `
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted">
                            • ${escapeHtml(addon.name)} (x${addon.quantity})
                        </small>
                        <small class="text-success">
                            +₱${addonTotal.toFixed(2)}
                        </small>
                    </div>
                `;
            });
            
            addonsHtml += `
                    </div>
                </div>
            `;
        }
        
        cartHtml += `
            <div class="cart-item" data-id="${item.product_id}">
                <div class="cart-item-info">
                    <h6 class="mb-1">${escapeHtml(item.name)}</h6>
                    <p class="text-muted mb-1">₱${parseFloat(item.price).toFixed(2)} × ${item.quantity}</p>
                    ${addonsHtml}
                    ${item.special_request ? `
                    <div class="special-request mt-2">
                        <small class="text-warning">
                            <i class="fas fa-sticky-note"></i>
                            ${escapeHtml(item.special_request)}
                        </small>
                    </div>
                    ` : ''}
                    <div class="mt-2">
                        <strong>₱${itemTotal.toFixed(2)}</strong>
                    </div>
                </div>
                <div class="cart-item-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="mx-2">${item.quantity}</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger ms-2">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    cartItemsContainer.innerHTML = cartHtml;
}

    // ========== CATEGORY FILTERING ==========
    document.querySelectorAll('.category-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // Update active tab
            document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Filter products
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

    // ========== EVENT LISTENERS ==========
    // Close modal when clicking outside
    document.getElementById('addonsModal').addEventListener('click', function(event) {
        if (event.target === this) {
            hideAddonsModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('addonsModal');
            if (modal.classList.contains('show')) {
                hideAddonsModal();
            }
        }
    });

    // FIXED: Add event delegation for cart item actions
document.addEventListener('click', function(event) {
    // Handle cart item delete
    if (event.target.closest('.cart-item-actions .btn-danger')) {
        const cartItem = event.target.closest('.cart-item');
        if (cartItem) {
            const productId = cartItem.dataset.id;
            if (productId && confirm('Remove this item from cart?')) {
                removeCartItem(productId);
                event.stopPropagation();
            }
        }
    }
    
    // Handle cart item quantity minus
    if (event.target.closest('.cart-item-actions .btn-outline-secondary:first-child')) {
        const cartItem = event.target.closest('.cart-item');
        if (cartItem) {
            const productId = cartItem.dataset.id;
            if (productId) {
                updateCartItem(productId, -1);
                event.stopPropagation();
            }
        }
    }
    
    // Handle cart item quantity plus
    if (event.target.closest('.cart-item-actions .btn-outline-secondary:nth-child(2)')) {
        const cartItem = event.target.closest('.cart-item');
        if (cartItem) {
            const productId = cartItem.dataset.id;
            if (productId) {
                updateCartItem(productId, 1);
                event.stopPropagation();
            }
        }
    }
});


// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Menu page loaded');
    
    // Initialize cart summary
    const cartCount = <?php echo $cartCount; ?>;
    updateCheckoutButtons(cartCount);
    
    // Remove the auto-refresh on load to prevent loops
    // refreshCartItems(); // COMMENT THIS OUT
    
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
});

    // Test function
    function testModal() {
        const firstProduct = document.querySelector('.product-item');
        if (firstProduct) {
            const productId = firstProduct.dataset.id;
            console.log('Testing modal with product:', productId);
            showAddonsModal(productId);
        }
    }

    // Test cart refresh
    function testCartRefresh() {
        console.log('Manually testing cart refresh...');
        refreshCartItems();
    }

// FIXED: Update cart item function
function updateCartItem(productId, change) {
    console.log('Updating item:', productId, change);
    
    fetch('api/update-cart-item.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: parseInt(productId),
            change: parseInt(change)
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            updateCartSummary(data);
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

// FIXED: Remove cart item function
function removeCartItem(productId) {
    console.log('Removing item:', productId);
    
    fetch('api/remove-cart-item.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: parseInt(productId)
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            updateCartSummary(data);
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

    // For products with no addons
function updateSimpleQuantity(change) {
    if (!window.simpleQuantity) window.simpleQuantity = 1;
    
    let newQty = window.simpleQuantity + change;
    newQty = Math.max(1, newQty);
    newQty = Math.min(99, newQty);
    
    window.simpleQuantity = newQty;
    document.getElementById('simpleQtyDisplay').textContent = newQty;
    updateAddonTotal();
}

// Update total price calculation
function updateAddonTotal() {
    if (!currentProduct) return;
    
    let total = parseFloat(currentProduct.price);
    
    // If we have addons
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
        // For products with no addons, multiply by simple quantity
        total *= (window.simpleQuantity || 1);
    }
    
    document.getElementById('addonTotalPrice').textContent = total.toFixed(2);
}
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