<?php

namespace WPIcons\Admin;

use WPIcons\Template\Template;

if (!\defined('ABSPATH')) {
    exit;
}

class LibraryPage
{
    public function __construct(private Template $templates)
    {
    }

    public function renderPage(): void
    {
        if (!current_user_can(Menu::CAPABILITY)) {
            return;
        }

        echo $this->templates->render('admin/library', []);
    }
}
