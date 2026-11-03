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
            ],
            \is_array($atts) ? $atts : [],
            'wp_icon'
        );

        $stroke = $atts['stroke'] !== '' ? $atts['stroke'] : $atts['width'];

        return (new Renderer())->fromAttributes([
            'library' => $atts['library'],
            'name' => $atts['name'],
            'size' => $atts['size'],
            'color' => $atts['color'],
            'stroke' => $stroke,
            'variant' => $atts['variant'] !== '' ? $atts['variant'] : ($atts['weight'] !== '' ? $atts['weight'] : $atts['style']),
            'class' => $atts['class'],
            'title' => $atts['title'],
        ]);
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
