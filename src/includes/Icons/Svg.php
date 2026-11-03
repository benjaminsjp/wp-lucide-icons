<?php

namespace WPIcons\Icons;

if (!\defined('ABSPATH')) {
    exit;
}

final class Svg
{
    public static function color(string $color): string
    {
        $color = trim($color);
        if ($color === '' || strcasecmp($color, 'currentColor') === 0) {
            return 'currentColor';
        }

        if ($color === 'inherit' || $color === 'transparent') {
            return $color;
        }

        if (preg_match('/^var\(\s*(--[a-zA-Z0-9_-]+)\s*\)$/', $color, $matches)) {
            return 'var(' . $matches[1] . ')';
        }

        if (\function_exists('sanitize_hex_color')) {
            $hex = sanitize_hex_color($color);
            if (\is_string($hex) && $hex !== '') {
                return $hex;
            }
        } elseif (preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color)) {
            return $color;
        }

        if (preg_match('/^(?:rgb|hsl)a?\([^)]+\)$/i', $color)) {
            return $color;
        }

        if (preg_match('/^[a-z]{3,20}$/i', $color)) {
            return strtolower($color);
        }

        return 'currentColor';
    }

    public static function classAttr(string $class): string
    {
        $class = trim(preg_replace('/[^A-Za-z0-9 _\-:]/', '', $class) ?? '');

        return $class;
    }

    public static function sanitizeInner(string $inner): string
    {
        $inner = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $inner) ?? $inner;
        $inner = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $inner) ?? $inner;
        $inner = preg_replace('/javascript\s*:/i', '', $inner) ?? $inner;

        return trim($inner);
    }

    public static function lucide(string $inner, IconOptions $options): string
    {
        $hidden = Common::hidden($options->hiddenOrTitle);
        $title = Common::title($options->hiddenOrTitle);
        $name = esc_attr($options->name);
        $width = (int) $options->size;
        $height = (int) $options->height;
        $color = esc_attr(self::color($options->color));
        $stroke = esc_attr((string) $options->strokeWidth);
        $class = trim('wpicons wpicons--lucide lucide lucide-' . $name . '-icon lucide-' . $name . ' ' . self::classAttr($options->class));
        $class_attr = esc_attr($class);
        $inner = self::sanitizeInner($inner);

        return <<<SVG
<svg {$hidden} xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 24 24" fill="none" stroke="{$color}" stroke-width="{$stroke}" stroke-linecap="round" stroke-linejoin="round" class="{$class_attr}">
{$title}{$inner}
</svg>
SVG;
    }

    /**
     * Wraps an icon from one of the npm-packaged libraries (see Package\PackageLibrary).
     * Paint comes from currentColor; an explicit color is set with the CSS color property so it also
     * reaches child elements that use currentColor themselves (Ionicons outlines, Phosphor duotone).
     *
     * @param string      $paint    "stroke" (outline styles) or "fill".
     * @param string|null $brandHex Official brand color (Simple Icons), used when the color is "brand".
     */
    public static function package(
        string $inner,
        IconOptions $options,
        string $libraryId,
        string $variant,
        string $viewBox,
        string $paint,
        ?string $brandHex = null
    ): string {
        $hidden = Common::hidden($options->hiddenOrTitle);
        $title = Common::title($options->hiddenOrTitle);
        $width = (int) $options->size;
        $height = (int) $options->height;

        if (strcasecmp(trim($options->color), 'brand') === 0) {
            $color = $brandHex !== null && preg_match('/^[0-9a-f]{6}$/i', $brandHex) === 1
                ? '#' . strtolower($brandHex)
                : 'currentColor';
        } else {
            $color = self::color($options->color);
        }

        $id = self::classAttr($libraryId);
        $name = self::classAttr($options->name);
        $classes = ['wpicons', 'wpicons--' . $id];
        if ($variant !== '') {
            $classes[] = 'wpicons--' . $id . '-' . self::classAttr($variant);
        }
        $classes[] = 'wpicons-' . $id . '-' . $name;
        $class_attr = esc_attr(trim(implode(' ', $classes) . ' ' . self::classAttr($options->class)));

        if (preg_match('/^-?\d+(?:\.\d+)?(?:[ ,]+-?\d+(?:\.\d+)?){3}$/', trim($viewBox)) !== 1) {
            $viewBox = '0 0 24 24';
        }
        $view_box = esc_attr(trim($viewBox));

        $paint_attr = $paint === 'stroke'
            ? 'fill="none" stroke="currentColor" stroke-width="' . esc_attr((string) $options->strokeWidth) . '" stroke-linecap="round" stroke-linejoin="round"'
            : 'fill="currentColor"';
        $style_attr = $color === 'currentColor' ? '' : ' style="' . esc_attr('color:' . $color) . '"';
        $inner = self::sanitizeInner($inner);

        return <<<SVG
<svg {$hidden} xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="{$view_box}" {$paint_attr} class="{$class_attr}"{$style_attr}>
{$title}{$inner}
</svg>
SVG;
    }

    public static function material(string $inner, IconOptions $options): string
    {
        $hidden = Common::hidden($options->hiddenOrTitle);
        $title = Common::title($options->hiddenOrTitle);
        $name = esc_attr($options->name);
        $width = (int) $options->size;
        $height = (int) $options->height;
        $color = esc_attr(self::color($options->color));
        $class = trim('wpicons wpicons--material-symbols material-symbols material-symbols-' . $name . ' ' . self::classAttr($options->class));
        $class_attr = esc_attr($class);
        $inner = self::sanitizeInner($inner);

        return <<<SVG
<svg {$hidden} xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 -960 960 960" fill="{$color}" class="{$class_attr}">
{$title}{$inner}
</svg>
SVG;
    }
}
