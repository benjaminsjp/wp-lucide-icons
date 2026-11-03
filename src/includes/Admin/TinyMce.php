<?php

namespace WPIcons\Admin;

if (!\defined('ABSPATH')) {
    exit;
}

class TinyMce
{
    public function register(): void
    {
        add_filter('mce_external_plugins', [$this, 'registerPlugin']);
        add_filter('mce_buttons', [$this, 'registerButton']);
        add_filter('mce_css', [$this, 'editorCss']);
        add_filter('tiny_mce_before_init', [$this, 'beforeInit']);
    }

    /**
     * @param array<string, string> $plugins
     * @return array<string, string>
     */
    public function registerPlugin(array $plugins): array
    {
        $plugins['wpicons'] = WP_ICONS__URL . 'src/assets/js/tinymce/plugin.js?ver=' . WP_ICONS__VERSION;

        return $plugins;
    }

    /**
     * @param array<int, string> $buttons
     * @return array<int, string>
     */
    public function registerButton(array $buttons): array
    {
        $buttons[] = 'wpicons';

        return $buttons;
    }

    public function editorCss(string $css): string
    {
        $path = WP_ICONS__PATH . 'src/assets/css/wpicons.css';
        $url = WP_ICONS__URL . 'src/assets/css/wpicons.css';
        if (is_readable($path)) {
            $url .= '?ver=' . date('ymd-Gis', filemtime($path));
        }

        return $css === '' ? $url : $css . ',' . $url;
    }

    /**
     * @param array<string, mixed> $init
     * @return array<string, mixed>
     */
    public function beforeInit(array $init): array
    {
        $extra = 'img[class|src|alt|title|width|height|style|data-src|data-library|data-name|data-color|data-stroke|data-size|data-title|data-variant],span[class|style|data-src|data-library|data-name|data-color|data-stroke|data-size|data-title|data-variant]';
        if (empty($init['extended_valid_elements'])) {
            $init['extended_valid_elements'] = $extra;
        } else {
            $init['extended_valid_elements'] .= ',' . $extra;
        }

        $init['allow_html_data_urls'] = true;
        if (isset($init['valid_styles']) && \is_string($init['valid_styles'])) {
            $init['valid_styles'] = $this->allowMaskStyle($init['valid_styles']);
        }

        return $init;
    }

    /**
     * Keep icon preview styles when a style allow-list already exists.
     * An empty allow-list would replace TinyMCE's default and strip every other inline style.
     */
    private function allowMaskStyle(string $styles): string
    {
        $properties = ['--wpicons-src', 'background-color', 'width', 'height'];
        if (trim($styles) === '' || !str_contains($styles, '{')) {
            return $styles;
        }

        if (preg_match('/"\*"\s*:\s*"([^"]*)"/', $styles, $match) === 1) {
            $existing = $match[1] === '' ? [] : array_map('trim', explode(',', $match[1]));
            $missing = array_values(array_filter(
                $properties,
                static fn (string $property): bool => !\in_array($property, $existing, true)
            ));
            if ($missing === []) {
                return $styles;
            }

            $next = array_values(array_filter([...$existing, ...$missing]));

            return str_replace($match[0], '"*":"' . implode(',', $next) . '"', $styles);
        }

        return rtrim(rtrim(trim($styles), '}')) . ',"*":"' . implode(',', $properties) . '"}';
    }
}
