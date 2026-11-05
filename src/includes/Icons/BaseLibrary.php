<?php

namespace WPIcons\Icons;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Defaults for libraries. PackageLibrary overrides these for the bundled catalogs.
 */
abstract class BaseLibrary implements LibraryInterface
{
    public function bundledCount(string $variant = ''): int
    {
        return \count($this->bundledNames($variant));
    }

    public function variants(): array
    {
        return [];
    }

    public function variantParam(): string
    {
        return '';
    }

    public function defaultVariant(): string
    {
        return '';
    }

    public function supportsStroke(string $variant = ''): bool
    {
        return \in_array('stroke', $this->supports(), true);
    }

    public function defaultStroke(): float
    {
        return 2.0;
    }

    public function hasBrandColors(): bool
    {
        return false;
    }

    public function hasBrands(): bool
    {
        return false;
    }

    public function isBrand(string $name): bool
    {
        return false;
    }

    public function iconMeta(string $name): array
    {
        return [];
    }

    public function listsAllVariants(): bool
    {
        return false;
    }

    public function viewBox(string $name, string $variant = ''): string
    {
        return '0 0 24 24';
    }

    public function packageEntry(string $path): ?array
    {
        $normalized = str_replace('\\', '/', $path);
        if (!str_contains($normalized, '/icons/')) {
            return null;
        }

        $file = basename($normalized, '.svg');

        return $file === '' ? null : ['', $file];
    }
}