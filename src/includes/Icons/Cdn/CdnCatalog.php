<?php

namespace WPIcons\Icons\Cdn;

use WPIcons\Admin\Settings;
use WPIcons\Icons\Exclusions;
use WPIcons\Icons\LibraryInterface;
use WPIcons\Icons\Package\PackageLibrary;
use WPIcons\Icons\Registry;
use WPIcons\Icons\Svg;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * "CDN (npm, cached)" source: downloads a library's npm package from registry.npmjs.org in the
 * background (cron), extracts the SVGs and stores them in uploads/wp-icons/<library>.json.
 * Front-end page views only ever read that local file.
 */
final class CdnCatalog
{
    public const CRON_HOOK = 'wp_icons_refresh_cdn_catalogs';
    public const META_OPTION = 'wp_icons__cdn_meta';

    private const FETCHING_TRANSIENT = 'wp_icons_cdn_fetching';

    /** @var array<string, array{variants: array<string, array<string, string>>, meta: array<string, array<string, string>>, viewBoxes: array<string, array<string, string>>}> */
    private static array $cache = [];

    public function register(): void
    {
        add_action('init', [$this, 'schedule']);
        add_action(self::CRON_HOOK, [self::class, 'refreshEnabled']);
    }

    public function schedule(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
        delete_transient(self::FETCHING_TRANSIENT);
    }

    /**
     * Queue a near-term refresh for every enabled CDN library.
     * The daily event stays on the same hook and does not count as waiting.
     */
    public static function queueRefresh(): void
    {
        $settings = Settings::get();
        $ids = [];
        foreach (array_keys(Registry::all()) as $id) {
            if (!Settings::libraryEnabled($settings, $id) || Settings::librarySource($settings, $id) !== 'cdn') {
                continue;
            }
            $ids[] = $id;
        }

        if ($ids === []) {
            delete_transient(self::FETCHING_TRANSIENT);
            return;
        }

        set_transient(self::FETCHING_TRANSIENT, $ids, 15 * MINUTE_IN_SECONDS);
        if (!self::singleEventWaiting()) {
            wp_schedule_single_event(time() + 5, self::CRON_HOOK);
        }
    }

    public static function isFetching(string $libraryId): bool
    {
        $pending = get_transient(self::FETCHING_TRANSIENT);

        return \is_array($pending) && \in_array($libraryId, $pending, true);
    }

    private static function singleEventWaiting(): bool
    {
        if (!\function_exists('_get_cron_array')) {
            return false;
        }

        $crons = _get_cron_array();
        if (!\is_array($crons)) {
            return false;
        }

        foreach ($crons as $hooks) {
            if (!isset($hooks[self::CRON_HOOK]) || !\is_array($hooks[self::CRON_HOOK])) {
                continue;
            }
            foreach ($hooks[self::CRON_HOOK] as $event) {
                if (\is_array($event) && empty($event['schedule'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, array{version: string, fetched_at: int, count: int, file: string}>
     */
    public static function meta(string $libraryId = '', string $name = ''): array
    {
        if ($libraryId !== '') {
            // Brand metadata for one icon (Simple Icons hex / guidelines from a newer package).
            $entry = self::load($libraryId)['meta'][$name] ?? [];

            return \is_array($entry) ? $entry : [];
        }

        $stored = get_option(self::META_OPTION, []);

        return \is_array($stored) ? $stored : [];
    }

    public static function version(string $libraryId): ?string
    {
        $meta = self::meta();

        return isset($meta[$libraryId]['version']) && \is_string($meta[$libraryId]['version'])
            ? $meta[$libraryId]['version']
            : null;
    }

    public static function fetchedAt(string $libraryId): ?int
    {
        $meta = self::meta();

        return isset($meta[$libraryId]['fetched_at']) ? (int) $meta[$libraryId]['fetched_at'] : null;
    }

    public static function count(string $libraryId): int
    {
        $meta = self::meta();

        return isset($meta[$libraryId]['count']) ? (int) $meta[$libraryId]['count'] : 0;
    }

    /**
     * @return list<string>
     */
    public static function names(string $libraryId, string $variant = '', bool $allVariants = false): array
    {
        $data = self::load($libraryId);
        if (!$allVariants) {
            return array_keys($data['variants'][$variant] ?? []);
        }

        $names = [];
        foreach ($data['variants'] as $icons) {
            foreach (array_keys($icons) as $name) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    public static function inner(string $libraryId, string $name, string $variant = ''): ?string
    {
        $icons = self::load($libraryId)['variants'][$variant] ?? [];
        if (isset($icons[$name]) && \is_string($icons[$name])) {
            return $icons[$name];
        }

        $alt = str_contains($name, '-') ? str_replace('-', '_', $name) : str_replace('_', '-', $name);
        if (isset($icons[$alt]) && \is_string($icons[$alt])) {
            return $icons[$alt];
        }

        return null;
    }

    public static function viewBox(string $libraryId, string $name, string $variant = ''): ?string
    {
        $viewBox = self::load($libraryId)['viewBoxes'][$variant][$name] ?? null;

        return \is_string($viewBox) ? $viewBox : null;
    }

    /**
     * Flat icon map for the library's default style (kept for callers that predate styles).
     *
     * @return array<string, string>
     */
    public static function icons(string $libraryId, string $variant = ''): array
    {
        $data = self::load($libraryId);
        if ($variant === '' && !isset($data['variants'][''])) {
            $library = Registry::get($libraryId);
            $variant = $library?->defaultVariant() ?? '';
        }

        return $data['variants'][$variant] ?? [];
    }

    /**
     * @return array{variants: array<string, array<string, string>>, meta: array<string, array<string, string>>, viewBoxes: array<string, array<string, string>>}
     */
    private static function load(string $libraryId): array
    {
        if (isset(self::$cache[$libraryId])) {
            return self::$cache[$libraryId];
        }

        $empty = ['variants' => [], 'meta' => [], 'viewBoxes' => []];
        $stored = get_option(self::META_OPTION, []);
        $path = \is_array($stored) ? ($stored[$libraryId]['file'] ?? null) : null;
        if (!\is_string($path) || !is_readable($path)) {
            self::$cache[$libraryId] = $empty;

            return $empty;
        }

        $json = file_get_contents($path);
        $decoded = $json === false ? null : json_decode($json, true);
        if (!\is_array($decoded)) {
            self::$cache[$libraryId] = $empty;

            return $empty;
        }

        $data = $empty;
        // Older Lucide and Material Symbols caches stored a flat "icons" map. Those libraries now
        // use their default style, so a legacy cache is read as that style and does not re-download.
        $legacyVariant = Registry::get($libraryId)?->defaultVariant() ?? '';
        $variants = isset($decoded['variants']) && \is_array($decoded['variants'])
            ? $decoded['variants']
            : [$legacyVariant => $decoded['icons'] ?? []];
        foreach ($variants as $variant => $icons) {
            if (!\is_string($variant) && !\is_int($variant)) {
                continue;
            }
            foreach ((array) $icons as $name => $inner) {
                if (\is_string($name) && \is_string($inner) && $inner !== '') {
                    $data['variants'][(string) $variant][$name] = $inner;
                }
            }
        }
        if (isset($decoded['meta']) && \is_array($decoded['meta'])) {
            $data['meta'] = $decoded['meta'];
        }
        if (isset($decoded['viewBoxes']) && \is_array($decoded['viewBoxes'])) {
            $data['viewBoxes'] = $decoded['viewBoxes'];
        }

        self::$cache[$libraryId] = $data;

        return $data;
    }

    public static function refreshEnabled(): void
    {
        try {
            $settings = Settings::get();

            foreach (array_keys(Registry::all()) as $libraryId) {
                if (!Settings::libraryEnabled($settings, $libraryId)) {
                    continue;
                }
                if (Settings::librarySource($settings, $libraryId) !== 'cdn') {
                    continue;
                }

                self::refresh($libraryId, Settings::libraryCdnVersion($settings, $libraryId));
            }
        } finally {
            delete_transient(self::FETCHING_TRANSIENT);
        }
    }

    /**
     * @return array{ok: bool, message: string, version?: string, count?: int}
     */
    public static function refresh(string $libraryId, string $requestedVersion = 'latest'): array
    {
        $library = Registry::get($libraryId);
        if ($library === null) {
            return ['ok' => false, 'message' => 'Unknown library'];
        }

        $package = $library->npmPackage();
        $resolved = self::resolveNpmVersion($package, $requestedVersion);
        if ($resolved === null) {
            return ['ok' => false, 'message' => sprintf('Could not resolve npm version for %s.', $package)];
        }

        $existing = self::version($libraryId);
        if ($existing === $resolved && self::count($libraryId) > 0) {
            return ['ok' => true, 'message' => 'Catalog already up to date.', 'version' => $resolved, 'count' => self::count($libraryId)];
        }

        $tarball = self::npmTarball($package, $resolved);
        if ($tarball === null) {
            return ['ok' => false, 'message' => sprintf('Could not find tarball for %s@%s.', $package, $resolved)];
        }

        $parsed = self::downloadAndParse($library, $tarball);
        if ($parsed === null || $parsed['variants'] === []) {
            return ['ok' => false, 'message' => 'Could not parse icon SVGs from the CDN package.'];
        }

        $dir = self::storageDir();
        if ($dir === null) {
            return ['ok' => false, 'message' => 'Could not create the local CDN cache directory.'];
        }

        $count = 0;
        foreach ($parsed['variants'] as $icons) {
            $count = max($count, \count($icons));
        }

        $file = $dir . '/' . sanitize_file_name($libraryId) . '.json';
        $payload = $library->variants() === [] && isset($parsed['variants'][''])
            ? ['version' => $resolved, 'package' => $package, 'icons' => $parsed['variants']['']]
            : ['version' => $resolved, 'package' => $package, 'variants' => $parsed['variants']];
        if ($parsed['meta'] !== []) {
            $payload['meta'] = $parsed['meta'];
        }
        if ($parsed['viewBoxes'] !== []) {
            $payload['viewBoxes'] = $parsed['viewBoxes'];
        }
        $json = wp_json_encode($payload);

        if (!\is_string($json) || file_put_contents($file, $json) === false) {
            return ['ok' => false, 'message' => 'Could not write the CDN catalog cache.'];
        }

        $meta = self::meta();
        $meta[$libraryId] = [
            'version' => $resolved,
            'fetched_at' => time(),
            'count' => $count,
            'file' => $file,
        ];
        update_option(self::META_OPTION, $meta, false);
        unset(self::$cache[$libraryId]);

        return [
            'ok' => true,
            'message' => 'CDN catalog updated.',
            'version' => $resolved,
            'count' => $count,
        ];
    }

    public static function deleteAll(): void
    {
        $meta = self::meta();
        foreach ($meta as $entry) {
            if (isset($entry['file']) && \is_string($entry['file']) && is_file($entry['file'])) {
                wp_delete_file($entry['file']);
            }
        }
        delete_option(self::META_OPTION);

        $dir = self::storageDir();
        if ($dir && is_dir($dir)) {
            $index = $dir . '/index.php';
            if (is_file($index)) {
                wp_delete_file($index);
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function npmVersions(string $package): array
    {
        $key = 'wp_icons__npm_versions_' . md5($package);
        $cached = get_transient($key);
        if (\is_array($cached)) {
            return $cached;
        }

        $url = 'https://registry.npmjs.org/' . rawurlencode($package);
        if (str_starts_with($package, '@')) {
            $url = 'https://registry.npmjs.org/' . str_replace('/', '%2f', $package);
        }

        // Abbreviated metadata: much smaller than the full packument for packages with many releases.
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/vnd.npm.install-v1+json'],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!\is_array($body) || !isset($body['versions']) || !\is_array($body['versions'])) {
            return [];
        }

        $versions = array_values(array_filter(
            array_keys($body['versions']),
            static function ($version) {
                return \is_string($version) && preg_match('/^\d+\.\d+\.\d+$/', $version);
            }
        ));

        usort($versions, static function ($a, $b) {
            return version_compare($b, $a);
        });

        $versions = \array_slice($versions, 0, 50);
        set_transient($key, $versions, 12 * HOUR_IN_SECONDS);

        return $versions;
    }

    private static function resolveNpmVersion(string $package, string $requested): ?string
    {
        if ($requested !== 'latest' && preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $requested)) {
            return $requested;
        }

        $versions = self::npmVersions($package);

        return $versions[0] ?? null;
    }

    private static function npmTarball(string $package, string $version): ?string
    {
        $url = 'https://registry.npmjs.org/' . rawurlencode($package);
        if (str_starts_with($package, '@')) {
            $url = 'https://registry.npmjs.org/' . str_replace('/', '%2f', $package);
        }

        $response = wp_remote_get($url . '/' . rawurlencode($version), ['timeout' => 15]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!\is_array($body) || !isset($body['dist']['tarball']) || !\is_string($body['dist']['tarball'])) {
            return null;
        }

        return $body['dist']['tarball'];
    }

    /**
     * @return array{variants: array<string, array<string, string>>, meta: array<string, array<string, string>>, viewBoxes: array<string, array<string, string>>}|null
     */
    private static function downloadAndParse(LibraryInterface $library, string $tarballUrl): ?array
    {
        if (!class_exists(\PharData::class)) {
            return null;
        }

        $response = wp_remote_get($tarballUrl, [
            'timeout' => 60,
            'stream' => false,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if ($body === '') {
            return null;
        }

        $tmp = wp_tempnam($tarballUrl);
        if ($tmp === false) {
            return null;
        }

        $gz = $tmp . '.tar.gz';
        if (file_put_contents($gz, $body) === false) {
            wp_delete_file($tmp);

            return null;
        }

        wp_delete_file($tmp);
        $libraryId = $library->id();

        try {
            $phar = new \PharData($gz);
            $tarPath = preg_replace('/\.gz$/', '', $gz);
            if (!is_string($tarPath)) {
                return null;
            }

            if (!is_file($tarPath)) {
                $phar->decompress();
            }

            $tar = new \PharData($tarPath);

            // Simple Icons: brand data and per-logo licenses come from data/simple-icons.json.
            $brandData = null;
            if ($libraryId === 'simple-icons') {
                $brandData = [];
                $dataFile = 'phar://' . str_replace('\\', '/', $tarPath) . '/package/data/simple-icons.json';
                $decoded = is_file($dataFile) ? json_decode((string) file_get_contents($dataFile), true) : null;
                $entries = \is_array($decoded) ? ($decoded['icons'] ?? $decoded) : [];
                foreach ((array) $entries as $entry) {
                    if (\is_array($entry) && isset($entry['slug']) && \is_string($entry['slug'])) {
                        $brandData[$entry['slug']] = $entry;
                    }
                }
            }

            $variants = [];
            $priorities = [];
            $viewBoxes = [];
            $meta = [];

            foreach (new \RecursiveIteratorIterator($tar) as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                if (!str_ends_with(strtolower($path), '.svg')) {
                    continue;
                }

                $entry = $library->packageEntry($path);
                if ($entry === null) {
                    continue;
                }
                [$variant, $name] = $entry;
                $priority = (int) ($entry[2] ?? 0);

                if ($name === '' || Exclusions::isExcluded($libraryId, $name)) {
                    continue;
                }
                if (isset($priorities[$variant][$name]) && $priorities[$variant][$name] >= $priority) {
                    continue;
                }

                $item = null;
                if ($brandData !== null) {
                    $item = $brandData[$name] ?? null;
                    $licenseType = \is_array($item) && isset($item['license']['type']) ? (string) $item['license']['type'] : '';
                    if (!\is_array($item) || Exclusions::isExcluded($libraryId, $name, $licenseType)) {
                        continue;
                    }
                }

                $svg = file_get_contents($file->getPathname());
                if (!\is_string($svg) || $svg === '') {
                    continue;
                }

                $inner = Svg::sanitizeInner(self::innerSvg($svg));
                if ($inner === '') {
                    continue;
                }

                $variants[$variant][$name] = $inner;
                $priorities[$variant][$name] = $priority;

                if ($library instanceof PackageLibrary && preg_match('/<svg\b[^>]*\bviewBox="([^"]+)"/i', $svg, $vb) === 1) {
                    $viewBox = trim($vb[1]);
                    if ($viewBox !== $library->viewBox('', $variant)) {
                        $viewBoxes[$variant][$name] = $viewBox;
                    } else {
                        unset($viewBoxes[$variant][$name]);
                    }
                }

                if (\is_array($item)) {
                    $meta[$name] = array_filter([
                        'title' => isset($item['title']) ? (string) $item['title'] : '',
                        'hex' => isset($item['hex']) ? strtoupper((string) $item['hex']) : '',
                        'guidelines' => isset($item['guidelines']) && \is_string($item['guidelines']) && preg_match('#^https?://#', $item['guidelines']) ? $item['guidelines'] : '',
                        'license' => isset($item['license']['type']) && $item['license']['type'] !== 'CC0-1.0' ? (string) $item['license']['type'] : '',
                    ]);
                }
            }

            if (is_file($tarPath) && $tarPath !== $gz) {
                wp_delete_file($tarPath);
            }
            wp_delete_file($gz);

            foreach ($variants as $variant => $icons) {
                ksort($icons, SORT_STRING);
                $variants[$variant] = $icons;
            }

            return ['variants' => $variants, 'meta' => $meta, 'viewBoxes' => $viewBoxes];
        } catch (\Throwable $e) {
            if (isset($gz) && is_file($gz)) {
                wp_delete_file($gz);
            }

            return null;
        }
    }

    private static function innerSvg(string $svg): string
    {
        $svg = preg_replace('/<\?xml[^>]*>/i', '', $svg) ?? $svg;
        $svg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg) ?? $svg;
        $svg = preg_replace('/<svg\b[^>]*>/i', '', $svg) ?? $svg;
        $svg = preg_replace('/<\/svg>\s*$/i', '', $svg) ?? $svg;
        // Our own <title> is added when rendering; Tabler's invisible 24x24 bounding path is dropped.
        $svg = preg_replace('/<title>.*?<\/title>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<path stroke="none" d="M0 0h24v24H0z" fill="none"\s*\/>/i', '', $svg) ?? $svg;

        return trim(preg_replace('/\s+/', ' ', $svg) ?? $svg);
    }

    private static function storageDir(): ?string
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return null;
        }

        $dir = trailingslashit($uploads['basedir']) . 'wp-icons';
        if (!wp_mkdir_p($dir)) {
            return null;
        }

        $index = $dir . '/index.php';
        if (!is_file($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        return $dir;
    }
}