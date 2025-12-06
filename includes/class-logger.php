<?php
/**
 * Logger class for tracking errors and debug information
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_ATC_Notifications_Logger {
    
    private static $instance = null;
    private $log_file = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wp-atc-notifications-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $this->log_file = $log_dir . '/wp-atc-notifications-' . date('Y-m-d') . '.log';
    }
    
    /**
     * Log a message
     */
    public function log($message, $level = 'INFO') {
        // Always log errors and warnings, but only log INFO/DEBUG if WP_DEBUG is enabled
        if ($level !== 'ERROR' && $level !== 'WARNING') {
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                return;
            }
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            $level,
            $message
        );
        
        // Write to log file (always write errors and warnings)
        if ($this->log_file && is_writable(dirname($this->log_file))) {
            @file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        }
        
        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('WP ATC Notifications [' . $level . ']: ' . $message);
        }
    }
    
    /**
     * Log an error
     */
    public function error($message) {
        $this->log($message, 'ERROR');
    }
    
    /**
     * Log a warning
     */
    public function warning($message) {
        $this->log($message, 'WARNING');
    }
    
    /**
     * Log debug information
     */
    public function debug($message) {
        $this->log($message, 'DEBUG');
    }
    
    /**
     * Get log file path
     */
    public function get_log_file() {
        return $this->log_file;
    }
    
    /**
     * Clear old log files (older than 7 days)
     */
    public function clean_old_logs() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wp-atc-notifications-logs';
        
        if (!is_dir($log_dir)) {
            return;
        }
        
        $files = glob($log_dir . '/wp-atc-notifications-*.log');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > (7 * 24 * 60 * 60)) {
                @unlink($file);
            }
        }
    }
}

