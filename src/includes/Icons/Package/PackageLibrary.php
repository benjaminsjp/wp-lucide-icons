<?php

namespace WPIcons\Icons\Package;

use WPIcons\Icons\BaseLibrary;
use WPIcons\Icons\CatalogStore;
use WPIcons\Icons\IconOptions;
use WPIcons\Icons\Svg;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * An icon library bundled from an npm package by scripts/sync-packages.js.
 */
final class PackageLibrary extends BaseLibrary
{
    /** Registered package libraries, in picker order. */
    public const IDS = ['phosphor', 'simple-icons', 'tabler', 'heroicons', 'ionicons', 'octicons'];

    /** @var array<string, list<string>> */
    private array $unionNames = [];

    /** @var array<string, true>|null */
    private ?array $brandSet = null;

    public function __construct(private string $id)
    {
    }

    /**
     * Static description of each library. Labels are translated on every call (cheap), so they are
     * never cached before the text domain is loaded.
     *
     * @return array{label: string, package: string, param: string, variants: array<string, array{label: string, paint: string, stroke?: float}>, default: string, brandColors?: bool, brandPattern?: string, listAll?: bool, glyph: array{0: string, 1: string}}
     */
    private function definition(): array
    {
        return match ($this->id) {
            'simple-icons' => [
                'label' => __('Simple Icons', 'wpicons'),
                'package' => 'simple-icons',
                'param' => '',
                'default' => 'default',
                'variants' => [
                    'default' => ['label' => '', 'paint' => 'fill'],
                ],
                'brandColors' => true,
                'brandPattern' => '/./',
                'glyph' => ['simpleicons', 'default'],
            ],
            'phosphor' => [
                'label' => __('Phosphor', 'wpicons'),
                'package' => '@phosphor-icons/core',
                'param' => 'weight',
                'default' => 'regular',
                'variants' => [
                    'thin' => ['label' => __('Thin', 'wpicons'), 'paint' => 'fill'],
                    'light' => ['label' => __('Light', 'wpicons'), 'paint' => 'fill'],
                    'regular' => ['label' => __('Regular', 'wpicons'), 'paint' => 'fill'],
                    'bold' => ['label' => __('Bold', 'wpicons'), 'paint' => 'fill'],
                    'fill' => ['label' => __('Fill', 'wpicons'), 'paint' => 'fill'],
                    'duotone' => ['label' => __('Duotone', 'wpicons'), 'paint' => 'fill'],
                ],
                'brandPattern' => '/-logo(?:-|$)/',
                'glyph' => ['atom', 'regular'],
            ],
            'tabler' => [
                'label' => __('Tabler Icons', 'wpicons'),
                'package' => '@tabler/icons',
                'param' => 'style',
                'default' => 'outline',
                'variants' => [
                    'outline' => ['label' => __('Outline', 'wpicons'), 'paint' => 'stroke', 'stroke' => 2.0],
                    'filled' => ['label' => __('Filled', 'wpicons'), 'paint' => 'fill'],
                ],
                'brandPattern' => '/^brand-/',
                'glyph' => ['brand-tabler', 'outline'],
            ],
            'heroicons' => [
                'label' => __('Heroicons', 'wpicons'),
                'package' => 'heroicons',
                'param' => 'style',
                'default' => 'outline',
                'variants' => [
                    'outline' => ['label' => __('Outline', 'wpicons'), 'paint' => 'stroke', 'stroke' => 1.5],
                    'solid' => ['label' => __('Solid', 'wpicons'), 'paint' => 'fill'],
                    'mini' => ['label' => __('Mini', 'wpicons'), 'paint' => 'fill'],
                    'micro' => ['label' => __('Micro', 'wpicons'), 'paint' => 'fill'],
                ],
                'glyph' => ['sparkles', 'outline'],
            ],
            'ionicons' => [
                'label' => __('Ionicons', 'wpicons'),
                'package' => 'ionicons',
                'param' => 'style',
                'default' => 'outline',
                'variants' => [
                    'outline' => ['label' => __('Outline', 'wpicons'), 'paint' => 'fill'],
                    'filled' => ['label' => __('Filled', 'wpicons'), 'paint' => 'fill'],
                    'sharp' => ['label' => __('Sharp', 'wpicons'), 'paint' => 'fill'],
                ],
                'brandPattern' => '/^logo-/',
                'listAll' => true,
                'glyph' => ['logo-ionic', 'filled'],
            ],
            default => [
                'label' => __('Octicons', 'wpicons'),
                'package' => '@primer/octicons',
                'param' => '',
                'default' => 'default',
                'variants' => [
                    'default' => ['label' => '', 'paint' => 'fill'],
                ],
                // GitHub's own mark is excluded (trademark), so the library is shown with another glyph.
                'glyph' => ['telescope', 'default'],
            ],
        };
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->definition()['label'];
    }

    public function npmPackage(): string
    {
        return $this->definition()['package'];
    }

    public function bundledVersion(): string
    {
        $version = PackageCatalog::index($this->id)['version'] ?? '';

        return \is_string($version) && $version !== '' ? $version : '0.0.0';
    }

    public function supports(): array
    {
        $supports = ['size', 'color'];
        $definition = $this->definition();
        foreach ($definition['variants'] as $variant) {
            if ($variant['paint'] === 'stroke') {
                $supports[] = 'stroke';
                break;
            }
        }
        if (\count($definition['variants']) > 1) {
            $supports[] = 'variants';
        }
        if (!empty($definition['brandColors'])) {
            $supports[] = 'brand-color';
        }

        return $supports;
    }

    /** Resolve '' or an unknown style to the library default. */
    public function normalizeVariant(string $variant): string
    {
        $variants = $this->definition()['variants'];

        return isset($variants[$variant]) ? $variant : $this->definition()['default'];
    }

    /**
     * @return list<string>
     */
    public function variantKeys(): array
    {
        return array_keys($this->definition()['variants']);
    }

    public function bundledNames(string $variant = ''): array
    {
        $variant = $this->normalizeVariant($variant);
        if (!$this->listsAllVariants()) {
            return array_keys(PackageCatalog::icons($this->id, $variant));
        }

        if (!isset($this->unionNames[$variant])) {
            $names = [];
            foreach ($this->variantKeys() as $key) {
                foreach (array_keys(PackageCatalog::icons($this->id, $key)) as $name) {
                    $names[$name] = true;
                }
            }
            $names = array_keys($names);
            sort($names, SORT_STRING);
            $this->unionNames[$variant] = $names;
        }

        return $this->unionNames[$variant];
    }

    public function bundledCount(string $variant = ''): int
    {
        $index = PackageCatalog::index($this->id);
        if ($this->listsAllVariants()) {
            return (int) ($index['count'] ?? 0);
        }

        return (int) ($index['variants'][$this->normalizeVariant($variant)] ?? 0);
    }

    public function bundledInner(string $name, string $variant = ''): ?string
    {
        $icons = PackageCatalog::icons($this->id, $this->normalizeVariant($variant));

        return isset($icons[$name]) && \is_string($icons[$name]) ? $icons[$name] : null;
    }

    public function wrap(string $inner, IconOptions $options): string
    {
        $variant = $this->normalizeVariant($options->variant);
        $paint = $this->definition()['variants'][$variant]['paint'];
        $hex = $this->hasBrandColors() ? (CatalogStore::meta($this->id, $options->name)['hex'] ?? null) : null;

        return Svg::package(
            $inner,
            $options,
            $this->id,
            \count($this->definition()['variants']) > 1 ? $variant : '',
            CatalogStore::viewBox($this->id, $options->name, $variant),
            $paint,
            \is_string($hex) ? $hex : null
        );
    }

    public function variants(): array
    {
        $variants = $this->definition()['variants'];
        if (\count($variants) < 2) {
            return [];
        }

        return array_map(static fn (array $variant): string => $variant['label'], $variants);
    }

    public function variantParam(): string
    {
        return $this->definition()['param'];
    }

    public function defaultVariant(): string
    {
        return $this->definition()['default'];
    }

    public function supportsStroke(string $variant = ''): bool
    {
        $variants = $this->definition()['variants'];

        return ($variants[$this->normalizeVariant($variant)]['paint'] ?? '') === 'stroke';
    }

    public function defaultStroke(): float
    {
        foreach ($this->definition()['variants'] as $variant) {
            if (isset($variant['stroke'])) {
                return (float) $variant['stroke'];
            }
        }

        return 2.0;
    }

    public function hasBrandColors(): bool
    {
        return !empty($this->definition()['brandColors']);
    }

    public function hasBrands(): bool
    {
        return isset($this->definition()['brandPattern']);
    }

    public function isBrand(string $name): bool
    {
        if ($this->brandSet === null) {
            $brands = PackageCatalog::index($this->id)['brands'] ?? [];
            $this->brandSet = \is_array($brands) ? array_fill_keys($brands, true) : [];
        }

        if (isset($this->brandSet[$name])) {
            return true;
        }

        // Icons that only exist in a newer CDN catalog.
        $pattern = $this->definition()['brandPattern'] ?? null;

        return \is_string($pattern) && preg_match($pattern, $name) === 1 && !isset(PackageCatalog::icons($this->id, $this->defaultVariant())[$name]);
    }

    public function iconMeta(string $name): array
    {
        $meta = PackageCatalog::index($this->id)['meta'][$name] ?? [];

        return \is_array($meta) ? $meta : [];
    }

    public function glyph(): array
    {
        return $this->definition()['glyph'];
    }

    public function listsAllVariants(): bool
    {
        return !empty($this->definition()['listAll']);
    }

    public function viewBox(string $name, string $variant = ''): string
    {
        $variant = $this->normalizeVariant($variant);
        $index = PackageCatalog::index($this->id);
        if ($name !== '' && isset($index['viewBoxes'][$variant][$name]) && \is_string($index['viewBoxes'][$variant][$name])) {
            return $index['viewBoxes'][$variant][$name];
        }

        $default = $index['viewBox'][$variant] ?? '';

        return \is_string($default) && $default !== '' ? $default : '0 0 24 24';
    }

    public function packageEntry(string $path): ?array
    {
        $path = str_replace('\\', '/', $path);

        switch ($this->id) {
            case 'simple-icons':
                return preg_match('#/package/icons/([^/]+)\.svg$#', $path, $m) ? ['default', $m[1]] : null;

            case 'phosphor':
                if (!preg_match('#/package/assets/(thin|light|regular|bold|fill|duotone)/([^/]+)\.svg$#', $path, $m)) {
                    return null;
                }
                $suffix = $m[1] === 'regular' ? '' : '-' . $m[1];
                if ($suffix !== '' && !str_ends_with($m[2], $suffix)) {
                    return null;
                }

                return [$m[1], $suffix === '' ? $m[2] : substr($m[2], 0, -\strlen($suffix))];

            case 'tabler':
                return preg_match('#/package/icons/(outline|filled)/([^/]+)\.svg$#', $path, $m) ? [$m[1], $m[2]] : null;

            case 'heroicons':
                if (!preg_match('#/package/(24|20|16)/(outline|solid)/([^/]+)\.svg$#', $path, $m)) {
                    return null;
                }
                $variant = match ($m[1] . '/' . $m[2]) {
                    '24/outline' => 'outline',
                    '24/solid' => 'solid',
                    '20/solid' => 'mini',
                    '16/solid' => 'micro',
                    default => '',
                };

                return $variant === '' ? null : [$variant, $m[3]];

            case 'ionicons':
                if (!preg_match('#/package/dist/svg/([^/]+)\.svg$#', $path, $m)) {
                    return null;
                }
                if (preg_match('/^(.+)-(outline|sharp)$/', $m[1], $s) && !str_starts_with($m[1], 'logo-')) {
                    return [$s[2], $s[1]];
                }

                return ['filled', $m[1]];

            case 'octicons':
                if (!preg_match('#/package/build/svg/(.+)-(\d+)\.svg$#', $path, $m)) {
                    return null;
                }

                // Prefer the 24 px drawing, then 16, then anything else.
                return ['default', $m[1], match ($m[2]) {
                    '24' => 3,
                    '16' => 2,
                    default => 1,
                }];
        }

        return null;
    }
}