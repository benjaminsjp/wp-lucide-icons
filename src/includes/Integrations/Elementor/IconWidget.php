<?php

namespace WPIcons\Integrations\Elementor;

use WPIcons\Icons\Renderer;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Standalone icon, matching the Gutenberg WP Icons block.
 *
 * @since 2.0.0
 */
class IconWidget extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'wpicons-icon';
    }

    public function get_title(): string
    {
        return __('WP Icons', 'wpicons');
    }

    public function get_icon(): string
    {
        return 'wpicons-elementor-widget-icon';
    }

    public function get_categories(): array
    {
        return ['wpicons'];
    }

    /**
     * @return list<string>
     */
    public function get_keywords(): array
    {
        return ['icon', 'lucide', 'svg', 'wp icons'];
    }

    public function register_controls(): void
    {
        $this->start_controls_section('section_icon', [
            'label' => __('Icon', 'wpicons'),
        ]);

        $this->add_control('icon', [
            'label' => __('Icon', 'wpicons'),
            'type' => 'wpicons_icon',
        ]);

        $this->add_responsive_control('align', [
            'label' => __('Alignment', 'wpicons'),
            'type' => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'left' => [
                    'title' => __('Left', 'wpicons'),
                    'icon' => 'eicon-text-align-left',
                ],
                'center' => [
                    'title' => __('Center', 'wpicons'),
                    'icon' => 'eicon-text-align-center',
                ],
                'right' => [
                    'title' => __('Right', 'wpicons'),
                    'icon' => 'eicon-text-align-right',
                ],
            ],
            'selectors' => [
                '{{WRAPPER}}' => 'text-align: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $icon = $this->iconSettings();
        if ($icon['name'] === '') {
            if ($this->isEditorPreview()) {
                echo '<div class="wpicons wpicons-icon wpicons-icon__placeholder">'
                    . esc_html__('Choose an icon', 'wpicons')
                    . '</div>';
            }

            return;
        }

        $attributes = [
            'library' => $icon['library'],
            'name' => $icon['name'],
            'size' => $icon['size'],
            'color' => $icon['color'],
            'variant' => $icon['variant'],
            'title' => $icon['title'],
            'class' => 'wpicons-icon__svg',
        ];
        if ($icon['stroke'] !== '' && $icon['stroke'] !== null) {
            $attributes['stroke'] = $icon['stroke'];
        }

        $svg = (new Renderer())->fromAttributes($attributes);
        if ($svg === '') {
            return;
        }

        echo '<div class="wpicons wpicons-icon">' . $svg . '</div>';
    }

    /**
     * @return array{library: string, name: string, variant: string, size: int|string, color: string, stroke: mixed, title: string}
     */
    private function iconSettings(): array
    {
        $icon = $this->get_settings_for_display('icon');
        if (\is_string($icon)) {
            $decoded = json_decode($icon, true);
            $icon = \is_array($decoded) ? $decoded : [];
        }
        if (!\is_array($icon)) {
            $icon = [];
        }

        return [
            'library' => isset($icon['library']) ? sanitize_key((string) $icon['library']) : '',
            'name' => isset($icon['name']) ? sanitize_text_field((string) $icon['name']) : '',
            'variant' => isset($icon['variant']) ? sanitize_key((string) $icon['variant']) : '',
            'size' => isset($icon['size']) ? $icon['size'] : '',
            'color' => isset($icon['color']) ? (string) $icon['color'] : '',
            'stroke' => $icon['stroke'] ?? '',
            'title' => isset($icon['title']) ? sanitize_text_field((string) $icon['title']) : '',
        ];
    }

    private function isEditorPreview(): bool
    {
        if (Integration::isPreview()) {
            return true;
        }

        $editor = \Elementor\Plugin::$instance->editor ?? null;

        return \is_object($editor) && $editor->is_edit_mode();
    }
}
