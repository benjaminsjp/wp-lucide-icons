<?php

namespace WPIcons\Icons;

use WPIcons\Admin\Settings;
use WPIcons\Icons\Lucide\LucideLibrary;
use WPIcons\Icons\Material\MaterialSymbolsLibrary;
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
            $list = [new LucideLibrary(), new MaterialSymbolsLibrary()];
            foreach (PackageLibrary::IDS as $id) {
                $list[] = new PackageLibrary($id);
            }

            self::$libraries = [];
            foreach ($list as $library) {
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
        $inner = $library->bundledInner($name, $variant);
        if ($inner === null || $inner === '') {
            return '';
        }

        return $library->wrap($inner, new IconOptions(
            name: $name,
            size: $size,
            color: 'currentColor',
            strokeWidth: 2.0,
            class: 'wpicons__library-glyph',
            variant: $variant,
        ));
    }
}