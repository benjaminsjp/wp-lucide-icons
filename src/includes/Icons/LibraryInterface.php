<?php

namespace WPIcons\Icons;

if (!\defined('ABSPATH')) {
    exit;
}

interface LibraryInterface
{
    public function id(): string;

    public function label(): string;

    public function npmPackage(): string;

    public function bundledVersion(): string;

    /**
     * Capabilities used by the editor UI: size, color, stroke, variants, brand-color.
     *
     * @return list<string>
     */
    public function supports(): array;

    /**
     * Icon names in the bundled catalog for a style ('' = the library's default style).
     *
     * @return list<string>
     */
    public function bundledNames(string $variant = ''): array;

    public function bundledCount(string $variant = ''): int;

    public function bundledInner(string $name, string $variant = ''): ?string;

    public function wrap(string $inner, IconOptions $options): string;

    /**
     * Selectable styles (id => translated label). Empty when the library has a single style.
     *
     * @return array<string, string>
     */
    public function variants(): array;

    /** Shortcode attribute that carries the style: "weight", "style", or '' when there is none. */
    public function variantParam(): string;

    public function defaultVariant(): string;

    /** Whether the given style is drawn with strokes, so its stroke width can be changed. */
    public function supportsStroke(string $variant = ''): bool;

    public function defaultStroke(): float;

    /** Whether icons have an official brand color (Simple Icons). */
    public function hasBrandColors(): bool;

    /** Whether the library contains brand logos at all. */
    public function hasBrands(): bool;

    public function isBrand(string $name): bool;

    /**
     * Brand metadata for one icon.
     *
     * @return array{title?: string, hex?: string, guidelines?: string, license?: string}
     */
    public function iconMeta(string $name): array;

    /**
     * Icon used to represent the library in the admin UI.
     *
     * @return array{0: string, 1: string} [icon name, style]
     */
    public function glyph(): array;

    /**
     * Maps a file inside the npm package tarball to [style, icon name, priority], or null to skip it.
     * Used when a site downloads a newer catalog with the CDN source.
     *
     * @return array{0: string, 1: string, 2?: int}|null
     */
    public function packageEntry(string $path): ?array;

    /** Whether browsing one style also lists icons that only exist in another style (style-less logos). */
    public function listsAllVariants(): bool;

    /** viewBox of an icon; pass '' as name for the style's default viewBox. */
    public function viewBox(string $name, string $variant = ''): string;
}