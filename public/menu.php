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

// Get all products with addons
$productsSql = "
    SELECT p.*, c.name as category_name, c.color_code,
           GROUP_CONCAT(DISTINCT CONCAT(a.id, '::', a.name, '::', a.price, '::', a.description)) as addons_data
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_addons pa ON p.id = pa.product_id
    LEFT JOIN addons a ON pa.addon_id = a.id AND a.is_available = 1
    WHERE p.is_available = 1
    GROUP BY p.id
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

/// Get cart items with details
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
        /* Force bottom action bar to show on all devices */
.bottom-action-bar {
    display: block !important;
    position: fixed !important;
    bottom: 0 !important;
    left: 0 !important;
    right: 0 !important;
    background: #fff !important;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1) !important;
    z-index: 1050 !important;
    padding: 10px !important;
}

.action-buttons {
    display: flex !important;
    gap: 10px;
    justify-content: space-around;
    max-width: 1200px;
    margin: 0 auto;
}

.action-btn {
    flex: 1;
    padding: 12px 15px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex !important;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
}

.action-btn i {
    font-size: 1.3rem;
}

.view-cart-btn {
    background: #6c757d;
    color: white;
}

.track-order-btn {
    background: #17a2b8;
    color: white;
}

.checkout-btn {
    background: #28a745;
    color: white;
}

.checkout-btn:disabled {
    background: #6c757d;
    opacity: 0.5;
}

/* Add padding to prevent content from being hidden */
.products-container {
    padding-bottom: 100px !important;
}

/* Desktop responsive */
@media (min-width: 768px) {
    .action-btn {
        flex-direction: row !important;
        justify-content: center;
        padding: 15px 25px;
        gap: 10px;
    }
    
    .action-btn span {
        font-size: 1rem;
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
                        <!-- <i class="fas fa-utensils"></i> -->
                        <img src="../uploads/samara.jpg" height="30px" width="30px" alt="Icon" class="header-icon">
                        <span>SAMARA</span>
                    </div>
                </div>
                <div class="col-6 text-end">
                    <div class="cart-summary" onclick="toggleCart()">
                        <i class="fas fa-shopping-cart"></i>
                        <span id="cart-count"><?php echo $cartCount; ?></span> items
                        <span class="ms-2">₱<span id="cart-total"><?php echo number_format($cartTotal, 2); ?></span></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Category Navigation -->
    <nav class="category-nav">
        <div class="container">
            <div class="category-tabs" id="categoryTabs">
                <button class="category-tab active" data-category="all">
                    <i class="fas fa-th-large"></i> All
                </button>
                <?php foreach ($categories as $category): ?>
                <button class="category-tab" 
                        data-category="<?php echo $category['id']; ?>"
                        style="border-left-color: <?php echo $category['color_code']; ?>">
                    <i class="fas fa-<?php echo getCategoryIcon($category['name']); ?>"></i>
                    <?php echo htmlspecialchars($category['name']); ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>

    <!-- Products Grid -->
    <main class="container products-container p-2">
        <div id="productsGrid">
            <?php foreach ($categories as $category): ?>
            <?php if (!empty($productsByCategory[$category['id']])): ?>
            <div class="category-section" data-category="<?php echo $category['id']; ?>">
                <h4 class="category-title mb-3" style="color: <?php echo $category['color_code']; ?>">
                    <i class="fas fa-<?php echo getCategoryIcon($category['name']); ?> me-2"></i>
                    <?php echo htmlspecialchars($category['name']); ?>
                </h4>
                
                <div class="row p-4">
                    <?php foreach ($productsByCategory[$category['id']] as $product): ?>
                    <?php 
                    $hasAddons = $product['has_addons'] || !empty($product['addons_data']);
                    $isAvailable = $product['stock'] > 0 || $product['stock'] === null;
                    ?>
                    <div class="col-md-4 col-sm-6 mb-4 product-item" 
                         data-category="<?php echo $product['category_id']; ?>"
                         data-id="<?php echo $product['id']; ?>">
                        
                        <div class="product-card">
                            <div class="product-image">
                                <i class="fas fa-<?php echo getProductIcon($product['name']); ?>"></i>
                            </div>
                            
                            <div class="product-body">
                                <h5 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                
                                <?php if (!empty($product['description'])): ?>
                                <p class="product-description">
                                    <?php echo htmlspecialchars($product['description']); ?>
                                </p>
                                <?php endif; ?>
                                
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
                                        <i class="fas fa-plus-circle"></i> Customizable with add-ons
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <div class="product-actions">
                                    <?php if ($hasAddons): ?>
                                    <button class="add-to-cart-btn w-100" 
                                            onclick="showAddonsModal(<?php echo $product['id']; ?>)"
                                            <?php echo !$isAvailable ? 'disabled' : ''; ?>>
                                        <i class="fas fa-cog"></i> Customize & Add
                                    </button>
                                    <?php else: ?>
                                    <div class="quantity-control">
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
                                        <i class="fas fa-cart-plus"></i> Add
                                    </button>
                                    <?php endif; ?>
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
                <button class="btn-close float-end" onclick="hideAddonsModal()"></button>
            </div>
            <div class="addons-body" id="addonsList">
                <!-- Addons will be loaded here -->
            </div>
            <div class="addons-footer p-3 border-top">
                <div class="row">
                    <div class="col-8">
                        <h5>Total: ₱<span id="addonTotalPrice">0.00</span></h5>
                        <small class="text-muted">Base price: ₱<span id="basePrice">0.00</span></small>
                    </div>
                    <div class="col-4 text-end">
                        <button class="btn btn-success btn-lg" onclick="addCustomizedToCart()">
                            <i class="fas fa-check"></i> Add
                        </button>
                    </div>
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
            <button class="btn-close btn-close-white float-end" onclick="toggleCart()"></button>
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
                <button class="btn btn-success btn-lg" onclick="proceedToCheckout()" <?php echo empty($cartItems) ? 'disabled' : ''; ?>>
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
            <button class="action-btn checkout-btn" onclick="proceedToCheckout()" <?php echo empty($cartItems) ? 'disabled' : ''; ?>>
                <i class="fas fa-credit-card"></i>
                <span>Checkout</span>
            </button>
        </div>
    </div>

    <!-- Backdrop -->
    <div class="modal-backdrop fade" id="modalBackdrop" style="display: none;"></div>

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

        // Initialize toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        // Current product being customized
        let currentProductId = null;
        let currentProductPrice = 0;
        let selectedAddons = {};

        // Category filtering
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

        // Initialize quantities from cart
        <?php foreach ($_SESSION['cart'] as $item): ?>
        updateQuantityDisplay(<?php echo $item['product_id']; ?>, <?php echo $item['quantity']; ?>);
        <?php endforeach; ?>

        function updateQuantity(productId, change) {
            const currentQty = parseInt(document.getElementById('qty-' + productId).textContent) || 0;
            const newQty = Math.max(0, currentQty + change);
            
            if (newQty === 0) {
                // Remove from cart
                removeCartItem(productId);
            } else {
                // Add/update in cart
                addToCart(productId, newQty);
            }
        }

        function updateQuantityDisplay(productId, quantity) {
            const element = document.getElementById('qty-' + productId);
            if (element) {
                element.textContent = quantity;
                
                // Update the add to cart button text
                const addBtn = element.closest('.product-actions')?.querySelector('.add-to-cart-btn');
                if (addBtn && quantity > 0) {
                    addBtn.innerHTML = `<i class="fas fa-cart-plus"></i> Add ${quantity}`;
                }
            }
        }

        // Modified addToCart function
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
            
            // Show success message
            toastr.success(`${quantity} item${quantity > 1 ? 's' : ''} added to cart!`);
            
            // Refresh cart display if sidebar is open
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

        function showAddonsModal(productId) {
            fetch('api/get-product-addons.php?product_id=' + productId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentProductId = productId;
                        currentProductPrice = parseFloat(data.product.price);
                        selectedAddons = {};
                        
                        // Set product name and base price
                        document.getElementById('addonProductName').textContent = data.product.name;
                        document.getElementById('basePrice').textContent = currentProductPrice.toFixed(2);
                        
                        // Build addons list
                        let addonsHtml = '<h5 class="mb-3">Add-ons:</h5>';
                        
                        if (data.addons.length === 0) {
                            addonsHtml += '<p class="text-muted">No addons available for this product.</p>';
                        } else {
                            data.addons.forEach(addon => {
                                addonsHtml += `
                                    <div class="addon-item">
                                        <div class="addon-info">
                                            <h5 class="mb-1">${addon.name}</h5>
                                            ${addon.description ? `<small class="text-muted">${addon.description}</small>` : ''}
                                        </div>
                                        <div class="addon-quantity">
                                            <span class="addon-price">₱${parseFloat(addon.price).toFixed(2)}</span>
                                            <div class="quantity-control" style="width: 100px;">
                                                <button class="qty-btn" onclick="updateAddonQuantity(${addon.id}, -1)">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <span class="qty-display" id="addon-qty-${addon.id}">0</span>
                                                <button class="qty-btn" onclick="updateAddonQuantity(${addon.id}, 1)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                        
                        // Add special request field
                        addonsHtml += `
                            <div class="mt-4">
                                <h5>Special Instructions:</h5>
                                <textarea class="form-control" id="specialRequest" rows="3" 
                                          placeholder="Any special requests? (e.g., extra sauce, no onions, etc.)"></textarea>
                            </div>
                        `;
                        
                        document.getElementById('addonsList').innerHTML = addonsHtml;
                        updateAddonTotal();
                        
                        // Show modal
                        document.getElementById('addonsModal').style.display = 'flex';
                        document.getElementById('modalBackdrop').style.display = 'block';
                    }
                });
        }

        function hideAddonsModal() {
            document.getElementById('addonsModal').style.display = 'none';
            document.getElementById('modalBackdrop').style.display = 'none';
            currentProductId = null;
            selectedAddons = {};
        }

        function updateAddonQuantity(addonId, change) {
            const currentQty = selectedAddons[addonId] || 0;
            const newQty = Math.max(0, currentQty + change);
            
            selectedAddons[addonId] = newQty;
            document.getElementById('addon-qty-' + addonId).textContent = newQty;
            updateAddonTotal();
        }

        function updateAddonTotal() {
            let total = currentProductPrice;
            
            // Calculate addons total
            Object.keys(selectedAddons).forEach(addonId => {
                const qty = selectedAddons[addonId];
                if (qty > 0) {
                    // Find addon price (you might want to store this in selectedAddons)
                    total += (10 * qty); // This should come from your addons data
                }
            });
            
            document.getElementById('addonTotalPrice').textContent = total.toFixed(2);
        }

        function addCustomizedToCart() {
            const specialRequest = document.getElementById('specialRequest').value;
            
            // Filter addons with quantity > 0
            const addons = [];
            Object.keys(selectedAddons).forEach(addonId => {
                if (selectedAddons[addonId] > 0) {
                    addons.push({
                        addon_id: addonId,
                        quantity: selectedAddons[addonId]
                    });
                }
            });
            
            fetch('api/add-to-cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: currentProductId,
                    quantity: 1,
                    addons: addons,
                    special_request: specialRequest
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateQuantityDisplay(currentProductId, data.cartQuantity);
                    updateCartSummary(data.cart);
                    hideAddonsModal();
                    toastr.success('Added to cart!');
                } else {
                    toastr.error(data.message || 'Failed to add to cart');
                }
            });
        }

        function toggleCart() {
            const sidebar = document.getElementById('cartSidebar');
            sidebar.classList.toggle('open');
            document.getElementById('modalBackdrop').style.display = 
                sidebar.classList.contains('open') ? 'block' : 'none';
        }

       // Modified updateCartItem function
function updateCartItem(productId, change) {
    fetch('api/update-cart-item.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId,
            change: change
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartSummary(data);
            
            // If quantity becomes 0, remove the item from display
            if (data.cart && !data.cart[productId]) {
                document.querySelector(`.cart-item[data-id="${productId}"]`)?.remove();
                // Also update the product quantity display
                document.getElementById('qty-' + productId).textContent = '0';
            }
            
            // Refresh cart display
            refreshCartItems();
            
            toastr.info('Cart updated');
        } else {
            toastr.error(data.message || 'Failed to update cart');
        }
    });
}


// Modified removeCartItem function
function removeCartItem(productId) {
    if (!confirm('Remove this item from cart?')) return;
    
    fetch('api/remove-cart-item.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update product quantity display
            document.getElementById('qty-' + productId).textContent = '0';
            
            // Remove cart item element
            document.querySelector(`.cart-item[data-id="${productId}"]`)?.remove();
            
            updateCartSummary(data);
            toastr.success('Removed from cart');
            
            // If cart is empty, show empty message
            if (data.cartCount === 0) {
                refreshCartItems();
            }
        } else {
            toastr.error(data.message || 'Failed to remove item');
        }
    });
}

// Add customized product to cart
function addCustomizedToCart() {
    const specialRequest = document.getElementById('specialRequest')?.value || '';
    
    // Filter addons with quantity > 0
    const addons = [];
    Object.keys(selectedAddons).forEach(addonId => {
        if (selectedAddons[addonId] > 0) {
            // Find addon info (you'll need to store this in selectedAddons)
            const addonInfo = addonsData?.find(a => a.id == addonId);
            if (addonInfo) {
                addons.push({
                    addon_id: addonId,
                    name: addonInfo.name,
                    price: parseFloat(addonInfo.price),
                    quantity: selectedAddons[addonId]
                });
            }
        }
    });
    
    if (!currentProductId) {
        toastr.error('No product selected');
        return;
    }
    
    addToCart(currentProductId, 1, addons, specialRequest);
    hideAddonsModal();
}


        // Update cart summary
function updateCartSummary(data) {
    const total = data.cartTotal || 0;
    const count = data.cartCount || 0;
    
    // Update all cart displays
    document.getElementById('cart-count').textContent = count;
    document.getElementById('mobile-cart-count').textContent = count;
    document.getElementById('cart-total').textContent = total.toFixed(2);
    document.getElementById('sidebar-cart-total').textContent = total.toFixed(2);
    
    // Update bottom bar checkout button
    const checkoutBtn = document.querySelector('.checkout-btn');
    if (checkoutBtn) {
        checkoutBtn.disabled = count === 0;
    }
    
    // Update cart sidebar if open
    if (document.getElementById('cartSidebar').classList.contains('open')) {
        refreshCartItems();
    }
}

        function proceedToCheckout() {
            if (<?php echo empty($cartItems) ? 'true' : 'false'; ?>) {
                toastr.warning('Your cart is empty');
                return;
            }
            window.location.href = 'checkout.php';
        }

        function trackOrder() {
            // Check if user has saved tracking info
            const savedOrder = localStorage.getItem('lastOrderNumber');
            const holder = "<?php echo $existing_order_handle; ?>";
            // alert(holder);
            if (holder) {
                window.location.href = `order-status.php?order=${holder}`;
            } else {
                // Prompt for order number
                const orderNumber = prompt('Enter your order number:');
                if (orderNumber) {
                    window.location.href = `order-status.php?order=${holder}&pin=${orderNumber}`;
                }
            }
        }

        // Quantity controls (local, not in cart yet)
        function changeQuantity(productId, change) {
            const element = document.getElementById('qty-' + productId);
            let currentQty = parseInt(element.textContent) || 0;
            let newQty = Math.max(0, currentQty + change);
            
            // Update display
            element.textContent = newQty;
            
            // Update the add to cart button
            const addBtn = element.closest('.product-actions').querySelector('.add-to-cart-btn');
            if (addBtn) {
                if (newQty === 0) {
                    addBtn.innerHTML = '<i class="fas fa-cart-plus"></i> Add';
                    addBtn.disabled = false;
                } else {
                    addBtn.innerHTML = `<i class="fas fa-cart-plus"></i> Add ${newQty}`;
                }
            }
        }

        // Add product to cart with current quantity
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
    
    // Reset button text
    const addBtn = qtyElement.closest('.product-actions').querySelector('.add-to-cart-btn');
    if (addBtn) {
        addBtn.innerHTML = '<i class="fas fa-cart-plus"></i> Add';
    }
}

// Function to refresh cart sidebar items
function refreshCartItems() {
    fetch('api/get-cart.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartDisplay(data.cart);
                updateCartSummary(data);
            }
        });
}

// Function to update cart display
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
    
    // Build cart items HTML
    Object.values(cart).forEach(item => {
        const itemTotal = (item.price * item.quantity).toFixed(2);
        
        cartHtml += `
            <div class="cart-item" data-id="${item.product_id}">
                <div class="cart-item-info">
                    <h6 class="mb-1">${escapeHtml(item.name)}</h6>
                    <p class="text-muted mb-1">₱${parseFloat(item.price).toFixed(2)} × ${item.quantity}</p>
                    ${item.addons && item.addons.length > 0 ? `
                    <small class="text-success">
                        <i class="fas fa-plus-circle"></i>
                        ${item.addons.length} addon(s)
                    </small>
                    ` : ''}
                    ${item.special_request ? `
                    <div class="special-request mt-1">
                        <small class="text-warning">
                            <i class="fas fa-sticky-note"></i>
                            ${escapeHtml(item.special_request)}
                        </small>
                    </div>
                    ` : ''}
                    <div class="mt-2">
                        <strong>₱${itemTotal}</strong>
                    </div>
                </div>
                <div class="cart-item-actions">
                    <button class="btn btn-sm btn-outline-secondary" onclick="updateCartItem(${item.product_id}, -1)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="mx-2">${item.quantity}</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="updateCartItem(${item.product_id}, 1)">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="btn btn-sm btn-danger ms-2" onclick="removeCartItem(${item.product_id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    cartItemsContainer.innerHTML = cartHtml;
}


// Initialize quantities from cart on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load initial cart data
    fetch('api/get-cart.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update product quantity displays
                Object.values(data.cart || {}).forEach(item => {
                    updateQuantityDisplay(item.product_id, item.quantity);
                });
                updateCartSummary(data);
            }
        });
});



        // Close modal when clicking backdrop
        document.getElementById('modalBackdrop').addEventListener('click', function() {
            hideAddonsModal();
            toggleCart();
        });

        // Prevent cart sidebar click from closing
        document.getElementById('cartSidebar').addEventListener('click', function(e) {
            e.stopPropagation();
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