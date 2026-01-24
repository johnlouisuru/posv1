<?php
// order-status.php
require_once '../includes/db.php';

$orderNumber = $_GET['order'] ?? '';
$pin = $_GET['pin'] ?? '';
$nickname = $_GET['nickname'] ?? '';
$searchError = '';
$orderNumberFromOnline = null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderNumber = trim($_POST['order_number'] ?? '');
    $pin = trim($_POST['order_number'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
}

if(!empty($pin)) {
        $get_onumber_from_online_orders = "
        SELECT order_number
        FROM online_orders
        WHERE tracking_pin = ?
        LIMIT 1
        ";

        $stmt = $pdo->prepare($get_onumber_from_online_orders);
        $stmt->execute([$pin]);
        $result = $stmt->fetch();

        if ($result) {
            $orderNumberFromOnline = $result['order_number'];
        } else {
            // Handle case where PIN is not found
            $orderNumberFromOnline = null;
            // or throw an error, redirect, etc.
        }
} else {
    $pin = '';
}

// Search for order
$order = null;
$orderItems = [];

if (!empty($orderNumber) || !empty($nickname) || !empty($orderNumberFromOnline)) {
    try {
        $sql = "SELECT 
                    o.*,
                    ods.display_name,
                    ods.status as display_status,
                    ods.estimated_time,
                    TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as minutes_passed
                FROM orders o
                LEFT JOIN order_display_status ods ON o.id = ods.order_id
                WHERE (o.order_number = ? OR ods.display_name = ? OR o.order_number = ?)
                ORDER BY o.created_at DESC
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderNumber, $nickname, $orderNumberFromOnline]);
        $order = $stmt->fetch();
        
        if ($order) {
    // Get order items WITH ADDONS (using the simpler approach)
    $itemsSql = "
        SELECT 
            oi.id as order_item_id,
            p.name,
            oi.quantity,
            oi.unit_price,
            oi.total_price,
            oi.special_request,
            oi.status as item_status
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ";
    
    $itemsStmt = $pdo->prepare($itemsSql);
    $itemsStmt->execute([$order['id']]);
    $orderItems = $itemsStmt->fetchAll();
    
    // Get addons for each order item
    foreach ($orderItems as &$item) {
        $addonsSql = "
            SELECT 
                a.name,
                oia.price_at_time as price,
                oia.quantity
            FROM order_item_addons oia
            JOIN addons a ON oia.addon_id = a.id
            WHERE oia.order_item_id = ?
        ";
        
        $addonsStmt = $pdo->prepare($addonsSql);
        $addonsStmt->execute([$item['order_item_id']]);
        $item['addons'] = $addonsStmt->fetchAll();
        
        // Calculate item total with addons
        $item['item_total'] = $item['total_price'];
        foreach ($item['addons'] as $addon) {
            $item['item_total'] += ($addon['price'] * $addon['quantity']);
        }
    }
    unset($item); // Break the reference
} else {
    $searchError = "Order not found. Please check your order number or nickname.";
}
        
    } catch (Exception $e) {
        $searchError = "Error searching for order: " . $e->getMessage();
    }
}
else {
$message = "Please enter an order number, PIN, or nickname to track your order.";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .order-tracker {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .tracker-header {
            background: linear-gradient(to right, #4a00e0, #8e2de2);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .tracker-body {
            padding: 2rem;
        }
        
        .status-timeline {
            position: relative;
            padding: 2rem 0;
        }
        
        .status-step {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .status-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 1rem;
            z-index: 2;
        }
        
        .status-icon.active {
            background: #28a745;
            color: white;
        }
        
        .status-icon.inactive {
            background: #e9ecef;
            color: #6c757d;
        }
        
        .status-icon.current {
            background: #007bff;
            color: white;
            animation: pulse 2s infinite;
        }
        
        .status-content {
            flex: 1;
        }
        
        .status-line {
            position: absolute;
            left: 25px;
            top: 50px;
            bottom: -15px;
            width: 2px;
            background: #e9ecef;
            z-index: 1;
        }
        
        .status-step:last-child .status-line {
            display: none;
        }
        
        .order-items {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .estimated-time {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .qr-section {
            text-align: center;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-top: 2rem;
        }
        /* Addons styling */
.addons-list {
    background: #f8f9fa;
    border-radius: 5px;
    padding: 8px;
    margin: 5px 0;
    border-left: 3px solid #28a745;
}

.addon-item {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
    font-size: 0.85rem;
}

.addon-item .price {
    color: #28a745;
    font-weight: 500;
}
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="order-tracker">
            <!-- Header -->
            <div class="tracker-header">
                <h1><i class="fas fa-search-location"></i> Track Your Order</h1>
                <p class="mb-0">Enter your order number or nickname to check status</p>
            </div>
            
            <!-- Search Form -->
            <div class="tracker-body">
            <?php 
            if(!empty($message)) {
                echo '<div class="alert alert-info"><i class="fas fa-info-circle"></i> ' . htmlspecialchars($message) . '</div>';
            }
            ?>
                <?php if (!$order): ?>
                    <div class="search-form">
                        <form method="POST" action="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Order Number / PIN</label>
                                    <input type="text" class="form-control form-control-lg" 
                                           name="order_number" value="<?php echo htmlspecialchars($orderNumber); ?>"
                                           placeholder="e.g., ONL-2026-001">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">OR Nickname</label>
                                    <input type="text" class="form-control form-control-lg" 
                                           name="nickname" value="<?php echo htmlspecialchars($nickname); ?>"
                                           placeholder="e.g., Kanor">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-search"></i> Track Order
                                </button>
                                <hr />
                                <a href="menu.php" class="btn btn-secondary btn-lg w-100">
                                    <i class="fas fa-list"></i> Back to Menu
                                </a>
                            </div>
                        </form>
                        
                        <?php if ($searchError): ?>
                            <div class="alert alert-danger mt-3">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $searchError; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- QR Code Info -->
                        <div class="qr-section mt-4">
                            <h4><i class="fas fa-qrcode"></i> Quick Access</h4>
                            <p>Scan QR code at your table to track order instantly</p>
                            <div id="qrcode"></div>
                            <small class="text-muted">Point your camera at the QR code on your table</small>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Order Found - Show Status -->
                    <div class="order-found">
                        <div class="alert alert-success">
                            <h4><i class="fas fa-check-circle"></i> Order Found!</h4>
                            <p class="mb-0">
                                Order: <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                • Name: <strong><?php echo htmlspecialchars($order['display_name'] ?? $order['customer_nickname'] ?? 'Customer'); ?></strong>
                                • Placed: <?php echo date('h:i A', strtotime($order['created_at'])); ?>
                            </p>
                        </div>
                        
                        <!-- Status Timeline -->
                        <div class="status-timeline">
                            <?php
                            $statusSteps = [
                                'pending' => ['icon' => 'fas fa-clock', 'label' => 'Order Received', 'description' => 'We have received your order'],
                                'preparing' => ['icon' => 'fas fa-utensils', 'label' => 'Preparing', 'description' => 'Kitchen is preparing your food'],
                                'ready' => ['icon' => 'fas fa-check-circle', 'label' => 'Ready', 'description' => 'Your order is ready for pickup'],
                                'completed' => ['icon' => 'fas fa-box', 'label' => 'Completed', 'description' => 'Order has been served']
                            ];
                            
                            $currentStatus = $order['display_status'] ?? $order['status'];
                            $currentStep = array_search($currentStatus, array_keys($statusSteps));
                            
                            $stepIndex = 0;
                            foreach ($statusSteps as $status => $step):
                                $isActive = $stepIndex < $currentStep;
                                $isCurrent = $stepIndex == $currentStep;
                            ?>
                                <div class="status-step">
                                    <div class="status-icon <?php echo $isActive ? 'active' : ($isCurrent ? 'current' : 'inactive'); ?>">
                                        <i class="<?php echo $step['icon']; ?>"></i>
                                    </div>
                                    <div class="status-content">
                                        <h5 class="mb-1"><?php echo $step['label']; ?></h5>
                                        <p class="text-muted mb-0"><?php echo $step['description']; ?></p>
                                        <?php if ($isCurrent): ?>
                                            <small class="text-primary">
                                                <i class="fas fa-hourglass-half"></i>
                                                Currently at this step
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($stepIndex < count($statusSteps) - 1): ?>
                                        <div class="status-line"></div>
                                    <?php endif; ?>
                                </div>
                            <?php 
                                $stepIndex++;
                            endforeach; 
                            ?>
                        </div>
                        
                        <!-- Order Details -->
                        <div class="order-items">
                            <h5><i class="fas fa-receipt"></i> Order Details</h5>
                            <table class="table">
                                <thead>
    <tr>
        <th>Item</th>
        <th>Qty</th>
        <th class="text-end">Price</th>
        <th>Status</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($orderItems as $item): ?>
        <tr>
            <td>
                <div>
                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                    <div class="text-muted small">₱<?php echo number_format($item['unit_price'], 2); ?> each</div>
                    
                    <?php if (!empty($item['addons'])): ?>
    <div class="addons-list mt-2">
        <?php foreach ($item['addons'] as $addon): ?>
            <?php if (is_array($addon) && isset($addon['name'])): ?>
                <div class="addon-item">
                    <span class="name">
                        • <?php echo htmlspecialchars($addon['name']); ?> 
                        <span class="text-muted">(x<?php echo $addon['quantity']; ?>)</span>
                    </span>
                    <span class="price">
                        +₱<?php echo number_format($addon['price'], 2); ?>
                    </span>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?> 
                    
                    <?php if ($item['special_request']): ?>
                        <div class="mt-2">
                            <small class="text-warning">
                                <i class="fas fa-sticky-note"></i>
                                <?php echo htmlspecialchars($item['special_request']); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </td>
            <td><?php echo $item['quantity']; ?></td>
            <td class="text-end">
                <strong>₱<?php echo number_format($item['item_total'] ?? $item['total_price'], 2); ?></strong>
            </td>
            <td>
                <span class="badge 
                    <?php 
                        if ($item['item_status'] == 'pending') echo 'bg-warning';
                        elseif ($item['item_status'] == 'preparing') echo 'bg-info';
                        elseif ($item['item_status'] == 'ready') echo 'bg-success';
                        else echo 'bg-secondary';
                    ?>">
                    <?php echo ucfirst($item['item_status']); ?>
                </span>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
                                <tfoot>
    <tr>
        <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
        <td><strong>₱<?php echo number_format($order['subtotal'], 2); ?></strong></td>
    </tr>
    <?php if ($order['tax_amount'] > 0): ?>
    <tr>
        <td colspan="2" class="text-end">Tax:</td>
        <td>₱<?php echo number_format($order['tax_amount'], 2); ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($order['discount_amount'] > 0): ?>
    <tr>
        <td colspan="2" class="text-end">Discount:</td>
        <td class="text-danger">-₱<?php echo number_format($order['discount_amount'], 2); ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <th colspan="3" class="text-end">Total:</th>
        <th>₱<?php echo number_format($order['total_amount'], 2); ?></th>
    </tr>
</tfoot>
                            </table>
                        </div>
                        
                        <!-- Estimated Time -->
                        <?php if ($order['estimated_time']): ?>
                            <div class="estimated-time">
                                <h6><i class="fas fa-hourglass-half"></i> Estimated Preparation Time</h6>
                                <p class="mb-0">
                                    Your order will be ready in approximately 
                                    <strong><?php echo $order['estimated_time']; ?> minutes</strong>
                                    <?php if ($order['minutes_passed']): ?>
                                        (<?php echo max(0, $order['estimated_time'] - $order['minutes_passed']); ?> minutes remaining)
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Auto-refresh notice -->
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-sync-alt"></i>
                            This page auto-refreshes every 30 seconds. Last updated: 
                            <span id="last-updated"><?php echo date('h:i:s A'); ?></span>
                        </div>
                        
                        <!-- Actions -->
                        <div class="text-center mt-4">
                            <a href="order-status.php" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i> Track Another Order
                            </a>
                            <a href="menu.php" target="_blank" class="btn btn-outline-secondary">
                                <i class="fas fa-tv"></i> Back to Menu
                            </a>
                            <!-- <button onclick="window.print()" class="btn btn-outline-success">
                                <i class="fas fa-print"></i> Print Receipt
                            </button> -->
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        <?php if ($order): ?>
        // Auto-refresh for order status page
        setTimeout(() => location.reload(), 5000);
        
        // Update last updated time
        setInterval(() => {
            const now = new Date();
            document.getElementById('last-updated').textContent = 
                now.toLocaleTimeString('en-US', { hour12: true });
        }, 1000);
        <?php endif; ?>
        
        // Generate QR code for current page
        function generateQRCode() {
            const currentUrl = window.location.href.split('?')[0];
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(currentUrl)}`;
            $('#qrcode').html(`<img src="${qrUrl}" alt="QR Code" class="img-fluid">`);
        }
        
        $(document).ready(function() {
            generateQRCode();
        });
    </script>
</body>
</html>