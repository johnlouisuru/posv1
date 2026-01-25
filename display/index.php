<?php
// display/index.php (DEBUG VERSION)
require_once '../includes/db.php';
require_once '../includes/display-functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header('Location: ../admin/login.php');
    exit();
}

// Get display settings
$settings = getDisplaySettings();
$maxItems = $settings['max_display_items'] ?? 10;
$orders = getProcessingOrders($maxItems);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['display_title'] ?? 'Processing Orders'); ?></title>
    <link rel="icon" type="image/png" href="../uploads/samara.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/display.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --waiting-color: #95a5a6;
        }
        
        /* Notifications */
        .display-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInRight 0.3s ease-out;
            max-width: 400px;
        }

        .notification-success {
            border-left: 4px solid #2ecc71;
        }

        .notification-error {
            border-left: 4px solid #e74c3c;
        }

        .notification-warning {
            border-left: 4px solid #f39c12;
        }

        .notification-info {
            border-left: 4px solid #3498db;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Loading state */
        #orders-container.loading {
            position: relative;
            opacity: 0.7;
            pointer-events: none;
        }

        #orders-container.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            z-index: 1000;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Button loading states */
        .btn-disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Addons styling */
        .addons-indicator {
            font-size: 0.7em;
            padding: 2px 6px;
            cursor: help;
        }

        .addons-container {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 5px;
        }

        .addon-item {
            border-bottom: 1px dashed #dee2e6;
        }

        .addon-item:last-child {
            border-bottom: none;
        }

        .addon-name {
            color: #495057;
        }

        /* Enhanced item styling with addons */
        .item-with-addons {
            background: linear-gradient(90deg, #f8f9fa 0%, #ffffff 100%);
            border-left: 3px solid var(--primary-color);
        }

        /* Animation for new addons */
        @keyframes highlightAddon {
            0% { background-color: #e8f4fd; }
            100% { background-color: transparent; }
        }

        .highlight-addon {
            animation: highlightAddon 2s ease-out;
        }
        
        /* Fullscreen styling */
        body:fullscreen .display-header,
        body:-webkit-full-screen .display-header {
            background-color: rgba(0,0,0,0.9);
        }
        /* Add these styles to your existing display.css */
        .order-card {
            transition: all 0.3s ease;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .status-waiting { background-color: #f8f9fa; }
        .status-preparing { background-color: #fff3cd; }
        .status-ready { background-color: #d1ecf1; }
        .status-completed { background-color: #d4edda; }

        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body class="display-body">
    <!-- Header -->
    <header class="display-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-title">
                        <img src="../uploads/samara.jpg" height="50px" width="50px" alt="Icon" class="header-icon">
                        <?php echo htmlspecialchars($settings['display_title'] ?? 'Processing Orders'); ?>
                    </h1>
                    <div class="display-subtitle">
                        <span id="current-time"></span>
                        • 
                        <span id="order-count"><?php echo count($orders); ?> active orders</span>
                        •
                        <span id="last-updated">Updated: Just now</span>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="display-controls">
                        <button id="refreshBtn" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button id="fullscreenToggle" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-expand"></i> Fullscreen
                        </button>
                        <div class="form-check form-switch d-inline-block ms-2">
                            <input class="form-check-input" type="checkbox" id="soundToggle" checked>
                            <label class="form-check-label text-light" for="soundToggle">Sound</label>
                        </div>
                        <a class="btn btn-sm btn-outline-light" href="../admin/pos.php">
                            <i class="fas fa-list"></i> Cashier Menu
                        </a>
                        <a class="btn btn-sm btn-outline-light" href="../admin/logout.php">
                            <i class="fas fa-door-open"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mt-4">
        <div id="orders-container" class="row">
            <?php if (empty($orders)): ?>
                <div class="no-orders text-center py-5">
                    <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                    <h3 class="text-muted">No orders in progress</h3>
                    <p class="text-muted">Waiting for new orders...</p>
                </div>
            <?php else: ?>
                <!-- Initial static content while JS loads -->
                <?php foreach ($orders as $order): ?>
                    <?php
                    $items = getOrderItemsForDisplay($order['order_id']);
                    $statusClass = 'status-' . $order['status'];
                    $statusIcons = [
                        'waiting' => 'fas fa-clock',
                        'preparing' => 'fas fa-utensils',
                        'ready' => 'fas fa-check-circle',
                        'completed' => 'fas fa-box'
                    ];
                    ?>
                    
                    <div class="col-lg-6 col-md-6 mb-4">
                        <div class="order-card <?php echo $statusClass; ?>">
                            <div class="order-header">
                                <div class="order-meta">
                                    <span class="order-number badge bg-dark">
                                        <?php echo htmlspecialchars($order['order_number']); ?>
                                    </span>
                                    <span class="order-type badge bg-secondary">
                                        <?php echo strtoupper($order['order_type']); ?>
                                    </span>
                                </div>
                                <h5 class="customer-name">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($order['display_name']); ?> 
                                </h5>
                                <div class="order-timing">
                                    <span class="order-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('h:i A', strtotime($order['order_created'])); ?>
                                    </span>
                                    •
                                    <span class="estimated-time">
                                        <i class="fas fa-hourglass-half"></i>
                                        Est: <?php echo $order['estimated_time']; ?> min
                                    </span>
                                </div>
                            </div>
                            
                            <div class="order-body">
                                <div class="order-items">
                                    <h6 class="items-title mb-2">
                                        <i class="fas fa-list me-2"></i>Order Items
                                    </h6>
                                    
                                    <ul class="item-list list-unstyled">
                                        <?php if (empty($items)): ?>
                                            <li class="no-items text-muted py-2 px-3 rounded bg-light">
                                                <i class="fas fa-info-circle me-2"></i>No items found
                                            </li>
                                        <?php else: ?>
                                            <?php foreach ($items as $index => $item): ?>
                                                <li class="item-entry <?php echo $index === 0 ? 'first-item' : ''; ?> <?php echo $index === count($items) - 1 ? 'last-item' : ''; ?>">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <div class="item-info">
                                                            <span class="item-quantity badge bg-secondary me-2">
                                                                <?php echo $item['quantity']; ?>x
                                                            </span>
                                                            <span class="item-name fw-semibold">
                                                                <?php echo htmlspecialchars($item['name']); ?>
                                                            </span>
                                                            <?php if (!empty($item['addons']) && count($item['addons']) > 0): ?>
                                                                <span class="addons-indicator badge bg-info ms-1" 
                                                                      title="<?php echo count($item['addons']); ?> addon(s)">
                                                                    <i class="fas fa-plus-circle fa-xs"></i>
                                                                    <?php echo count($item['addons']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <?php if (!empty($item['category'])): ?>
                                                            <span class="item-category badge bg-light text-dark border">
                                                                <?php echo htmlspecialchars($item['category']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Display Addons -->
                                                    <?php if (!empty($item['addons']) && count($item['addons']) > 0): ?>
                                                        <div class="addons-container mt-1 mb-2">
                                                            <div class="addons-list ps-3" style="border-left: 2px solid #3498db;">
                                                                <?php foreach ($item['addons'] as $addon): ?>
                                                                    <div class="addon-item d-flex justify-content-between align-items-center py-1">
                                                                        <div class="addon-info">
                                                                            <i class="fas fa-circle fa-2xs text-primary me-1"></i>
                                                                            <span class="addon-name text-muted" style="font-size: 0.85em;">
                                                                                <?php echo htmlspecialchars($addon['name'] ?? 'Addon'); ?>
                                                                                <?php if (isset($addon['quantity']) && $addon['quantity'] > 1): ?>
                                                                                    <small class="text-muted">(x<?php echo $addon['quantity']; ?>)</small>
                                                                                <?php endif; ?>
                                                                            </span>
                                                                        </div>
                                                                        <?php if (isset($addon['price']) && $addon['price'] > 0): ?>
                                                                            <span class="addon-price text-success" style="font-size: 0.85em;">
                                                                                +₱<?php echo number_format($addon['price'] * ($addon['quantity'] ?? 1), 2); ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($item['special_request'])): ?>
                                                        <div class="special-request alert alert-warning alert-sm py-1 px-2 mb-0 mt-1">
                                                            <i class="fas fa-sticky-note me-1"></i>
                                                            <small class="request-text">
                                                                <?php echo htmlspecialchars($item['special_request']); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                
                                <div class="order-footer">
                                    <div class="status-indicator">
                                        <i class="<?php echo $statusIcons[$order['status']]; ?>"></i>
                                        <span class="status-text"><?php echo ucfirst($order['status']); ?></span>
                                    </div>
                                    <div class="order-amount">
                                        <i class="fas fa-receipt"></i>
                                        ₱<?php echo number_format($order['total_amount'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Enhanced order-actions section -->
                            <div class="order-actions">
                                <?php 
                                $currentStatus = $order['status'];
                                $orderNumber = htmlspecialchars($order['order_number']);
                                $orderId = $order['order_id'];
                                ?>
                                
                                <!-- Status progression buttons -->
<?php if ($currentStatus == 'waiting'): ?>
    <button class="btn btn-sm btn-primary" 
            onclick="displayManager.updateOrderStatus(<?php echo $orderId; ?>, 'completed', '<?php echo $orderNumber; ?>')"
            title="Serve order immediately">
        <i class="fas fa-check-circle"></i> Mark Served
    </button>
    <button class="btn btn-sm btn-warning" 
            onclick="displayManager.updateOrderStatus(<?php echo $orderId; ?>, 'preparing', '<?php echo $orderNumber; ?>')"
            title="Start preparing order">
        <i class="fas fa-play"></i> Start Prep
    </button>
    
<?php elseif ($currentStatus == 'preparing'): ?>
<button class="btn btn-sm btn-primary" 
            onclick="displayManager.updateOrderStatus(<?php echo $orderId; ?>, 'completed', '<?php echo $orderNumber; ?>')"
            title="Serve order immediately">
        <i class="fas fa-check-circle"></i> Mark Served
    </button>
    <button class="btn btn-sm btn-success" 
            onclick="displayManager.updateOrderStatus(<?php echo $orderId; ?>, 'ready', '<?php echo $orderNumber; ?>')"
            title="Mark order as ready for pickup">
        <i class="fas fa-check"></i> Mark Ready
    </button>
    
<?php elseif ($currentStatus == 'ready'): ?>
    <button class="btn btn-sm btn-primary" 
            onclick="displayManager.updateOrderStatus(<?php echo $orderId; ?>, 'completed', '<?php echo $orderNumber; ?>')"
            title="Mark order as served/completed">
        <i class="fas fa-box"></i> Mark Served
    </button>
    <button class="btn btn-sm btn-info" 
            onclick="displayManager.notifyCustomer('<?php echo $orderNumber; ?>')"
            title="Notify customer their order is ready">
        <i class="fas fa-bell"></i> Notify
    </button>
<?php endif; ?>

<!-- Cancel button (always visible except for completed/cancelled) -->
<?php if (!in_array($currentStatus, ['completed', 'cancelled'])): ?>
    <button class="btn btn-sm btn-danger" 
            onclick="displayManager.cancelOrder(<?php echo $orderId; ?>, '<?php echo $orderNumber; ?>')"
            title="Cancel this order">
        <i class="fas fa-times"></i> Cancel
    </button>
<?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="display-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="next-number">
                        <i class="fas fa-forward"></i>
                        Next Order: 
                        <span id="next-order-number">Loading...</span>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="refresh-info">
                        <i class="fas fa-sync"></i>
                        Auto-refreshing every 
                        <span id="refresh-interval"><?php echo $settings['refresh_interval'] ?? 30; ?></span> seconds
                        (<span id="refresh-countdown"><?php echo $settings['refresh_interval'] ?? 30; ?>s</span>)
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Sound element -->
    <audio id="notification-sound" preload="auto">
        <source src="sounds/kitchen-notification.mp3" type="audio/mpeg">
        <source src="sounds/kitchen-notification.wav" type="audio/wav">
        Your browser does not support the audio element.
    </audio>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/display.js"></script>
    
    <script>
        // Pass PHP data to JavaScript
        const displayConfig = {
            refreshInterval: <?php echo $settings['refresh_interval'] ?? 30; ?>,
            displayTitle: "<?php echo addslashes($settings['display_title'] ?? 'Processing Orders'); ?>"
        };

        // Debug function to check if JS is loading
        console.log('Display page loaded');
        console.log('Config:', displayConfig);
        
        // Check if display.js loaded properly
        if (typeof DisplayManager === 'undefined') {
            console.error('DisplayManager class not found in display.js');
        } else {
            console.log('DisplayManager class found');
        }

        $(document).ready(function() {
            console.log('Document ready, initializing...');
            
            try {
                // Initialize display manager
                const displayManager = new DisplayManager();
                console.log('DisplayManager initialized successfully');
                
                // Store globally for debugging
                window.displayManager = displayManager;
                
            } catch (error) {
                console.error('Error initializing DisplayManager:', error);
                alert('Error initializing display. Check console for details.');
            }
        });
    </script>
</body>
</html>