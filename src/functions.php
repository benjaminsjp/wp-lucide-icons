<?php

/**
 * Public functions for themes and plugins. Available as soon as WP Icons loads, before plugins_loaded.
 *
 * @package WPIcons
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SVG markup for one icon, or '' when the library is unknown or disabled, or the icon does not exist.
 *
 * Safe to echo: attributes are escaped and the inner markup is sanitized by WP Icons.
 * Do not run the result through wp_kses_post(), which strips <svg>.
 *
 * An icon with a title is exposed as role="img" with that accessible name. Leave the title empty
 * for a decorative icon. On a button or link, keep the icon decorative and name the control.
 *
 * @since 2.0.0
 *
 * @param string               $ref  'library:name[:variant]', or a bare name (Settings default library).
 * @param array<string, mixed> $args {
 *     Optional. size defaults to the Settings default size. color and stroke default per library.
 *     $args['variant'] overrides the variant in $ref. $args['library'] and $args['name'] are ignored.
 *
 *     @type int|float    $size    Icon width in pixels.
 *     @type int|float    $height  Icon height in pixels. Defaults to size.
 *     @type string       $color   CSS color, var(--token), currentColor, or 'brand' (Simple Icons).
 *     @type float|int|string $stroke Stroke width. Stroke-drawn styles only.
 *     @type string       $variant Style key (Phosphor weight, Tabler / Heroicons / Ionicons style).
 *     @type string       $class   Extra class on the SVG.
 *     @type string       $title   Accessible name. Empty means decorative.
 * }
 */
function wpicons_get_icon(string $ref, array $args = []): string
{
    return \WPIcons\Api\Icons::get($ref, $args);
}

/**
 * Prints the markup from wpicons_get_icon().
 *
 * @since 2.0.0
 *
 * @param string               $ref  'library:name[:variant]', or a bare name (Settings default library).
 * @param array<string, mixed> $args Same arguments as wpicons_get_icon().
 */
function wpicons_icon(string $ref, array $args = []): void
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WP Icons.
    echo wpicons_get_icon($ref, $args);
}

/**
 * Whether wpicons_get_icon() would render something.
 *
 * @since 2.0.0
 *
 * @param string $ref 'library:name[:variant]', or a bare name (Settings default library).
 */
function wpicons_icon_exists(string $ref): bool
{
    return \WPIcons\Api\Icons::exists($ref);
}

/**
 * Libraries as plain arrays, keyed by id.
 *
 * Each value is id, label, enabled, variants (style key => label), default_variant, supports, and version.
 * A library with one style has an empty variants list.
 *
 * @since 2.0.0
 *
 * @param bool $enabled_only When true, only libraries turned on in Settings are returned.
 * @return array<string, array<string, mixed>>
 */
function wpicons_get_libraries(bool $enabled_only = true): array
{
    return \WPIcons\Api\Icons::libraries($enabled_only);
}
