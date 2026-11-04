<?php
/**
 * Admin UI chrome icons, rendered from the bundled Lucide catalog.
 *
 * Returns a renderer: $icon( 'name', 16 ) → inline SVG.
 * The markup is escaped by WP Icons, so the returned string is safe to echo.
 * The plugin mark is the Lucide "shapes" icon (the same name the editor script uses).
 */

if (!defined('ABSPATH')) {
    exit;
}

return static function (string $name, int $size = 16, string $class = ''): string {
    return \WPIcons\Api\Icons::bundled('lucide', $name, [
        'size' => $size,
        'class' => trim('wpicons__admin-icon ' . $class),
    ]);
};
