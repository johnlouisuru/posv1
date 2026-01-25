// display/js/display.js - CORRECTED VERSION
class DisplayManager {
    constructor() {
        this.refreshInterval = 5;
        this.refreshTimer = null;
        this.refreshCountdown = null;
        this.currentOrders = [];
        this.soundEnabled = true;
        
        console.log('DisplayManager constructor called');
        this.initialize();
    }
    
    initialize() {
        console.log('DisplayManager initialize called');
        this.setupEventListeners();
        this.loadPreferences();
        this.updateCurrentTime();
        this.getNextOrderNumber();
        
        // Start auto-refresh
        this.startAutoRefresh();
        
        // Initial refresh after a short delay
        setTimeout(() => {
            console.log('Performing initial refresh...');
            this.refreshDisplay();
        }, 1000);
        
        // Export to window for debugging
        window.displayManager = this;
    }
    
    setupEventListeners() {
        console.log('Setting up event listeners');
        
        // Sound toggle
        $('#soundToggle').change(() => {
            this.soundEnabled = $('#soundToggle').is(':checked');
            localStorage.setItem('soundEnabled', this.soundEnabled);
            this.showNotification(`Sound ${this.soundEnabled ? 'enabled' : 'disabled'}`, 'info');
        });
        
        // Manual refresh button
        $('#refreshBtn').click(() => {
            console.log('Manual refresh triggered');
            this.refreshDisplay();
        });
        
        // Fullscreen toggle
        $('#fullscreenToggle').click(() => this.toggleFullscreen());
        
        // Keyboard shortcuts
        $(document).keydown((e) => {
            if (e.key === 'F5') {
                e.preventDefault();
                this.refreshDisplay();
                this.showNotification('Manual refresh initiated', 'info');
            }
            
            if (e.key === 'F11') {
                e.preventDefault();
                this.toggleFullscreen();
            }
        });
    }
    
    loadPreferences() {
        const savedSoundPref = localStorage.getItem('soundEnabled');
        if (savedSoundPref !== null) {
            this.soundEnabled = savedSoundPref === 'true';
            $('#soundToggle').prop('checked', this.soundEnabled);
        }
        
        // Load refresh interval from HTML element
        const intervalElement = document.getElementById('refresh-interval');
        if (intervalElement) {
            const interval = parseInt(intervalElement.textContent);
            if (!isNaN(interval) && interval > 0) {
                this.refreshInterval = interval;
            }
        }
    }
    
    startAutoRefresh() {
        console.log('Starting auto-refresh with interval:', this.refreshInterval);
        
        // Clear any existing timer
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }
        
        this.refreshCountdown = this.refreshInterval;
        
        // Update countdown display
        const updateCountdown = () => {
            this.refreshCountdown--;
            const countdownElement = $('#refresh-countdown');
            if (countdownElement.length) {
                countdownElement.text(this.refreshCountdown + 's');
            }
            
            if (this.refreshCountdown <= 0) {
                this.refreshDisplay(false);
                this.refreshCountdown = this.refreshInterval;
            }
        };
        
        // Initial update
        updateCountdown();
        
        // Start interval
        this.refreshTimer = setInterval(updateCountdown, 1000);
    }
    
    refreshDisplay(showNotification = true) {
        console.log('Refreshing display...');
        
        // Show loading indicator
        $('#orders-container').addClass('loading');
        $('#last-updated').text('Updating...');
        
        $.ajax({
            url: 'api/get-display-orders.php',
            method: 'GET',
            dataType: 'json',
            success: (response) => {
                console.log('Refresh response:', response);
                
                if (response.success) {
                    // Store old orders for comparison
                    const oldOrderIds = this.currentOrders.map(order => order.order_id);
                    const newOrderIds = response.orders.map(order => order.order_id);
                    
                    // Check for new orders
                    const newOrders = response.orders.filter(order => 
                        !oldOrderIds.includes(order.order_id)
                    );
                    
                    // Update display
                    this.currentOrders = response.orders;
                    this.updateOrdersDisplay(response.orders);
                    
                    // Update order count
                    $('#order-count').text(response.orders.length + ' active orders');
                    
                    // Update last updated time
                    const now = new Date();
                    $('#last-updated').text('Updated: ' + now.toLocaleTimeString());
                    
                    // Play sound and show notification for new orders
                    if (newOrders.length > 0) {
                        this.playNotificationSound();
                        this.showNotification(`${newOrders.length} new order(s) received!`, 'success');
                    } else if (showNotification) {
                        this.showNotification('Display refreshed', 'info');
                    }
                    
                } else {
                    this.showNotification('Error: ' + response.message, 'error');
                }
                
                $('#orders-container').removeClass('loading');
            },
            error: (xhr, status, error) => {
                console.error('Refresh error:', error);
                this.showNotification('Error refreshing display', 'error');
                $('#orders-container').removeClass('loading');
                $('#last-updated').text('Update failed');
            }
        });
    }
    
    updateOrdersDisplay(orders) {
        console.log('Updating display with', orders.length, 'orders');
        
        const container = $('#orders-container');
        
        if (orders.length === 0) {
            container.html(`
                <div class="no-orders text-center py-5">
                    <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                    <h3 class="text-muted">No orders in progress</h3>
                    <p class="text-muted">Waiting for new orders...</p>
                </div>
            `);
            return;
        }
        
        // Generate HTML for each order
        let html = '';
        
        orders.forEach(order => {
            const statusClass = 'status-' + (order.status || 'waiting');
            const statusIcons = {
                'waiting': 'fas fa-clock',
                'preparing': 'fas fa-utensils',
                'ready': 'fas fa-check-circle',
                'completed': 'fas fa-box'
            };
            
            // Format time
            const orderTime = order.order_created ? new Date(order.order_created) : new Date();
            const timeString = orderTime.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            // Use safe defaults for all properties
            const orderType = order.order_type || 'unknown';
            const displayName = order.display_name || 'Customer';
            const estimatedTime = order.estimated_time || 0;
            const totalAmount = order.total_amount || 0;
            const orderNumber = order.order_number || 'N/A';
            
            html += `
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="order-card ${statusClass}">
                        <div class="order-header">
                            <div class="order-meta">
                                <span class="order-number badge bg-dark">${this.escapeHtml(orderNumber)}</span>
                                <span class="order-type badge bg-secondary">${orderType.toUpperCase()}</span>
                            </div>
                            <h5 class="customer-name">
                                <i class="fas fa-user"></i>
                                ${this.escapeHtml(displayName)}
                            </h5>
                            <div class="order-timing">
                                <span class="order-time">
                                    <i class="fas fa-clock"></i>
                                    ${timeString}
                                </span>
                                •
                                <span class="estimated-time">
                                    <i class="fas fa-hourglass-half"></i>
                                    Est: ${estimatedTime} min
                                </span>
                            </div>
                        </div>
                        
                        <div class="order-body">
                            <div class="order-items">
                                <h6 class="items-title mb-2">
                                    <i class="fas fa-list me-2"></i>Order Items
                                </h6>
                                ${this.getOrderItemsHtml(order)}
                            </div>
                            
                            <div class="order-footer">
                                <div class="status-indicator">
                                    <i class="${statusIcons[order.status] || 'fas fa-question'}"></i>
                                    <span class="status-text">${(order.status || 'unknown').charAt(0).toUpperCase() + (order.status || 'unknown').slice(1)}</span>
                                </div>
                                <div class="order-amount">
                                    <i class="fas fa-receipt"></i>
                                    ₱${parseFloat(totalAmount).toFixed(2)}
                                </div>
                            </div>
                        </div>
                        
                        <div class="order-actions">
                            ${this.getStatusButtons(order.order_id, orderNumber, order.status)}
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.html(html);
        
        // Initialize collapse functionality for addons
        this.initAddonsCollapse();
    }
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    getOrderItemsHtml(order) {
        if (!order.items || order.items.length === 0) {
            return `
                <div class="no-items text-muted py-2 px-3 rounded bg-light">
                    <i class="fas fa-info-circle me-2"></i>No items found
                </div>
            `;
        }
        
        let html = '<ul class="item-list list-unstyled">';
        
        order.items.forEach((item, index) => {
            const hasAddons = item.addons && item.addons.length > 0;
            const addonCount = hasAddons ? item.addons.length : 0;
            const itemClass = hasAddons ? 'item-with-addons' : '';
            const isFirst = index === 0 ? 'first-item' : '';
            const isLast = index === order.items.length - 1 ? 'last-item' : '';
            
            html += `
                <li class="item-entry ${itemClass} ${isFirst} ${isLast} p-2 rounded mb-2">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="item-info flex-grow-1">
                            <div class="d-flex align-items-center">
                                <span class="item-quantity badge bg-secondary me-2">
                                    ${item.quantity}x
                                </span>
                                <span class="item-name fw-semibold">
                                    ${this.escapeHtml(item.name || 'Item')}
                                </span>
            `;
            
            // Add addons indicator
            if (hasAddons) {
                html += `
                    <button class="btn btn-xs btn-outline-info btn-sm ms-2 py-0 px-2 addons-toggle" 
                            type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#addons-${order.order_id}-${item.id}"
                            aria-expanded="false" 
                            aria-controls="addons-${order.order_id}-${item.id}"
                            style="font-size: 0.7em;">
                        <i class="fas fa-chevron-down fa-xs"></i>
                        ${addonCount} addon(s)
                    </button>
                `;
            }
            
            html += `</div>`;
            
            // Special request
            if (item.special_request) {
                html += `
                    <div class="special-request alert alert-warning alert-sm py-1 px-2 mb-0 mt-2">
                        <i class="fas fa-sticky-note me-1"></i>
                        <small class="request-text">
                            ${this.escapeHtml(item.special_request)}
                        </small>
                    </div>
                `;
            }
            
            html += `</div>`;
            
            // Category badge
            if (item.category) {
                html += `
                    <span class="item-category badge bg-light text-dark border ms-2 flex-shrink-0">
                        ${this.escapeHtml(item.category)}
                    </span>
                `;
            }
            
            html += `</div>`;
            
            // Addons section
            if (hasAddons) {
                html += this.getAddonsHtml(order.order_id, item);
            } else {
                // Item total without addons
                const itemTotal = (item.unit_price || 0) * (item.quantity || 1);
                html += `
                    <div class="item-total text-end mt-2 pt-1 border-top">
                        <small class="text-muted me-2">Item total:</small>
                        <span class="fw-bold">
                            ₱${itemTotal.toFixed(2)}
                        </span>
                    </div>
                `;
            }
            
            html += `</li>`;
        });
        
        html += '</ul>';
        return html;
    }
    
    getAddonsHtml(orderId, item) {
        let itemAddonTotal = 0;
        let addonsHtml = '';
        
        if (item.addons && item.addons.length > 0) {
            item.addons.forEach(addon => {
                const addonTotal = (addon.price || 0) * (addon.quantity || 1);
                itemAddonTotal += addonTotal;
                
                addonsHtml += `
                    <div class="addon-item d-flex justify-content-between align-items-center py-1">
                        <div class="addon-info">
                            <span class="addon-name" style="font-size: 0.85em;">
                                ${this.escapeHtml(addon.name || 'Addon')}
                            </span>
                            <small class="text-muted ms-2">
                                x${addon.quantity || 1}
                            </small>
                        </div>
                        <div class="addon-details">
                `;
                
                if (addon.price && addon.price > 0) {
                    addonsHtml += `
                        <span class="addon-price text-success fw-bold" style="font-size: 0.85em;">
                            +₱${addonTotal.toFixed(2)}
                        </span>
                    `;
                } else {
                    addonsHtml += `
                        <span class="addon-price text-muted" style="font-size: 0.85em;">
                            Free
                        </span>
                    `;
                }
                
                addonsHtml += `</div></div>`;
            });
        }
        
        const baseTotal = (item.unit_price || 0) * (item.quantity || 1);
        
        return `
            <div class="collapse mt-2" id="addons-${orderId}-${item.id}">
                <div class="card card-body py-2 px-3" style="background: #f8f9fa; border: none;">
                    <h6 class="addons-title mb-1" style="font-size: 0.9em;">
                        <i class="fas fa-plus-circle me-1"></i>Selected Addons:
                    </h6>
                    <div class="addons-list">
                        ${addonsHtml}
                        ${itemAddonTotal > 0 ? `
                            <div class="addon-total d-flex justify-content-between align-items-center pt-1 mt-1 border-top">
                                <span class="fw-bold" style="font-size: 0.85em;">Addons Total:</span>
                                <span class="fw-bold text-success" style="font-size: 0.85em;">
                                    +₱${itemAddonTotal.toFixed(2)}
                                </span>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
            <div class="item-total text-end mt-2 pt-1 border-top">
                <small class="text-muted me-2">Item total:</small>
                <span class="fw-bold">
                    ₱${(baseTotal + itemAddonTotal).toFixed(2)}
                </span>
                ${itemAddonTotal > 0 ? `
                    <small class="text-muted ms-2">
                        (₱${baseTotal.toFixed(2)} + ₱${itemAddonTotal.toFixed(2)} addons)
                    </small>
                ` : ''}
            </div>
        `;
    }
    
    initAddonsCollapse() {
        // Toggle chevron icon on collapse
        $('.addons-toggle').off('click').on('click', function() {
            const $icon = $(this).find('i');
            $icon.toggleClass('fa-chevron-down fa-chevron-up');
        });
        
        // Auto-open addons for items with special requests
        $('.item-entry').each(function() {
            const $item = $(this);
            const $specialRequest = $item.find('.special-request');
            const $addonsBtn = $item.find('.addons-toggle');
            
            if ($specialRequest.length > 0 && $addonsBtn.length > 0) {
                // Show addons if special request exists
                const targetId = $addonsBtn.attr('data-bs-target');
                if (targetId) {
                    $(targetId).addClass('show');
                    $addonsBtn.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-up');
                }
            }
        });
    }
    
    getStatusButtons(orderId, orderNumber, currentStatus) {
        let buttons = '';
        
        switch(currentStatus) {
            case 'waiting':
                buttons = `
                    <button class="btn btn-sm btn-primary" 
                            onclick="displayManager.updateOrderStatus(${orderId}, 'completed', '${this.escapeHtml(orderNumber)}')"
                            title="Serve order immediately">
                        <i class="fas fa-check-circle"></i> Mark Served
                    </button>
                    <button class="btn btn-sm btn-warning" 
                            onclick="displayManager.updateOrderStatus(${orderId}, 'preparing', '${this.escapeHtml(orderNumber)}')"
                            title="Start preparing order">
                        <i class="fas fa-play"></i> Start Prep
                    </button>
                `;
                break;
                
            case 'preparing':
                buttons = `
                <button class="btn btn-sm btn-primary" 
                            onclick="displayManager.updateOrderStatus(${orderId}, 'completed', '${this.escapeHtml(orderNumber)}')"
                            title="Serve order immediately">
                        <i class="fas fa-check-circle"></i> Mark Served
                    </button>
                    <button class="btn btn-sm btn-success" 
                            onclick="displayManager.updateOrderStatus(${orderId}, 'ready', '${this.escapeHtml(orderNumber)}')"
                            title="Mark order as ready for pickup">
                        <i class="fas fa-check"></i> Mark Ready
                    </button>
                `;
                break;
                
            case 'ready':
                buttons = `
                    <button class="btn btn-sm btn-primary" 
                            onclick="displayManager.updateOrderStatus(${orderId}, 'completed', '${this.escapeHtml(orderNumber)}')"
                            title="Mark order as served/completed">
                        <i class="fas fa-box"></i> Mark Served
                    </button>
                    <button class="btn btn-sm btn-info" 
                            onclick="displayManager.notifyCustomer('${this.escapeHtml(orderNumber)}')"
                            title="Notify customer their order is ready">
                        <i class="fas fa-bell"></i> Notify
                    </button>
                `;
                break;
                
            default:
                buttons = `
                    <button class="btn btn-sm btn-secondary" disabled>
                        <i class="fas fa-ban"></i> No Actions
                    </button>
                `;
        }
        
        // Cancel button (always visible except for completed/cancelled)
        if (!['completed', 'cancelled'].includes(currentStatus)) {
            buttons += `
                <button class="btn btn-sm btn-danger" 
                        onclick="displayManager.cancelOrder(${orderId}, '${this.escapeHtml(orderNumber)}')"
                        title="Cancel this order">
                    <i class="fas fa-times"></i> Cancel
                </button>
            `;
        }
        
        return buttons;
    }
    
    playNotificationSound() {
        if (this.soundEnabled) {
            const sound = document.getElementById('notification-sound');
            if (sound) {
                sound.currentTime = 0;
                sound.play().catch(e => console.log('Audio play failed:', e));
            }
        }
    }
    
    showNotification(message, type = 'info') {
        console.log('Notification:', message, type);
        
        // Create notification element
        const notification = $(`
            <div class="alert alert-${type} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'danger' ? 'fa-exclamation-triangle' : 'fa-info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('body').append(notification);
        
        // Auto-remove after 5 seconds for success/info, 10 seconds for danger
        const timeout = type === 'danger' ? 10000 : 5000;
        setTimeout(() => {
            notification.alert('close');
        }, timeout);
    }
    
    updateCurrentTime() {
        const update = () => {
            const now = new Date();
            const timeElement = $('#current-time');
            if (timeElement.length) {
                timeElement.text(now.toLocaleTimeString('en-US', { 
                    hour12: true, 
                    hour: '2-digit', 
                    minute: '2-digit' 
                }));
            }
        };
        
        update();
        setInterval(update, 1000);
    }
    
    getNextOrderNumber() {
        $.ajax({
            url: 'api/get-next-order.php',
            success: (data) => {
                if (data.success) {
                    $('#next-order-number').text(data.nextNumber);
                }
            }
        });
    }
    
    toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.log('Error enabling fullscreen:', err.message);
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }
    
    // REAL API CALLS - Fixed version without optional chaining
    updateOrderStatus(orderId, status, orderNumber) {
        console.log(`Updating order ${orderId} to ${status}`);
        
        // Get the button that was clicked
        const event = window.event || arguments.callee.caller.arguments[0];
        const button = event ? event.target : null;
        const originalHTML = button ? button.innerHTML : '';
        
        if (button) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            button.disabled = true;
        }
        
        // Make actual API call
        $.ajax({
            url: 'api/update-status.php',
            method: 'POST',
            data: { 
                order_id: orderId, 
                status: status 
            },
            success: (response) => {
                console.log('Update status response:', response);
                
                if (response.success) {
                    this.showNotification(`Order ${orderNumber} updated to: ${status}`, 'success');
                    this.playNotificationSound();
                    
                    // Refresh display after a short delay
                    setTimeout(() => this.refreshDisplay(), 1000);
                } else {
                    this.showNotification(`Error: ${response.message}`, 'error');
                    
                    // Restore button
                    if (button) {
                        button.innerHTML = originalHTML;
                        button.disabled = false;
                    }
                }
            },
            error: (xhr, status, error) => {
                console.error('Update status error:', error);
                this.showNotification('Error updating status. Please try again.', 'error');
                
                // Restore button
                if (button) {
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                }
            }
        });
    }
    
    cancelOrder(orderId, orderNumber) {
        if (!confirm(`Are you sure you want to cancel order ${orderNumber}?`)) {
            return;
        }
        
        console.log(`Cancelling order ${orderId}`);
        
        // Get the button that was clicked
        const event = window.event || arguments.callee.caller.arguments[0];
        const button = event ? event.target : null;
        const originalHTML = button ? button.innerHTML : '';
        
        if (button) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
            button.disabled = true;
        }
        
        // Make actual API call
        $.ajax({
            url: 'api/cancel-order.php',
            method: 'POST',
            data: { 
                order_id: orderId,
                order_number: orderNumber
            },
            success: (response) => {
                console.log('Cancel response:', response);
                
                if (response.success) {
                    this.showNotification(`Order ${orderNumber} cancelled successfully`, 'warning');
                    this.playNotificationSound();
                    
                    // Refresh display after a short delay
                    setTimeout(() => this.refreshDisplay(), 1000);
                } else {
                    this.showNotification(`Error: ${response.message}`, 'error');
                    
                    // Restore button
                    if (button) {
                        button.innerHTML = originalHTML;
                        button.disabled = false;
                    }
                }
            },
            error: (xhr, status, error) => {
                console.error('Cancel error:', error);
                this.showNotification('Error cancelling order. Please try again.', 'error');
                
                // Restore button
                if (button) {
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                }
            }
        });
    }
    
    notifyCustomer(orderNumber) {
        console.log(`Notifying customer for order ${orderNumber}`);
        
        // Get the button that was clicked
        const event = window.event || arguments.callee.caller.arguments[0];
        const button = event ? event.target : null;
        const originalHTML = button ? button.innerHTML : '';
        
        if (button) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Notifying...';
            button.disabled = true;
        }
        
        // Make actual API call
        $.ajax({
            url: 'api/notify-customer.php',
            method: 'POST',
            data: { order_number: orderNumber },
            success: (response) => {
                console.log('Notify response:', response);
                
                if (response.success) {
                    this.showNotification(`Customer notified for order ${orderNumber}`, 'success');
                    
                    // Restore button after delay
                    setTimeout(() => {
                        if (button) {
                            button.innerHTML = originalHTML;
                            button.disabled = false;
                        }
                    }, 2000);
                } else {
                    this.showNotification(`Error: ${response.message}`, 'error');
                    
                    // Restore button
                    if (button) {
                        button.innerHTML = originalHTML;
                        button.disabled = false;
                    }
                }
            },
            error: (xhr, status, error) => {
                console.error('Notify error:', error);
                this.showNotification('Error notifying customer. Please try again.', 'error');
                
                // Restore button
                if (button) {
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                }
            }
        });
    }
}

// Initialize when document is ready
$(document).ready(function() {
    console.log('jQuery ready, creating DisplayManager...');
    try {
        window.displayManager = new DisplayManager();
        console.log('DisplayManager created successfully');
    } catch (error) {
        console.error('Failed to create DisplayManager:', error);
    }
});