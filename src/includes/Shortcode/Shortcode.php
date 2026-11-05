<?php

namespace WPIcons\Shortcode;

use WPIcons\Admin\Settings;
use WPIcons\Icons\Renderer;

if (!\defined('ABSPATH')) {
    exit;
}

class Shortcode
{
    public function register(): void
    {
        add_shortcode('wp_icon', [$this, 'render']);
        add_shortcode('lucide_icon', [$this, 'renderLucideAlias']);
    }

    /**
     * @param array|string $atts
     */
    public function render($atts): string
    {
        $defaults = Settings::get();
        $atts = shortcode_atts(
            [
                'library' => $defaults['default_library'],
                'name' => '',
                'size' => (string) $defaults['default_size'],
                // Empty = the library's own default from Settings (Lucide: the global defaults, as before).
                'color' => '',
                'stroke' => '',
                'width' => '',
                // Library style: weight="bold" (Phosphor) or style="solid" (Tabler, Heroicons, Ionicons).
                'weight' => '',
                'style' => '',
                'variant' => '',
                'class' => '',
                'title' => '',
                'align' => '',
            ],
            \is_array($atts) ? $atts : [],
            'wp_icon'
        );

        $stroke = $atts['stroke'] !== '' ? $atts['stroke'] : $atts['width'];
        $name = trim((string) $atts['name']);

        if ($name === '' && $this->inUxBuilder()) {
            return $this->wrapAlign(
                '<span class="wpicons-icon__placeholder">' . esc_html__('Choose an icon', 'wpicons') . '</span>',
                (string) $atts['align']
            );
        }

        $svg = (new Renderer())->fromAttributes([
            'library' => $atts['library'],
            'name' => $atts['name'],
            'size' => $atts['size'],
            'color' => $atts['color'],
            'stroke' => $stroke,
            'variant' => $atts['variant'] !== '' ? $atts['variant'] : ($atts['weight'] !== '' ? $atts['weight'] : $atts['style']),
            'class' => $atts['class'],
            'title' => $atts['title'],
        ]);

        return $this->wrapAlign($svg, (string) $atts['align']);
    }

    private function inUxBuilder(): bool
    {
        return \function_exists('ux_builder_is_active') && ux_builder_is_active();
    }

    private function wrapAlign(string $html, string $align): string
    {
        if ($html === '') {
            return '';
        }

        $align = strtolower(trim($align));
        if (!\in_array($align, ['left', 'center', 'right'], true)) {
            return $html;
        }

        return '<div class="wpicons wpicons-icon is-align-' . esc_attr($align) . '">' . $html . '</div>';
    }

    /**
     * @param array|string $atts
     */
    public function renderLucideAlias($atts): string
    {
        $atts = \is_array($atts) ? $atts : [];
        $atts['library'] = 'lucide';

        return $this->render($atts);
    }
}
