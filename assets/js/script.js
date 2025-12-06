/**
 * WP Add to Cart Notifications Script
 */

(function($) {
    'use strict';
    
    var notification = null;
    var closeTimer = null;
    
    $(document).ready(function() {
        notification = $('#wp-atc-notification');
        
        if (!notification.length) {
            console.error('WP ATC Notifications: Notification element not found in DOM');
            return;
        }
        
        // Check if notifications are enabled for this page
        if (wpAtcNotifications.enabled === 0) {
            console.log('WP ATC Notifications: Notifications disabled for this page');
            return;
        }
        
        console.log('WP ATC Notifications: Plugin initialized', wpAtcNotifications);
        
        // Close button handler
        $(document).on('click', '.wp-atc-notification-close', function() {
            hideNotification();
        });
        
        // Listen for WooCommerce add to cart events
        $(document.body).on('added_to_cart', function(event, fragments, cart_hash, $button) {
            console.log('WP ATC Notifications: added_to_cart event fired', {fragments: fragments, button: $button});
            
            // Check if notification data is in fragments - show immediately
            if (fragments && fragments.wp_atc_notification_data) {
                console.log('WP ATC Notifications: Found notification data in fragments', fragments.wp_atc_notification_data);
                var notificationData = fragments.wp_atc_notification_data;
                
                // Verify we have the required data
                if (notificationData.product_name || notificationData.product_id) {
                    notification.data('wp_atc_shown', true); // Mark as shown
                    // Show immediately - no delay for fragments
                    showNotification(notificationData);
                    return; // Exit early, don't try AJAX
                } else {
                    console.warn('WP ATC Notifications: Fragment data incomplete', notificationData);
                }
            } else {
                console.log('WP ATC Notifications: No notification data in fragments');
            }
            
            // Only try fallback if notification wasn't shown via fragments
            if (!notification.data('wp_atc_shown')) {
                // Fallback: Get product ID from button
                var productId = null;
                
                if ($button && $button.length) {
                    productId = $button.data('product_id') || $button.val() || null;
                    
                    if (!productId) {
                        // Try to get from form
                        var $form = $button.closest('form');
                        if ($form.length) {
                            productId = $form.find('input[name="add-to-cart"]').val() || 
                                       $form.find('input[name="product_id"]').val() || 
                                       null;
                        }
                    }
                    
                    // Try to get from closest product wrapper
                    if (!productId) {
                        productId = $button.closest('.product').find('[data-product_id]').data('product_id') || null;
                    }
                }
                
                if (productId) {
                    console.log('WP ATC Notifications: Product ID found from button: ' + productId);
                    // Small delay to allow fragments to arrive first (fragments are faster)
                    setTimeout(function() {
                        if (!notification.data('wp_atc_shown')) {
                            showNotificationForProduct(productId);
                        }
                    }, 200); // Reduced from 500ms to 200ms
                } else {
                    console.warn('WP ATC Notifications: No product ID found, using tracked product ID');
                    // Use tracked product ID as fallback
                    if (trackedProductId) {
                        setTimeout(function() {
                            if (!notification.data('wp_atc_shown')) {
                                showNotificationForProduct(trackedProductId);
                                trackedProductId = null;
                            }
                        }, 200); // Reduced from 500ms to 200ms
                    }
                }
            } else {
                console.log('WP ATC Notifications: Notification already shown via fragments, skipping fallback');
            }
        });
        
        // Listen for cart fragments updated (WooCommerce AJAX) - show immediately
        $(document.body).on('updated_cart_totals', function(event, fragments) {
            console.log('WP ATC Notifications: updated_cart_totals event fired', fragments);
            if (fragments && fragments.wp_atc_notification_data) {
                var notificationData = fragments.wp_atc_notification_data;
                console.log('WP ATC Notifications: Found notification data in updated_cart_totals', notificationData);
                if (notificationData.product_name || notificationData.product_id) {
                    notification.data('wp_atc_shown', true); // Mark as shown
                    // Show immediately - no delay
                    showNotification(notificationData);
                }
            }
        });
        
        // Listen for fragments refreshed - show immediately
        $(document.body).on('wc_fragments_refreshed wc_fragment_refresh', function(event, fragments) {
            console.log('WP ATC Notifications: fragments refreshed', fragments);
            if (fragments && fragments.wp_atc_notification_data) {
                var notificationData = fragments.wp_atc_notification_data;
                console.log('WP ATC Notifications: Found notification data in fragments refresh', notificationData);
                if (notificationData.product_name || notificationData.product_id) {
                    notification.data('wp_atc_shown', true); // Mark as shown
                    // Show immediately - no delay
                    showNotification(notificationData);
                }
            }
        });
        
        // Also listen for custom events (for AJAX add to cart)
        $(document.body).on('wp_atc_product_added', function(event, data) {
            console.log('WP ATC Notifications: Custom event wp_atc_product_added', data);
            if (data && data.product_id) {
                showNotification(data);
            }
        });
    });
    
    /**
     * Show notification for a specific product
     */
    function showNotificationForProduct(productId) {
        if (!productId) {
            console.error('WP ATC Notifications: No product ID provided');
            return;
        }
        
        // Don't call AJAX if notification was already shown
        if (notification.data('wp_atc_shown')) {
            console.log('WP ATC Notifications: Notification already shown, skipping AJAX call');
            return;
        }
        
        console.log('WP ATC Notifications: Fetching product info for ID: ' + productId);
        
        // Check if nonce exists
        if (!wpAtcNotifications || !wpAtcNotifications.nonce) {
            console.error('WP ATC Notifications: Nonce not available. Reloading page to get fresh nonce.');
            // Try to get a fresh nonce via AJAX
            $.ajax({
                url: wpAtcNotifications.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_atc_get_fresh_nonce'
                },
                success: function(response) {
                    if (response && response.success && response.data && response.data.nonce) {
                        wpAtcNotifications.nonce = response.data.nonce;
                        // Retry the original request
                        fetchProductInfo(productId);
                    } else {
                        console.error('WP ATC Notifications: Could not get fresh nonce');
                    }
                }
            });
            return;
        }
        
        fetchProductInfo(productId);
    }
    
    /**
     * Fetch product info via AJAX
     */
    function fetchProductInfo(productId) {
        var currentProductId = productId; // Store in local scope for error handler
        
        $.ajax({
            url: wpAtcNotifications.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_atc_get_product_info',
                product_id: currentProductId,
                nonce: wpAtcNotifications.nonce
            },
            success: function(response) {
                console.log('WP ATC Notifications: AJAX response received', response);
                if (response && response.success && response.data) {
                    showNotification(response.data);
                } else {
                    console.error('WP ATC Notifications: AJAX request failed', response);
                    if (response && response.data && response.data.message) {
                        console.error('WP ATC Notifications: Error message: ' + response.data.message);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('WP ATC Notifications: AJAX error', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                
                // Try to parse error response
                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    console.error('WP ATC Notifications: Error response:', errorResponse);
                    
                    // If it's a nonce error, try to get fresh nonce and retry
                    if (xhr.status === 400 && errorResponse.data && errorResponse.data.message) {
                        if (errorResponse.data.message.indexOf('Security check') !== -1 || 
                            errorResponse.data.message.indexOf('nonce') !== -1) {
                            console.warn('WP ATC Notifications: Nonce may have expired. Attempting to get fresh nonce...');
                            
                            // Get fresh nonce
                            $.ajax({
                                url: wpAtcNotifications.ajaxUrl,
                                type: 'POST',
                                data: {
                                    action: 'wp_atc_get_fresh_nonce'
                                },
                                success: function(nonceResponse) {
                                    if (nonceResponse && nonceResponse.success && nonceResponse.data && nonceResponse.data.nonce) {
                                        wpAtcNotifications.nonce = nonceResponse.data.nonce;
                                        console.log('WP ATC Notifications: Got fresh nonce, retrying...');
                                        // Retry the original request
                                        fetchProductInfo(currentProductId);
                                    }
                                }
                            });
                            return;
                        }
                    }
                } catch(e) {
                    console.error('WP ATC Notifications: Could not parse error response');
                }
                
                // If all else fails, try to show a basic notification
                console.warn('WP ATC Notifications: Falling back to basic notification');
                showNotification({
                    product_id: currentProductId,
                    product_name: 'Product #' + currentProductId,
                    product_price: '',
                    product_image: '',
                    cart_url: wpAtcNotifications.ajaxUrl.replace('admin-ajax.php', 'cart')
                });
            }
        });
    }
    
    /**
     * Show notification with product data
     */
    function showNotification(data) {
        console.log('WP ATC Notifications: showNotification called', data);
        
        if (!notification || !notification.length) {
            console.error('WP ATC Notifications: Notification element not found');
            return;
        }
        
        if (!data) {
            console.error('WP ATC Notifications: No data provided');
            return;
        }
        
        // Mark as shown to prevent duplicate AJAX calls
        notification.data('wp_atc_shown', true);
        
        // Update notification content
        var $image = notification.find('.wp-atc-notification-image');
        var $productName = notification.find('.wp-atc-notification-product-name');
        var $productPrice = notification.find('.wp-atc-notification-product-price');
        var $button = notification.find('.wp-atc-notification-button');
        var $content = notification.find('.wp-atc-notification-content');
        
        console.log('WP ATC Notifications: Updating notification with data:', {
            has_image: !!data.product_image,
            has_name: !!data.product_name,
            has_price: !!data.product_price,
            has_cart_url: !!data.cart_url
        });
        
        // Set product image
        if (data.product_image) {
            $image.attr('src', data.product_image).attr('alt', data.product_name || 'Product image');
            console.log('WP ATC Notifications: Image set to:', data.product_image);
            
            // For background layout, set background image
            if (wpAtcNotifications.layout === 'image_background') {
                $content.css('background-image', 'url(' + data.product_image + ')');
            }
        } else {
            // Use WooCommerce placeholder if no image - get from WooCommerce function
            // Fallback to a data URI if WooCommerce placeholder is not available
            var placeholder = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2VlZSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTgiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5ObyBJbWFnZTwvdGV4dD48L3N2Zz4=';
            $image.attr('src', placeholder).attr('alt', 'Product image');
            console.log('WP ATC Notifications: Using placeholder image');
        }
        
        // Set product name
        if (data.product_name) {
            $productName.text(data.product_name);
            console.log('WP ATC Notifications: Product name set to:', data.product_name);
        } else {
            $productName.text('Product');
            console.warn('WP ATC Notifications: No product name in data');
        }
        
        // Set product price
        if (data.product_price) {
            $productPrice.html(data.product_price);
            console.log('WP ATC Notifications: Product price set to:', data.product_price);
        } else {
            $productPrice.html('');
            console.warn('WP ATC Notifications: No product price in data');
        }
        
        // Set cart URL
        if (data.cart_url) {
            $button.attr('href', data.cart_url);
        } else {
            // Fallback to default cart URL
            var cartUrl = wpAtcNotifications.ajaxUrl.replace('admin-ajax.php', 'cart');
            $button.attr('href', cartUrl);
        }
        
        // Show notification immediately
        notification.removeClass('hide').css('display', 'block');
        // Use requestAnimationFrame for smoother immediate display
        requestAnimationFrame(function() {
            notification.fadeIn(200); // Reduced fade time from 300ms to 200ms
        });
        console.log('WP ATC Notifications: Notification displayed');
        
        // Auto-close timer
        if (wpAtcNotifications.closeAfter > 0) {
            clearTimeout(closeTimer);
            closeTimer = setTimeout(function() {
                console.log('WP ATC Notifications: Auto-closing notification after ' + wpAtcNotifications.closeAfter + ' seconds');
                hideNotification();
            }, wpAtcNotifications.closeAfter * 1000);
        }
    }
    
    /**
     * Hide notification
     */
    function hideNotification() {
        if (!notification) {
            return;
        }
        
        clearTimeout(closeTimer);
        notification.addClass('hide');
        
        setTimeout(function() {
            notification.fadeOut(300, function() {
                notification.removeClass('hide');
                // Reset flag so notification can be shown again for next product
                notification.data('wp_atc_shown', false);
            });
        }, 300);
    }
    
    /**
     * Handle WooCommerce AJAX add to cart response
     */
    $(document.body).on('wc_fragment_refresh wc_fragments_refreshed', function() {
        // This event fires after cart fragments are updated
        // The notification should already be triggered by added_to_cart event
    });
    
    /**
     * Intercept WooCommerce add to cart button clicks to track product
     */
    var trackedProductId = null;
    
    $(document).on('click', '.add_to_cart_button, .single_add_to_cart_button, button[name="add-to-cart"]', function(e) {
        var $button = $(this);
        trackedProductId = $button.data('product_id') || $button.val() || $button.attr('value') || null;
        
        console.log('WP ATC Notifications: Add to cart button clicked', {button: $button, productId: trackedProductId});
        
        // For variable products, we'll get the ID from the form
        if (!trackedProductId) {
            var $form = $button.closest('form');
            if ($form.length) {
                trackedProductId = $form.find('input[name="add-to-cart"]').val() || 
                                  $form.find('input[name="product_id"]').val() || 
                                  $form.find('input[type="hidden"][name*="product"]').val() ||
                                  null;
            }
        }
        
        // Also try to get from data attributes on parent elements
        if (!trackedProductId) {
            trackedProductId = $button.closest('.product, .product-item, [data-product_id]').data('product_id') || null;
        }
        
        console.log('WP ATC Notifications: Tracked product ID: ' + trackedProductId);
        
        // If we have a product ID, wait briefly for fragments first, then use AJAX as fallback
        if (trackedProductId) {
            // Short delay to give fragments time to arrive (fragments are usually instant)
            setTimeout(function() {
                // Check if notification was already shown via fragments
                // Only call AJAX if notification is not visible AND no data was received via fragments
                var isShown = notification.data('wp_atc_shown');
                var isVisible = notification.is(':visible');
                
                if (!isVisible && !isShown) {
                    console.log('WP ATC Notifications: Notification not shown via fragments, trying AJAX fallback');
                    showNotificationForProduct(trackedProductId);
                } else {
                    console.log('WP ATC Notifications: Notification already shown via fragments (shown: ' + isShown + ', visible: ' + isVisible + '), skipping AJAX call');
                }
            }, 300); // Reduced from 1000ms to 300ms - fragments should arrive almost instantly
        }
    });
    
    /**
     * Fallback: Check URL parameters for add to cart success (non-AJAX)
     */
    if (window.location.search.indexOf('added-to-cart=') > -1) {
        var productId = new URLSearchParams(window.location.search).get('added-to-cart');
        if (productId) {
            // Show immediately for non-AJAX add to cart
            setTimeout(function() {
                showNotificationForProduct(productId);
            }, 100); // Reduced from 500ms to 100ms
        }
    }
    
})(jQuery);

