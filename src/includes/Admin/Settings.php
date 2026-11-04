<?php

namespace WPIcons\Admin;

use WPIcons\Icons\CatalogStore;
use WPIcons\Icons\Cdn\CdnCatalog;
use WPIcons\Icons\Registry;
use WPIcons\Template\Template;

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    public const OPTION = 'wp_icons__settings';

    /** Legacy admin.php slug, kept so old bookmarks can redirect. */
    public const PAGE = 'wp-icons-settings';

    public const GROUP = 'wp_icons';

    private Template $templates;

    public function __construct(Template $templates)
    {
        $this->templates = $templates;
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSetting']);
        add_action('update_option_' . self::OPTION, [$this, 'onUpdated'], 10, 2);
        add_filter('option_page_capability_' . self::GROUP, static fn (): string => Menu::CAPABILITY);
        add_filter(
            'plugin_action_links_' . plugin_basename(WP_ICONS__FILE),
            [$this, 'pluginLinks']
        );
    }

    /**
     * @return array{
     *   default_library: string,
     *   default_size: int,
     *   default_color: string,
     *   default_stroke: float,
     *   libraries: array<string, array{enabled: bool, source: string, cdn_version: string}>
     * }
     */
    public static function get(): array
    {
        $defaults = self::defaults();
        $stored = get_option(self::OPTION, []);
        if (!\is_array($stored)) {
            return $defaults;
        }

        $stored = self::migrate($stored);
        $libraries = $defaults['libraries'];
        if (isset($stored['libraries']) && \is_array($stored['libraries'])) {
            foreach ($libraries as $id => $libraryDefaults) {
                if (!isset($stored['libraries'][$id]) || !\is_array($stored['libraries'][$id])) {
                    continue;
                }
                $libraries[$id] = [
                    ...$libraryDefaults,
                    ...$stored['libraries'][$id],
                    'enabled' => !empty($stored['libraries'][$id]['enabled']),
                    'source' => ($stored['libraries'][$id]['source'] ?? '') === 'cdn' ? 'cdn' : 'plugin',
                ];
            }
        }

        $defaultLibrary = isset($stored['default_library']) ? (string) $stored['default_library'] : $defaults['default_library'];
        if (!isset($libraries[$defaultLibrary])) {
            $defaultLibrary = $defaults['default_library'];
        }

        return [
            'default_library' => $defaultLibrary,
            'default_size' => isset($stored['default_size']) ? max(1, (int) $stored['default_size']) : $defaults['default_size'],
            'default_color' => isset($stored['default_color']) && \is_string($stored['default_color']) && $stored['default_color'] !== ''
                ? $stored['default_color']
                : $defaults['default_color'],
            'default_stroke' => isset($stored['default_stroke']) && is_numeric($stored['default_stroke'])
                ? (float) $stored['default_stroke']
                : $defaults['default_stroke'],
            'libraries' => $libraries,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function libraryEnabled(array $settings, string $libraryId): bool
    {
        return !empty($settings['libraries'][$libraryId]['enabled']);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function librarySource(array $settings, string $libraryId): string
    {
        $source = $settings['libraries'][$libraryId]['source'] ?? 'plugin';

        return $source === 'cdn' ? 'cdn' : 'plugin';
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function libraryVariant(array $settings, string $libraryId): string
    {
        $library = Registry::get($libraryId);
        if ($library === null) {
            return '';
        }

        $variant = $settings['libraries'][$libraryId]['variant'] ?? '';

        return \is_string($variant) && isset($library->variants()[$variant]) ? $variant : $library->defaultVariant();
    }

    /**
     * Default stroke width for new icons from a library. Lucide keeps using the global default_stroke.
     *
     * @param array<string, mixed> $settings
     */
    public static function libraryStroke(array $settings, string $libraryId): float
    {
        $stored = $settings['libraries'][$libraryId]['stroke'] ?? '';
        if ($stored !== '' && is_numeric($stored)) {
            return max(0.25, min(4, (float) $stored));
        }

        if ($libraryId === 'lucide') {
            return (float) $settings['default_stroke'];
        }

        return Registry::get($libraryId)?->defaultStroke() ?? (float) $settings['default_stroke'];
    }

    /**
     * "brand" (Simple Icons' official colors), "current" (inherit) or "custom".
     *
     * @param array<string, mixed> $settings
     */
    public static function libraryColorMode(array $settings, string $libraryId): string
    {
        $library = Registry::get($libraryId);
        $mode = $settings['libraries'][$libraryId]['color_mode'] ?? '';
        $allowed = $library !== null && $library->hasBrandColors() ? ['brand', 'current', 'custom'] : ['current', 'custom'];
        if (\is_string($mode) && \in_array($mode, $allowed, true)) {
            return $mode;
        }

        return $library !== null && $library->hasBrandColors() ? 'brand' : '';
    }

    /**
     * Default color for new icons from a library: its own override, else the global default color.
     *
     * @param array<string, mixed> $settings
     */
    public static function libraryColor(array $settings, string $libraryId): string
    {
        $mode = self::libraryColorMode($settings, $libraryId);
        if ($mode === 'brand') {
            return 'brand';
        }
        if ($mode === 'current') {
            return 'currentColor';
        }
        if ($mode === 'custom') {
            $color = $settings['libraries'][$libraryId]['color'] ?? '';
            if (\is_string($color) && $color !== '') {
                return $color;
            }
        }

        return (string) $settings['default_color'];
    }

    public static function sanitizeColor(string $color): string
    {
        $color = trim($color);
        if ($color === '' || strcasecmp($color, 'currentColor') === 0) {
            return 'currentColor';
        }
        if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color) === 1) {
            return strtolower($color);
        }
        if (preg_match('/^var\(--[a-zA-Z0-9_-]+\)$/', $color) === 1) {
            return $color;
        }

        return '';
    }

    public static function libraryCdnVersion(array $settings, string $libraryId): string
    {
        $version = $settings['libraries'][$libraryId]['cdn_version'] ?? 'latest';

        return \is_string($version) && $version !== '' ? $version : 'latest';
    }

    public function registerSetting(): void
    {
        register_setting(self::GROUP, self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default' => self::defaults(),
        ]);
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitize($input): array
    {
        $current = self::get();
        $input = \is_array($input) ? $input : [];
        $defaults = self::defaults();
        $libraries = [];

        foreach ($defaults['libraries'] as $id => $libraryDefaults) {
            $posted = isset($input['libraries'][$id]) && \is_array($input['libraries'][$id])
                ? $input['libraries'][$id]
                : [];

            $source = (isset($posted['source']) && $posted['source'] === 'cdn') ? 'cdn' : 'plugin';
            $version = isset($posted['cdn_version']) ? trim((string) $posted['cdn_version']) : $libraryDefaults['cdn_version'];
            if ($version !== 'latest' && !preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version)) {
                add_settings_error(
                    self::OPTION,
                    'wp_icons_invalid_version_' . $id,
                    sprintf(
                        /* translators: %s: icon library label */
                        __('CDN version for %s must be “latest” or a valid semver (for example 0.468.0).', 'wpicons'),
                        Registry::get($id)?->label() ?? $id
                    )
                );
                $version = $current['libraries'][$id]['cdn_version'] ?? 'latest';
            }

            $library = Registry::get($id);
            $previous = $current['libraries'][$id] ?? [];

            $variant = isset($posted['variant']) ? sanitize_key((string) $posted['variant']) : (string) ($previous['variant'] ?? '');
            if ($library === null || !isset($library->variants()[$variant])) {
                $variant = '';
            }

            $libraryStroke = $previous['stroke'] ?? '';
            if (isset($posted['stroke']) && is_numeric($posted['stroke'])) {
                $libraryStroke = (string) max(0.25, min(4, round((float) $posted['stroke'], 2)));
            }

            $colorMode = isset($posted['color_mode']) ? sanitize_key((string) $posted['color_mode']) : (string) ($previous['color_mode'] ?? '');
            $modes = $library !== null && $library->hasBrandColors() ? ['brand', 'current', 'custom'] : ['current', 'custom'];
            if (!\in_array($colorMode, $modes, true)) {
                $colorMode = '';
            }

            $libraryColor = isset($posted['color']) ? self::sanitizeColor((string) $posted['color']) : (string) ($previous['color'] ?? '');

            $libraries[$id] = [
                'enabled' => !empty($posted['enabled']),
                'source' => $source,
                'cdn_version' => $version,
                'variant' => $variant,
                'stroke' => $libraryStroke,
                'color_mode' => $colorMode,
                'color' => $libraryColor,
            ];
        }

        $enabledIds = array_keys(array_filter($libraries, static fn ($row) => !empty($row['enabled'])));
        if ($enabledIds === []) {
            $libraries[$defaults['default_library']]['enabled'] = true;
            $enabledIds = [$defaults['default_library']];
            add_settings_error(
                self::OPTION,
                'wp_icons_library_required',
                __('At least one icon library must stay enabled.', 'wpicons'),
                'warning'
            );
        }

        $defaultLibrary = isset($input['default_library']) ? (string) $input['default_library'] : $current['default_library'];
        if (!\in_array($defaultLibrary, $enabledIds, true)) {
            $defaultLibrary = $enabledIds[0];
        }

        $size = isset($input['default_size']) ? (int) $input['default_size'] : $current['default_size'];
        $stroke = isset($input['default_stroke']) && is_numeric($input['default_stroke'])
            ? (float) $input['default_stroke']
            : $current['default_stroke'];
        // The Lucide row's "Default stroke width" is the global default stroke (kept in sync).
        if (isset($input['libraries']['lucide']['stroke']) && is_numeric($input['libraries']['lucide']['stroke'])) {
            $stroke = (float) $input['libraries']['lucide']['stroke'];
        }
        $libraries['lucide']['stroke'] = '';
        $color = isset($input['default_color']) ? trim((string) $input['default_color']) : $current['default_color'];
        if ($color === '') {
            $color = 'currentColor';
        }


        return [
            'default_library' => $defaultLibrary,
            'default_size' => max(8, min(256, $size)),
            'default_color' => $color,
            'default_stroke' => max(0.25, min(4, $stroke)),
            'libraries' => $libraries,
        ];
    }

    /**
     * @param mixed $old
     * @param mixed $new
     */
    public function onUpdated($old, $new): void
    {
        if (!\is_array($new)) {
            return;
        }

        CdnCatalog::queueRefresh();
    }

    /**
     * @param array<int, string> $links
     * @return array<int, string>
     */
    public function pluginLinks(array $links): array
    {
        $library = Menu::url('library');
        $settings = Menu::url('settings');
        array_unshift(
            $links,
            '<a href="' . esc_url($library) . '">' . esc_html__('Library', 'wpicons') . '</a>',
            '<a href="' . esc_url($settings) . '">' . esc_html__('Settings', 'wpicons') . '</a>'
        );

        return $links;
    }

    public function renderPage(): void
    {
        if (!current_user_can(Menu::CAPABILITY)) {
            return;
        }

        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
        if ($view === 'licenses') {
            echo $this->templates->render('admin/licenses', [
                'licenses' => self::licenseFiles(),
                'back_url' => Menu::url('settings'),
            ]);

            return;
        }

        $settings = self::get();
        $libraries = [];

        foreach (Registry::all() as $id => $library) {
            $variant = self::libraryVariant($settings, $id);
            $libraries[] = [
                'id' => $id,
                'label' => $library->label(),
                'glyph' => Registry::glyph($id, 20),
                'package' => $library->npmPackage(),
                'bundled_version' => $library->bundledVersion(),
                'count' => CatalogStore::count($id, $variant),
                'cdn_version' => self::libraryCdnVersion($settings, $id),
                'source' => self::librarySource($settings, $id),
                'enabled' => self::libraryEnabled($settings, $id),
                'cdn_versions' => CdnCatalog::npmVersions($library->npmPackage()),
                'cached_version' => CdnCatalog::version($id),
                'cached_at' => CdnCatalog::fetchedAt($id),
                'cached_count' => CdnCatalog::count($id),
                'fetching' => CdnCatalog::isFetching($id),
                'variants' => $library->variants(),
                'variant_param' => $library->variantParam(),
                'variant' => $variant,
                'stored_variant' => (string) ($settings['libraries'][$id]['variant'] ?? ''),
                'has_stroke' => \in_array('stroke', $library->supports(), true),
                'stroke' => self::libraryStroke($settings, $id),
                'brand_colors' => $library->hasBrandColors(),
                'has_brands' => $library->hasBrands(),
                'color_mode' => self::libraryColorMode($settings, $id),
                'color' => (string) ($settings['libraries'][$id]['color'] ?? ''),
            ];
        }

        echo $this->templates->render('admin/settings', [
            'settings' => $settings,
            'option_name' => self::OPTION,
            'group' => self::GROUP,
            'libraries' => $libraries,
            'licenses_url' => self::licensesUrl(),
        ]);
    }

    public static function licensesUrl(): string
    {
        return Menu::url('settings', ['view' => 'licenses']);
    }

    /**
     * License texts shipped in licenses/ (plain text, shown escaped).
     *
     * @return list<array{file: string, title: string, text: string}>
     */
    private static function licenseFiles(): array
    {
        $dir = WP_ICONS__PATH . 'licenses/';
        $files = glob($dir . '*.{txt,md}', GLOB_BRACE) ?: [];
        $order = ['README.md', 'lucide.txt', 'material-symbols.txt', 'phosphor.txt', 'simple-icons.txt', 'simple-icons-disclaimer.txt', 'simple-icons-icon-licenses.md', 'tabler.txt', 'heroicons.txt', 'ionicons.txt', 'octicons.txt'];
        usort($files, static function (string $a, string $b) use ($order): int {
            $ia = array_search(basename($a), $order, true);
            $ib = array_search(basename($b), $order, true);

            return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib) ?: strcmp($a, $b);
        });

        $licenses = [];
        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }
            $licenses[] = [
                'file' => basename($file),
                'title' => basename($file),
                'text' => (string) file_get_contents($file),
            ];
        }

        return $licenses;
    }

    /**
     * @return array{
     *   default_library: string,
     *   default_size: int,
     *   default_color: string,
     *   default_stroke: float,
     *   libraries: array<string, array{enabled: bool, source: string, cdn_version: string}>
     * }
     */
    public static function defaults(): array
    {
        $libraries = [];
        foreach (array_keys(Registry::all()) as $id) {
            $libraries[$id] = [
                'enabled' => true,
                'source' => 'plugin',
                'cdn_version' => 'latest',
                'variant' => '',
                'stroke' => '',
                'color_mode' => '',
                'color' => '',
            ];
        }

        return [
            'default_library' => 'lucide',
            'default_size' => 24,
            'default_color' => 'currentColor',
            'default_stroke' => 2.0,
            'libraries' => $libraries,
        ];
    }

    /**
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    private static function migrate(array $stored): array
    {
        if (isset($stored['source']) && !isset($stored['libraries']['lucide'])) {
            $stored['libraries'] ??= [];
            $stored['libraries']['lucide'] = [
                'enabled' => true,
                'source' => $stored['source'] === 'cdn' ? 'cdn' : 'plugin',
                'cdn_version' => isset($stored['cdn_version']) ? (string) $stored['cdn_version'] : 'latest',
            ];
        }

        return $stored;
    }
}
