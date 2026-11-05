<?php

namespace WPIcons\Api;

use WPIcons\Admin\Settings;
use WPIcons\Icons\CatalogStore;
use WPIcons\Icons\IconOptions;
use WPIcons\Icons\LibraryInterface;
use WPIcons\Icons\Package\PackageCatalog;
use WPIcons\Icons\Registry;
use WPIcons\Icons\Renderer;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Public icon helpers. The global functions in src/functions.php are thin wrappers around this class.
 *
 * @since 2.0.0
 */
final class Icons
{
    /** @var array<string, string> */
    private static array $cache = [];

    /**
     * SVG markup for one icon, or '' when the reference is invalid, the library is disabled, or the icon is missing.
     *
     * Safe to echo. Attributes are escaped and the inner markup is sanitized. Do not pass the result through
     * wp_kses_post(), which strips SVG. A wpicons_icon_svg callback must return safe markup of its own.
     *
     * @since 2.0.0
     *
     * @param string $ref  'library:name[:variant]', or a bare name that uses the Settings default library.
     * @param array<string, mixed> $args size, height, color, stroke, variant, class, title.
     *        size defaults to the Settings default size. color and stroke default per library inside the renderer.
     *        variant in $args overrides the variant in $ref. library and name in $args are ignored.
     */
    public static function get(string $ref, array $args = []): string
    {
        $parsed = self::parseRef($ref);
        if ($parsed === null) {
            return '';
        }

        [$library, $name, $refVariant] = $parsed;
        $settings = Settings::get();
        if ($library === '') {
            $library = (string) $settings['default_library'];
        }

        $attrs = $args;
        unset($attrs['library'], $attrs['name']);
        $attrs['library'] = $library;
        $attrs['name'] = $name;
        $variant = $args['variant'] ?? $refVariant;
        $attrs['variant'] = \is_string($variant) ? $variant : $refVariant;
        if (!\array_key_exists('size', $args)) {
            $attrs['size'] = $settings['default_size'];
        }

        /**
         * Filters the attributes passed to the renderer for a wpicons_get_icon() call.
         *
         * @since 2.0.0
         *
         * @param array<string, mixed> $attrs Attributes for Renderer::fromAttributes().
         * @param string               $ref   The icon reference the caller passed.
         */
        $filtered = apply_filters('wpicons_icon_args', $attrs, $ref);
        if (!\is_array($filtered)) {
            return '';
        }

        $key = '';
        try {
            $key = md5(serialize($filtered));
        } catch (\Exception) {
            $key = '';
        }

        if ($key !== '' && isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $svg = (new Renderer())->fromAttributes($filtered);
        if ($key !== '') {
            self::$cache[$key] = $svg;
        }

        return $svg;
    }

    /**
     * Whether get() would return markup: the reference parses, the library is enabled, and the icon is in the bundle or CDN cache.
     *
     * @since 2.0.0
     */
    public static function exists(string $ref): bool
    {
        $parsed = self::parseRef($ref);
        if ($parsed === null) {
            return false;
        }

        [$library, $name, $variant] = $parsed;
        $settings = Settings::get();
        if ($library === '') {
            $library = (string) $settings['default_library'];
        }

        if (Registry::get($library) === null || !Settings::libraryEnabled($settings, $library)) {
            return false;
        }

        return CatalogStore::resolve($library, $name, $variant) !== null;
    }

    /**
     * Libraries as plain arrays. No internal objects are returned.
     *
     * @since 2.0.0
     *
     * @return array<string, array{id: string, label: string, enabled: bool, variants: array<string, string>, default_variant: string, supports: list<string>, version: string}>
     */
    public static function libraries(bool $enabledOnly = true): array
    {
        $settings = Settings::get();
        $libraries = [];

        foreach ($enabledOnly ? Registry::enabled() : Registry::all() as $id => $library) {
            $variants = [];
            foreach ($library->variants() as $key => $label) {
                $variants[(string) $key] = (string) $label;
            }

            $libraries[$id] = [
                'id' => $id,
                'label' => $library->label(),
                'enabled' => Settings::libraryEnabled($settings, $id),
                'variants' => $variants,
                'default_variant' => $library->defaultVariant(),
                'supports' => array_values($library->supports()),
                'version' => CatalogStore::version($id),
            ];
        }

        return $libraries;
    }

    /**
     * Renders from the bundled catalog, ignoring the enabled flag, the CDN source, and the public filters.
     * Used by the plugin's own admin chrome. Not part of the public function API.
     *
     * @since 2.0.0
     *
     * @param array<string, mixed> $args size, height, color, stroke, variant, class.
     */
    public static function bundled(string $libraryId, string $name, array $args = []): string
    {
        $library = Registry::get($libraryId);
        if ($library === null || $name === '') {
            return '';
        }

        $variant = isset($args['variant']) && \is_string($args['variant']) ? $args['variant'] : '';
        $inner = self::bundledInner($library, $name, $variant);
        if ($inner === null || $inner === '') {
            return '';
        }

        $stroke = $args['stroke'] ?? $args['strokeWidth'] ?? $library->defaultStroke();
        $size = isset($args['size']) ? (int) $args['size'] : 24;
        $height = isset($args['height']) ? (int) $args['height'] : null;

        return $library->wrap($inner, new IconOptions(
            name: $name,
            size: $size > 0 ? $size : 24,
            height: $height !== null && $height > 0 ? $height : null,
            color: isset($args['color']) && \is_string($args['color']) && $args['color'] !== '' ? $args['color'] : 'currentColor',
            strokeWidth: is_numeric($stroke) ? (float) $stroke : $library->defaultStroke(),
            class: isset($args['class']) && \is_string($args['class']) ? $args['class'] : '',
            hiddenOrTitle: true,
            variant: $variant,
        ));
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null [library, name, variant]. An empty library means the Settings default.
     */
    private static function parseRef(string $ref): ?array
    {
        $ref = strtolower(trim($ref));
        if ($ref === '') {
            return null;
        }

        $parts = explode(':', $ref);
        if (\count($parts) === 1) {
            return preg_match('/^[a-z0-9_-]+$/', $parts[0]) === 1 ? ['', $parts[0], ''] : null;
        }

        if (\count($parts) !== 2 && \count($parts) !== 3) {
            return null;
        }

        if (preg_match('/^[a-z0-9-]+$/', $parts[0]) !== 1 || preg_match('/^[a-z0-9_-]+$/', $parts[1]) !== 1) {
            return null;
        }

        $variant = '';
        if (\count($parts) === 3) {
            $variant = IconOptions::cleanVariant($parts[2]);
            if ($variant === '') {
                return null;
            }
        }

        return [$parts[0], $parts[1], $variant];
    }

    private static function bundledInner(LibraryInterface $library, string $name, string $variant): ?string
    {
        $glyph = PackageCatalog::index($library->id())['glyph'] ?? null;
        $wanted = $variant !== '' ? $variant : $library->defaultVariant();
        if (
            \is_array($glyph)
            && ($glyph['name'] ?? '') === $name
            && (string) ($glyph['variant'] ?? '') === $wanted
            && \is_string($glyph['inner'] ?? null)
            && $glyph['inner'] !== ''
        ) {
            return $glyph['inner'];
        }

        return $library->bundledInner($name, $variant);
    }
}
