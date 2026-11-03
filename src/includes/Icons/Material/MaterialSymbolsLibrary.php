<?php

namespace WPIcons\Icons\Material;

use WPIcons\Icons\BaseLibrary;
use WPIcons\Icons\IconOptions;
use WPIcons\Icons\Svg;

if (!\defined('ABSPATH')) {
    exit;
}

final class MaterialSymbolsLibrary extends BaseLibrary
{
    public function id(): string
    {
        return 'material-symbols';
    }

    public function label(): string
    {
        return __('Material Symbols', 'wpicons');
    }

    public function npmPackage(): string
    {
        return '@material-symbols/svg-400';
    }

    public function bundledVersion(): string
    {
        return MaterialSymbols::version();
    }

    public function supports(): array
    {
        return ['size', 'color'];
    }

    public function bundledNames(string $variant = ''): array
    {
        return array_keys(MaterialSymbols::map());
    }

    public function bundledInner(string $name, string $variant = ''): ?string
    {
        $map = MaterialSymbols::map();
        $key = str_replace('-', '_', $name);

        if (isset($map[$key]) && \is_string($map[$key])) {
            return $map[$key];
        }

        if (isset($map[$name]) && \is_string($map[$name])) {
            return $map[$name];
        }

        return null;
    }

    public function wrap(string $inner, IconOptions $options): string
    {
        return Svg::material($inner, $options);
    }

    public function viewBox(string $name, string $variant = ''): string
    {
        return '0 -960 960 960';
    }

    public function glyph(): array
    {
        return ['interests', ''];
    }

    public function packageEntry(string $path): ?array
    {
        $normalized = str_replace('\\', '/', $path);
        if (!str_contains($normalized, '/outlined/')) {
            return null;
        }

        $file = basename($normalized, '.svg');

        return $file === '' ? null : ['', $file];
    }
}