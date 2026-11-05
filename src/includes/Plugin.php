<?php

namespace WPIcons;

use WPIcons\Admin\Assets;
use WPIcons\Admin\LibraryPage;
use WPIcons\Admin\Menu;
use WPIcons\Admin\Settings;
use WPIcons\Admin\TinyMce;
use WPIcons\Blocks\IconBlock;
use WPIcons\Content\InlineIcons;
use WPIcons\Icons\Cdn\CdnCatalog;
use WPIcons\Integrations\Elementor\Integration as ElementorIntegration;
use WPIcons\Integrations\Flatsome\IconElement;
use WPIcons\Rest\IconsController;
use WPIcons\Shortcode\Shortcode;
use WPIcons\Template\Template;

if (!\defined('ABSPATH')) {
    exit;
}

class Plugin
{
    private static ?self $instance = null;

    private Template $templates;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->templates = new Template();
    }

    public function init(): void
    {
        load_plugin_textdomain(
            'wpicons',
            false,
            dirname(plugin_basename(WP_ICONS__FILE)) . '/src/languages'
        );

        $settings = new Settings($this->templates);
        $library = new LibraryPage($this->templates);

        (new Assets($this->templates))->register();
        $settings->register();
        (new Menu($this->templates, $settings, $library))->register();
        (new Shortcode())->register();
        (new IconsController())->register();
        (new IconBlock())->register();
        (new InlineIcons())->register();
        (new TinyMce())->register();
        (new CdnCatalog())->register();
        (new ElementorIntegration($this->templates))->register();
        (new IconElement($this->templates))->register();

        /**
         * Fires once WP Icons has registered its hooks and the public helpers are ready.
         *
         * @since 2.0.0
         */
        do_action('wpicons_loaded');
    }

    public function templates(): Template
    {
        return $this->templates;
    }
}
