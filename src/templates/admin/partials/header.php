<?php
/**
 * Shared header for the WP Icons admin pages: plugin tile, title, version badge and page switcher.
 */

use WPIcons\Admin\Menu;

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $active One of "library" or "settings". */
/** @var string $description */
/** @var callable(string, int=, string=): string $icon */

$wpicons_tabs = [
    'library' => [
        'label' => __('Library', 'wpicons'),
        'icon' => 'layout-grid',
        'url' => Menu::url('library'),
    ],
    'settings' => [
        'label' => __('Settings', 'wpicons'),
        'icon' => 'settings',
        'url' => Menu::url('settings'),
    ],
];

?>
<header class="wpicons__admin-header">
    <span class="wpicons__admin-header__logo">
        <?php echo $icon('shapes', 24); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
    </span>
    <div class="wpicons__admin-header__heading">
        <div class="wpicons__admin-header__title-row">
            <h1 class="wpicons__admin-header__title"><?php esc_html_e('WP Icons', 'wpicons'); ?></h1>
            <span class="wpicons__badge"><?= esc_html('v' . WP_ICONS__VERSION); ?></span>
        </div>
        <p class="wpicons__admin-header__description"><?= esc_html($description); ?></p>
    </div>
    <nav class="wpicons__admin-header__tabs" aria-label="<?php esc_attr_e('WP Icons pages', 'wpicons'); ?>">
        <?php foreach ($wpicons_tabs as $wpicons_tab_id => $wpicons_tab): ?>
            <a
                class="wpicons__pill<?= $wpicons_tab_id === $active ? ' is-active' : ''; ?>"
                href="<?= esc_url($wpicons_tab['url']); ?>"
                <?= $wpicons_tab_id === $active ? 'aria-current="page"' : ''; ?>
            >
                <?php echo $icon($wpicons_tab['icon'], 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                <span><?= esc_html($wpicons_tab['label']); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</header>
<hr class="wp-header-end">
