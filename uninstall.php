<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('wp_icons__settings');
delete_option('wp_icons__cdn_meta');
delete_transient('wp_icons__npm_versions');

global $wpdb;
if (isset($wpdb) && $wpdb instanceof wpdb) {
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wp_icons__npm_versions_%' OR option_name LIKE '_transient_timeout_wp_icons__npm_versions_%'");
}

$timestamp = wp_next_scheduled('wp_icons_refresh_cdn_catalogs');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'wp_icons_refresh_cdn_catalogs');
}

$uploads = wp_upload_dir();
if (empty($uploads['error']) && !empty($uploads['basedir'])) {
    $dir = trailingslashit($uploads['basedir']) . 'wp-icons';
    if (is_dir($dir)) {
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                wp_delete_file($file);
            }
        }
        rmdir($dir);
    }
}
