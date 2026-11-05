<?php

namespace WPIcons\Template;

if (!\defined('ABSPATH')) {
    exit;
}

class Template
{
    private string $directory;

    public function __construct()
    {
        $this->directory = WP_ICONS__PATH . 'src/templates';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $name, array $data = []): string
    {
        $path = "{$this->directory}/{$name}.php";

        if (!is_readable($path)) {
            return '';
        }

        ob_start();
        (static function ($path, $data) {
            extract($data, EXTR_SKIP);
            include $path;
        })($path, $data);

        return (string) ob_get_clean();
    }
}
