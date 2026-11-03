<?php

namespace WPIcons\Icons;

use WPIcons\Admin\Settings;
use WPIcons\Icons\Cdn\CdnCatalog;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Bundled catalog, or the cached CDN catalog when a library uses the CDN source.
 */
final class CatalogStore
{
    /** @var array<string, list<string>> */
    private static array $names = [];

    private static function usesCdn(string $libraryId): bool
    {
        return Settings::librarySource(Settings::get(), $libraryId) === 'cdn';
    }

    /**
     * Inner markup and the style it was found in. Falls back to the library default style, then any
     * style, so an icon that only exists in one style (for example an Ionicons logo) still renders.
     *
     * @return array{inner: string, variant: string}|null
     */
    public static function resolve(string $libraryId, string $name, string $variant = ''): ?array
    {
        $library = Registry::get($libraryId);
        if ($library === null || $name === '') {
            return null;
        }

        $order = array_values(array_unique(array_filter(
            [$variant, $library->defaultVariant(), ...array_keys($library->variants())],
            static fn ($v) => $v !== ''
        )));
        if ($order === []) {
            $order = [''];
        }

        $cdn = self::usesCdn($libraryId);
        foreach ($order as $candidate) {
            if ($cdn) {
                $cached = CdnCatalog::inner($libraryId, $name, $candidate);
                if ($cached !== null) {
                    return ['inner' => $cached, 'variant' => $candidate];
                }
            }

            $inner = $library->bundledInner($name, $candidate);
            if ($inner !== null && $inner !== '') {
                return ['inner' => $inner, 'variant' => $candidate];
            }
        }

        return null;
    }

    public static function inner(string $libraryId, string $name, string $variant = ''): ?string
    {
        return self::resolve($libraryId, $name, $variant)['inner'] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function names(string $libraryId, string $variant = ''): array
    {
        $library = Registry::get($libraryId);
        if ($library === null) {
            return [];
        }

        $source = Settings::librarySource(Settings::get(), $libraryId);
        $cacheKey = $libraryId . ':' . $source . ':' . $variant;
        if (isset(self::$names[$cacheKey])) {
            return self::$names[$cacheKey];
        }

        $names = $library->bundledNames($variant);

        if ($source === 'cdn') {
            $cdnNames = CdnCatalog::names($libraryId, self::cdnVariant($library, $variant), $library->listsAllVariants());
            if ($cdnNames !== []) {
                $names = array_values(array_unique([...$cdnNames, ...$names]));
            }
        }

        sort($names, SORT_STRING);
        self::$names[$cacheKey] = $names;

        return $names;
    }

    /** Number of icons listed for a style, without loading SVG markup when the bundled catalog is used. */
    public static function count(string $libraryId, string $variant = ''): int
    {
        $library = Registry::get($libraryId);
        if ($library === null) {
            return 0;
        }

        if (self::usesCdn($libraryId) && CdnCatalog::version($libraryId) !== null) {
            return \count(self::names($libraryId, $variant));
        }

        return $library->bundledCount($variant);
    }

    public static function version(string $libraryId): string
    {
        $library = Registry::get($libraryId);
        if ($library === null) {
            return '';
        }

        if (self::usesCdn($libraryId)) {
            $cdnVersion = CdnCatalog::version($libraryId);
            if ($cdnVersion !== null && $cdnVersion !== '') {
                return $cdnVersion;
            }
        }

        return $library->bundledVersion();
    }

    /**
     * Brand metadata (title, hex, guidelines, license) for one icon.
     *
     * @return array<string, string>
     */
    public static function meta(string $libraryId, string $name): array
    {
        $library = Registry::get($libraryId);
        if ($library === null) {
            return [];
        }

        $meta = $library->iconMeta($name);
        if (self::usesCdn($libraryId)) {
            $meta = [...$meta, ...CdnCatalog::meta($libraryId, $name)];
        }

        return $meta;
    }

    public static function viewBox(string $libraryId, string $name, string $variant = ''): string
    {
        $library = Registry::get($libraryId);
        if ($library === null) {
            return '0 0 24 24';
        }

        if (self::usesCdn($libraryId)) {
            $cached = CdnCatalog::viewBox($libraryId, $name, self::cdnVariant($library, $variant));
            if ($cached !== null) {
                return $cached;
            }
        }

        return $library->viewBox($name, $variant);
    }

    public static function isBrand(string $libraryId, string $name): bool
    {
        $library = Registry::get($libraryId);

        return $library !== null && $library->isBrand($name);
    }

    private static function cdnVariant(LibraryInterface $library, string $variant): string
    {
        if ($library->variants() === [] && $library->defaultVariant() === '') {
            return '';
        }

        if ($variant === '' || !\in_array($variant, $library instanceof Package\PackageLibrary ? $library->variantKeys() : array_keys($library->variants()), true)) {
            return $library->defaultVariant();
        }

        return $variant;
    }
}