<?php

namespace WPIcons\Icons;

use WPIcons\Admin\Settings;

if (!\defined('ABSPATH')) {
    exit;
}

final class Renderer
{
    public function render(string $libraryId, IconOptions $options): string
    {
        $settings = Settings::get();
        $libraryId = $libraryId === '' ? $settings['default_library'] : $libraryId;
        $library = Registry::get($libraryId);
        if ($library === null) {
            return '';
        }

        if (!Settings::libraryEnabled($settings, $libraryId)) {
            return '';
        }

        $variant = '';
        if ($library->variants() !== []) {
            $variant = isset($library->variants()[$options->variant])
                ? $options->variant
                : Settings::libraryVariant($settings, $libraryId);
        }

        $resolved = CatalogStore::resolve($libraryId, $options->name, $variant);
        if ($resolved === null || $resolved['inner'] === '') {
            return '';
        }

        $options = clone $options;
        $options->variant = $resolved['variant'];

        return $library->wrap($resolved['inner'], $options);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function fromAttributes(array $attributes): string
    {
        $settings = Settings::get();
        $library = isset($attributes['library']) ? (string) $attributes['library'] : 'lucide';
        $libraryId = $library === '' ? $settings['default_library'] : $library;

        $class = '';
        if (isset($attributes['class']) && \is_string($attributes['class'])) {
            $class = $attributes['class'];
        } elseif (isset($attributes['className']) && \is_string($attributes['className'])) {
            $class = $attributes['className'];
        }

        // Values left out fall back to the library's defaults from Settings (Lucide: the global
        // default stroke and color, exactly as before).
        $hasStroke = false;
        foreach (['strokeWidth', 'stroke', 'width'] as $key) {
            if (isset($attributes[$key]) && $attributes[$key] !== '' && is_numeric($attributes[$key])) {
                $hasStroke = true;
                break;
            }
        }
        if (!$hasStroke) {
            unset($attributes['strokeWidth'], $attributes['width']);
            $attributes['stroke'] = Settings::libraryStroke($settings, $libraryId);
        }

        if (!isset($attributes['color']) || trim((string) $attributes['color']) === '') {
            $attributes['color'] = Settings::libraryColor($settings, $libraryId);
        }

        $options = IconOptions::fromArray([
            ...$attributes,
            'class' => $class,
        ]);

        return $this->render($library, $options);
    }
}