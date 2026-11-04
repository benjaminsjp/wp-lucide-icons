<?php

namespace WPIcons\Icons;

use WPIcons\Admin\Settings;
use WPIcons\Api\Icons;
use WPIcons\Icons\Package\PackageLibrary;

if (!\defined('ABSPATH')) {
    exit;
}

final class Registry
{
    /**
     * @var array<string, LibraryInterface>|null
     */
    private static ?array $libraries = null;

    /**
     * @return array<string, LibraryInterface>
     */
    public static function all(): array
    {
        if (self::$libraries === null) {
            self::$libraries = [];
            foreach (PackageLibrary::IDS as $id) {
                $library = new PackageLibrary($id);
                self::$libraries[$library->id()] = $library;
            }
        }

        return self::$libraries;
    }

    public static function get(string $id): ?LibraryInterface
    {
        return self::all()[$id] ?? null;
    }

    /**
     * @return array<string, LibraryInterface>
     */
    public static function enabled(): array
    {
        $settings = Settings::get();
        $enabled = [];

        foreach (self::all() as $id => $library) {
            if (Settings::libraryEnabled($settings, $id)) {
                $enabled[$id] = $library;
            }
        }

        return $enabled;
    }

    /**
     * The library's own icon (from its bundled catalog, even when the library is disabled), for the
     * admin UI. Decorative: aria-hidden, currentColor.
     */
    public static function glyph(string $id, int $size = 18): string
    {
        $library = self::get($id);
        if ($library === null) {
            return '';
        }

        [$name, $variant] = $library->glyph();

        return Icons::bundled($id, $name, [
            'size' => $size,
            'variant' => $variant,
            'color' => 'currentColor',
            'class' => 'wpicons__library-glyph',
        ]);
    }
}