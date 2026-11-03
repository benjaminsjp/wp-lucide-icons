<?php

namespace WPIcons\Icons\Package;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Loads the generated catalogs in src/includes/Icons/Catalogs/<library>/ (see scripts/sync-packages.js).
 * Each style is its own file, so rendering a Phosphor "bold" icon never loads the other five weights.
 */
final class PackageCatalog
{
    /** @var array<string, array<string, mixed>> */
    private static array $indexes = [];

    /** @var array<string, array<string, string>> */
    private static array $variants = [];

    private static function dir(string $libraryId): string
    {
        return WP_ICONS__PATH . 'src/includes/Icons/Catalogs/' . $libraryId . '/';
    }

    /**
     * @return array{version?: string, package?: string, defaultVariant?: string, listUnion?: bool, count?: int, variants?: array<string, int>, viewBox?: array<string, string>, viewBoxes?: array<string, array<string, string>>, brands?: list<string>, meta?: array<string, array<string, string>>, excluded?: list<string>}
     */
    public static function index(string $libraryId): array
    {
        if (isset(self::$indexes[$libraryId])) {
            return self::$indexes[$libraryId];
        }

        $index = [];
        if (preg_match('/^[a-z0-9-]+$/', $libraryId) === 1) {
            $path = self::dir($libraryId) . 'index.php';
            if (is_readable($path)) {
                $loaded = require $path;
                $index = \is_array($loaded) ? $loaded : [];
            }
        }

        self::$indexes[$libraryId] = $index;

        return $index;
    }

    /**
     * @return array<string, string> icon name => inner SVG
     */
    public static function icons(string $libraryId, string $variant): array
    {
        $key = $libraryId . '/' . $variant;
        if (isset(self::$variants[$key])) {
            return self::$variants[$key];
        }

        $icons = [];
        $index = self::index($libraryId);
        // Only styles listed in the generated index can be loaded (no arbitrary paths).
        if (isset($index['variants']) && \is_array($index['variants']) && \array_key_exists($variant, $index['variants'])) {
            $path = self::dir($libraryId) . $variant . '.php';
            if (is_readable($path)) {
                $loaded = require $path;
                $icons = \is_array($loaded) ? $loaded : [];
            }
        }

        self::$variants[$key] = $icons;

        return $icons;
    }
}