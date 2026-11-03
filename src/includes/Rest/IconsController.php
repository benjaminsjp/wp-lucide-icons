<?php

namespace WPIcons\Rest;

use WPIcons\Admin\Settings;
use WPIcons\Icons\Cdn\CdnCatalog;
use WPIcons\Icons\CatalogStore;
use WPIcons\Icons\IconOptions;
use WPIcons\Icons\LibraryInterface;
use WPIcons\Icons\Registry;
use WPIcons\Icons\Renderer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!\defined('ABSPATH')) {
    exit;
}

class IconsController
{
    public const NAMESPACE = 'wpicons/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/libraries', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'libraries'],
            'permission_callback' => [$this, 'canRead'],
        ]);

        register_rest_route(self::NAMESPACE, '/icons', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'icons'],
            'permission_callback' => [$this, 'canRead'],
            'args' => [
                'library' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'search' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'variant' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 200,
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/search', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'search'],
            'permission_callback' => [$this, 'canRead'],
            'args' => [
                'search' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'per_library' => [
                    'type' => 'integer',
                    'default' => 24,
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/icon', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'icon'],
            'permission_callback' => [$this, 'canRead'],
            'args' => [
                'library' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'name' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'size' => [
                    'type' => 'integer',
                    'default' => 24,
                ],
                'color' => [
                    'type' => 'string',
                    'default' => 'currentColor',
                ],
                'stroke' => [
                    'type' => 'number',
                    'default' => 2,
                ],
                'variant' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);
    }

    public function canRead(): bool
    {
        return current_user_can('edit_posts') || current_user_can('manage_options');
    }

    public function libraries(): WP_REST_Response
    {
        $settings = Settings::get();
        $items = [];
        $total = 0;

        foreach (Registry::enabled() as $id => $library) {
            $defaultVariant = Settings::libraryVariant($settings, $id);
            $count = CatalogStore::count($id, $defaultVariant);
            $total += $count;

            $variants = [];
            $strokeVariants = [];
            foreach ($library->variants() as $variantId => $label) {
                $variants[] = ['id' => $variantId, 'label' => $label];
                if ($library->supportsStroke($variantId)) {
                    $strokeVariants[] = $variantId;
                }
            }
            if ($variants === [] && $library->supportsStroke()) {
                $strokeVariants[] = '';
            }

            $items[] = [
                'id' => $id,
                'label' => $library->label(),
                'package' => $library->npmPackage(),
                'version' => CatalogStore::version($id),
                'bundledVersion' => $library->bundledVersion(),
                'source' => Settings::librarySource($settings, $id),
                'supports' => $library->supports(),
                'count' => $count,
                'variants' => $variants,
                'variantParam' => $library->variantParam(),
                'defaultVariant' => $variants === [] ? '' : $defaultVariant,
                'nativeVariant' => $variants === [] ? '' : $library->defaultVariant(),
                'strokeVariants' => $strokeVariants,
                'defaultStroke' => Settings::libraryStroke($settings, $id),
                'brandColors' => $library->hasBrandColors(),
                'defaultColor' => Settings::libraryColor($settings, $id),
                'hasBrands' => $library->hasBrands(),
                'glyph' => Registry::glyph($id, 18),
                'cdn' => [
                    'requested' => Settings::libraryCdnVersion($settings, $id),
                    'cachedVersion' => CdnCatalog::version($id),
                    'cachedAt' => CdnCatalog::fetchedAt($id),
                ],
            ];
        }

        return new WP_REST_Response([
            'libraries' => $items,
            'total' => $total,
            'defaults' => [
                'library' => $settings['default_library'],
                'size' => $settings['default_size'],
                'color' => $settings['default_color'],
                'stroke' => $settings['default_stroke'],
            ],
            'licensesUrl' => current_user_can('manage_options') ? Settings::licensesUrl() : '',
        ]);
    }

    public function icons(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $libraryId = (string) $request->get_param('library');
        $library = $this->enabledLibrary($libraryId);
        if ($library === null) {
            return new WP_Error('wpicons_unknown_library', __('Unknown or disabled icon library.', 'wpicons'), ['status' => 404]);
        }

        $variant = $this->variantFor($library, (string) $request->get_param('variant'));
        $search = strtolower(trim((string) $request->get_param('search')));
        $page = max(1, (int) $request->get_param('page'));
        $perPage = min(200, max(1, (int) $request->get_param('per_page')));

        $cacheKey = '';
        if ($search === '') {
            $cacheKey = 'wpicons_icons_' . md5('v2|' . $libraryId . '|' . $variant . '|' . CatalogStore::version($libraryId) . '|' . $page . '|' . $perPage);
            $cached = get_transient($cacheKey);
            if (\is_array($cached)) {
                return $this->iconsResponse($cached);
            }
        }

        $names = CatalogStore::names($libraryId, $variant);
        if ($search !== '') {
            $names = $this->rank($names, $search);
        }

        $total = \count($names);
        $offset = ($page - 1) * $perPage;
        $slice = \array_slice($names, $offset, $perPage);

        $payload = [
            'library' => $libraryId,
            'variant' => $variant,
            'items' => $this->items($library, $slice, $variant),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) max(1, ceil($total / $perPage)),
        ];

        if ($cacheKey !== '') {
            set_transient($cacheKey, $payload, HOUR_IN_SECONDS);
        }

        return $this->iconsResponse($payload);
    }

    /**
     * Searches every enabled library at once ("All libraries" in the picker), best matches first.
     */
    public function search(WP_REST_Request $request): WP_REST_Response
    {
        $settings = Settings::get();
        $search = strtolower(trim((string) $request->get_param('search')));
        $perLibrary = min(60, max(1, (int) $request->get_param('per_library')));
        $groups = [];
        $total = 0;

        if ($search !== '') {
            foreach (Registry::enabled() as $id => $library) {
                $variant = $library->variants() === [] ? '' : Settings::libraryVariant($settings, $id);
                $names = $this->rank(CatalogStore::names($id, $variant), $search);
                if ($names === []) {
                    continue;
                }

                $total += \count($names);
                $groups[] = [
                    'library' => $id,
                    'label' => $library->label(),
                    'variant' => $variant,
                    'total' => \count($names),
                    'items' => $this->items($library, \array_slice($names, 0, $perLibrary), $variant),
                ];
            }
        }

        return new WP_REST_Response([
            'search' => $search,
            'groups' => $groups,
            'total' => $total,
        ]);
    }

    /**
     * @param list<string> $names
     * @return list<array<string, mixed>>
     */
    private function items(LibraryInterface $library, array $names, string $variant): array
    {
        $id = $library->id();
        $items = [];
        $preview = new IconOptions(size: 24, color: 'currentColor', variant: $variant);
        $preview->strokeWidth = $library->supportsStroke($variant) ? $library->defaultStroke() : 2.0;

        foreach ($names as $name) {
            $resolved = CatalogStore::resolve($id, $name, $variant);
            if ($resolved === null) {
                continue;
            }

            $options = clone $preview;
            $options->name = $name;
            $options->variant = $resolved['variant'];

            $item = [
                'name' => $name,
                'svg' => $library->wrap($resolved['inner'], $options),
            ];

            if ($library->isBrand($name)) {
                $item['brand'] = true;
                $meta = CatalogStore::meta($id, $name);
                foreach (['title', 'hex', 'guidelines'] as $key) {
                    if (isset($meta[$key]) && \is_string($meta[$key]) && $meta[$key] !== '') {
                        $item[$key] = $meta[$key];
                    }
                }
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Matches ranked: exact name, then prefix, then word start, then anywhere. Separators are ignored,
     * so "arrowright", "arrow right" and "arrow-right" all find arrow-right / arrow_right.
     *
     * @param list<string> $names
     * @return list<string>
     */
    private function rank(array $names, string $search): array
    {
        $needle = preg_replace('/[\s_-]+/', '', $search) ?? $search;
        if ($needle === '') {
            return $names;
        }

        $buckets = [[], [], [], []];
        foreach ($names as $name) {
            $lower = strtolower($name);
            $flat = preg_replace('/[\s_-]+/', '', $lower) ?? $lower;
            if ($flat === $needle) {
                $buckets[0][] = $name;
            } elseif (str_starts_with($flat, $needle)) {
                $buckets[1][] = $name;
            } elseif (preg_match('/(?:^|[\s_-])' . preg_quote($search, '/') . '/', $lower) === 1) {
                $buckets[2][] = $name;
            } elseif (str_contains($flat, $needle)) {
                $buckets[3][] = $name;
            }
        }

        foreach ($buckets as $i => $bucket) {
            usort($bucket, static fn (string $a, string $b): int => \strlen($a) <=> \strlen($b) ?: strcmp($a, $b));
            $buckets[$i] = $bucket;
        }

        return array_merge(...$buckets);
    }

    private function enabledLibrary(string $libraryId): ?LibraryInterface
    {
        $library = Registry::get($libraryId);

        return $library !== null && Settings::libraryEnabled(Settings::get(), $libraryId) ? $library : null;
    }

    private function variantFor(LibraryInterface $library, string $requested): string
    {
        if ($library->variants() === []) {
            return '';
        }

        $requested = IconOptions::cleanVariant($requested);

        return isset($library->variants()[$requested]) ? $requested : Settings::libraryVariant(Settings::get(), $library->id());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function iconsResponse(array $payload): WP_REST_Response
    {
        $response = new WP_REST_Response($payload);
        $response->header('X-WP-Total', (string) ($payload['total'] ?? 0));
        $response->header('X-WP-TotalPages', (string) ($payload['totalPages'] ?? 1));

        return $response;
    }

    public function icon(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $libraryId = (string) $request->get_param('library');
        $name = (string) $request->get_param('name');
        $svg = (new Renderer())->fromAttributes([
            'library' => $libraryId,
            'name' => $name,
            'size' => (int) $request->get_param('size'),
            'color' => (string) $request->get_param('color'),
            'stroke' => $request->get_param('stroke'),
            'variant' => (string) $request->get_param('variant'),
            'class' => '',
        ]);

        if ($svg === '') {
            return new WP_Error('wpicons_unknown_icon', __('Icon not found.', 'wpicons'), ['status' => 404]);
        }

        $library = Registry::get($libraryId);
        $brand = $library !== null && $library->isBrand($name);

        return new WP_REST_Response([
            'library' => $libraryId,
            'name' => $name,
            'svg' => $svg,
            'brand' => $brand,
            'meta' => $brand ? CatalogStore::meta($libraryId, $name) : (object) [],
        ]);
    }
}