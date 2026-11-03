<?php

namespace WPIcons\Icons;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Icons that must never be bundled or stored from a CDN download (trademarks, incompatible licenses).
 * The rules live in config/icon-exclusions.json, which the build script reads as well.
 */
final class Exclusions
{
    /**
     * @return array<string, array{excludeNames?: list<string>, excludePrefixes?: list<string>, excludeLicenseTypes?: list<string>}>
     */
    private static function rules(): array
    {
        static $rules = null;
        if ($rules !== null) {
            return $rules;
        }

        $rules = [];
        $path = WP_ICONS__PATH . 'config/icon-exclusions.json';
        if (!is_readable($path)) {
            return $rules;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (\is_array($decoded) && isset($decoded['libraries']) && \is_array($decoded['libraries'])) {
            $rules = $decoded['libraries'];
        }

        return $rules;
    }

    public static function isExcluded(string $libraryId, string $name, string $licenseType = ''): bool
    {
        $rules = self::rules()[$libraryId] ?? [];

        foreach ((array) ($rules['excludeNames'] ?? []) as $excluded) {
            if ($name === $excluded) {
                return true;
            }
        }

        foreach ((array) ($rules['excludePrefixes'] ?? []) as $prefix) {
            if (\is_string($prefix) && $prefix !== '' && str_starts_with($name, $prefix)) {
                return true;
            }
        }

        if ($licenseType !== '') {
            foreach ((array) ($rules['excludeLicenseTypes'] ?? []) as $pattern) {
                if (\is_string($pattern) && @preg_match('~' . str_replace('~', '\~', $pattern) . '~i', $licenseType) === 1) {
                    return true;
                }
            }
        }

        return false;
    }
}