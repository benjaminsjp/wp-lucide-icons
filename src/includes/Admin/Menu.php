<?php

namespace WPIcons\Admin;

use WPIcons\Template\Template;

if (!\defined('ABSPATH')) {
    exit;
}

class Menu
{
    public const SLUG = 'wp-icons';

    public const CAPABILITY = 'edit_theme_options';

    /**
     * Hook suffix returned by add_theme_page(), used to load admin assets.
     */
    public static ?string $hook = null;

    public function __construct(
        private Template $templates,
        private Settings $settings,
        private LibraryPage $library,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addPages']);
        // menu.php denies unregistered plugin pages before admin_init, so the old settings slug has to redirect here.
        add_action('admin_menu', [$this, 'redirectLegacyUrls'], 99);
    }

    public function addPages(): void
    {
        $hook = add_theme_page(
            __('WP Icons', 'wpicons'),
            __('WP Icons', 'wpicons'),
            self::CAPABILITY,
            self::SLUG,
            [$this, 'renderPage']
        );

        self::$hook = \is_string($hook) ? $hook : null;
    }

    public function renderPage(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab routing.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'library';
        if ($tab === 'settings') {
            $this->settings->renderPage();

            return;
        }

        $this->library->renderPage();
    }

    /**
     * Admin URL for the WP Icons page under Appearance.
     *
     * @param array<string, scalar|null> $args Extra query args. `page` and `tab` are set here.
     */
    public static function url(string $tab = 'library', array $args = []): string
    {
        $query = [
            'page' => self::SLUG,
            'tab' => $tab === 'library' ? null : $tab,
        ] + $args;

        return add_query_arg(array_filter($query), admin_url('themes.php'));
    }

    /**
     * Send bookmarks of the old top-level menu to the Appearance page.
     */
    public function redirectLegacyUrls(): void
    {
        if (($GLOBALS['pagenow'] ?? '') !== 'admin.php') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only legacy URL redirect.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page !== self::SLUG && $page !== Settings::PAGE) {
            return;
        }

        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        $forwarded = [];
        foreach (['view', 'settings-updated'] as $key) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- whitelisted query args forwarded to the new URL.
            if (!isset($_GET[$key])) {
                continue;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- whitelisted query args forwarded to the new URL.
            $value = sanitize_key(wp_unslash($_GET[$key]));
            if ($value !== '') {
                $forwarded[$key] = $value;
            }
        }

        wp_safe_redirect(self::url($page === Settings::PAGE ? 'settings' : 'library', $forwarded), 301);
        exit;
    }
}
