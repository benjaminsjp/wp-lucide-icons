<?php

namespace WPIcons\Integrations\Flatsome;

use WPIcons\Admin\Assets;
use WPIcons\Admin\Settings;
use WPIcons\Template\Template;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Registers [wp_icon] as a UX Builder element.
 *
 * @since 2.0.0
 */
class IconElement
{
    public function __construct(private Template $templates)
    {
    }

    public function register(): void
    {
        add_action('ux_builder_setup', [$this, 'registerElement']);
        add_action('ux_builder_enqueue_scripts', [$this, 'enqueueEditor']);
    }

    public function registerElement(): void
    {
        if (!\function_exists('add_ux_builder_shortcode')) {
            return;
        }

        $settings = Settings::get();

        add_ux_builder_shortcode('wp_icon', [
            'name' => __('WP Icons', 'wpicons'),
            'category' => __('Content', 'ux-builder'),
            'wrap' => false,
            'info' => '{{ name }}',
            'options' => [
                'icon_options' => [
                    'type' => 'group',
                    'heading' => __('Icon', 'wpicons'),
                    'options' => [
                        'library' => [
                            'type' => 'textfield',
                            'heading' => __('Library', 'wpicons'),
                            'default' => (string) $settings['default_library'],
                            'full_width' => true,
                        ],
                        'name' => [
                            'type' => 'textfield',
                            'heading' => __('Icon', 'wpicons'),
                            'default' => '',
                            'full_width' => true,
                        ],
                        'variant' => [
                            'type' => 'textfield',
                            'heading' => __('Style', 'wpicons'),
                            'default' => '',
                            'full_width' => true,
                        ],
                        'color' => [
                            'type' => 'textfield',
                            'heading' => __('Color', 'wpicons'),
                            'default' => '',
                            'placeholder' => 'currentColor',
                            'full_width' => true,
                        ],
                        'stroke' => [
                            'type' => 'textfield',
                            'heading' => __('Stroke', 'wpicons'),
                            'default' => '',
                            'full_width' => true,
                        ],
                        'title' => [
                            'type' => 'textfield',
                            'heading' => __('Accessible name', 'wpicons'),
                            'default' => '',
                            'description' => __('Empty hides the icon from assistive technology.', 'wpicons'),
                            'full_width' => true,
                        ],
                        'size' => [
                            'type' => 'slider',
                            'heading' => __('Size', 'wpicons'),
                            'default' => (string) $settings['default_size'],
                            'min' => '1',
                            'max' => '512',
                            'step' => '1',
                            'unit' => 'px',
                        ],
                        'align' => [
                            'type' => 'radio-buttons',
                            'heading' => __('Align', 'wpicons'),
                            'default' => 'left',
                            'save_when_default' => true,
                            'options' => [
                                'left' => ['title' => __('Left', 'wpicons')],
                                'center' => ['title' => __('Center', 'wpicons')],
                                'right' => ['title' => __('Right', 'wpicons')],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param string $context
     */
    public function enqueueEditor($context): void
    {
        if ($context !== 'editor') {
            return;
        }

        (new Assets($this->templates))->enqueueTinyMcePicker();

        $relative = 'js/flatsome/icon-element.js';
        $path = WP_ICONS__PATH . 'src/assets/' . $relative;
        if (!is_readable($path)) {
            return;
        }

        $version = $this->assetVersion($path);

        wp_enqueue_style(
            'wpicons-flatsome-editor',
            WP_ICONS__URL . 'src/assets/css/wpicons.css',
            ['ux-builder-core', 'wp-components'],
            $this->styleVersion()
        );
        wp_add_inline_style(
            'wpicons-flatsome-editor',
            '.components-modal__screen-overlay,.components-modal__frame{z-index:1000000;}'
        );

        wp_enqueue_script(
            'wpicons-flatsome-icon',
            WP_ICONS__URL . 'src/assets/' . $relative,
            ['ux-builder-core', 'wpicons-api'],
            $version,
            true
        );

        wp_localize_script('wpicons-flatsome-icon', 'WPIconsFlatsome', [
            'choose' => __('Choose icon', 'wpicons'),
        ]);
    }

    private function assetVersion(string $path): string
    {
        $mtime = filemtime($path);

        return $mtime ? (string) $mtime : WP_ICONS__VERSION;
    }

    private function styleVersion(): string
    {
        $path = WP_ICONS__PATH . 'src/assets/css/wpicons.css';
        $mtime = is_readable($path) ? filemtime($path) : false;

        return $mtime ? (string) $mtime : WP_ICONS__VERSION;
    }
}
