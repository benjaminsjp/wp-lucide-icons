<?php

namespace WPIcons\Content;

use WPIcons\Icons\Renderer;
use WP_HTML_Tag_Processor;

if (!\defined('ABSPATH')) {
    exit;
}

class InlineIcons
{
    public function register(): void
    {
        add_filter('wp_kses_allowed_html', [$this, 'allowAttributes'], 10, 2);
        add_filter('render_block', [$this, 'replaceInHtml']);
        add_filter('the_content', [$this, 'replaceInHtml'], 13);
        add_filter('acf_the_content', [$this, 'replaceInHtml'], 13);
        add_filter('do_shortcode_tag', [$this, 'replaceInHtml']);
    }

    /**
     * @param array<string, array<string, mixed>> $tags
     * @return array<string, array<string, mixed>>
     */
    public function allowAttributes(array $tags, $context): array
    {
        if ($context !== 'post') {
            return $tags;
        }

        if (!isset($tags['img']) || !\is_array($tags['img'])) {
            $tags['img'] = [];
        }
        if (!isset($tags['span']) || !\is_array($tags['span'])) {
            $tags['span'] = [];
        }

        foreach (['img', 'span'] as $tag) {
            $tags[$tag]['class'] = true;
            $tags[$tag]['style'] = true;
            $tags[$tag]['data-src'] = true;
            $tags[$tag]['data-library'] = true;
            $tags[$tag]['data-name'] = true;
            $tags[$tag]['data-color'] = true;
            $tags[$tag]['data-stroke'] = true;
            $tags[$tag]['data-size'] = true;
            $tags[$tag]['data-title'] = true;
            $tags[$tag]['data-variant'] = true;
        }

        return $tags;
    }

    public function replaceInHtml(string $html): string
    {
        if ($html === '' || !str_contains($html, 'wpicons-inline')) {
            return $html;
        }

        if (!$this->shouldReplace()) {
            return $html;
        }

        $replaced = preg_replace_callback(
            '/<(?:img\b(?=[^>]*\bwpicons-inline\b)[^>]*\/?>|span\b(?=[^>]*\bwpicons-inline\b)[^>]*>.*?<\/span>)/is',
            [$this, 'replaceTag'],
            $html
        );

        return \is_string($replaced) ? $replaced : $html;
    }

    /**
     * @param array<int, string> $matches
     */
    private function replaceTag(array $matches): string
    {
        $tag = $matches[0];
        $inner = '';
        if (preg_match('/^<span\b[^>]*>(.*)<\/span>$/is', $tag, $innerMatch) === 1) {
            $inner = $innerMatch[1];
        }

        $processor = new WP_HTML_Tag_Processor($tag);
        if (!$processor->next_tag()) {
            return $tag;
        }

        $name = trim((string) $processor->get_attribute('data-name'));
        if ($name === '') {
            return $tag;
        }

        $size = (int) $processor->get_attribute('data-size');
        if ($size < 1 || $size > 512) {
            $size = 0;
        }

        $attributes = [
            'library' => (string) $processor->get_attribute('data-library'),
            'name' => $name,
            'color' => (string) ($processor->get_attribute('data-color') ?: 'currentColor'),
            'stroke' => $processor->get_attribute('data-stroke'),
            'variant' => (string) $processor->get_attribute('data-variant'),
            'class' => 'wpicons-inline',
            'title' => trim((string) $processor->get_attribute('data-title')),
        ];
        if ($size > 0) {
            $attributes['size'] = $size;
        }

        $svg = (new Renderer())->fromAttributes($attributes);
        if ($svg !== '' && $size > 0) {
            $svg = $this->withExplicitSize($svg, $size);
        }

        return $svg !== '' ? $svg . $inner : $tag;
    }

    private function withExplicitSize(string $svg, int $size): string
    {
        $processor = new WP_HTML_Tag_Processor($svg);
        if (!$processor->next_tag('svg')) {
            return $svg;
        }

        // Keep an existing style (an explicit icon color is set there).
        $existing = trim((string) $processor->get_attribute('style'));
        $sizeStyle = 'width:' . $size . 'px;height:' . $size . 'px';
        $processor->set_attribute('style', $existing === '' ? $sizeStyle : rtrim($existing, '; ') . ';' . $sizeStyle);
        $updated = $processor->get_updated_html();

        return \is_string($updated) && $updated !== '' ? $updated : $svg;
    }

    private function shouldReplace(): bool
    {
        if (is_admin()) {
            return false;
        }

        if (\defined('REST_REQUEST') && REST_REQUEST) {
            $context = isset($_GET['context']) ? sanitize_key(wp_unslash($_GET['context'])) : '';
            if ($context === 'edit') {
                return false;
            }
        }

        if (\function_exists('ux_builder_is_active') && ux_builder_is_active()) {
            return false;
        }

        return true;
    }
}
