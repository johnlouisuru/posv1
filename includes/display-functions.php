<?php
// includes/display-functions.php (UPDATED VERSION)
/**
 * Get all active orders for display
 */
// FIXED VERSION - getProcessingOrders function
function getProcessingOrders($limit = 10) {
    global $pdo;
    
    // FIXED: Proper SQL syntax for LIMIT with prepared statement
    $sql = "SELECT DISTINCT
                ods.*,
                o.order_type,
                o.created_at as order_created,
                o.total_amount,
                o.status as order_status
            FROM order_display_status ods
            JOIN orders o ON ods.order_id = o.id
            WHERE ods.status IN ('waiting', 'preparing', 'ready')
                AND (ods.display_until IS NULL OR ods.display_until > NOW())
            ORDER BY 
                CASE ods.status 
                    WHEN 'waiting' THEN 1
                    WHEN 'preparing' THEN 2
                    WHEN 'ready' THEN 3
                    ELSE 4
                END,
                ods.created_at ASC
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get display settings from public_display_settings table
 */
function getDisplaySettings() {
    global $pdo;
    
    // Get customer display settings (for the public display)
    $sql = "SELECT * FROM public_display_settings 
            WHERE display_type = 'customer'
            LIMIT 1";
    
    $stmt = $pdo->query($sql);
    $displaySettings = $stmt->fetch();
    
    if (!$displaySettings) {
        // Create default settings if none exist
        $displaySettings = [
            'refresh_interval' => 30,
            'max_display_items' => 10,
            'show_completed_for' => 5,
            'auto_remove_after' => 120,
            'theme' => 'default',
            'is_active' => true
        ];
    }
    
    // Additional settings not in the table
    $additionalSettings = [
        'display_title' => '🛎️ Processing Orders',
        'sound_enabled' => true,
        'new_order_sound' => 'notification.mp3'
    ];
    
    return array_merge($displaySettings, $additionalSettings);
}

/**
 * Update order display status
 */
function updateDisplayStatus($orderId, $status, $estimatedMinutes = null) {
    global $pdo;
    
    $sql = "UPDATE order_display_status 
            SET status = ?, 
                estimated_minutes = COALESCE(?, estimated_minutes),
                updated_at = NOW()
            WHERE order_id = ?";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$status, $estimatedMinutes, $orderId]);
}

/**
 * Create display entry for new order
 */
function createDisplayEntry($orderId, $orderNumber, $displayName) {
    global $pdo;
    
    // Check if entry already exists
    $checkSql = "SELECT id FROM order_display_status WHERE order_id = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$orderId]);
    
    if ($checkStmt->fetch()) {
        return false; // Entry already exists
    }
    
    // Calculate estimated time based on order items
    $estimatedTime = calculateEstimatedTime($orderId);
    
    $sql = "INSERT INTO order_display_status 
            (order_id, order_number, display_name, estimated_minutes, display_until) 
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$orderId, $orderNumber, $displayName, $estimatedTime]);
}

/**
 * Calculate estimated preparation time
 */
function calculateEstimatedTime($orderId) {
    global $pdo;
    
    $sql = "SELECT AVG(p.preparation_time) as avg_time 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ? AND p.preparation_time > 0";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderId]);
    $result = $stmt->fetch();
    
    $baseTime = $result['avg_time'] ? ceil($result['avg_time']) : 15;
    
    // Add buffer time
    return min($baseTime + 5, 45); // Cap at 45 minutes
}

/**
 * Clean up old display entries
 */
function cleanupOldDisplayEntries() {
    global $pdo;
    
    $sql = "DELETE FROM order_display_status 
            WHERE (display_until IS NOT NULL AND display_until < NOW())
               OR (status = 'completed' AND updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))";
    
    return $pdo->exec($sql);
}

/**
 * Get order items for display
 */
function getOrderItemsForDisplay($orderId) {
    global $pdo;
    
    $sql = "SELECT 
                oi.id,
                oi.product_id,
                p.name,
                p.category_id,
                oi.quantity,
                oi.unit_price,
                oi.special_request,
                oi.status
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY oi.created_at";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add addons to each item
    foreach ($items as &$item) {
        $addonSql = "SELECT 
                        oa.id,
                        oa.addon_id,
                        a.name,
                        a.price,
                        oa.quantity
                    FROM order_item_addons oa
                    LEFT JOIN addons a ON oa.addon_id = a.id
                    WHERE oa.order_item_id = ?";
        
        $addonStmt = $pdo->prepare($addonSql);
        $addonStmt->execute([$item['id']]);
        $item['addons'] = $addonStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $items;
}

/**
 * Get all display types settings
 */
function getAllDisplaySettings() {
    global $pdo;
    
    $sql = "SELECT * FROM public_display_settings ORDER BY display_type";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function getItemAddons($itemId) {
    global $pdo;
    
    $sql = "SELECT 
                oa.id,
                oa.addon_id,
                a.name,
                a.price,
                oa.quantity
            FROM order_item_addons oa
            LEFT JOIN addons a ON oa.addon_id = a.id
            WHERE oa.order_item_id = ?
            ORDER BY a.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$itemId]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update display settings
 */
function updateDisplaySetting($displayType, $field, $value) {
    global $pdo;
    
    $sql = "UPDATE public_display_settings 
            SET $field = ?, updated_at = NOW() 
            WHERE display_type = ?";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$value, $displayType]);
}

/**
 * Get next sequence number for orders
 */
function getNextOrderSequence() {
    global $pdo;
    
    // Get current date in YYMMDD format
    $datePrefix = date('ymd');
    
    // Get the last order number for today from both tables
    $sql = "SELECT MAX(order_number) as last_order FROM (
                SELECT order_number FROM orders WHERE order_number LIKE 'ONLINE-$datePrefix-%'
                UNION ALL
                SELECT order_number FROM online_orders WHERE order_number LIKE 'ONLINE-$datePrefix-%'
            ) as all_orders";
    
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch();
    
    if ($result && $result['last_order']) {
        // Extract sequence number
        $parts = explode('-', $result['last_order']);
        $lastSeq = (int) end($parts);
        return $lastSeq + 1;
    }
    
    return 1; // First order of the day
}

/**
 * Create a new online order
 */
/**
 * Create a new online order
 */
function createOnlineOrder($cart, $customerName = '', $customerPhone = '') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Generate order number
        $sequence = getNextOrderSequence();
        $orderNumber = 'ONLINE-' . date('ymd') . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
        $trackingPin = sprintf('%04d', rand(0, 9999));
        
        // Calculate totals
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        $tax = $subtotal * 0; // 12% tax
        $total = $subtotal + $tax;
        
        // 1. Insert into main orders table
        $orderSql = "
            INSERT INTO orders 
            (order_number, order_type, customer_nickname, customer_phone, 
             subtotal, tax_amount, total_amount, status, order_date)
            VALUES (?, 'online', ?, ?, ?, ?, ?, 'pending', CURDATE())
        ";
        
        $orderStmt = $pdo->prepare($orderSql);
        $orderStmt->execute([
            $orderNumber,
            $customerName,
            $customerPhone,
            $subtotal,
            $tax,
            $total
        ]);
        
        $orderId = $pdo->lastInsertId();
        
        // 2. Insert order items
        foreach ($cart as $productId => $item) {
            $itemSql = "
                INSERT INTO order_items 
                (order_id, product_id, quantity, unit_price, total_price, special_request)
                VALUES (?, ?, ?, ?, ?, ?)
            ";
            
            $itemTotal = $item['price'] * $item['quantity'];
            $itemStmt = $pdo->prepare($itemSql);
            $itemStmt->execute([
                $orderId,
                $productId,
                $item['quantity'],
                $item['price'],
                $itemTotal,
                $item['special_request'] ?? ''
            ]);
            
            $orderItemId = $pdo->lastInsertId();
            
            // 3. Insert addons if any
            if (!empty($item['addons'])) {
                foreach ($item['addons'] as $addon) {
                    $addonSql = "
                        INSERT INTO order_item_addons 
                        (order_item_id, addon_id, quantity, price_at_time)
                        VALUES (?, ?, ?, ?)
                    ";
                    
                    $addonStmt = $pdo->prepare($addonSql);
                    $addonStmt->execute([
                        $orderItemId,
                        $addon['addon_id'],
                        $addon['quantity'],
                        $addon['price'] ?? 0
                    ]);
                }
            }
        }
        
        // 4. Create display entry (using estimated_time not estimated_minutes)
        $displayName = !empty($customerName) ? $customerName : 'Online Customer';
        $displaySql = "
            INSERT INTO order_display_status 
            (order_id, order_number, display_name, status, estimated_time)
            VALUES (?, ?, ?, 'waiting', 15)
        ";
        
        $displayStmt = $pdo->prepare($displaySql);
        $displayStmt->execute([$orderId, $orderNumber, $displayName]);
        
        // 5. Save to online_orders table for tracking
        $onlineOrderSql = "
            INSERT INTO online_orders 
            (order_number, session_id, customer_nickname, customer_phone, 
             tracking_pin, order_data, status, estimated_time)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', 15)
        ";
        
        $orderData = [
            'cart' => $cart,
            'totals' => [
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total
            ],
            'items_count' => count($cart)
        ];
        
        $onlineStmt = $pdo->prepare($onlineOrderSql);
        $onlineStmt->execute([
            $orderNumber,
            session_id(),
            $customerName,
            $customerPhone,
            $trackingPin,
            json_encode($orderData)
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'order_number' => $orderNumber,
            'tracking_pin' => $trackingPin,
            'order_id' => $orderId
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>