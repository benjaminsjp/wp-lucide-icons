<?php

if (!defined('ABSPATH')) {
    exit;
}

$icon = require __DIR__ . '/partials/icons.php';
$active = 'library';
$description = __('Browse bundled icon libraries, preview customization, and copy a shortcode. In the block editor, insert the Icon block to place an icon on a page.', 'wpicons');

?>
<div class="wpicons wpicons--admin wpicons__admin wpicons__admin--library">
    <?php include __DIR__ . '/partials/header.php'; ?>
    <div class="wpicons__library">
        <div id="wpicons-library-root" class="wpicons__library__root"></div>
    </div>
</div>
