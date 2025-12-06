<?php
/**
 * Frontend functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_ATC_Notifications_Frontend {
    
    private static $instance = null;
    private $logger = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->logger = WP_ATC_Notifications_Logger::get_instance();

        // Add to cart AJAX
        add_action('wp_ajax_wp_atc_add_to_cart', [$this, 'handle_add_to_cart']);
        add_action('wp_ajax_nopriv_wp_atc_add_to_cart', [$this, 'handle_add_to_cart']);

        // Get product info AJAX
        add_action('wp_ajax_wp_atc_get_product_info', [$this, 'get_product_info']);
        add_action('wp_ajax_nopriv_wp_atc_get_product_info', [$this, 'get_product_info']);

        // Get fresh nonce AJAX
        add_action('wp_ajax_wp_atc_get_fresh_nonce', [$this, 'get_fresh_nonce']);
        add_action('wp_ajax_nopriv_wp_atc_get_fresh_nonce', [$this, 'get_fresh_nonce']);


        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        if ($this->should_display()) {
            add_action('wp_footer', [$this, 'render_notification']);

            add_action('woocommerce_add_to_cart', [$this, 'trigger_notification'], 10, 6);
            add_filter('woocommerce_add_to_cart_fragments', [$this, 'add_to_cart_fragments']);
            add_action('wp_loaded', [$this, 'handle_woocommerce_ajax_add_to_cart'], 20);

            $this->logger->debug('Frontend notifications initialized.');
        } else {
            $this->logger->debug('Notifications disabled for this page.');
        }
    }

    /**
     * Check if notification should be displayed based on conditions
     */
    private function should_display() {
        $display_conditions = get_option('wp_atc_notifications_display_conditions', array('all_pages'));
        
        // If "All pages" is selected, always display
        if (in_array('all_pages', $display_conditions)) {
            return true;
        }
        
        // Check current page type
        if (is_shop() && in_array('shop_archive', $display_conditions)) {
            return true;
        }
        
        if (is_product_category() && in_array('shop_categories', $display_conditions)) {
            return true;
        }
        
        if (is_product_tag() && in_array('shop_tags', $display_conditions)) {
            return true;
        }
        
        // Check for product attributes (taxonomies that are not category or tag)
        if (is_product_taxonomy() && in_array('shop_attributes', $display_conditions)) {
            $taxonomy = get_queried_object()->taxonomy ?? '';
            // Exclude product_cat and product_tag
            if ($taxonomy && $taxonomy !== 'product_cat' && $taxonomy !== 'product_tag') {
                // Check if it's a product attribute taxonomy
                $attribute_taxonomies = wc_get_attribute_taxonomies();
                $attribute_taxonomy_names = array();
                foreach ($attribute_taxonomies as $attribute) {
                    $attribute_taxonomy_names[] = 'pa_' . $attribute->attribute_name;
                }
                if (in_array($taxonomy, $attribute_taxonomy_names)) {
                    return true;
                }
            }
        }
        
        if (is_product() && in_array('single_products', $display_conditions)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'wp-atc-notifications-style',
            WP_ATC_NOTIFICATIONS_PLUGIN_URL . 'assets/css/style.css',
            array(),
            WP_ATC_NOTIFICATIONS_VERSION
        );
        
        wp_enqueue_script(
            'wp-atc-notifications-script',
            WP_ATC_NOTIFICATIONS_PLUGIN_URL . 'assets/js/script.js',
            array('jquery'),
            WP_ATC_NOTIFICATIONS_VERSION,
            true
        );
        
        // Localize script
        $close_after = get_option('wp_atc_notifications_close_after', 3);
        
        /**
         * Filter: wp_atc_notifications_close_after
         * 
         * Allows developers to modify the auto-close timer value programmatically.
         * 
         * @param int $close_after The number of seconds before notification auto-closes (0 to disable)
         * 
         * @example
         * // Set to 5 seconds
         * add_filter('wp_atc_notifications_close_after', function($seconds) {
         *     return 5;
         * });
         * 
         * // Disable auto-close
         * add_filter('wp_atc_notifications_close_after', function($seconds) {
         *     return 0;
         * });
         * 
         * // Different time based on condition
         * add_filter('wp_atc_notifications_close_after', function($seconds) {
         *     if (is_product_category('sale')) {
         *         return 10; // Longer for sale items
         *     }
         *     return $seconds;
         * });
         */
        $close_after = apply_filters('wp_atc_notifications_close_after', $close_after);
        
        wp_localize_script('wp-atc-notifications-script', 'wpAtcNotifications', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'closeAfter' => absint($close_after), // Ensure it's an integer
            'layout' => get_option('wp_atc_notifications_layout', 'image_left'),
            'position' => get_option('wp_atc_notifications_position', 'top'),
            'nonce' => wp_create_nonce('wp_atc_notifications_nonce'),
            'enabled' => $this->should_display() ? 1 : 0
        ));
    }
    
    /**
     * Trigger notification when product is added to cart
     */
    public function trigger_notification($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        $this->logger->debug('Product added to cart. Product ID: ' . $product_id . ', Variation ID: ' . $variation_id);
        
        // Use variation ID if available, otherwise product ID
        $display_product_id = $variation_id > 0 ? $variation_id : $product_id;
        
        // Store the product ID for fragment retrieval
        set_transient('wp_atc_last_added_product_' . get_current_user_id(), $display_product_id, 10);
        
        $product = wc_get_product($display_product_id);
        if (!$product) {
            $this->logger->error('Product not found for ID: ' . $display_product_id);
            return;
        }
        
        $this->logger->debug('Product found: ' . $product->get_name());
    }
    
    /**
     * Handle AJAX add to cart
     */
    public function handle_add_to_cart() {
        check_ajax_referer('wp_atc_notifications_nonce', 'nonce');
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
        
        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product ID', 'wp-atc-notifications')));
        }
        
        // Add to cart
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);
        
        if ($cart_item_key) {
            $product = wc_get_product($product_id);
            
            $response = array(
                'success' => true,
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'product_price' => $product->get_price_html(),
                'product_image' => get_the_post_thumbnail_url($product_id, 'woocommerce_thumbnail'),
                'cart_url' => wc_get_cart_url(),
                'cart_count' => WC()->cart->get_cart_contents_count()
            );
            
            wp_send_json_success($response);
        } else {
            wp_send_json_error(array('message' => __('Failed to add product to cart', 'wp-atc-notifications')));
        }
    }
    
    /**
     * Get product info via AJAX
     */
    public function get_product_info() {

        try {

            // Start output buffering to catch accidental echo/print notices
            ob_start();
            // Validate nonce
            if ( empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'wp_atc_notifications_nonce') ) {
                throw new Exception('Security check failed.');
            }

            // Validate product ID
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

            if (!$product_id) {
                throw new Exception('Invalid product ID.');
            }

            $product = wc_get_product($product_id);

            if (!$product) {
                $this->logger->error("Product not found: {$product_id}");
                throw new Exception('Product not found.');
            }

            // Image
            $image_id = $product->get_image_id();
            $product_image = $image_id
                ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail')
                : wc_placeholder_img_src('woocommerce_thumbnail');

            // Build response
            $response = [
                'product_id'    => $product_id,
                'product_name'  => $product->get_name(),
                'product_price' => $product->get_price_html(),
                'product_image' => $product_image,
                'cart_url'      => wc_get_cart_url(),
            ];

            // Clean any accidental output
            $buffer = ob_get_clean();
            if (!empty($buffer)) {
                $this->logger->warning("Unexpected output caught: " . $buffer);
            }

            wp_send_json_success($response);

        } catch (Throwable $e) {

            // Catch ANY PHP exception or fatal error
            $this->logger->error("Exception in get_product_info(): " . $e->getMessage());

            // Clean buffer if any
            if (ob_get_length()) {
                ob_end_clean();
            }

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get fresh nonce via AJAX (no nonce required for this)
     */
    public function get_fresh_nonce() {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        wp_send_json_success(array('nonce' => wp_create_nonce('wp_atc_notifications_nonce')));
        wp_die();
    }
    
    /**
     * Handle WooCommerce AJAX add to cart
     */
    public function handle_woocommerce_ajax_add_to_cart() {
        // Store the product ID being added
        if (isset($_POST['product_id']) || isset($_POST['add-to-cart'])) {
            $product_id = absint(isset($_POST['add-to-cart']) && $_POST['add-to-cart'] ? $_POST['add-to-cart'] : (isset($_POST['product_id']) ? $_POST['product_id'] : 0));
            if ($product_id) {
                $this->logger->debug('WooCommerce AJAX add to cart detected. Product ID: ' . $product_id);
                set_transient('wp_atc_last_added_product_' . get_current_user_id(), $product_id, 10);
            }
        }
    }
    
    /**
     * Add notification data to cart fragments
     */
    public function add_to_cart_fragments($fragments) {
        $this->logger->debug('Cart fragments filter called');
        
        // Try to get the last added product from transient first
        $last_product_id = get_transient('wp_atc_last_added_product_' . get_current_user_id());
        
        if (!$last_product_id) {
            // Fallback: Get the last added product from cart
            $cart_items = WC()->cart->get_cart();
            
            if (!empty($cart_items)) {
                // Get the last item (most recently added)
                // Use array_reverse to get the most recent item
                $cart_items_reversed = array_reverse($cart_items, true);
                $last_item = reset($cart_items_reversed);
                $last_product_id = isset($last_item['variation_id']) && $last_item['variation_id'] > 0 
                    ? $last_item['variation_id'] 
                    : $last_item['product_id'];
            }
        }
        
        if ($last_product_id) {
            $product = wc_get_product($last_product_id);
            
            if ($product) {
                // Use WooCommerce product image method for better compatibility
                $image_id = $product->get_image_id();
                if ($image_id) {
                    $image_array = wp_get_attachment_image_src($image_id, 'woocommerce_thumbnail');
                    $product_image = $image_array ? $image_array[0] : '';
                } else {
                    $product_image = '';
                }
                
                // Fallback to placeholder if no image
                if (!$product_image) {
                    $product_image = wc_placeholder_img_src('woocommerce_thumbnail');
                }
                
                // Ensure full URL (wc_placeholder_img_src already returns full URL, but check anyway)
                if ($product_image && !preg_match('/^https?:\/\//', $product_image)) {
                    $product_image = home_url($product_image);
                }
        
                $product_name = $product->get_name();
                $product_price = $product->get_price_html();
                
                $notification_data = array(
                    'product_id' => $last_product_id,
                    'product_name' => $product_name,
                    'product_price' => $product_price,
                    'product_image' => $product_image,
                    'cart_url' => wc_get_cart_url()
                );
                
                $fragments['wp_atc_notification_data'] = $notification_data;
                
                $this->logger->debug('Notification data added to fragments. Product: ' . $product_name);
                $this->logger->debug('Fragment data: ' . print_r($notification_data, true));
                
                // Clear transient
                delete_transient('wp_atc_last_added_product_' . get_current_user_id());
            } else {
                $this->logger->warning('Product not found for ID: ' . $last_product_id);
            }
        } else {
            $this->logger->debug('No product ID found for notification');
        }
        
        return $fragments;
    }
    
    /**
     * Render notification HTML
     */
    public function render_notification() {
        $layout = get_option('wp_atc_notifications_layout', 'image_left');
        $position = get_option('wp_atc_notifications_position', 'top');
        ?>
        <div id="wp-atc-notification" class="wp-atc-notification wp-atc-position-<?php echo esc_attr($position); ?> wp-atc-layout-<?php echo esc_attr($layout); ?>" style="display: none;">
            <div class="wp-atc-notification-content">
                <button class="wp-atc-notification-close" aria-label="<?php _e('Close', 'wp-atc-notifications'); ?>">&times;</button>
                <div class="wp-atc-notification-body">
                    <div class="wp-atc-notification-image-wrapper">
                        <img class="wp-atc-notification-image" src="" alt="">
                    </div>
                    <div class="wp-atc-notification-info">
                        <h3 class="wp-atc-notification-title"><?php _e('Added To Cart', 'wp-atc-notifications'); ?></h3>
                        <p class="wp-atc-notification-product-name"></p>
                        <p class="wp-atc-notification-product-price"></p>
                        <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="wp-atc-notification-button"><?php _e('VIEW CART', 'wp-atc-notifications'); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

