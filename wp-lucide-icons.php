<?php

/**
 * Plugin Name:         WP Icons
 * Plugin URI:          https://github.com/kimlukasmyrvold/wp-lucide-icons
 * Description:         Adds icon elements to place on pages, uses icons from Lucide Icons, Material Icons and other various icon libraries. Integrates with some WordPress themes like Flatsome.
 * Version:             2.0.0
 * Requires at least:   6.6
 * Requires PHP:        8.1
 * Author:              Kim Lukas Myrvold
 * Author URI:          https://www.kimlukas.dev/?utm_source=wordpress&utm_medium=wp-icons&utm_campaign=author_uri
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:          https://github.com/kimlukasmyrvold/wp-lucide-icons
 * Text Domain:         wpicons
 * Domain Path:         /src/languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_ICONS__VERSION', '2.0.0');
define('WP_ICONS__MIN_PHP', '8.1');
define('WP_ICONS__MIN_WP', '6.6');
define('WP_ICONS__FILE', __FILE__);
define('WP_ICONS__PATH', plugin_dir_path(__FILE__));
define('WP_ICONS__URL', plugin_dir_url(__FILE__));

function wp_icons_compatible()
{
    if (version_compare(PHP_VERSION, WP_ICONS__MIN_PHP, '<')) {
        return false;
    }

    global $wp_version;
    if (isset($wp_version) && version_compare($wp_version, WP_ICONS__MIN_WP, '<')) {
        return false;
    }

    return true;
}

function wp_icons_incompatible_message()
{
    global $wp_version;

    $wp = isset($wp_version) ? $wp_version : 'unknown';

    return sprintf(
        'WP Icons requires PHP %1$s or higher and WordPress %2$s or higher. You are running PHP %3$s and WordPress %4$s.',
        WP_ICONS__MIN_PHP,
        WP_ICONS__MIN_WP,
        PHP_VERSION,
        $wp
    );
}

function wp_icons_incompatible_notice()
{
    echo '<div class="notice notice-error"><p>' . esc_html(wp_icons_incompatible_message()) . '</p></div>';
}

function wp_icons_activation()
{
    if (wp_icons_compatible()) {
        return;
    }

    deactivate_plugins(plugin_basename(WP_ICONS__FILE));

    wp_die(
        esc_html(wp_icons_incompatible_message()),
        'Plugin Activation Error',
        array('back_link' => true)
    );
}

register_activation_hook(__FILE__, 'wp_icons_activation');

if (!wp_icons_compatible()) {
    add_action('admin_notices', 'wp_icons_incompatible_notice');
    return;
}

require_once WP_ICONS__PATH . 'bootstrap.php';
