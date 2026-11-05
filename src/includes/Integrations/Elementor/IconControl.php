<?php

namespace WPIcons\Integrations\Elementor;

use WPIcons\Admin\Settings;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * One control that opens the shared icon picker and stores its selection.
 *
 * @since 2.0.0
 */
class IconControl extends \Elementor\Base_Data_Control
{
    public function get_type(): string
    {
        return 'wpicons_icon';
    }

    /**
     * @return array<string, mixed>
     */
    public function get_default_value(): array
    {
        $settings = Settings::get();

        return [
            'library' => (string) $settings['default_library'],
            'name' => '',
            'variant' => '',
            'size' => (int) $settings['default_size'],
            'color' => '',
            'stroke' => '',
            'title' => '',
        ];
    }

    public function content_template(): void
    {
        ?>
        <div class="elementor-control-field">
            <label class="elementor-control-title">{{{ data.label }}}</label>
            <div class="elementor-control-input-wrapper">
                <button type="button" class="elementor-button elementor-button-default wpicons-elementor-choose">
                    <?php echo esc_html__('Choose icon', 'wpicons'); ?>
                </button>
                <#
                var icon = data.controlValue;
                if (!icon || typeof icon !== 'object') {
                    icon = {};
                }
                #>
                <p class="wpicons-elementor-summary">
                    <# if (icon.name) { #>
                        {{ icon.library || 'lucide' }} / {{ icon.name }}
                    <# } else { #>
                        <?php echo esc_html__('No icon selected', 'wpicons'); ?>
                    <# } #>
                </p>
            </div>
        </div>
        <?php
    }
}
