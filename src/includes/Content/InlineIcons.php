<?php

namespace WPIcons\Content;

use WPIcons\Admin\Settings;
use WPIcons\Icons\CatalogStore;
use WPIcons\Icons\Registry;
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
            $tags[$tag]['data-hex'] = true;
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
            // UX Builder keeps the editable placeholder. Paint its color instead of replacing it.
            if ($this->shouldPaintPlaceholders()) {
                return $this->paintPlaceholders($html);
            }

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
        if (is_admin() || $this->isRestEditRequest()) {
            return false;
        }

        if (\function_exists('ux_builder_is_active') && ux_builder_is_active()) {
            return false;
        }

        return true;
    }

    /**
     * Preview-only. The frontend still replaces the placeholder with an SVG and ignores data-hex.
     */
    private function shouldPaintPlaceholders(): bool
    {
        if (is_admin() || $this->isRestEditRequest()) {
            return false;
        }

        return \function_exists('ux_builder_is_active') && ux_builder_is_active();
    }

    private function isRestEditRequest(): bool
    {
        if (!\defined('REST_REQUEST') || !REST_REQUEST) {
            return false;
        }

        // Public read of the REST editor flag. No state change, same check as core's edit context.
        $context = isset($_GET['context']) ? sanitize_key(wp_unslash($_GET['context'])) : '';

        return $context === 'edit';
    }

    private function paintPlaceholders(string $html): string
    {
        $processor = new WP_HTML_Tag_Processor($html);

        while ($processor->next_tag()) {
            if (!$this->isInlinePlaceholder($processor)) {
                continue;
            }

            $this->paintPlaceholder($processor);
        }

        $updated = $processor->get_updated_html();

        return \is_string($updated) && $updated !== '' ? $updated : $html;
    }

    private function isInlinePlaceholder(WP_HTML_Tag_Processor $processor): bool
    {
        $tag = strtoupper((string) $processor->get_tag());
        if ($tag !== 'IMG' && $tag !== 'SPAN') {
            return false;
        }

        $class = $processor->get_attribute('class');
        if (!\is_string($class)) {
            return false;
        }

        return preg_match('/(?:^|\s)wpicons-inline(?:\s|$)/', $class) === 1;
    }

    private function paintPlaceholder(WP_HTML_Tag_Processor $processor): void
    {
        $colorAttr = $processor->get_attribute('data-color');
        $color = \is_string($colorAttr) ? trim($colorAttr) : '';
        $styleAttr = $processor->get_attribute('style');
        $style = \is_string($styleAttr) ? $styleAttr : '';

        if (strcasecmp($color, 'brand') === 0) {
            $libraryAttr = $processor->get_attribute('data-library');
            $nameAttr = $processor->get_attribute('data-name');
            $hex = $this->brandHex(
                \is_string($libraryAttr) ? trim($libraryAttr) : '',
                \is_string($nameAttr) ? trim($nameAttr) : ''
            );

            if ($hex === null) {
                $this->writeStyle($processor, $style, $this->withoutBackground($style, 'brand'));
                return;
            }

            $processor->set_attribute('data-hex', $hex);
            $this->writeStyle($processor, $style, $this->withBackground($style, '#' . $hex));
            return;
        }

        $paint = $this->previewColor($color);
        if ($paint === '') {
            return;
        }

        $this->writeStyle($processor, $style, $this->withBackground($style, $paint));
    }

    /**
     * Official brand hex from the catalog. data-hex on the placeholder is never trusted.
     */
    private function brandHex(string $libraryId, string $name): ?string
    {
        if ($name === '') {
            return null;
        }

        if ($libraryId === '') {
            $settings = Settings::get();
            $libraryId = \is_string($settings['default_library'] ?? null) ? $settings['default_library'] : '';
        }

        $library = Registry::get($libraryId);
        if ($library === null || !$library->hasBrandColors()) {
            return null;
        }

        $hex = CatalogStore::meta($libraryId, $name)['hex'] ?? '';
        if (!\is_string($hex) || preg_match('/^[0-9a-f]{6}$/i', $hex) !== 1) {
            return null;
        }

        return strtolower($hex);
    }

    /**
     * Mirrors placeholder.js safeColor(). Empty means "leave currentColor alone".
     */
    private function previewColor(string $color): string
    {
        $value = trim($color);
        if ($value === '' || strcasecmp($value, 'currentColor') === 0 || strcasecmp($value, 'brand') === 0) {
            return '';
        }

        if (preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) === 1) {
            return $value;
        }

        if (preg_match('/^(?:rgb|hsl)a?\([0-9.,%\s\/]+\)$/i', $value) === 1) {
            return str_replace([';', '"', '\\'], '', $value);
        }

        if (preg_match('/^[a-z]{3,20}$/i', $value) === 1) {
            return strtolower($value);
        }

        if (preg_match('/^var\(\s*(--[a-zA-Z0-9_-]+)\s*\)$/', $value, $matches) === 1) {
            return 'var(' . $matches[1] . ')';
        }

        return '';
    }

    private function writeStyle(WP_HTML_Tag_Processor $processor, string $current, string $style): void
    {
        if ($style === $current) {
            return;
        }

        if ($style === '') {
            $processor->remove_attribute('style');
            return;
        }

        $processor->set_attribute('style', $style);
    }

    private function withBackground(string $style, string $color): string
    {
        if ($color === '' || preg_match('/[;{}<>\\\\"\']/', $color) === 1) {
            return $style;
        }

        $kept = $this->withoutBackground($style, null);
        $declaration = 'background-color:' . $color;

        return $kept === '' ? $declaration : $kept . ';' . $declaration;
    }

    /**
     * Drops background-color declarations. $onlyValue limits that to one value (for example "brand").
     * Semicolons inside quotes or url() are left intact.
     */
    private function withoutBackground(string $style, ?string $onlyValue): string
    {
        $kept = [];
        foreach ($this->styleDeclarations($style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            $property = strtolower(trim($parts[0]));
            if ($property === 'background-color') {
                $value = isset($parts[1]) ? trim($parts[1]) : '';
                if ($onlyValue === null || strcasecmp($value, $onlyValue) === 0) {
                    continue;
                }
            }
            $kept[] = $declaration;
        }

        return implode(';', $kept);
    }

    /**
     * @return list<string>
     */
    private function styleDeclarations(string $style): array
    {
        $declarations = [];
        $current = '';
        $length = strlen($style);
        $quote = '';
        $parens = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $style[$i];
            if ($quote !== '') {
                $current .= $char;
                if ($char === $quote && ($i === 0 || $style[$i - 1] !== '\\')) {
                    $quote = '';
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ($char === '(') {
                ++$parens;
                $current .= $char;
                continue;
            }

            if ($char === ')' && $parens > 0) {
                --$parens;
                $current .= $char;
                continue;
            }

            if ($char === ';' && $parens === 0) {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $declarations[] = $trimmed;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $trimmed = trim($current);
        if ($trimmed !== '') {
            $declarations[] = $trimmed;
        }

        return $declarations;
    }
}
