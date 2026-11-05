<?php

namespace WPIcons\Integrations\Elementor;

use WPIcons\Admin\Assets;
use WPIcons\Admin\TinyMce;
use WPIcons\Template\Template;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Elementor text-editor button and the WP Icons widget.
 *
 * @since 2.0.0
 */
class Integration
{
    private bool $tinyMceHooksKept = false;

    public function __construct(private Template $templates)
    {
    }

    public function register(): void
    {
        add_action('elementor/elements/categories_registered', [$this, 'registerCategory']);
        add_action('elementor/controls/register', [$this, 'registerControls']);
        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
        add_action('elementor/editor/init', [$this, 'keepTinyMcePlugin']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueueEditor']);
    }

    /**
     * Elementor removes TinyMCE button and plugin filters at priority 10 before
     * it builds the text editor. Register the same callbacks afterwards.
     *
     * @since 2.0.0
     */
    public function keepTinyMcePlugin(): void
    {
        if ($this->tinyMceHooksKept) {
            return;
        }

        $this->tinyMceHooksKept = true;
        $tinymce = new TinyMce();
        add_filter('mce_external_plugins', [$tinymce, 'registerPlugin'], 11);
        add_filter('mce_buttons', static function (array $buttons) use ($tinymce): array {
            if (\in_array('wpicons', $buttons, true)) {
                return $buttons;
            }

            return $tinymce->registerButton($buttons);
        }, 11);
    }

    /**
     * Elementor canvas and preview requests. Published pages are not a preview.
     */
    public static function isPreview(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preview detection only.
        if (isset($_GET['elementor-preview'])) {
            return true;
        }

        if (!class_exists('\Elementor\Plugin', false)) {
            return false;
        }

        $plugin = \Elementor\Plugin::$instance ?? null;
        if (!\is_object($plugin) || !isset($plugin->preview) || !\is_object($plugin->preview)) {
            return false;
        }

        return (bool) $plugin->preview->is_preview_mode();
    }

    /**
     * @param \Elementor\Elements_Manager $elements_manager
     */
    public function registerCategory($elements_manager): void
    {
        $elements_manager->add_category('wpicons', [
            'title' => __('WP Icons', 'wpicons'),
            'icon' => 'fa fa-plug',
        ]);
    }

    /**
     * @param \Elementor\Controls_Manager $controls_manager
     */
    public function registerControls($controls_manager): void
    {
        if (!class_exists('\Elementor\Base_Data_Control', false)) {
            return;
        }

        $controls_manager->register(new IconControl());
    }

    /**
     * @param \Elementor\Widgets_Manager $widgets_manager
     */
    public function registerWidgets($widgets_manager): void
    {
        if (!class_exists('\Elementor\Widget_Base', false)) {
            return;
        }

        $widgets_manager->register(new IconWidget());
    }

    public function enqueueEditor(): void
    {
        (new Assets($this->templates))->enqueueTinyMcePicker();

        $relative = 'js/elementor/editor.js';
        $path = WP_ICONS__PATH . 'src/assets/' . $relative;
        if (!is_readable($path)) {
            return;
        }

        wp_enqueue_script(
            'wpicons-elementor-editor',
            WP_ICONS__URL . 'src/assets/' . $relative,
            ['elementor-editor', 'editor', 'wpicons-api', 'wpicons-tinymce-picker'],
            $this->assetVersion($path),
            true
        );

        $tinymce = new TinyMce();
        $plugins = $tinymce->registerPlugin([]);
        wp_add_inline_script(
            'wpicons-elementor-editor',
            'window.WPIconsElementor=' . wp_json_encode([
                'pluginUrl' => $plugins['wpicons'] ?? '',
                'cssUrl' => $tinymce->editorCss(''),
            ]) . ';',
            'before'
        );

        wp_register_style('wpicons-elementor-editor', false, [], WP_ICONS__VERSION);
        wp_enqueue_style('wpicons-elementor-editor');
        wp_add_inline_style('wpicons-elementor-editor', $this->editorChromeCss());
    }

    private function assetVersion(string $path): string
    {
        $mtime = filemtime($path);

        return $mtime ? (string) $mtime : WP_ICONS__VERSION;
    }

    /**
     * Keep the picker above the Elementor panel.
     */
    private function editorChromeCss(): string
    {
        return '.components-modal__screen-overlay,.components-modal__frame{z-index:1000000;}';
    }
}
