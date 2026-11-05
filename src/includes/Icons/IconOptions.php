<?php

namespace WPIcons\Icons;

if (!\defined('ABSPATH')) {
    exit;
}

final class IconOptions
{
    public string $name;
    public int $size;
    public int $height;
    public string $color;
    public float $strokeWidth;
    public string $class;
    public string|bool $hiddenOrTitle;
    /** Library style: Phosphor weight, or Tabler / Heroicons / Ionicons style. '' = library default. */
    public string $variant;

    public function __construct(
        string $name = '',
        int $size = 24,
        ?int $height = null,
        string $color = 'currentColor',
        float $strokeWidth = 2.0,
        string $class = '',
        string|bool $hiddenOrTitle = true,
        string $variant = '',
    ) {
        $this->name = $name;
        $this->size = max(1, $size);
        $this->height = max(1, $height ?? $this->size);
        $this->color = $color === '' ? 'currentColor' : $color;
        $this->strokeWidth = max(0.25, $strokeWidth);
        $this->class = $class;
        $this->hiddenOrTitle = $hiddenOrTitle;
        $this->variant = self::cleanVariant($variant);
    }

    public static function cleanVariant(mixed $value): string
    {
        if (!\is_string($value)) {
            return '';
        }

        $value = strtolower(trim($value));

        return preg_match('/^[a-z0-9-]{1,20}$/', $value) === 1 ? $value : '';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $title = $data['title'] ?? $data['hiddenOrTitle'] ?? true;
        if (\is_string($title)) {
            $title = $title === '' ? true : $title;
        } elseif (!\is_bool($title)) {
            $title = true;
        }

        $size = isset($data['size']) ? (int) $data['size'] : 24;
        $height = isset($data['height']) ? (int) $data['height'] : null;
        $stroke = $data['strokeWidth'] ?? $data['stroke'] ?? $data['width'] ?? 2;

        // "variant" (block / inline icons), "weight" (Phosphor shortcode) or "style" (shortcode).
        // A block's "style" attribute is WordPress' style object, so only strings count.
        $variant = '';
        foreach (['variant', 'weight', 'style'] as $key) {
            if (isset($data[$key]) && \is_string($data[$key]) && trim($data[$key]) !== '') {
                $variant = $data[$key];
                break;
            }
        }

        return new self(
            name: isset($data['name']) ? (string) $data['name'] : '',
            size: $size > 0 ? $size : 24,
            height: $height !== null && $height > 0 ? $height : null,
            color: isset($data['color']) ? (string) $data['color'] : 'currentColor',
            strokeWidth: is_numeric($stroke) ? (float) $stroke : 2.0,
            class: isset($data['class']) ? (string) $data['class'] : '',
            hiddenOrTitle: $title,
            variant: $variant,
        );
    }
}