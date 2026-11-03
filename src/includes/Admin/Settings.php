<?php

namespace WPIcons\Admin;

use WPIcons\Icons\Cdn\CdnCatalog;
use WPIcons\Icons\Registry;
use WPIcons\Template\Template;

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    public const OPTION = 'wp_icons__settings';
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

            $libraries[$id] = [
                'enabled' => !empty($posted['enabled']),
                'source' => $source,
                'cdn_version' => $version,
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

        $needsRefresh = false;
        foreach (array_keys(Registry::all()) as $id) {
            if (self::libraryEnabled($new, $id) && self::librarySource($new, $id) === 'cdn') {
                $needsRefresh = true;
                break;
            }
        }

        if ($needsRefresh && !wp_next_scheduled(CdnCatalog::CRON_HOOK)) {
            wp_schedule_single_event(time() + 5, CdnCatalog::CRON_HOOK);
        }
    }

    /**
     * @param array<int, string> $links
     * @return array<int, string>
     */
    public function pluginLinks(array $links): array
    {
        $library = admin_url('admin.php?page=wp-icons');
        $settings = admin_url('admin.php?page=' . self::PAGE);
        array_unshift(
            $links,
            '<a href="' . esc_url($library) . '">' . esc_html__('Library', 'wpicons') . '</a>',
            '<a href="' . esc_url($settings) . '">' . esc_html__('Settings', 'wpicons') . '</a>'
        );

        return $links;
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get();
        $libraries = [];
        $cdnFetchPending = (bool) wp_next_scheduled(CdnCatalog::CRON_HOOK);

        foreach (Registry::all() as $id => $library) {
            $libraries[] = [
                'id' => $id,
                'label' => $library->label(),
                'package' => $library->npmPackage(),
                'bundled_version' => $library->bundledVersion(),
                'cdn_version' => self::libraryCdnVersion($settings, $id),
                'source' => self::librarySource($settings, $id),
                'enabled' => self::libraryEnabled($settings, $id),
                'cdn_versions' => CdnCatalog::npmVersions($library->npmPackage()),
                'cached_version' => CdnCatalog::version($id),
                'cached_at' => CdnCatalog::fetchedAt($id),
                'cached_count' => \count(CdnCatalog::icons($id)),
                'fetching' => $cdnFetchPending && self::libraryEnabled($settings, $id) && self::librarySource($settings, $id) === 'cdn',
            ];
        }

        echo $this->templates->render('admin/settings', [
            'settings' => $settings,
            'option_name' => self::OPTION,
            'group' => self::GROUP,
            'libraries' => $libraries,
        ]);
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
