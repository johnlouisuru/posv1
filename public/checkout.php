<?php
// session_start();
require_once '../includes/db.php';
require_once '../includes/display-functions.php';

// Redirect if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: menu.php');
    exit;
}

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    
    // Create the order
    $result = createOnlineOrder($_SESSION['cart'], $customerName, $customerPhone);
    
    if ($result['success']) {
        // Store order info for confirmation page
        $_SESSION['last_order'] = [
            'order_number' => $result['order_number'],
            'tracking_pin' => $result['tracking_pin'],
            'customer_name' => $customerName ?: 'Online Customer',
            'cart' => $_SESSION['cart']
        ];
        
        // Clear the cart
        $_SESSION['cart'] = [];
        
        // Redirect to confirmation
        header('Location: order-confirmation.php');
        exit;
    } else {
        $error = "Order failed: " . ($result['error'] ?? 'Unknown error');
    }
}

// Calculate totals for display
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $itemTotal = $item['price'] * $item['quantity'];
    
    // Add addons price
    if (!empty($item['addons'])) {
        foreach ($item['addons'] as $addon) {
            $itemTotal += $addon['price'] * $addon['quantity'];
        }
    }
    
    $subtotal += $itemTotal;
}
$tax = $subtotal * 0;
$total = $subtotal + $tax;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Restaurant Name</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding-top: 20px;
        }
        .checkout-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .checkout-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .checkout-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .order-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .totals-section {
            background: #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .form-section {
            padding: 25px;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-checkout {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            border: none;
            color: white;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 10px;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.4);
        }
        .back-to-cart {
            color: #667eea;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .addon-badge {
            background: #d4edda;
            color: #155724;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container checkout-container">
        <div class="checkout-card">
            <!-- Header -->
            <div class="checkout-header">
                <h1><i class="fas fa-shopping-bag"></i> Checkout</h1>
                <p class="mb-0">Complete your order</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger m-3"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Order Summary -->
                <div class="col-lg-7">
                    <div class="form-section">
                        <h4 class="mb-4"><i class="fas fa-receipt"></i> Order Summary</h4>
                        
                        <div class="order-summary">
                            <!-- In checkout.php, replace the order items display section -->
<?php foreach ($_SESSION['cart'] as $productId => $item): ?>
<div class="order-item">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
            <p class="text-muted mb-1">
                ₱<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?>
                <?php if (!empty($item['addons'])): ?>
                <br>
                <div class="mt-2">
                    <small class="text-success fw-bold">Addons:</small>
                    <div class="ps-3 mt-1">
                        <?php foreach ($item['addons'] as $addon): ?>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">
                                • <?php echo htmlspecialchars($addon['name']); ?> (x<?php echo $addon['quantity']; ?>)
                            </small>
                            <small class="text-success">
                                +₱<?php echo number_format($addon['price'] * $addon['quantity'], 2); ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </p>
            <?php if (!empty($item['special_request'])): ?>
            <p class="text-warning mb-0">
                <small><i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars($item['special_request']); ?></small>
            </p>
            <?php endif; ?>
        </div>
        <div class="text-end">
            <?php 
            $itemTotal = $item['price'] * $item['quantity'];
            // Add addons price
            if (!empty($item['addons'])) {
                foreach ($item['addons'] as $addon) {
                    $itemTotal += $addon['price'] * $addon['quantity'];
                }
            }
            ?>
            <h6 class="mb-0">₱<?php echo number_format($itemTotal, 2); ?></h6>
        </div>
    </div>
</div>
<?php endforeach; ?>
                            
                            <div class="totals-section">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tax (0%):</span>
                                    <span>₱<?php echo number_format($tax, 2); ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Total:</strong>
                                    <strong class="h4">₱<?php echo number_format($total, 2); ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="menu.php" class="back-to-cart">
                                <i class="fas fa-arrow-left"></i> Back to Menu
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Information -->
                <div class="col-lg-5">
                    <div class="form-section">
                        <h4 class="mb-4"><i class="fas fa-user"></i> Customer Information</h4>
                        
                        <form method="POST" id="checkoutForm">
                            <div class="mb-3">
                                <label for="customer_name" class="form-label">
                                    Your Name (Optional)
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="customer_name" 
                                       name="customer_name" 
                                       placeholder="How should we call you?"
                                       value="<?php echo htmlspecialchars($_SESSION['customer_name'] ?? ''); ?>">
                                <small class="text-muted">This name will appear on your order receipt.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_phone" class="form-label">
                                    Phone Number (Optional)
                                </label>
                                <input type="tel" 
                                       class="form-control" 
                                       id="customer_phone" 
                                       name="customer_phone" 
                                       placeholder="For order updates"
                                       value="<?php echo htmlspecialchars($_SESSION['customer_phone'] ?? ''); ?>">
                                <small class="text-muted">We'll send order updates via SMS.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cash" value="cash" checked>
                                    <label class="form-check-label" for="cash">
                                        <i class="fas fa-money-bill-wave"></i> Cash on Pickup
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="payment_method" id="card" value="bank">
                                    <label class="form-check-label" for="card">
                                        <i class="fas fa-credit-card"></i> Bank Transfer
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="ewallet" value="ewallet">
                                    <label class="form-check-label" for="ewallet">
                                        <i class="fas fa-wallet"></i> E-Wallet (GCash)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" checked>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">terms and conditions</a>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-checkout">
                                <i class="fas fa-lock"></i> Place Order & Pay on Pickup
                            </button>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    You'll pay when you pick up your order.
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms & Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Order Policy</h6>
                    <ul>
                        <li>All orders must be picked up within 1 hour of order completion</li>
                        <li>Unclaimed orders will be cancelled after 2 hours</li>
                        <li>Customizations cannot be changed once order is placed</li>
                        <li>Prices are subject to change without notice</li>
                    </ul>
                    
                    <h6>Refund Policy</h6>
                    <ul>
                        <li>Refunds are available for cancelled orders before preparation begins</li>
                        <li>No refunds for completed orders unless there is an error on our part</li>
                        <li>Contact us immediately if there's an issue with your order</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Your Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Please review your order before submitting:</p>
                    <div id="confirmOrderSummary"></div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Your order number and tracking PIN will be displayed on the next page.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Edit Order</button>
                    <button type="button" class="btn btn-primary" onclick="submitOrder()">Confirm & Place Order</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
    <script>
        // Form validation and confirmation
        $(document).ready(function() {
            $('#checkoutForm').on('submit', function(e) {
                e.preventDefault();
                
                // Validate terms agreement
                if (!$('#terms').is(':checked')) {
                    toastr.error('Please agree to the terms and conditions');
                    return;
                }
                
                // Show confirmation modal
                showOrderConfirmation();
            });
        });
        
        function showOrderConfirmation() {
            // Build order summary for confirmation
            let summaryHtml = `
                <div class="order-summary p-3 bg-light rounded">
                    <h6>Order Items:</h6>
                    <ul class="list-unstyled">
            `;
            
            // This would ideally come from the cart data
            // For now, we'll just show a simple message
            summaryHtml += `
                        <li><strong><?php echo count($_SESSION['cart']); ?> items</strong> in your order</li>
                    </ul>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <strong>Total Amount:</strong>
                        <strong>₱<?php echo number_format($total, 2); ?></strong>
                    </div>
                </div>
                <div class="mt-3">
                    <p><strong>Payment Method:</strong> Cash on Pickup</p>
                </div>
            `;
            
            $('#confirmOrderSummary').html(summaryHtml);
            
            // Show modal
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            confirmModal.show();
        }
        
        function submitOrder() {
            // Submit the form
            document.getElementById('checkoutForm').submit();
            
            // Show loading state
            const submitBtn = document.querySelector('.btn-checkout');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
            }
        }
    </script>
</body>
</html>