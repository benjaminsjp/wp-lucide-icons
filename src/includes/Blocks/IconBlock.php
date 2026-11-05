<?php

namespace WPIcons\Blocks;

use WPIcons\Admin\Settings;
use WPIcons\Icons\Renderer;

if (!\defined('ABSPATH')) {
    exit;
}

class IconBlock
{
    public function register(): void
    {
        add_action('init', [$this, 'registerBlock']);
    }

    public function registerBlock(): void
    {
        $dir = WP_ICONS__PATH . 'build/blocks/icon';
        if (!is_readable($dir . '/block.json')) {
            return;
        }

        register_block_type($dir, [
            'render_callback' => [$this, 'render'],
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content = '', $block = null): string
    {
        $settings = Settings::get();
        if (empty($attributes['library'])) {
            $attributes['library'] = $settings['default_library'];
        }
        if (empty($attributes['size'])) {
            $attributes['size'] = $settings['default_size'];
        }
        if (empty($attributes['color'])) {
            $attributes['color'] = $settings['default_color'];
        }
        if (!isset($attributes['strokeWidth']) || $attributes['strokeWidth'] === '' || $attributes['strokeWidth'] === null) {
            $attributes['strokeWidth'] = $settings['default_stroke'];
        }

        $className = isset($attributes['className']) && \is_string($attributes['className'])
            ? $attributes['className']
            : '';

        $svg = (new Renderer())->fromAttributes([
            ...$attributes,
            'class' => trim('wp-block-wpicons-icon ' . $className),
        ]);

        if ($svg === '') {
            return '';
        }

        $wrapper = get_block_wrapper_attributes([
            'class' => 'wpicons wp-block-wpicons-icon__wrap',
        ]);

        return '<span ' . $wrapper . '>' . $svg . '</span>';
    }
}
