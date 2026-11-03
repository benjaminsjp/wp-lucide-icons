<?php

namespace WPIcons\Admin;

use WPIcons\Template\Template;

if (!\defined('ABSPATH')) {
    exit;
}

class Assets
{
    private bool $pickerEnqueued = false;

    /** @var array<string, true> */
    private array $themeColorCssHandles = [];

    public function __construct(protected Template $templates)
    {
        $this->templates = $templates;
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFront']);
        add_action('enqueue_block_assets', [$this, 'enqueueFront']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditor']);
        add_action('wp_enqueue_editor', [$this, 'enqueueTinyMcePicker']);
        add_filter('tiny_mce_before_init', [$this, 'editorContentStyle']);
    }

    public function enqueueFront(): void
    {
        $this->enqueueWpiconsStyle('wpicons');

        if (!isset($_GET['uxb_iframe'])) {
            return;
        }

        $this->enqueueTinyMcePicker();

        $relative = 'js/inline/mask.js';
        wp_enqueue_script(
            'wpicons-inline-mask',
            $this->assetUrl($relative),
            [],
            $this->assetVersion($relative),
            true
        );
    }

    public function enqueueAdmin(string $hook): void
    {
        if ($this->shouldEnqueueAdminStyle($hook)) {
            $this->enqueueWpiconsStyle('wpicons_admin_style');
        }

        if (in_array($hook, ['post.php', 'post-new.php'], true)) {
            $this->enqueueTinyMcePicker();
        }

        if ($hook !== 'toplevel_page_' . Menu::SLUG) {
            return;
        }

        wp_enqueue_style('wp-components');

        $script = WP_ICONS__PATH . 'build/admin/library/index.js';
        $asset = WP_ICONS__PATH . 'build/admin/library/index.asset.php';
        $deps = ['wp-element', 'wp-api-fetch', 'wp-components', 'wp-i18n'];
        $version = WP_ICONS__VERSION;

        if (is_readable($asset)) {
            $meta = require $asset;
            if (\is_array($meta)) {
                $deps = $meta['dependencies'] ?? $deps;
                $version = $meta['version'] ?? $version;
            }
        }

        if (!is_readable($script)) {
            return;
        }

        wp_enqueue_script(
            'wpicons-admin-library',
            WP_ICONS__URL . 'build/admin/library/index.js',
            $deps,
            $version,
            true
        );

        wp_localize_script('wpicons-admin-library', 'WPIconsAdmin', $this->editorConfig());
    }

    public function enqueueEditor(): void
    {
        $this->enqueueWpiconsStyle('wpicons_editor_style');

        $config = 'window.WPIconsAdmin = window.WPIconsAdmin || ' . wp_json_encode($this->editorConfig()) . ';';
        wp_register_script('wpicons-admin-config', false, [], WP_ICONS__VERSION, true);
        wp_enqueue_script('wpicons-admin-config');
        wp_add_inline_script('wpicons-admin-config', $config, 'after');
        wp_add_inline_script('wpicons-icon-editor-script', $config, 'before');

        $this->enqueueTinyMcePicker();
    }

    public function enqueueTinyMcePicker(): void
    {
        if ($this->pickerEnqueued) {
            return;
        }

        $script = WP_ICONS__PATH . 'build/tinymce/picker.js';
        $asset = WP_ICONS__PATH . 'build/tinymce/picker.asset.php';
        if (!is_readable($script)) {
            return;
        }

        $this->pickerEnqueued = true;

        wp_enqueue_style('wp-components');
        $this->enqueueWpiconsStyle('wpicons_tinymce_picker_style', ['wp-components']);

        $deps = ['wp-element', 'wp-api-fetch', 'wp-components', 'wp-i18n'];
        $version = WP_ICONS__VERSION;
        if (is_readable($asset)) {
            $meta = require $asset;
            if (\is_array($meta)) {
                $deps = $meta['dependencies'] ?? $deps;
                $version = $meta['version'] ?? $version;
            }
        }

        wp_enqueue_script(
            'wpicons-tinymce-picker',
            WP_ICONS__URL . 'build/tinymce/picker.js',
            $deps,
            $version,
            true
        );

        wp_localize_script('wpicons-tinymce-picker', 'WPIconsAdmin', $this->editorConfig());
    }

    /**
     * @param array<string, mixed> $init
     * @return array<string, mixed>
     */
    public function editorContentStyle(array $init): array
    {
        $css = $this->themeColorCss();
        if ($css === '') {
            return $init;
        }

        $existing = isset($init['content_style']) && \is_string($init['content_style'])
            ? $init['content_style']
            : '';
        $init['content_style'] = trim($existing . ' ' . $css);

        return $init;
    }

    /**
     * @return array<string, mixed>
     */
    private function editorConfig(): array
    {
        $settings = Settings::get();

        return [
            'restUrl' => rest_url('wpicons/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'defaults' => [
                'library' => $settings['default_library'],
                'size' => $settings['default_size'],
                'color' => $settings['default_color'],
                'stroke' => $settings['default_stroke'],
            ],
            'themeColors' => $this->themeColors(),
            'licensesUrl' => current_user_can('manage_options') ? Settings::licensesUrl() : '',
        ];
    }

    /**
     * @param list<string> $deps
     */
    private function enqueueWpiconsStyle(string $handle, array $deps = []): void
    {
        $relative = 'css/wpicons.css';
        wp_enqueue_style(
            $handle,
            $this->assetUrl($relative),
            $deps,
            $this->assetVersion($relative)
        );

        if (isset($this->themeColorCssHandles[$handle])) {
            return;
        }

        $this->themeColorCssHandles[$handle] = true;
        $css = $this->themeColorCss();
        if ($css !== '') {
            wp_add_inline_style($handle, $css);
        }
    }

    private function isPluginSettingsPage(string $hook): bool
    {
        return $hook === 'wp-icons_page_' . Settings::PAGE;
    }

    private function shouldEnqueueAdminStyle(string $hook): bool
    {
        return $this->isPluginSettingsPage($hook) || $hook === 'toplevel_page_' . Menu::SLUG;
    }

    private function assetUrl(string $relative): string
    {
        return WP_ICONS__URL . 'src/assets/' . ltrim($relative, '/');
    }

    private function assetPath(string $relative): string
    {
        return WP_ICONS__PATH . 'src/assets/' . ltrim($relative, '/');
    }

    private function assetVersion(string $relative): string
    {
        $path = $this->assetPath($relative);

        if (!file_exists($path)) {
            return WP_ICONS__VERSION;
        }

        return date('ymd-Gis', filemtime($path));
    }

    private function isFlatsome(): bool
    {
        return class_exists('Flatsome_Default', false) || \function_exists('flatsome_option');
    }

    /**
     * @return list<array{id: string, label: string, value: string, preview: string}>
     */
    private function themeColors(): array
    {
        $swatches = [];

        foreach ($this->themeColorVariables() as $item) {
            $swatches[] = [
                'id' => $item['id'],
                'label' => $item['label'],
                'value' => 'var(' . $item['variable'] . ')',
                'preview' => $item['preview'],
            ];
        }

        return $swatches;
    }

    private function themeColorCss(): string
    {
        $items = $this->themeColorVariables();
        if ($items === []) {
            return '';
        }

        $rules = [];
        foreach ($items as $item) {
            $rules[] = $item['variable'] . ':' . $item['preview'];
        }

        return ':root{' . implode(';', $rules) . '}';
    }

    /**
     * @return list<array{id: string, label: string, variable: string, preview: string}>
     */
    private function themeColorVariables(): array
    {
        if (!$this->isFlatsome()) {
            return [];
        }

        $items = [];
        foreach ($this->flatsomeCoreColors() as $color) {
            $preview = $this->sanitizeThemeColor($color['preview']);
            if ($preview === '') {
                continue;
            }

            $items[] = [
                'id' => $color['id'],
                'label' => $color['label'],
                'variable' => $color['variable'],
                'preview' => $preview,
            ];
        }

        if (\function_exists('fef_active_colors')) {
            foreach (fef_active_colors() as $color) {
                if (!\is_array($color)) {
                    continue;
                }

                $id = sanitize_key((string) ($color['id'] ?? ''));
                $label = trim((string) ($color['label'] ?? ''));
                $preview = $this->sanitizeThemeColor((string) ($color['color'] ?? ''));
                if ($id === '' || $label === '' || $preview === '') {
                    continue;
                }

                $variable = \function_exists('fef_variable_name')
                    ? (string) fef_variable_name($id)
                    : '--fs-color-' . $id;

                if (!preg_match('/^--[a-zA-Z0-9_-]+$/', $variable)) {
                    continue;
                }

                $items[] = [
                    'id' => $id,
                    'label' => $label,
                    'variable' => $variable,
                    'preview' => $preview,
                ];
            }
        }

        return $items;
    }

    /**
     * @return list<array{id: string, label: string, variable: string, preview: string}>
     */
    private function flatsomeCoreColors(): array
    {
        $primary = class_exists('Flatsome_Default', false) ? \Flatsome_Default::COLOR_PRIMARY : '';
        $secondary = class_exists('Flatsome_Default', false) ? \Flatsome_Default::COLOR_SECONDARY : '';
        $success = class_exists('Flatsome_Default', false) ? \Flatsome_Default::COLOR_SUCCESS : '';
        $alert = class_exists('Flatsome_Default', false) ? \Flatsome_Default::COLOR_ALERT : '';

        return [
            [
                'id' => 'primary',
                'label' => __('Primary', 'wpicons'),
                'variable' => '--primary-color',
                'preview' => (string) get_theme_mod('color_primary', $primary),
            ],
            [
                'id' => 'secondary',
                'label' => __('Secondary', 'wpicons'),
                'variable' => '--secondary-color',
                'preview' => (string) get_theme_mod('color_secondary', $secondary),
            ],
            [
                'id' => 'success',
                'label' => __('Success', 'wpicons'),
                'variable' => '--success-color',
                'preview' => (string) get_theme_mod('color_success', $success),
            ],
            [
                'id' => 'alert',
                'label' => __('Alert', 'wpicons'),
                'variable' => '--alert-color',
                'preview' => (string) get_theme_mod('color_alert', $alert),
            ],
        ];
    }

    private function sanitizeThemeColor(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (\function_exists('sanitize_hex_color')) {
            $hex = sanitize_hex_color($value);
            if (\is_string($hex) && $hex !== '') {
                return $hex;
            }
        }

        if (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $value, $matches)) {
            $raw = strtolower($matches[1]);
            if (strlen($raw) === 3) {
                $raw = $raw[0] . $raw[0] . $raw[1] . $raw[1] . $raw[2] . $raw[2];
            }

            return '#' . $raw;
        }

        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*[\d.]+)?\s*\)$/i', $value, $matches)) {
            return sprintf(
                '#%02x%02x%02x',
                max(0, min(255, (int) $matches[1])),
                max(0, min(255, (int) $matches[2])),
                max(0, min(255, (int) $matches[3]))
            );
        }

        return '';
    }
}
