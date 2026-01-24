<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'cashier')) {
    header('Location: login.php');
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['full_name'];

// Get POS settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE category = 'pos'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAMARA'S CAFFEINATED DREAMS</title>
    <link rel="icon" type="image/png" href="../uploads/samara.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            overflow: hidden;
        }
        
        .pos-container {
            display: grid;
            grid-template-columns: 300px 1fr 400px;
            grid-template-rows: 70px 1fr;
            height: 100vh;
            gap: 0;
        }
        
        /* Header */
        .pos-header {
            grid-column: 1 / -1;
            background: var(--dark-color);
            color: white;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .header-left h3 {
            margin: 0;
            font-weight: 600;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Categories Sidebar */
        .categories-sidebar {
            background: white;
            border-right: 1px solid #dee2e6;
            overflow-y: auto;
            padding: 20px 0;
        }
        
        .category-item {
            padding: 12px 20px;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }
        
        .category-item:hover {
            background: #f8f9fa;
        }
        
        .category-item.active {
            background: var(--primary-color);
            color: white;
        }
        
        /* Products Grid */
        .products-grid {
            background: white;
            padding: 20px;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            align-content: start;
        }
        
        .product-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }
        
        .product-card.out-of-stock {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .product-price {
            color: var(--secondary-color);
            font-weight: 600;
            font-size: 1.1em;
        }
        
        .product-stock {
            font-size: 0.85em;
            color: #666;
            margin-top: auto;
        }
        
        .stock-low {
            color: var(--warning-color);
            font-weight: 600;
        }
        
        .stock-out {
            color: var(--danger-color);
            font-weight: 600;
        }
        
        /* Cart Sidebar Layout Fix */
.cart-sidebar {
    background: white;
    border-left: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden; /* Prevent double scrollbars */
}

.cart-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    background: var(--dark-color);
    color: white;
    flex-shrink: 0; /* Prevent header from shrinking */
}

.cart-items {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    min-height: 0; /* Important for flex scrolling */
}

.cart-footer {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    background: #f8f9fa;
    flex-shrink: 0; /* Prevent footer from shrinking */
    box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
    position: sticky;
    bottom: 0;
    z-index: 10;
}

/* Modern Cart Item Styling */
.cart-item {
    background: white;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}
/* Empty Cart State */
.cart-items .text-center {
    padding: 40px 20px;
    color: #6c757d;
}


.cart-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    position: relative;
}

.cart-item-header > div:first-child {
    flex: 1;
    padding-right: 10px;
}

.cart-item-header strong {
    font-size: 16px;
    color: var(--dark-color);
    display: block;
    margin-bottom: 4px;
}

/* Better Scrollbar */
.cart-items::-webkit-scrollbar {
    width: 6px;
}

.cart-items::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.cart-items::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.cart-items::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Modern Remove Button */
.cart-item-actions {
    position: absolute;
    top: 0;
    right: 0;
}

.remove-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    border: none;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(238, 90, 82, 0.3);
}

.remove-btn:hover {
    background: linear-gradient(135deg, #ff5252, #e53935);
    transform: scale(1.1) rotate(90deg);
    box-shadow: 0 4px 12px rgba(238, 90, 82, 0.4);
}

.remove-btn:active {
    transform: scale(0.95);
}

/* Modern Quantity Controls */
.quantity-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 16px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 8px;
    max-width: 140px;
}

.quantity-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, var(--primary-color), #2980b9);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(52, 152, 219, 0.3);
}

.quantity-btn:hover {
    background: linear-gradient(135deg, #2980b9, #1c5d87);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
}

.quantity-btn:active {
    transform: translateY(0);
}

.quantity-btn.disabled {
    background: #e9ecef;
    color: #adb5bd;
    cursor: not-allowed;
    box-shadow: none;
}

.quantity-btn.disabled:hover {
    transform: none;
    background: #e9ecef;
}

.quantity-display {
    min-width: 40px;
    text-align: center;
    font-weight: 600;
    font-size: 16px;
    color: var(--dark-color);
    padding: 6px 0;
    background: white;
    border-radius: 6px;
    box-shadow: inset 0 0 0 1px #e9ecef;
}

/* Price Display */
.price-display {
    margin-top: 8px;
    padding: 8px 12px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 8px;
    font-size: 14px;
}

.base-price {
    color: var(--dark-color);
    font-weight: 500;
}

.addon-total {
    color: var(--secondary-color);
    font-weight: 500;
}

.item-total {
    font-size: 15px;
    font-weight: 600;
    color: var(--dark-color);
}

/* Addons List Styling */
.addons-list {
    margin: 12px 0;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 3px solid var(--primary-color);
}

.addon-header {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.addon-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px dashed #e9ecef;
}

.addon-item:last-child {
    border-bottom: none;
}

.addon-name {
    font-size: 13px;
    color: var(--dark-color);
    display: flex;
    align-items: center;
    gap: 4px;
}

.addon-price {
    font-size: 13px;
    color: var(--secondary-color);
    font-weight: 500;
}

/* Special Request Styling */
.special-request {
    margin: 8px 0;
    padding: 8px 12px;
    background: #fff3cd;
    border-radius: 6px;
    border-left: 3px solid #ffc107;
}

.special-request small {
    font-size: 12px;
    color: #856404;
    font-style: italic;
}

/* Divider */
.cart-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, #e9ecef, transparent);
    margin: 12px 0;
}

/* Badge for addon indicator */
.addon-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    margin-left: 6px;
}
        
       /* Summary Row */
.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
}

.summary-row.total {
    font-size: 1.2em;
    font-weight: 600;
    border-top: 2px solid #dee2e6;
    padding-top: 10px;
    margin-top: 10px;
    color: var(--dark-color);
}
        
        /* Action Buttons */
.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 20px;
}

.btn-pos {
    padding: 12px;
    font-weight: 600;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.btn-checkout {
    background: var(--secondary-color);
    color: white;
}

.btn-checkout:hover {
    background: #27ae60;
    transform: translateY(-1px);
}

.btn-clear {
    background: var(--danger-color);
    color: white;
}

.btn-clear:hover {
    background: #c0392b;
    transform: translateY(-1px);
}
        
        /* Order Type */
        .order-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .order-type-btn {
            flex: 1;
            padding: 10px;
            border: 2px solid #dee2e6;
            background: white;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .order-type-btn.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
        
        /* Customer Info */
.customer-info {
    margin-bottom: 20px;
    flex-shrink: 0;
    padding: 15px;
    background: #fff;
    border-bottom: 1px solid #dee2e6;
}

.customer-info input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    margin-bottom: 10px;
    font-size: 14px;
}
        
        /* Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Search */
        .search-box {
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .mobile-close-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--danger-color);
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    cursor: pointer;
    z-index: 1002;
}
        
 /* Mobile View */
@media (max-width: 992px) {
    .pos-container {
        grid-template-columns: 1fr;
        grid-template-rows: 70px 1fr 60px;
        height: 100vh;
    }
    .cart-header {
        padding: 15px 50px 15px 20px;
     
        position: relative;
    }
    .cart-header .mobile-close-btn {
        top: 50%;
        transform: translateY(-50%);
        right: 10px;
    }
    
    .categories-sidebar {
        position: fixed;
        top: 70px;
        left: -100%;
        width: 100%;
        height: calc(100vh - 70px);
        z-index: 1000;
        background: white;
        transition: left 0.3s ease;
        display: block !important;
        padding: 20px;
    }
    
    .categories-sidebar.active {
        left: 0;
    }
    
    .cart-sidebar {
        position: fixed;
        top: 70px;
        right: -100%;
        width: 100%;
        height: calc(100vh - 70px);
        z-index: 1000;
        transition: right 0.3s ease;
        display: flex !important;
    }
    
    .cart-sidebar.active {
        right: 0;
    }
    
    .mobile-controls {
        display: flex;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: var(--dark-color);
        z-index: 1001;
        height: 60px;
    }
    
    .mobile-btn {
        flex: 1;
        background: none;
        border: none;
        color: white;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        gap: 5px;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }
    
    .mobile-btn:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    
    .mobile-btn.active {
        background: var(--primary-color);
    }
    
    .mobile-btn i {
        font-size: 18px;
    }
    
    /* Cart counter badge */
    .cart-badge {
        position: absolute;
        top: 5px;
        right: 10px;
        background: var(--danger-color);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Product grid adjustments */
    .products-grid {
        padding: 15px;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
    }
    
    .product-card {
        padding: 12px;
    }
    
    /* Modal adjustments */
    .modal-dialog {
        margin: 10px;
    }
}

        /* Addon Styles */
.addon-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    transition: all 0.2s;
}

.addon-card:hover {
    border-color: #3498db;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.addons-list {
    padding-left: 15px;
    border-left: 2px solid #e9ecef;
}

.addon-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.9em;
    padding: 2px 0;
}

.addon-quantity .input-group {
    width: 120px;
}

/* Product card addon indicator */
.product-card[data-has-addons="true"]::after {
    content: '+';
    position: absolute;
    top: 10px;
    right: 10px;
    width: 24px;
    height: 24px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

/* Glassmorphism Cart Items */
.cart-item.glass {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

/* Gradient Borders */
.cart-item.gradient-border {
    border: double 2px transparent;
    background-image: linear-gradient(white, white), 
                      linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    background-origin: border-box;
    background-clip: content-box, border-box;
}

/* Modern Quantity Toggle */
.quantity-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 16px;
}

.toggle-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 2px solid var(--primary-color);
    background: white;
    color: var(--primary-color);
    font-size: 18px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toggle-btn:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-2px);
}

.toggle-display {
    width: 60px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
    box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
}

/* Add to your existing CSS */
.product-name-container {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 8px;
}

.product-name {
    flex-grow: 1;
    font-weight: 600;
    font-size: 14px;
    line-height: 1.3;
}

.addon-indicator {
    color: var(--primary-color, #007bff);
    font-size: 14px;
    cursor: help;
    padding: 2px;
    background: rgba(0, 123, 255, 0.1);
    border-radius: 50%;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s;
}

.addon-indicator:hover {
    background: rgba(0, 123, 255, 0.2);
    transform: scale(1.1);
}

/* Alternative styles for a badge-style indicator */
.addon-indicator.badge-style {
    background: #28a745;
    color: white;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 600;
    width: auto;
    height: auto;
}

.addon-indicator.badge-style:hover {
    background: #218838;
}

/* For a more subtle approach */
.addon-indicator.subtle {
    color: #6c757d;
    background: transparent;
}

.addon-indicator.subtle:hover {
    color: var(--primary-color, #007bff);
}

.product-card {
    position: relative;
    /* ... your existing styles ... */
}

.product-name-container {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 8px;
}

.product-name {
    flex-grow: 1;
    font-weight: 600;
    font-size: 14px;
    line-height: 1.3;
}

.addon-indicator {
    color: var(--primary-color, #007bff);
    font-size: 14px;
    cursor: help;
    padding: 4px;
    background: rgba(0, 123, 255, 0.1);
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s;
    border: 1px solid rgba(0, 123, 255, 0.2);
}

.addon-indicator:hover {
    background: rgba(0, 123, 255, 0.2);
    transform: scale(1.1);
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.3);
}

/* Pulse animation for attention */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.addon-indicator.pulse {
    animation: pulse 2s infinite;
    background: linear-gradient(45deg, #007bff, #00bfff);
    color: white;
}

/* For global addons - different style */
.addon-indicator.global {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
    border-color: rgba(40, 167, 69, 0.2);
}

.addon-indicator.global:hover {
    background: rgba(40, 167, 69, 0.2);
    box-shadow: 0 0 8px rgba(40, 167, 69, 0.3);
}
    </style>
</head>
<body>
    <div class="pos-container">
        <!-- Header -->
        <header class="pos-header">
            <div class="header-left">
            
                <h5><img src="../uploads/samara.jpg" height="30px" width="30px" alt="Icon" class="header-icon me-2"> </h5>
            </div>
            <div class="header-right">
            <a class="mobile-btn fs-5" href="../display" id="mobile-checkout-btn">
                    <i class="fas fa-list"></i>
                    <span></span>
                </a>
                <div class="user-info">
                    <i class="fas fa-user-circle fa-lg"></i>
                    <div>
                        <div><strong><?php echo htmlspecialchars($user_name); ?></strong></div>
                        <small class="text-light"><?php echo ucfirst($user_role); ?></small>
                    </div>
                </div>
                
                <div id="current-time" class="text-light fs-6"></div>
                <?= ($_SESSION['role'] == 'admin') ? '<a href="dashboard.php" class="btn btn-info">Dashboard</a>' : '' ?>
                <a href="logout.php" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </header>
        
        <!-- Categories Sidebar -->
        <nav class="categories-sidebar">
        
            <div class="order-type-selector">
                
                
                <button class="order-type-btn active" data-type="walkin">Walk-in</button>
                <button class="order-type-btn" data-type="online">Online</button>
            </div>
            
            <div class="search-box px-3">
                <input type="text" id="search-products" placeholder="Search products...">
            </div>
            
            <div id="categories-list">
                <!-- Categories will be loaded here -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Products Grid -->
        <main class="products-grid" id="products-container">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </main>

        
        
        <!-- Cart Sidebar Structure -->
            <aside class="cart-sidebar">
                <!-- Header (fixed) -->
                <div class="cart-header">
                    <h4 class="mb-0"><i class="fas fa-shopping-cart me-2"></i> Current Order</h4>
                    <small id="order-number">New Order</small>
                </div>
                
                <!-- Customer Info (fixed) -->
                <div class="customer-info">
                    <input type="text" id="customer-name" placeholder="Customer name (optional)" class="mb-2">
                    <input type="text" id="customer-phone" placeholder="Phone number (optional)">
                </div>
                
                <!-- Scrollable Cart Items -->
                <div class="cart-items" id="cart-items">
                    <div class="text-center py-5">
                        <p class="text-muted">No items in cart</p>
                        <p><small>Add products from the list</small></p>
                    </div>
                </div>
                
                <!-- Fixed Footer with Summary and Buttons -->
                <div class="cart-footer">
                    <div id="order-summary">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">₱0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Tax (12%):</span>
                            <span id="tax">₱0.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span id="total">₱0.00</span>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button id="clear-cart" class="btn-pos btn-clear">
                            <i class="fas fa-trash me-2"></i> Clear
                        </button>
                        <button id="checkout-btn" class="btn-pos btn-checkout">
                            <i class="fas fa-check me-2"></i> Checkout
                        </button>
                    </div>
                </div>
            </aside>
    </div>

    <!-- Mobile Controls (visible only on mobile) -->
    <div class="mobile-controls">
        <button class="mobile-btn" id="mobile-categories-btn">
            <i class="fas fa-list"></i>
            <span>Categories</span>
        </button>
        <button class="mobile-btn active" id="mobile-products-btn">
            <i class="fas fa-th-large"></i>
            <span>Products</span>
        </button>
        <button class="mobile-btn" id="mobile-cart-btn">
            <i class="fas fa-shopping-cart"></i>
            <span>Cart</span>
            <span class="cart-badge" id="cart-count">0</span>
        </button>
        <a class="mobile-btn" href="../display" id="mobile-checkout-btn">
            <i class="fas fa-bell"></i>
            <span>Orders</span>
        </a>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container"></div>
    
    <!-- Modals -->
    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="order-summary mb-4">
                        <h6>Order Summary:</h6>
                        <div id="modal-summary"></div>
                    </div>
                    
                    <div class="payment-methods mb-3">
                        <h6>Payment Method:</h6>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="paymentMethod" id="cash" value="cash" checked>
                            <label class="btn btn-outline-primary" for="cash">Cash</label>
                            
                            <input type="radio" class="btn-check" name="paymentMethod" id="card" value="card">
                            <label class="btn btn-outline-primary" for="card">Card</label>
                            
                            <input type="radio" class="btn-check" name="paymentMethod" id="ewallet" value="ewallet">
                            <label class="btn btn-outline-primary" for="ewallet">E-Wallet</label>
                            
                            <input type="radio" class="btn-check" name="paymentMethod" id="online" value="online">
                            <label class="btn btn-outline-primary" for="online">Online</label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="cash-payment-section">
                        <label for="amount-tendered" class="form-label">Amount Tendered:</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" class="form-control" id="amount-tendered" step="0.01" min="0">
                        </div>
                        <div class="mt-2">
                            <strong>Change: <span id="change-amount">₱0.00</span></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="reference-section" style="display: none;">
                        <label for="reference-number" class="form-label">Reference Number:</label>
                        <input type="text" class="form-control" id="reference-number">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirm-checkout" class="btn btn-success">Complete Order</button>
                </div>
            </div>
        </div>
    </div>

<!-- Addon Selection Modal -->
<div class="modal fade" id="addonModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addonProductName"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="addonList" class="row">
                    <!-- Addons will be loaded here -->
                </div>
                <div class="special-request mt-4">
                    <label for="specialRequest" class="form-label">Special Instructions (Optional)</label>
                    <textarea class="form-control" id="specialRequest" rows="2" placeholder="E.g., No onions, extra sauce, etc."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addProductWithAddons()">Add to Cart</button>
            </div>
        </div>
    </div>
</div>


    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let cart = [];
        let currentCategory = null;
        let orderType = 'walkin';
        let taxRate = 0;
        
        // DOM Elements
        const categoriesList = document.getElementById('categories-list');
        const productsContainer = document.getElementById('products-container');
        const cartItems = document.getElementById('cart-items');
        const orderSummary = document.getElementById('order-summary');
        const searchInput = document.getElementById('search-products');
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateTime();
            setInterval(updateTime, 1000);
            
            loadCategories();
            loadProducts();
            setupEventListeners();
            updateCartDisplay();
            setupMobileView(); // ADD THIS
            updateCartCount(); // ADD THIS
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 992) {
                    // Reset to desktop view
                    document.querySelector('.products-grid').style.display = 'grid';
                    document.querySelector('.categories-sidebar').style.left = '';
                    document.querySelector('.cart-sidebar').style.right = '';
                } else {
                    setupMobileView();
                }
                updateCartHeight();
            });
        });
        
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = 
                now.toLocaleDateString() + ' ' + now.toLocaleTimeString();
        }
        
        function showToast(message, type = 'info') {
            const container = document.querySelector('.toast-container');
            const toast = document.createElement('div');
            toast.className = 'toast';
            
            let icon = 'info-circle';
            if (type === 'success') icon = 'check-circle';
            if (type === 'error') icon = 'exclamation-circle';
            if (type === 'warning') icon = 'exclamation-triangle';
            
            toast.innerHTML = `
                <i class="fas fa-${icon} text-${type}"></i>
                <span>${message}</span>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideIn 0.3s ease-out reverse';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        function loadCategories() {
            fetch('../api/get-categories.php')
                .then(response => response.json())
                .then(data => {
                    categoriesList.innerHTML = '';
                    
                    // Add "All Products" category
                    const allBtn = document.createElement('button');
                    allBtn.className = 'category-item active';
                    allBtn.innerHTML = '<i class="fas fa-th-large"></i> All Products';
                    allBtn.onclick = () => {
                        document.querySelectorAll('.category-item').forEach(btn => btn.classList.remove('active'));
                        allBtn.classList.add('active');
                        currentCategory = null;
                        loadProducts();
                    };
                    categoriesList.appendChild(allBtn);
                    
                    // Add other categories
                    data.forEach(category => {
                        const btn = document.createElement('button');
                        btn.className = 'category-item';
                        btn.innerHTML = `
                            <i class="fas fa-${getCategoryIcon(category.name)}"></i>
                            ${category.name}
                        `;
                        btn.onclick = () => {
                            document.querySelectorAll('.category-item').forEach(btn => btn.classList.remove('active'));
                            btn.classList.add('active');
                            currentCategory = category.id;
                            loadProducts();
                        };
                        categoriesList.appendChild(btn);
                    });
                })
                .catch(error => {
                    console.error('Error loading categories:', error);
                    showToast('Error loading categories', 'error');
                });
        }
        
        function getCategoryIcon(name) {
            const icons = {
                'Burgers': 'hamburger',
                'Pizza': 'pizza-slice',
                'Drinks': 'wine-glass',
                'Sides': 'utensils',
                'Desserts': 'ice-cream'
            };
            return icons[name] || 'tag';
        }
        
        function loadProducts(search = '') {
            let url = '../api/get-products.php';
            if (currentCategory) {
                url += `?category_id=${currentCategory}`;
            }
            if (search) {
                url += (url.includes('?') ? '&' : '?') + `search=${encodeURIComponent(search)}`;
            }
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    console.log("Products loaded:", data);
                    
                    productsContainer.innerHTML = '';
                    
                    if (data.length === 0) {
                        productsContainer.innerHTML = `
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No products found</p>
                            </div>
                        `;
                        return;
                    }
                    
                    data.forEach(product => {
                        const card = document.createElement('div');
                        card.className = `product-card ${product.stock <= 0 ? 'out-of-stock' : ''}`;
                        
                        card.onclick = () => {
                            if (product.stock > 0) {
                                addToCart(product);
                            }
                        };
                        
                        let stockClass = '';
                        let stockText = '';
                        if (product.stock <= 0) {
                            stockClass = 'stock-out';
                            stockText = 'Out of stock';
                        } else if (product.stock <= product.min_stock) {
                            stockClass = 'stock-low';
                            stockText = `Low stock: ${product.stock}`;
                        } else {
                            stockText = `In stock: ${product.stock}`;
                        }
                        // In displayProducts function
                        const hasAddons = parseInt(product.has_any_addons) === 1;

                        card.innerHTML = `
                            <div class="product-name-container">
                                <div class="product-name">${product.name}</div>
                                ${hasAddons ? 
                                    '<div class="addon-indicator" title="Has customizable options"><i class="fas fa-plus-circle"></i></div>' : 
                                    ''}
                            </div>
                            <div class="product-price">₱${parseFloat(product.price).toFixed(2)}</div>
                            <div class="product-stock ${stockClass}">${stockText}</div>
                        `;
                        
                        productsContainer.appendChild(card);
                    });
                })
                .catch(error => {
                    console.error('Error loading products:', error);
                    showToast('Error loading products', 'error');
                });
        }
        
        function setupEventListeners() {
            // Order type buttons
            document.querySelectorAll('.order-type-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.order-type-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    orderType = this.dataset.type;
                });
            });
            
            // Search
            searchInput.addEventListener('input', function() {
                loadProducts(this.value);
            });
            
            // Clear cart
            document.getElementById('clear-cart').addEventListener('click', function() {
                if (cart.length > 0) {
                    if (confirm('Clear all items from cart?')) {
                        cart = [];
                        updateCartDisplay();
                        showToast('Cart cleared', 'info');
                    }
                }
            });
            
            // Checkout
            document.getElementById('checkout-btn').addEventListener('click', function() {
                if (cart.length === 0) {
                    showToast('Cart is empty', 'warning');
                    return;
                }
                
                showCheckoutModal();
            });
            
            // Payment method changes
            document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const method = this.value;
                    document.getElementById('cash-payment-section').style.display = 
                        method === 'cash' ? 'block' : 'none';
                    document.getElementById('reference-section').style.display = 
                        method !== 'cash' ? 'block' : 'none';
                    
                    if (method === 'cash') {
                        updateChangeAmount();
                    }
                });
            });
            
            // Amount tendered changes
            document.getElementById('amount-tendered')?.addEventListener('input', updateChangeAmount);
            
            // Confirm checkout
            document.getElementById('confirm-checkout')?.addEventListener('click', processCheckout);
        }
        
        function addToCart(product) {
            console.log("addToCart called for:", product.name);
            
            // Always check if this product has addons (global or specific)
            fetch(`../api/get-addons.php?product_id=${product.id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.addons && data.addons.length > 0) {
                        console.log(`Found ${data.addons.length} addons for ${product.name}`);
                        showAddonModal(product);
                    } else {
                        console.log(`No addons found for ${product.name}`);
                        addProductToCart(product);
                    }
                })
                .catch(error => {
                    console.error('Error checking addons:', error);
                    // If error, add without addons
                    addProductToCart(product);
                });
        }

       function addProductToCart(product, addons = [], specialRequest = '') {
            // Calculate addon total
            const addonTotal = addons.reduce((sum, addon) => sum + (addon.price * addon.quantity), 0);
            
            // Check if this exact combination already exists in cart
            const existingItemIndex = findExistingCartItem(product.id, addons, specialRequest);
            
            if (existingItemIndex !== -1) {
                // Found exact match, increase quantity
                const existingItem = cart[existingItemIndex];
                if (product.stock && existingItem.quantity >= product.stock) {
                    showToast(`Only ${product.stock} available in stock`, 'warning');
                    return;
                }
                existingItem.quantity++;
                updateCartDisplay();
                showToast(`${product.name} added to cart`, 'success');
            } else {
                // New combination, add as new item
                cart.push({
                    product_id: product.id,
                    name: product.name,
                    price: parseFloat(product.price),
                    quantity: 1,
                    stock: product.stock,
                    has_addons: parseInt(product.has_addons) === 1,
                    addons: addons,
                    addon_total: addonTotal,
                    special_request: specialRequest || null
                });
                updateCartDisplay();
                showToast(`${product.name} added to cart${addons.length > 0 ? ' with addons' : ''}`, 'success');
            }
            
            updateCartCount();
        }

        function findExistingCartItem(productId, addons, specialRequest) {
            // Convert addons array to a comparable string
            const addonsKey = JSON.stringify(addons.map(a => ({addon_id: a.addon_id, quantity: a.quantity})).sort());
            
            return cart.findIndex(item => {
                // Check if same product
                if (item.product_id !== productId) return false;
                
                // Check if same special request (both null or same string)
                if ((item.special_request || '') !== (specialRequest || '')) return false;
                
                // Check if same addons
                const itemAddonsKey = JSON.stringify(
                    (item.addons || []).map(a => ({addon_id: a.addon_id, quantity: a.quantity})).sort()
                );
                
                return itemAddonsKey === addonsKey;
            });
        }

let currentProductForAddons = null;


function updateAddonQuantity(addonId, change, maxQty) {
    const input = document.getElementById(`addon_qty_${addonId}`);
    let currentQty = parseInt(input.value) || 0;
    let newQty = currentQty + change;
    
    if (newQty >= 0 && newQty <= maxQty) {
        input.value = newQty;
        
        // Add visual feedback
        if (change > 0) {
            input.style.backgroundColor = '#e8f5e8';
            setTimeout(() => {
                input.style.backgroundColor = '';
            }, 200);
        } else {
            input.style.backgroundColor = '#fff3cd';
            setTimeout(() => {
                input.style.backgroundColor = '';
            }, 200);
        }
    }
}

function addProductWithAddons() {
    if (!currentProductForAddons) return;
    
    const addons = [];
    let totalAddonPrice = 0;
    
    // Collect selected addons
    document.querySelectorAll('[id^="addon_qty_"]').forEach(input => {
        const addonId = input.id.replace('addon_qty_', '');
        const quantity = parseInt(input.value) || 0;
        
        if (quantity > 0) {
            // Get addon details from DOM
            const card = input.closest('.addon-card');
            if (!card) return;
            
            const addonName = card.querySelector('h6')?.textContent || `Addon ${addonId}`;
            const priceText = card.querySelector('.text-success')?.textContent;
            const price = priceText ? parseFloat(priceText.replace('+₱', '')) : 0;
            
            addons.push({
                addon_id: parseInt(addonId),
                name: addonName,
                quantity: quantity,
                price: price
            });
            
            totalAddonPrice += price * quantity;
        }
    });
    
    const specialRequest = document.getElementById('specialRequest').value.trim();
    
    // Add to cart
    addProductToCart(currentProductForAddons, addons, specialRequest);
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('addonModal'));
    modal.hide();
    
    // Reset modal
    document.getElementById('specialRequest').value = '';
    document.querySelectorAll('[id^="addon_qty_"]').forEach(input => {
        input.value = '0';
    });
}

function updateCartCount() {
    const cartCount = document.getElementById('cart-count');
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    cartCount.textContent = totalItems;
    
    // Show/hide badge
    cartCount.style.display = totalItems > 0 ? 'flex' : 'none';
}
        
function updateCartDisplay() {
    if (cart.length === 0) {
        cartItems.innerHTML = `
            <div class="text-center py-5">
                <p class="text-muted">No items in cart</p>
                <p><small>Add products from the list</small></p>
            </div>
        `;
        updateOrderSummary();
        updateCartHeight(); // ADD THIS
        updateCartCount();
        return;
    }
    
    let html = '';
    
    cart.forEach((item, index) => {
        // Calculate item total including addons
        let itemBasePrice = item.price;
        let itemAddonTotal = 0;
        
        if (item.addons && item.addons.length > 0) {
            itemAddonTotal = item.addons.reduce((sum, addon) => sum + (addon.price * addon.quantity), 0);
        }
        
        const itemTotal = (itemBasePrice + itemAddonTotal) * item.quantity;
        
        html += `
                <div class="cart-item">
                    <div class="cart-item-header">
                        <div>
                            <strong>${escapeHtml(item.name)}</strong>
                            ${item.has_addons ? '<span class="addon-badge"><i class="fas fa-plus-circle fa-xs"></i> Addons</span>' : ''}
                            ${item.special_request ? `
                                <div class="special-request">
                                    <small><i class="fas fa-sticky-note me-1"></i>${escapeHtml(item.special_request)}</small>
                                </div>
                            ` : ''}
                        </div>
                        <div class="cart-item-actions">
                            <button class="remove-btn" onclick="removeFromCart(${index})" title="Remove item">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    ${item.addons && item.addons.length > 0 ? `
                        <div class="addons-list">
                            <div class="addon-header">
                                <i class="fas fa-plus-circle fa-xs"></i>
                                <span>Selected Addons</span>
                            </div>
                            ${item.addons.map(addon => `
                                <div class="addon-item">
                                    <div class="addon-name">
                                        <i class="fas fa-circle fa-2xs" style="color: ${getRandomColor()}"></i>
                                        ${addon.quantity}× ${escapeHtml(addon.name || `Addon #${addon.addon_id}`)}
                                    </div>
                                    <div class="addon-price">
                                        +₱${(addon.price * addon.quantity).toFixed(2)}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                    
                    <div class="cart-divider"></div>
                    
                    <div class="price-display">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="base-price">₱${itemBasePrice.toFixed(2)}</span>
                            ${itemAddonTotal > 0 ? `
                                <span class="addon-total">+₱${itemAddonTotal.toFixed(2)} addons</span>
                            ` : ''}
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">× ${item.quantity}</span>
                            <span class="item-total">₱${itemTotal.toFixed(2)}</span>
                        </div>
                    </div>
                    
                    <div class="quantity-controls">
                        <button class="quantity-btn ${item.quantity <= 1 ? 'disabled' : ''}" 
                                onclick="updateQuantity(${index}, -1)"
                                ${item.quantity <= 1 ? 'disabled' : ''}>
                            <i class="fas fa-minus"></i>
                        </button>
                        <div class="quantity-display" id="quantity-${index}">
                            ${item.quantity}
                        </div>
                        <button class="quantity-btn" onclick="updateQuantity(${index}, 1)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
            `;
    });
    
    cartItems.innerHTML = html;
    updateOrderSummary();
    updateCartHeight(); // ADD THIS - Update cart height after content changes
}

// Helper function to get addon name (would need to be implemented properly)
function getAddonName(addonId) {
    // This is a placeholder - you'd need to store addon names or fetch them
    return `Addon #${addonId}`;
}

function updateOrderSummary() {
    let subtotal = 0;
    
    cart.forEach(item => {
        let itemPrice = item.price;
        let itemAddonTotal = 0;
        
        if (item.addons && item.addons.length > 0) {
            itemAddonTotal = item.addons.reduce((sum, addon) => sum + (addon.price * addon.quantity), 0);
        }
        
        subtotal += (itemPrice + itemAddonTotal) * item.quantity;
    });
    
    const tax = subtotal * taxRate;
    const total = subtotal + tax;
    
    document.getElementById('subtotal').textContent = `₱${subtotal.toFixed(2)}`;
    document.getElementById('tax').textContent = `₱${tax.toFixed(2)}`;
    document.getElementById('total').textContent = `₱${total.toFixed(2)}`;
    document.getElementById('checkout-btn').disabled = false;
}
        
        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
            showToast('Item removed from cart', 'info');
        }
        
        function updateQuantity(index, change) {
            const item = cart[index];
            const newQuantity = item.quantity + change;
            
            if (newQuantity < 1) {
                // Animate removal
                const cartItem = document.querySelector(`.cart-item:nth-child(${index + 1})`);
                if (cartItem) {
                    cartItem.style.transform = 'translateX(100%)';
                    cartItem.style.opacity = '0';
                    setTimeout(() => {
                        removeFromCart(index);
                    }, 300);
                }
                return;
            }
            
            if (item.stock && newQuantity > item.stock) {
                showToast(`Only ${item.stock} available in stock`, 'warning');
                return;
            }
            
            // Animate quantity change
            const quantityDisplay = document.getElementById(`quantity-${index}`);
            if (quantityDisplay) {
                quantityDisplay.style.transform = 'scale(1.2)';
                quantityDisplay.style.color = change > 0 ? 'var(--secondary-color)' : 'var(--danger-color)';
                setTimeout(() => {
                    item.quantity = newQuantity;
                    updateCartDisplay();
                }, 150);
            } else {
                item.quantity = newQuantity;
                updateCartDisplay();
            }
            
            // Show visual feedback
            const btn = event.target.closest('.quantity-btn');
            if (btn) {
                btn.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    btn.style.transform = '';
                }, 150);
            }
        }
        
        
        function updateChangeAmount() {
            const amountTendered = parseFloat(document.getElementById('amount-tendered').value) || 0;
            
            // Calculate correct subtotal including addons
            const subtotal = cart.reduce((sum, item) => {
                const itemPrice = item.price;
                const itemAddonTotal = item.addon_total || 0;
                return sum + ((itemPrice + itemAddonTotal) * item.quantity);
            }, 0);
            
            const tax = subtotal * taxRate;
            const total = subtotal + tax;
            const change = amountTendered - total;
            
            document.getElementById('change-amount').textContent = 
                change >= 0 ? `₱${change.toFixed(2)}` : `-₱${Math.abs(change).toFixed(2)}`;
            document.getElementById('change-amount').style.color = 
                change >= 0 ? 'green' : 'red';
        }
        
        function processCheckout() {
            const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
            const customerName = document.getElementById('customer-name').value.trim();
            const customerPhone = document.getElementById('customer-phone').value.trim();
            
            if (paymentMethod === 'cash') {
                const amountTendered = parseFloat(document.getElementById('amount-tendered').value) || 0;
                
                // Calculate correct total including addons
                const subtotal = cart.reduce((sum, item) => {
                    const itemPrice = item.price;
                    const itemAddonTotal = item.addon_total || 0;
                    return sum + ((itemPrice + itemAddonTotal) * item.quantity);
                }, 0);
                
                const tax = subtotal * taxRate;
                const total = subtotal + tax;
                
                if (amountTendered < total) {
                    showToast('Amount tendered is less than total amount', 'error');
                    return;
                }
            }
            
            const orderData = {
                order_type: orderType,
                customer_nickname: customerName || null,
                customer_phone: customerPhone || null,
                items: cart,
                payment_method: paymentMethod,
                payment_reference: document.getElementById('reference-number').value.trim() || null,
                created_by: <?php echo $user_id; ?>
            };
            
            fetch('../api/create-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(orderData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('checkoutModal'));
                    modal.hide();
                    
                    cart = [];
                    updateCartDisplay();
                    document.getElementById('customer-name').value = '';
                    document.getElementById('customer-phone').value = '';
                    
                    showToast(`Order #${data.order_number} created successfully!`, 'success');
                    
                    // Print receipt if needed
                    if (<?php echo $settings['pos_auto_print'] === 'true' ? 'true' : 'false'; ?>) {
                        printReceipt(data.order_id);
                    }
                } else {
                    showToast(data.message || 'Error creating order', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
            });
        }
        
        function printReceipt(orderId) {
            // Implement receipt printing here
            console.log('Printing receipt for order:', orderId);
        }

        // Add this to your pos.php JavaScript section
function updateCartHeight() {
    const cartItems = document.getElementById('cart-items');
    const cartHeader = document.querySelector('.cart-header');
    const customerInfo = document.querySelector('.customer-info');
    const cartFooter = document.querySelector('.cart-footer');
    
    if (!cartHeader || !customerInfo || !cartFooter || !cartItems) {
        return; // Elements not found yet
    }
    
    const headerHeight = cartHeader.offsetHeight;
    const customerInfoHeight = customerInfo.offsetHeight;
    const footerHeight = cartFooter.offsetHeight;
    const totalHeight = window.innerHeight;
    
    // Calculate available height for cart items
    const availableHeight = totalHeight - headerHeight - customerInfoHeight - footerHeight - 40;
    
    // Set max height for cart items
    if (availableHeight > 100) { // Only apply if reasonable height
        cartItems.style.maxHeight = availableHeight + 'px';
        cartItems.style.overflowY = 'auto';
    }
}

// Call on load and resize
window.addEventListener('load', updateCartHeight);
window.addEventListener('resize', updateCartHeight);

// Add to your existing setupMobileView() function
function setupMobileView() {
    // Only setup mobile controls if on mobile
    if (window.innerWidth <= 992) {
        const categoriesBtn = document.getElementById('mobile-categories-btn');
        const productsBtn = document.getElementById('mobile-products-btn');
        const cartBtn = document.getElementById('mobile-cart-btn');
        const checkoutBtn = document.getElementById('mobile-checkout-btn');
        const mobileControls = document.querySelector('.mobile-controls');
        
        // Show products by default
        document.querySelector('.products-grid').style.display = 'grid';
        document.querySelector('.categories-sidebar').classList.remove('active');
        document.querySelector('.cart-sidebar').classList.remove('active');
        mobileControls.style.display = 'flex'; // Show by default
        
        // Categories button
        categoriesBtn.addEventListener('click', function() {
            document.querySelector('.categories-sidebar').classList.add('active');
            document.querySelector('.products-grid').style.display = 'none';
            document.querySelector('.cart-sidebar').classList.remove('active');
            mobileControls.style.display = 'flex'; // Keep visible
            updateMobileButtons('categories');
        });
        
        // Products button
        productsBtn.addEventListener('click', function() {
            document.querySelector('.categories-sidebar').classList.remove('active');
            document.querySelector('.products-grid').style.display = 'grid';
            document.querySelector('.cart-sidebar').classList.remove('active');
            mobileControls.style.display = 'flex'; // Keep visible
            updateMobileButtons('products');
        });
        
        // Cart button - hide mobile controls when cart is open
        cartBtn.addEventListener('click', function() {
            document.querySelector('.categories-sidebar').classList.remove('active');
            document.querySelector('.products-grid').style.display = 'none';
            document.querySelector('.cart-sidebar').classList.add('active');
            mobileControls.style.display = 'none'; // Hide mobile controls
            updateMobileButtons('cart');
        });
        
        // Checkout button
        checkoutBtn.addEventListener('click', function() {
            if (cart.length === 0) {
                showToast('Cart is empty', 'warning');
                return;
            }
            showCheckoutModal();
        });
        
        // Add close button to cart sidebar for mobile
        const cartHeader = document.querySelector('.cart-header');
        if (cartHeader && !cartHeader.querySelector('.mobile-close-btn')) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'mobile-close-btn';
            closeBtn.innerHTML = '<i class="fas fa-times"></i>';
            closeBtn.onclick = function() {
                document.querySelector('.cart-sidebar').classList.remove('active');
                document.querySelector('.products-grid').style.display = 'grid';
                mobileControls.style.display = 'flex'; // Show mobile controls again
                updateMobileButtons('products');
            };
            cartHeader.appendChild(closeBtn);
        }
    }
}

function updateMobileButtons(activeBtn) {
    if (window.innerWidth > 992) return;
    
    const btns = document.querySelectorAll('.mobile-btn');
    btns.forEach(btn => btn.classList.remove('active'));
    
    switch(activeBtn) {
        case 'categories':
            document.getElementById('mobile-categories-btn').classList.add('active');
            break;
        case 'products':
            document.getElementById('mobile-products-btn').classList.add('active');
            break;
        case 'cart':
            document.getElementById('mobile-cart-btn').classList.add('active');
            break;
    }
}

// For Debug

function showAddonModal(product) {
    console.log("showAddonModal called for product:", product);
    currentProductForAddons = product;
    
    document.getElementById('addonProductName').textContent = product.name;
    document.getElementById('specialRequest').value = '';
    
    // Load addons for this product
    console.log("Fetching addons for product_id:", product.id);
    fetch(`../api/get-addons.php?product_id=${product.id}`)
        .then(response => {
            console.log("API Response status:", response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log("Addons data received:", data);
            const addonList = document.getElementById('addonList');
            
            if (data.success && data.addons && data.addons.length > 0) {
                console.log(`Found ${data.addons.length} addons:`, data.addons);
                let html = '<h6 class="mb-3">Available Addons:</h6>';
                
                data.addons.forEach(addon => {
                    const maxQty = addon.max_quantity || 1;
                    console.log("Processing addon:", addon);
                    html += `
                        <div class="col-md-6 mb-3">
                            <div class="card addon-card" data-addon-id="${addon.id}">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">${escapeHtml(addon.name)}</h6>
                                            <p class="text-muted mb-1" style="font-size: 0.9em;">${escapeHtml(addon.description || '')}</p>
                                            <strong class="text-success">+₱${parseFloat(addon.price).toFixed(2)}</strong>
                                        </div>
                                        <div class="addon-quantity">
                                            <div class="input-group input-group-sm" style="width: 120px;">
                                                <button class="btn btn-outline-secondary" type="button" onclick="updateAddonQuantity('${addon.id}', -1, ${maxQty})">-</button>
                                                <input type="text" class="form-control text-center" id="addon_qty_${addon.id}" value="0" readonly>
                                                <button class="btn btn-outline-secondary" type="button" onclick="updateAddonQuantity('${addon.id}', 1, ${maxQty})">+</button>
                                            </div>
                                            <small class="text-muted d-block mt-1">Max: ${maxQty}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                addonList.innerHTML = html;
            } else {
                console.log("No addons found or error in response");
                addonList.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-plus-circle fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No addons available for this product</p>
                        <p><small>Debug: ${data.message || 'No addons configured'}</small></p>
                        <button class="btn btn-sm btn-primary mt-2" onclick="addProductToCart(currentProductForAddons, [], '')">
                            Add without addons
                        </button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading addons:', error);
            document.getElementById('addonList').innerHTML = `
                <div class="text-center py-4">
                    <p class="text-danger">Error loading addons</p>
                    <p><small>${error.message}</small></p>
                    <button class="btn btn-sm btn-primary mt-2" onclick="addProductToCart(currentProductForAddons, [], '')">
                        Add without addons
                    </button>
                </div>
            `;
        });
    
    const modal = new bootstrap.Modal(document.getElementById('addonModal'));
    modal.show();
}

function showCheckoutModal() {
    console.log("showCheckoutModal called");
    
    if (cart.length === 0) {
        showToast('Cart is empty', 'warning');
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
    
    // Update modal summary
    let summaryHtml = '';
    let subtotal = 0;
    
    cart.forEach((item, index) => {
        // Calculate item total including addons
        let itemBasePrice = item.price;
        let itemAddonTotal = item.addon_total || 0;
        let addonsHtml = '';
        
        if (item.addons && item.addons.length > 0) {
            itemAddonTotal = item.addons.reduce((sum, addon) => sum + (addon.price * addon.quantity), 0);
            
            // Build addons list HTML
            addonsHtml = '<div class="ms-3 small text-muted">';
            item.addons.forEach(addon => {
                addonsHtml += `
                    <div class="d-flex justify-content-between">
                        <span>+ ${addon.quantity}× ${escapeHtml(addon.name || `Addon #${addon.addon_id}`)}</span>
                        <span>+₱${(addon.price * addon.quantity).toFixed(2)}</span>
                    </div>
                `;
            });
            addonsHtml += '</div>';
        }
        
        const itemTotal = (itemBasePrice + itemAddonTotal) * item.quantity;
        subtotal += itemTotal;
        
        summaryHtml += `
            <div class="mb-2">
                <div class="d-flex justify-content-between">
                    <span>${item.quantity}× ${escapeHtml(item.name)}</span>
                    <span>₱${itemTotal.toFixed(2)}</span>
                </div>
                ${addonsHtml}
                ${item.special_request ? `<div class="ms-3 small"><i>"${escapeHtml(item.special_request)}"</i></div>` : ''}
            </div>
        `;
    });
    
    const tax = subtotal * taxRate;
    const total = subtotal + tax;
    
    summaryHtml += `
        <hr>
        <div class="d-flex justify-content-between">
            <strong>Subtotal:</strong>
            <strong>₱${subtotal.toFixed(2)}</strong>
        </div>
        <div class="d-flex justify-content-between">
            <span>Tax (0%):</span>
            <span>₱${tax.toFixed(2)}</span>
        </div>
        <div class="d-flex justify-content-between fw-bold">
            <span>Total:</span>
            <span>₱${total.toFixed(2)}</span>
        </div>
    `;
    
    document.getElementById('modal-summary').innerHTML = summaryHtml;
    
    // Reset payment fields
    document.getElementById('amount-tendered').value = '';
    document.getElementById('reference-number').value = '';
    document.getElementById('change-amount').textContent = '₱0.00';
    document.getElementById('cash').checked = true;
    document.getElementById('cash-payment-section').style.display = 'block';
    document.getElementById('reference-section').style.display = 'none';
    
    modal.show();
}

// For Debug

function getRandomColor() {
    const colors = [
        '#3498db', '#2ecc71', '#e74c3c', '#f39c12', 
        '#9b59b6', '#1abc9c', '#d35400', '#34495e',
        '#e67e22', '#27ae60', '#2980b9', '#8e44ad'
    ];
    return colors[Math.floor(Math.random() * colors.length)];
}

// function testAddonDetection() {
//     // Test specific products
//     const testProducts = [13, 1, 6]; // Brown Sugar 16oz, Americano 16oz, etc.
    
//     testProducts.forEach(productId => {
//         console.log(`=== Testing Product ID: ${productId} ===`);
        
//         // Check via API
//         fetch(`../api/get-addons.php?product_id=${productId}`)
//             .then(response => response.json())
//             .then(data => {
//                 console.log(`Product ${productId} - API Response:`, data);
//                 console.log(`Has addons: ${data.success && data.addons && data.addons.length > 0}`);
//                 console.log(`Addon count: ${data.addons ? data.addons.length : 0}`);
                
//                 if (data.addons && data.addons.length > 0) {
//                     data.addons.forEach(addon => {
//                         console.log(`  - ${addon.name} (Global: ${addon.is_global}, Price: ₱${addon.price})`);
//                     });
//                 }
//             })
//             .catch(error => {
//                 console.error(`Error checking product ${productId}:`, error);
//             });
//     });
// }

// Call this in your console to test
// testAddonDetection();

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
    </script>
</body>
</html>