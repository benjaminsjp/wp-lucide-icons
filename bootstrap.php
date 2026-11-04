<?php

namespace WPIcons;

if (!\defined('ABSPATH')) {
    exit;
}

class Bootstrap
{
    public function wp_icons_missing_vendor_notice()
    {
        echo '<div class="notice notice-error"><p>' . esc_html__('WP Icons is missing Composer dependencies. Run composer install in the plugin directory.', 'wpicons') . '</p></div>';
    }

    public static function init()
    {
        $autoload = WP_ICONS__PATH . 'vendor/autoload.php';

        if (!is_readable($autoload)) {
            add_action('admin_notices', [new self(), 'wp_icons_missing_vendor_notice']);
            return;
        }

        require_once $autoload;
        require_once WP_ICONS__PATH . 'src/functions.php';

        add_action('plugins_loaded', static function () {
            Plugin::instance()->init();
        });
    }
}

Bootstrap::init();
