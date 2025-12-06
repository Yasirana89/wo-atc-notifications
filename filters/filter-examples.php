<?php
/**
 * Example: How to use the wp_atc_notifications_close_after filter
 * 
 * Add this code to your theme's functions.php file or a custom plugin
 */

/**
 * Example 1: Set a fixed close time (5 seconds)
 */
add_filter('wp_atc_notifications_close_after', function($seconds) {
    return 5; // Always close after 5 seconds
});

/**
 * Example 2: Disable auto-close completely
 */
add_filter('wp_atc_notifications_close_after', function($seconds) {
    return 0; // 0 disables auto-close
});

/**
 * Example 3: Different close time based on product category
 */
add_filter('wp_atc_notifications_close_after', function($seconds) {
    if (is_product_category('sale')) {
        return 10; // Longer display for sale items
    }
    if (is_product_category('featured')) {
        return 8; // Medium display for featured items
    }
    return $seconds; // Use default for others
});

/**
 * Example 4: Different close time based on user role
 */
add_filter('wp_atc_notifications_close_after', function($seconds) {
    if (current_user_can('administrator')) {
        return 0; // Admins see it until they close manually
    }
    return $seconds; // Others use default
});

/**
 * Example 5: Longer time on mobile devices
 */
add_filter('wp_atc_notifications_close_after', function($seconds) {
    if (wp_is_mobile()) {
        return $seconds * 2; // Double the time on mobile
    }
    return $seconds;
});

/**
 * Example 6: Time based on product price
 */
add_filter('wp_atc_notifications_close_after', function($seconds) {
    // Get the last added product from cart
    $cart_items = WC()->cart->get_cart();
    if (!empty($cart_items)) {
        $last_item = end($cart_items);
        $product = wc_get_product($last_item['product_id']);
        if ($product && $product->get_price() > 100) {
            return 8; // Longer for expensive items
        }
    }
    return $seconds;
});

