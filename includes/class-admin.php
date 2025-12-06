<?php
/**
 * Admin functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_ATC_Notifications_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Add to Cart Notifications', 'wp-atc-notifications'),
            __('ATC Notifications', 'wp-atc-notifications'),
            'manage_woocommerce',
            'wp-atc-notifications',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wp_atc_notifications_settings', 'wp_atc_notifications_layout');
        register_setting('wp_atc_notifications_settings', 'wp_atc_notifications_position');
        register_setting('wp_atc_notifications_settings', 'wp_atc_notifications_close_after');
        register_setting('wp_atc_notifications_settings', 'wp_atc_notifications_display_conditions');
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        // Save settings
        if (isset($_POST['wp_atc_notifications_save']) && check_admin_referer('wp_atc_notifications_save')) {
            update_option('wp_atc_notifications_layout', sanitize_text_field($_POST['wp_atc_notifications_layout']));
            update_option('wp_atc_notifications_position', sanitize_text_field($_POST['wp_atc_notifications_position']));
            update_option('wp_atc_notifications_close_after', absint($_POST['wp_atc_notifications_close_after']));
            update_option('wp_atc_notifications_display_conditions', isset($_POST['wp_atc_notifications_display_conditions']) ? array_map('sanitize_text_field', $_POST['wp_atc_notifications_display_conditions']) : array());
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'wp-atc-notifications') . '</p></div>';
        }
        
        // Get current settings
        $layout = get_option('wp_atc_notifications_layout', 'image_left');
        $position = get_option('wp_atc_notifications_position', 'top');
        $close_after = get_option('wp_atc_notifications_close_after', 3);
        $display_conditions = get_option('wp_atc_notifications_display_conditions', array('all_pages'));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Add to Cart Notifications Settings', 'wp-atc-notifications'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('wp_atc_notifications_save'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e('Layout', 'wp-atc-notifications'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="wp_atc_notifications_layout" value="image_left" <?php checked($layout, 'image_left'); ?>>
                                    <?php _e('Product image within the content on the left side', 'wp-atc-notifications'); ?>
                                </label>
                                <br><br>
                                <label>
                                    <input type="radio" name="wp_atc_notifications_layout" value="image_background" <?php checked($layout, 'image_background'); ?>>
                                    <?php _e('Product image as a background', 'wp-atc-notifications'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Display Position', 'wp-atc-notifications'); ?></label>
                        </th>
                        <td>
                            <select name="wp_atc_notifications_position">
                                <option value="top" <?php selected($position, 'top'); ?>><?php _e('Top', 'wp-atc-notifications'); ?></option>
                                <option value="bottom" <?php selected($position, 'bottom'); ?>><?php _e('Bottom', 'wp-atc-notifications'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wp_atc_notifications_close_after"><?php _e('Close After (Seconds)', 'wp-atc-notifications'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="wp_atc_notifications_close_after" id="wp_atc_notifications_close_after" value="<?php echo esc_attr($close_after); ?>" min="0" step="1" class="small-text">
                            <p class="description"><?php _e('Set to 0 to disable auto-close', 'wp-atc-notifications'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Display Conditions', 'wp-atc-notifications'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <p><?php _e('Select where the notification should appear:', 'wp-atc-notifications'); ?></p>
                                <label>
                                    <input type="checkbox" name="wp_atc_notifications_display_conditions[]" value="all_pages" <?php checked(in_array('all_pages', $display_conditions)); ?>>
                                    <?php _e('All pages', 'wp-atc-notifications'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="wp_atc_notifications_display_conditions[]" value="shop_archive" <?php checked(in_array('shop_archive', $display_conditions)); ?>>
                                    <?php _e('Shop Archive', 'wp-atc-notifications'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="wp_atc_notifications_display_conditions[]" value="shop_categories" <?php checked(in_array('shop_categories', $display_conditions)); ?>>
                                    <?php _e('Shop Archive Categories (product categories)', 'wp-atc-notifications'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="wp_atc_notifications_display_conditions[]" value="shop_tags" <?php checked(in_array('shop_tags', $display_conditions)); ?>>
                                    <?php _e('Shop Archive Tags (product tags)', 'wp-atc-notifications'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="wp_atc_notifications_display_conditions[]" value="shop_attributes" <?php checked(in_array('shop_attributes', $display_conditions)); ?>>
                                    <?php _e('Shop Archive Product Attributes', 'wp-atc-notifications'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="wp_atc_notifications_display_conditions[]" value="single_products" <?php checked(in_array('single_products', $display_conditions)); ?>>
                                    <?php _e('Single Products', 'wp-atc-notifications'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="wp_atc_notifications_save" class="button button-primary" value="<?php _e('Save Changes', 'wp-atc-notifications'); ?>">
                </p>
            </form>
            
            <hr>
            
            <h2><?php _e('Debug Information', 'wp-atc-notifications'); ?></h2>
            <p><?php _e('Log files are stored in:', 'wp-atc-notifications'); ?> <code><?php echo esc_html(wp_upload_dir()['basedir'] . '/wp-atc-notifications-logs/'); ?></code></p>
            <?php
            if (class_exists('WP_ATC_Notifications_Logger')) {
                $logger = WP_ATC_Notifications_Logger::get_instance();
                $log_file = $logger->get_log_file();
                if ($log_file && file_exists($log_file)) {
                    $log_size = size_format(filesize($log_file));
                    echo '<p>' . sprintf(__('Current log file: %s (%s)', 'wp-atc-notifications'), '<code>' . esc_html(basename($log_file)) . '</code>', $log_size) . '</p>';
                    echo '<p><em>' . __('Enable WP_DEBUG and WP_DEBUG_LOG in wp-config.php to enable logging.', 'wp-atc-notifications') . '</em></p>';
                } else {
                    echo '<p><em>' . __('Logging is disabled. Enable WP_DEBUG and WP_DEBUG_LOG in wp-config.php to enable logging.', 'wp-atc-notifications') . '</em></p>';
                }
            }
            ?>
        </div>
        <?php
    }
}

