<?php
/**
 * Read-only list of the license texts shipped in the plugin's licenses/ folder.
 */

use WPIcons\Admin\Menu;

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can(Menu::CAPABILITY)) {
    return;
}

/** @var list<array{file: string, title: string, text: string}> $licenses */
/** @var string $back_url */

$icon = require __DIR__ . '/partials/icons.php';
$active = 'settings';
$description = __('License texts and notices for the icon libraries included with this plugin.', 'wpicons');

?>
<div class="wpicons wpicons__admin wpicons__admin--licenses">
    <?php include __DIR__ . '/partials/header.php'; ?>

    <section class="wpicons__card" aria-labelledby="wpicons-licenses-title">
        <header class="wpicons__card__header">
            <a class="wpicons__back-link" href="<?= esc_url($back_url); ?>">
                <?php echo $icon('arrow-left', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                <span><?php esc_html_e('Back to settings', 'wpicons'); ?></span>
            </a>
            <h2 id="wpicons-licenses-title" class="wpicons__card__title"><?php esc_html_e('Icon licenses', 'wpicons'); ?></h2>
            <p class="wpicons__card__description">
                <?php esc_html_e('Icons are third-party works under their own licenses. You’re responsible for making sure your use complies with each icon’s license and the owner’s guidelines. Brand icons are trademarks of their owners; using them doesn’t imply endorsement.', 'wpicons'); ?>
            </p>
        </header>

        <?php if ($licenses === []): ?>
            <p class="wpicons__help"><?php esc_html_e('No license files were found in the plugin’s licenses folder.', 'wpicons'); ?></p>
        <?php else: ?>
            <div class="wpicons__licenses">
                <?php foreach ($licenses as $wpicons_index => $wpicons_license): ?>
                    <details class="wpicons__license" <?= $wpicons_index === 0 ? 'open' : ''; ?>>
                        <summary class="wpicons__license__summary">
                            <?php echo $icon('file-text', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                            <span class="wpicons__license__title"><?= esc_html($wpicons_license['title']); ?></span>
                            <?php echo $icon('chevron-down', 16, 'wpicons__license__chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                        </summary>
                        <pre class="wpicons__license__text"><?= esc_html($wpicons_license['text']); ?></pre>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>