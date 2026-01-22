<?php
// session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    handleAjaxRequest();
    exit();
}

// Handle GET request for single product
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_product') {
    header('Content-Type: application/json');
    getProduct();
    exit();
}

// Get all products with category and station info
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name, k.name as station_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN kitchen_stations k ON p.station_id = k.id 
    ORDER BY p.category_id, p.display_order, p.name
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for dropdown
$stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get kitchen stations for dropdown
$stmt = $pdo->query("SELECT * FROM kitchen_stations WHERE is_active = 1 ORDER BY display_order");
$stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to handle AJAX requests
function handleAjaxRequest() {
    global $pdo;
    
    $action = $_POST['ajax_action'] ?? '';
    
    switch ($action) {
        case 'create_product':
            createProduct();
            break;
        case 'update_product':
            updateProduct();
            break;
        case 'delete_product':
            deleteProduct();
            break;
        case 'update_stock':
            updateStock();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

// Function to create product
function createProduct() {
    global $pdo;
    
    try {
        // Handle file upload
        $image_url = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['image']['name']);
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_url = 'uploads/products/' . $file_name;
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO products (
                category_id, sku, name, description, price, cost, stock, 
                min_stock, image_url, is_available, is_popular, has_addons, 
                preparation_time, calories, display_order, station_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['category_id'] ?? null,
            !empty($_POST['sku']) ? $_POST['sku'] : null,
            $_POST['name'],
            $_POST['description'] ?? null,
            $_POST['price'],
            $_POST['cost'] ?? null,
            $_POST['stock'] ?? 0,
            $_POST['min_stock'] ?? 5,
            $image_url,
            isset($_POST['is_available']) ? 1 : 0,
            isset($_POST['is_popular']) ? 1 : 0,
            isset($_POST['has_addons']) ? 1 : 0,
            $_POST['preparation_time'] ?? null,
            $_POST['calories'] ?? null,
            $_POST['display_order'] ?? 0,
            $_POST['station_id'] ?: null
        ]);
        
        $product_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Product created successfully',
            'product_id' => $product_id
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error creating product: ' . $e->getMessage()]);
    }
}

// Function to update product
function updateProduct() {
    global $pdo;
    
    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        return;
    }
    
    try {
        // Get current product data
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $current_product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Handle file upload
        $image_url = $current_product['image_url'] ?? null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Delete old image if exists
            if ($image_url && file_exists('../' . $image_url)) {
                unlink('../' . $image_url);
            }
            
            $file_name = time() . '_' . basename($_FILES['image']['name']);
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_url = 'uploads/products/' . $file_name;
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE products SET 
                category_id = ?, sku = ?, name = ?, description = ?, price = ?, cost = ?, 
                stock = ?, min_stock = ?, image_url = ?, is_available = ?, is_popular = ?, 
                has_addons = ?, preparation_time = ?, calories = ?, display_order = ?, 
                station_id = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['category_id'] ?? null,
            !empty($_POST['sku']) ? $_POST['sku'] : null,
            $_POST['name'],
            $_POST['description'] ?? null,
            $_POST['price'],
            $_POST['cost'] ?? null,
            $_POST['stock'] ?? 0,
            $_POST['min_stock'] ?? 5,
            $image_url,
            isset($_POST['is_available']) ? 1 : 0,
            isset($_POST['is_popular']) ? 1 : 0,
            isset($_POST['has_addons']) ? 1 : 0,
            $_POST['preparation_time'] ?? null,
            $_POST['calories'] ?? null,
            $_POST['display_order'] ?? 0,
            $_POST['station_id'] ?: null,
            $_POST['id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating product: ' . $e->getMessage()]);
    }
}

// Function to delete product
function deleteProduct() {
    global $pdo;
    
    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        return;
    }
    
    try {
        // Check if product has orders
        $stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM order_items WHERE product_id = ?");
        $stmt->execute([$_POST['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['order_count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete product with existing orders']);
            return;
        }
        
        // Get image URL before deletion
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        
        // Delete image file if exists
        if ($product['image_url'] && file_exists('../' . $product['image_url'])) {
            unlink('../' . $product['image_url']);
        }
        
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting product: ' . $e->getMessage()]);
    }
}

// Function to update stock
function updateStock() {
    global $pdo;
    
    if (!isset($_POST['product_id'])) {
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        return;
    }
    
    $product_id = $_POST['product_id'];
    $action = $_POST['action'];
    $quantity = intval($_POST['quantity']);
    $notes = $_POST['notes'] ?? '';
    
    try {
        // Get current stock
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            return;
        }
        
        $current_stock = $current['stock'];
        $new_stock = $current_stock;
        
        switch ($action) {
            case 'restock':
                $new_stock = $current_stock + $quantity;
                break;
            case 'adjustment':
                $new_stock = $current_stock + $quantity;
                break;
            case 'waste':
                $new_stock = $current_stock - $quantity;
                break;
            case 'set':
                $new_stock = $quantity;
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                return;
        }
        
        // Update stock
        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->execute([$new_stock, $product_id]);
        
        // Log inventory change
        $change_type = $action === 'restock' ? 'restock' : 'adjustment';
        $quantity_change = $new_stock - $current_stock;
        
        $stmt = $pdo->prepare("
            INSERT INTO inventory_logs (product_id, change_type, quantity_change, new_stock_level, notes) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$product_id, $change_type, $quantity_change, $new_stock, $notes]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Stock updated successfully',
            'new_stock' => $new_stock
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating stock: ' . $e->getMessage()]);
    }
}

// Function to get single product
function getProduct() {
    global $pdo;
    
    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        echo json_encode(['success' => true, 'data' => $product]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 20px;
        }
        
        .card-header {
            background: #fff;
            border-bottom: 2px solid #e9ecef;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .btn-action {
            padding: 5px 10px;
            margin: 0 2px;
            font-size: 12px;
        }
        
        .badge-status {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 20px;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 8px 12px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .modal-content {
            border-radius: 10px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 20px;
        }
        
        .nav-tabs .nav-link.active {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            background: none;
        }
        
        .stock-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .stock-in { background: #28a745; }
        .stock-low { background: #ffc107; }
        .stock-out { background: #dc3545; }

        /* Addon Management Styles */
        .addon-management-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .addon-management-card .card-header {
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }

        /* Quick Addon Form */
        #quickAddonForm .input-group-text {
            background-color: #f8f9fa;
        }

        #quickAddonForm .form-control:focus {
            border-color: #ffc107;
            box-shadow: 0 0 0 0.25rem rgba(255, 193, 7, 0.25);
        }

        /* Addon Item Cards */
        .addon-item-card {
            transition: all 0.2s;
            border-left: 4px solid transparent;
        }

        .addon-item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .addon-item-card.global-addon {
            border-left-color: #0dcaf0;
        }

        .addon-item-card.product-addon {
            border-left-color: #198754;
        }

        /* Badge Styles */
        .badge-global {
            background-color: #0dcaf0;
        }

        .badge-product {
            background-color: #198754;
        }

        /* Smooth removal animations */
        .addon-item-card {
            transition: all 0.3s ease;
        }

        .addon-item-card.removing {
            transform: scale(0.95);
            opacity: 0.5;
        }

        /* Spinner styles */
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.2em;
        }

        /* Button hover effects */
        .btn-danger:hover {
            transform: scale(1.1);
            transition: transform 0.2s;
        }

        /* Empty state styling */
        .text-center.py-5 {
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        
        /* Loading spinner */
        .spinner-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .spinner-overlay.active {
            display: flex;
        }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
        <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-boxes me-2"></i> Product Management
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="products.php">
                            <i class="fas fa-box me-1"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">
                            <i class="fas fa-list me-1"></i> Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="station_assignments.php">
                            <i class="fas fa-project-diagram me-1"></i> Assignments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="addons.php">
                            <i class="fas fa-plus-circle me-1"></i> Addons
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pos.php">
                            <i class="fas fa-cash-register me-1"></i> POS
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-boxes me-2"></i> Product List</h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus me-2"></i> Add New Product
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <select id="filterCategory" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="filterStatus" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="1">Available</option>
                                    <option value="0">Unavailable</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="filterStock" class="form-select">
                                    <option value="">All Stock Levels</option>
                                    <option value="out">Out of Stock</option>
                                    <option value="low">Low Stock</option>
                                    <option value="in">In Stock</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" id="searchProduct" class="form-control" placeholder="Search products...">
                            </div>
                        </div>
                        
                        <!-- Products Table -->
                        <div class="table-responsive">
                            <table id="productsTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Station</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                    <?php
                                    // Determine stock indicator
                                    if ($product['stock'] <= 0) {
                                        $stock_class = 'stock-out';
                                        $stock_text = 'Out';
                                    } elseif ($product['stock'] <= $product['min_stock']) {
                                        $stock_class = 'stock-low';
                                        $stock_text = 'Low';
                                    } else {
                                        $stock_class = 'stock-in';
                                        $stock_text = 'In Stock';
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($product['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                 class="product-image">
                                            <?php else: ?>
                                            <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                            <?php if ($product['is_popular']): ?>
                                            <span class="badge bg-warning badge-status ms-1">Popular</span>
                                            <?php endif; ?>
                                            <?php if ($product['has_addons']): ?>
                                            <span class="badge bg-info badge-status ms-1">Addons</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                        <td>
                                            <strong>₱<?php echo number_format($product['price'], 2); ?></strong>
                                            <?php if ($product['cost']): ?>
                                            <br><small class="text-muted">Cost: ₱<?php echo number_format($product['cost'], 2); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="stock-indicator <?php echo $stock_class; ?>"></span>
                                            <?php echo $stock_text; ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo $product['stock']; ?> units
                                                <?php if ($product['stock'] <= $product['min_stock']): ?>
                                                <br><span class="text-danger">Min: <?php echo $product['min_stock']; ?></span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($product['is_available']): ?>
                                            <span class="badge bg-success badge-status">Available</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger badge-status">Unavailable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['station_name'] ?: 'N/A'); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info btn-action" 
                                                    onclick="editProduct(<?php echo $product['id']; ?>)"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <button class="btn btn-sm btn-secondary btn-action" 
                                                    onclick="manageAddons(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')"
                                                    title="Manage Addons">
                                                <i class="fas fa-plus-circle"></i>
                                            </button>
                                            
                                            <button class="btn btn-sm btn-warning btn-action" 
                                                    onclick="updateStock(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')"
                                                    title="Update Stock">
                                                <i class="fas fa-boxes"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger btn-action" 
                                                    onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Product</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addProductForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <ul class="nav nav-tabs mb-3" id="productTabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#basicInfo">Basic Info</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#pricingStock">Pricing & Stock</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#advanced">Advanced</a>
                            </li>
                        </ul>
                        
                        <div class="tab-content">
                            <!-- Basic Info Tab -->
                            <div class="tab-pane fade show active" id="basicInfo">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="productName" class="form-label">Product Name *</label>
                                            <input type="text" class="form-control" id="productName" name="name" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="productSKU" class="form-label">SKU (Optional)</label>
                                            <input type="text" class="form-control" id="productSKU" name="sku">
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="productCategory" class="form-label">Category *</label>
                                                <select class="form-select" id="productCategory" name="category_id" required>
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="productStation" class="form-label">Kitchen Station</label>
                                                <select class="form-select" id="productStation" name="station_id">
                                                    <option value="">No Station</option>
                                                    <?php foreach ($stations as $station): ?>
                                                    <option value="<?php echo $station['id']; ?>" 
                                                            data-color="<?php echo $station['color_code']; ?>"
                                                            style="border-left: 3px solid <?php echo $station['color_code']; ?>; padding-left: 10px;">
                                                        <?php echo htmlspecialchars($station['name']); ?>
                                                        <?php if (!$station['is_active']): ?>
                                                        <span class="text-muted">(Inactive)</span>
                                                        <?php endif; ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="productDescription" class="form-label">Description</label>
                                            <textarea class="form-control" id="productDescription" name="description" rows="3"></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="productImage" class="form-label">Product Image</label>
                                            <div class="image-upload-container text-center">
                                                <div class="image-preview mb-2" id="imagePreview">
                                                    <div class="default-image">
                                                        <i class="fas fa-image fa-3x text-muted"></i>
                                                        <p class="text-muted mt-2">No image selected</p>
                                                    </div>
                                                </div>
                                                <input type="file" class="form-control" id="productImage" name="image" accept="image/*">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pricing & Stock Tab -->
                            <div class="tab-pane fade" id="pricingStock">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="productPrice" class="form-label">Selling Price *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" class="form-control" id="productPrice" name="price" step="0.01" min="0" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="productCost" class="form-label">Cost Price (Optional)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" class="form-control" id="productCost" name="cost" step="0.01" min="0">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="productCalories" class="form-label">Calories (Optional)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="productCalories" name="calories" min="0">
                                                <span class="input-group-text">kcal</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="productStock" class="form-label">Initial Stock</label>
                                            <input type="number" class="form-control" id="productStock" name="stock" min="0" value="0">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="productMinStock" class="form-label">Minimum Stock Level</label>
                                            <input type="number" class="form-control" id="productMinStock" name="min_stock" min="0" value="5">
                                            <small class="text-muted">Low stock alert when stock reaches this level</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="preparationTime" class="form-label">Preparation Time (minutes)</label>
                                            <input type="number" class="form-control" id="preparationTime" name="preparation_time" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Advanced Tab -->
                            <div class="tab-pane fade" id="advanced">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="productAvailable" name="is_available" checked>
                                            <label class="form-check-label" for="productAvailable">Product Available for Sale</label>
                                        </div>
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="productPopular" name="is_popular">
                                            <label class="form-check-label" for="productPopular">Mark as Popular Product</label>
                                        </div>
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="productHasAddons" name="has_addons">
                                            <label class="form-check-label" for="productHasAddons">Enable Addons for this Product</label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="displayOrder" class="form-label">Display Order</label>
                                            <input type="number" class="form-control" id="displayOrder" name="display_order" min="0" value="0">
                                            <small class="text-muted">Lower numbers appear first</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveProductBtn">
                            <span id="saveProductText">Save Product</span>
                            <span id="saveProductSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Product</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editProductForm" enctype="multipart/form-data">
                    <input type="hidden" id="editProductId" name="id">
                    <div class="modal-body" id="editProductBody">
                        <!-- Content will be loaded dynamically -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="updateProductBtn">
                            <span id="updateProductText">Update Product</span>
                            <span id="updateProductSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Update Stock Modal -->
    <div class="modal fade" id="updateStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-boxes me-2"></i> Update Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="updateStockForm">
                        <input type="hidden" id="stockProductId" name="product_id">
                        <div class="mb-3">
                            <label for="stockProductName" class="form-label">Product</label>
                            <input type="text" class="form-control" id="stockProductName" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="currentStock" class="form-label">Current Stock</label>
                            <input type="text" class="form-control" id="currentStock" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="stockAction" class="form-label">Action</label>
                            <select class="form-select" id="stockAction" name="action" required>
                                <option value="">Select Action</option>
                                <option value="restock">Restock</option>
                                <option value="adjustment">Adjustment</option>
                                <option value="waste">Waste/Damage</option>
                                <option value="set">Set Stock Level</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="stockQuantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="stockQuantity" name="quantity" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="stockNotes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="stockNotes" name="notes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitStockUpdate()" id="updateStockBtn">
                        <span id="updateStockText">Update Stock</span>
                        <span id="updateStockSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Manage Addons Modal -->
    <div class="modal fade" id="manageAddonsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>
                        Manage Addons for: <span id="addonProductName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="manageAddonsProductId">
                    
                    <!-- Global Addons Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-globe me-2"></i>
                                Global Addons
                                <small class="float-end">Available for all products</small>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="globalAddonsList" class="row">
                                <!-- Global addons will be loaded here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Product-specific Addons Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-box me-2"></i>
                                Product-specific Addons
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <select class="form-select" id="availableAddons">
                                        <option value="">Select addon to assign...</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-success w-100" onclick="assignAddonToProduct()">
                                        <i class="fas fa-link me-2"></i> Assign Addon
                                    </button>
                                </div>
                            </div>
                            
                            <div id="productAddonsList" class="row">
                                <!-- Product-specific addons will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="saveProductAddons()">
                        <i class="fas fa-save me-2"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
    
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Initialize DataTable
    $(document).ready(function() {
        const table = $('#productsTable').DataTable({
            pageLength: 25,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search products..."
            },
            columnDefs: [
                { orderable: false, targets: [0, 7] }
            ]
        });
        
        // Category filter
        $('#filterCategory').on('change', function() {
            table.column(2).search(this.value).draw();
        });
        
        // Status filter
        $('#filterStatus').on('change', function() {
            table.column(5).search(this.value).draw();
        });
        
        // Stock filter
        $('#filterStock').on('change', function() {
            table.column(4).search(this.value).draw();
        });
        
        // Search input
        $('#searchProduct').on('keyup', function() {
            table.search(this.value).draw();
        });
        
        // Image preview for add form
        $('#productImage').on('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#imagePreview').html(`
                        <img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px;">
                    `);
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Handle add product form submission
        $('#addProductForm').on('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            $('#saveProductBtn').prop('disabled', true);
            $('#saveProductText').addClass('d-none');
            $('#saveProductSpinner').removeClass('d-none');
            
            const formData = new FormData(this);
            
            // Validate required fields
            if (!formData.get('name') || !formData.get('category_id') || !formData.get('price')) {
                showAlert('error', 'Please fill in all required fields');
                resetButtonState('saveProductBtn', 'Save Product');
                return;
            }
            
            // Add action parameter
            formData.append('ajax_action', 'create_product');
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Response:', response);
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        if (data.success) {
                            $('#addProductModal').modal('hide');
                            $('#addProductForm')[0].reset();
                            $('#imagePreview').html(`
                                <div class="default-image">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                    <p class="text-muted mt-2">No image selected</p>
                                </div>
                            `);
                            
                            showAlert('success', data.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert('error', data.message || 'Unknown error occurred');
                            resetButtonState('saveProductBtn', 'Save Product');
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        console.error('Response text:', response);
                        showAlert('error', 'Invalid response from server. Check console for details.');
                        resetButtonState('saveProductBtn', 'Save Product');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showAlert('error', 'An error occurred. Please try again.');
                    resetButtonState('saveProductBtn', 'Save Product');
                }
            });
        });
        
        // Handle edit product form submission
        $('#editProductForm').on('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            $('#updateProductBtn').prop('disabled', true);
            $('#updateProductText').addClass('d-none');
            $('#updateProductSpinner').removeClass('d-none');
            
            const formData = new FormData(this);
            formData.append('ajax_action', 'update_product');
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Update Response:', response);
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        if (data.success) {
                            $('#editProductModal').modal('hide');
                            showAlert('success', data.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert('error', data.message || 'Unknown error occurred');
                            resetButtonState('updateProductBtn', 'Update Product');
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        console.error('Response text:', response);
                        showAlert('error', 'Invalid response from server');
                        resetButtonState('updateProductBtn', 'Update Product');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showAlert('error', 'An error occurred. Please try again.');
                    resetButtonState('updateProductBtn', 'Update Product');
                }
            });
        });
    });
    
    function resetButtonState(buttonId, buttonText) {
        $(`#${buttonId}`).prop('disabled', false);
        $(`#${buttonId}Text`).removeClass('d-none').text(buttonText);
        $(`#${buttonId}Spinner`).addClass('d-none');
    }
    
    function editProduct(productId) {
        console.log('Editing product:', productId);
        $('#loadingSpinner').addClass('active');
        
        $.ajax({
            url: 'products.php?ajax_action=get_product&id=' + productId,
            type: 'GET',
            success: function(response) {
                $('#loadingSpinner').removeClass('active');
                console.log('Edit response:', response);
                
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (result.success) {
                        const product = result.data;
                        console.log('Product data:', product);
                        
                        let formHTML = `
                            <ul class="nav nav-tabs mb-3" id="editProductTabs">
                                <li class="nav-item">
                                    <a class="nav-link active" data-bs-toggle="tab" href="#editBasicInfo">Basic Info</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#editPricingStock">Pricing & Stock</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#editAdvanced">Advanced</a>
                                </li>
                            </ul>
                            
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="editBasicInfo">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label class="form-label">Product Name *</label>
                                                <input type="text" class="form-control" name="name" value="${escapeHtml(product.name || '')}" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">SKU (Optional)</label>
                                                <input type="text" class="form-control" name="sku" value="${escapeHtml(product.sku || '')}">
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Category *</label>
                                                    <select class="form-select" name="category_id" required>
                                                        <option value="">Select Category</option>
                        `;
                        
                        // Add category options
                        <?php foreach ($categories as $cat): ?>
                        formHTML += `<option value="<?php echo $cat['id']; ?>" ${product.category_id == <?php echo $cat['id']; ?> ? 'selected' : ''}><?php echo addslashes($cat['name']); ?></option>`;
                        <?php endforeach; ?>
                        
                        formHTML += `
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Kitchen Station</label>
                                                    <select class="form-select" name="station_id">
                                                        <option value="">No Station</option>
                        `;
                        
                        // Add station options
                        <?php foreach ($stations as $station): ?>
                        formHTML += `<option value="<?php echo $station['id']; ?>" ${product.station_id == <?php echo $station['id']; ?> ? 'selected' : ''}><?php echo addslashes($station['name']); ?></option>`;
                        <?php endforeach; ?>
                        
                        formHTML += `
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="description" rows="3">${escapeHtml(product.description || '')}</textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Product Image</label>
                                                <div class="image-upload-container text-center">
                                                    <div class="image-preview mb-2" id="editImagePreview">
                        `;
                        
                        if (product.image_url) {
                            formHTML += `<img src="../${escapeHtml(product.image_url)}" class="img-fluid rounded" style="max-height: 200px;">`;
                        } else {
                            formHTML += `
                                <div class="default-image">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                    <p class="text-muted mt-2">No image selected</p>
                                </div>
                            `;
                        }
                        
                        formHTML += `
                                                    </div>
                                                    <input type="file" class="form-control" name="image" accept="image/*">
                                                    <small class="text-muted">Leave empty to keep current image</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="editPricingStock">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Selling Price *</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₱</span>
                                                    <input type="number" class="form-control" name="price" value="${product.price || ''}" step="0.01" min="0" required>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Cost Price (Optional)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₱</span>
                                                    <input type="number" class="form-control" name="cost" value="${product.cost || ''}" step="0.01" min="0">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Calories (Optional)</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="calories" value="${product.calories || ''}" min="0">
                                                    <span class="input-group-text">kcal</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Current Stock</label>
                                                <input type="number" class="form-control" name="stock" value="${product.stock || 0}" min="0">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Minimum Stock Level</label>
                                                <input type="number" class="form-control" name="min_stock" value="${product.min_stock || 5}" min="0">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Preparation Time (minutes)</label>
                                                <input type="number" class="form-control" name="preparation_time" value="${product.preparation_time || ''}" min="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="editAdvanced">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" name="is_available" ${product.is_available == 1 ? 'checked' : ''}>
                                                <label class="form-check-label">Product Available for Sale</label>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" name="is_popular" ${product.is_popular == 1 ? 'checked' : ''}>
                                                <label class="form-check-label">Mark as Popular Product</label>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" name="has_addons" ${product.has_addons == 1 ? 'checked' : ''}>
                                                <label class="form-check-label">Enable Addons for this Product</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Display Order</label>
                                                <input type="number" class="form-control" name="display_order" value="${product.display_order || 0}" min="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        $('#editProductId').val(product.id);
                        $('#editProductBody').html(formHTML);
                        $('#editProductModal').modal('show');
                        
                        // Initialize tab functionality
                        new bootstrap.Tab(document.querySelector('#editProductTabs .nav-link'));
                        
                        // Image preview for edit form
                        $('#editProductForm input[name="image"]').on('change', function() {
                            const file = this.files[0];
                            if (file) {
                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    $('#editImagePreview').html(`
                                        <img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px;">
                                    `);
                                };
                                reader.readAsDataURL(file);
                            }
                        });
                    } else {
                        showAlert('error', result.message || 'Product not found');
                    }
                } catch (e) {
                    console.error('JSON Parse Error in edit:', e);
                    console.error('Response:', response);
                    showAlert('error', 'Invalid response from server. Check console.');
                }
            },
            error: function(xhr, status, error) {
                $('#loadingSpinner').removeClass('active');
                console.error('AJAX Error in edit:', error);
                showAlert('error', 'Failed to load product data');
            }
        });
    }
    
    function updateStock(productId, productName) {
        console.log('Updating stock for:', productId, productName);
        
        // First, try to get product data
        $.ajax({
            url: 'products.php?ajax_action=get_product&id=' + productId,
            type: 'GET',
            success: function(response) {
                console.log('Stock update response:', response);
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (result.success) {
                        const product = result.data;
                        console.log('Product stock data:', product);
                        
                        $('#stockProductId').val(productId);
                        $('#stockProductName').val(productName);
                        $('#currentStock').val(product.stock || 0);
                        $('#stockQuantity').val('');
                        $('#stockNotes').val('');
                        
                        $('#updateStockModal').modal('show');
                    } else {
                        showAlert('error', result.message || 'Failed to load product data');
                    }
                } catch (e) {
                    console.error('JSON Parse Error in stock:', e);
                    console.error('Response:', response);
                    showAlert('error', 'Invalid response format');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error in stock:', error);
                showAlert('error', 'Failed to load product data');
            }
        });
    }
    
    function submitStockUpdate() {
        const productId = $('#stockProductId').val();
        const action = $('#stockAction').val();
        const quantity = $('#stockQuantity').val();
        const notes = $('#stockNotes').val();
        
        console.log('Stock update data:', { productId, action, quantity, notes });
        
        if (!action || !quantity) {
            showAlert('warning', 'Please select action and enter quantity');
            return;
        }
        
        // Show loading state
        $('#updateStockBtn').prop('disabled', true);
        $('#updateStockText').addClass('d-none');
        $('#updateStockSpinner').removeClass('d-none');
        
        $.ajax({
            url: 'products.php',
            type: 'POST',
            data: {
                ajax_action: 'update_stock',
                product_id: productId,
                action: action,
                quantity: quantity,
                notes: notes
            },
            success: function(response) {
                console.log('Stock update response:', response);
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    if (data.success) {
                        $('#updateStockModal').modal('hide');
                        showAlert('success', data.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('error', data.message || 'Failed to update stock');
                        resetButtonState('updateStockBtn', 'Update Stock');
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response:', response);
                    showAlert('error', 'Invalid response from server');
                    resetButtonState('updateStockBtn', 'Update Stock');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showAlert('error', 'Failed to update stock');
                resetButtonState('updateStockBtn', 'Update Stock');
            }
        });
    }
    
    function deleteProduct(productId, productName) {
        Swal.fire({
            title: 'Delete Product',
            text: `Are you sure you want to delete "${productName}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#loadingSpinner').addClass('active');
                
                $.ajax({
                    url: 'products.php',
                    type: 'POST',
                    data: {
                        ajax_action: 'delete_product',
                        id: productId
                    },
                    success: function(response) {
                        $('#loadingSpinner').removeClass('active');
                        console.log('Delete response:', response);
                        
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;
                            if (data.success) {
                                showAlert('success', data.message);
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showAlert('error', data.message || 'Failed to delete product');
                            }
                        } catch (e) {
                            console.error('JSON Parse Error:', e);
                            console.error('Response:', response);
                            showAlert('success', 'Product deleted successfully (but server response was malformed)');
                            setTimeout(() => location.reload(), 1500);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#loadingSpinner').removeClass('active');
                        console.error('AJAX Error:', error);
                        showAlert('error', 'Failed to delete product');
                    }
                });
            }
        });
    }
    
    function showAlert(type, message) {
        Swal.fire({
            icon: type,
            title: type === 'success' ? 'Success!' : type === 'error' ? 'Error!' : 'Warning!',
            text: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Addon management functions
    let currentAddonProductId = null;
    let selectedAddons = new Set();

    function manageAddons(productId, productName) {
        currentAddonProductId = productId;
        selectedAddons.clear();
        
        document.getElementById('addonProductName').textContent = productName;
        document.getElementById('manageAddonsProductId').value = productId;
        
        loadAddons();
        
        $('#manageAddonsModal').modal('show');
    }

    function loadAddons() {
        // Load global addons
        $.ajax({
            url: '../api/get-addons.php?type=global',
            type: 'GET',
            success: function(response) {
                console.log('Global addons response:', response);
                const container = $('#globalAddonsList');
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    if (data.success && data.addons && data.addons.length > 0) {
                        let html = '';
                        data.addons.forEach(addon => {
                            html += `
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">${escapeHtml(addon.name)}</h6>
                                                    <p class="text-muted mb-1 small">${escapeHtml(addon.description || '')}</p>
                                                    <strong class="text-success">+₱${parseFloat(addon.price || 0).toFixed(2)}</strong>
                                                </div>
                                                <div>
                                                    <span class="badge bg-info">Global</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        container.html(html);
                    } else {
                        container.html(`
                            <div class="text-center py-3">
                                <i class="fas fa-globe fa-2x text-muted mb-2"></i>
                                <p class="text-muted">No global addons available</p>
                            </div>
                        `);
                    }
                } catch (e) {
                    console.error('Error parsing global addons:', e);
                    container.html(`
                        <div class="text-center py-3">
                            <p class="text-danger">Error loading global addons</p>
                        </div>
                    `);
                }
            },
            error: function() {
                $('#globalAddonsList').html(`
                    <div class="text-center py-3">
                        <p class="text-danger">Error loading global addons</p>
                    </div>
                `);
            }
        });

        // Load product-specific addons
        $.ajax({
            url: `../api/get-product-addons.php?product_id=${currentAddonProductId}`,
            type: 'GET',
            success: function(response) {
                console.log('Product addons response:', response);
                const container = $('#productAddonsList');
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (data.success) {
                        selectedAddons.clear();
                        let productAddonsHtml = '';
                        
                        if (data.addons && data.addons.length > 0) {
                            data.addons.forEach(addon => {
                                selectedAddons.add(parseInt(addon.id));
                                productAddonsHtml += `
                                    <div class="col-md-6 mb-3">
                                        <div class="card" id="addon_card_${addon.id}">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1">${escapeHtml(addon.name)}</h6>
                                                        <p class="text-muted mb-1 small">${escapeHtml(addon.description || '')}</p>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <strong class="text-success">+₱${parseFloat(addon.price || 0).toFixed(2)}</strong>
                                                            <span class="badge bg-secondary">Max: ${addon.max_quantity || 1}</span>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <button class="btn btn-sm btn-danger" 
                                                                onclick="removeProductAddon(${addon.id})">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            container.html(productAddonsHtml);
                        } else {
                            container.html(`
                                <div class="text-center py-5">
                                    <i class="fas fa-plus-circle fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No product-specific addons</p>
                                </div>
                            `);
                        }

                        // Load available addons for dropdown
                        loadAvailableAddons();
                    } else {
                        container.html(`
                            <div class="text-center py-5">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                                <p class="text-muted">${data.message || 'Error loading addons'}</p>
                            </div>
                        `);
                    }
                } catch (e) {
                    console.error('Error parsing product addons:', e);
                    container.html(`
                        <div class="text-center py-5">
                            <p class="text-danger">Error loading addons</p>
                        </div>
                    `);
                }
            },
            error: function() {
                $('#productAddonsList').html(`
                    <div class="text-center py-5">
                        <p class="text-danger">Error loading product addons</p>
                    </div>
                `);
            }
        });
    }

    function loadAvailableAddons() {
        $.ajax({
            url: '../api/get-addons.php?type=all',
            type: 'GET',
            success: function(response) {
                console.log('Available addons response:', response);
                const select = $('#availableAddons');
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    if (data.success && data.addons && data.addons.length > 0) {
                        let options = '<option value="">Select addon to assign...</option>';
                        data.addons.forEach(addon => {
                            if (!selectedAddons.has(parseInt(addon.id))) {
                                options += `<option value="${addon.id}">${escapeHtml(addon.name)} (+₱${addon.price || 0})</option>`;
                            }
                        });
                        select.html(options);
                    } else {
                        select.html('<option value="">No addons available</option>');
                    }
                } catch (e) {
                    console.error('Error parsing available addons:', e);
                    select.html('<option value="">Error loading addons</option>');
                }
            },
            error: function() {
                $('#availableAddons').html('<option value="">Error loading addons</option>');
            }
        });
    }

    function assignAddonToProduct() {
        const addonId = $('#availableAddons').val();
        
        if (!addonId) {
            showAlert('warning', 'Please select an addon first');
            return;
        }
        
        selectedAddons.add(parseInt(addonId));
        loadAddons();
        showAlert('success', 'Addon added to product');
    }

    function removeProductAddon(addonId) {
        Swal.fire({
            title: 'Remove Addon',
            text: 'Are you sure you want to remove this addon from the product?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove it!'
        }).then((result) => {
            if (result.isConfirmed) {
                selectedAddons.delete(parseInt(addonId));
                loadAddons();
                showAlert('success', 'Addon removed from product');
            }
        });
    }

    function saveProductAddons() {
        const addonsArray = Array.from(selectedAddons);
        console.log('Saving addons:', addonsArray);
        
        // Show loading
        Swal.fire({
            title: 'Saving...',
            text: 'Please wait while we save your changes',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: '../api/update-products-addons.php',
            type: 'POST',
            data: {
                product_id: currentAddonProductId,
                addons: addonsArray
            },
            success: function(response) {
                console.log('Save addons response:', response);
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            $('#manageAddonsModal').modal('hide');
                            // Reload page to update has_addons status
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to save addons'
                        });
                    }
                } catch (e) {
                    console.error('Error parsing save response:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Invalid response from server'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save addons'
                });
            }
        });
    }
</script>
</body>
</html>