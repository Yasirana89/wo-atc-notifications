<?php
/**
 * Plugin Name: WP Add to Cart Notifications
 * Plugin URI: https://example.com/wp-atc-notifications
 * Description: Display add to cart notifications in a popup.
 * Version: 1.0.0
 * Author: Yasir
 * Author URI: https://example.com
 * Text Domain: wp-atc-notifications
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 10.3.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_ATC_NOTIFICATIONS_VERSION', '1.0.0');
define('WP_ATC_NOTIFICATIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_ATC_NOTIFICATIONS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class WP_ATC_Notifications {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Declare WooCommerce compatibility early
        add_action('before_woocommerce_init', array($this, 'declare_woocommerce_compatibility'));
        
        // Check if WooCommerce is active
        add_action('plugins_loaded', array($this, 'check_woocommerce'));
        
        // Initialize plugin
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Declare WooCommerce compatibility
     * This must be called before WooCommerce initializes
     */
    public function declare_woocommerce_compatibility() {
        // Declare compatibility with WooCommerce features
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('product_block_editor', __FILE__, true);
        }
    }
    
    /**
     * Check if WooCommerce is active
     */
    public function check_woocommerce() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load plugin functionality
        $this->load_dependencies();
    }
    
    /**
     * Show notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('WP Add to Cart Notifications requires WooCommerce to be installed and active.', 'wp-atc-notifications'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once WP_ATC_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-logger.php';
        require_once WP_ATC_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WP_ATC_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-frontend.php';
        
        // Clean old logs on admin init
        add_action('admin_init', array($this, 'clean_logs'));
    }
    
    /**
     * Clean old log files
     */
    public function clean_logs() {
        WP_ATC_Notifications_Logger::get_instance()->clean_old_logs();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('wp-atc-notifications', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize admin
        if (is_admin()) {
            WP_ATC_Notifications_Admin::get_instance();
        }
        
        // Initialize frontend
        WP_ATC_Notifications_Frontend::get_instance();
    }
}

// Initialize the plugin
WP_ATC_Notifications::get_instance();

