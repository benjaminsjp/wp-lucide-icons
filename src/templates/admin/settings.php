<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $settings */
/** @var string $option_name */
/** @var string $group */
/** @var array<int, array<string, mixed>> $libraries */
/** @var string $licenses_url */

$default_library = (string) $settings['default_library'];
$default_size = (int) $settings['default_size'];
$default_color = (string) $settings['default_color'];
// True only when an enabled CDN library still has a background fetch queued.
$cdn_fetch_pending = array_filter(array_column($libraries, 'fetching')) !== [];

$normalize_hex = static function (string $color, string $fallback = '#000000'): string {
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color, $match)) {
        $raw = strtolower($match[1]);
        if (strlen($raw) === 3) {
            $raw = $raw[0] . $raw[0] . $raw[1] . $raw[1] . $raw[2] . $raw[2];
        }

        return '#' . $raw;
    }

    return $fallback;
};

$inherit_color = strtolower($default_color) === 'currentcolor';
$picker_color = $normalize_hex($default_color);

$range_fill = static function (float $value, float $min, float $max): string {
    $percent = $max > $min ? (($value - $min) / ($max - $min)) * 100 : 0;

    return (string) round(max(0, min(100, $percent)), 2) . '%';
};

$icon = require __DIR__ . '/partials/icons.php';
$active = 'settings';
$description = __('Set defaults for new icons and choose where each icon library is loaded from.', 'wpicons');

// Collect notices from the Settings API (same data settings_errors() would print), so they can be shown
// in the plugin's own notice style and CDN version errors can be shown next to their field.
$field_errors = [];
$notices = [];
$has_error = false;
foreach (get_settings_errors() as $wpicons_error) {
    $wpicons_code = (string) ($wpicons_error['code'] ?? '');
    $wpicons_type = (string) ($wpicons_error['type'] ?? 'error');
    $wpicons_type = match ($wpicons_type) {
        'updated' => 'success',
        'error', 'warning', 'info', 'success' => $wpicons_type,
        default => 'info',
    };

    if (str_starts_with($wpicons_code, 'wp_icons_invalid_version_')) {
        $field_errors[substr($wpicons_code, strlen('wp_icons_invalid_version_'))] = (string) $wpicons_error['message'];
        continue;
    }

    if ($wpicons_code === 'settings_updated') {
        continue;
    }

    if ($wpicons_type === 'error') {
        $has_error = true;
    }

    $notices[] = [
        'type' => $wpicons_type,
        'message' => (string) $wpicons_error['message'],
    ];
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by options.php after saving.
$just_saved = isset($_GET['settings-updated']) && sanitize_text_field(wp_unslash($_GET['settings-updated'])) === 'true';
if ($just_saved && !$has_error) {
    $wpicons_saved = __('Settings saved.', 'wpicons');
    if ($cdn_fetch_pending) {
        $wpicons_saved .= ' ' . __('Fetching the CDN catalog in the background.', 'wpicons');
    }
    array_unshift($notices, ['type' => 'success', 'message' => $wpicons_saved]);
}

$notice_icons = [
    'success' => 'circle-check',
    'warning' => 'triangle-alert',
    'error' => 'circle-alert',
    'info' => 'info',
];

$total_count = count($libraries);
$enabled_count = count(array_filter(array_column($libraries, 'enabled')));
$default_row = $libraries[0] ?? null;
foreach ($libraries as $wpicons_row) {
    if ($wpicons_row['id'] === $default_library) {
        $default_row = $wpicons_row;
        break;
    }
}

/* translators: 1: number of enabled icon libraries, 2: total number of icon libraries */
$enabled_format = __('%1$d of %2$d enabled', 'wpicons');
$brand_notice = __('Brand icons are trademarks of their owners and may have their own usage guidelines. Using them doesn’t imply endorsement.', 'wpicons');

?>
<div class="wpicons wpicons__admin wpicons__admin--settings">
    <?php include __DIR__ . '/partials/header.php'; ?>

    <?php if ($notices !== []): ?>
        <div class="wpicons__notices">
            <?php foreach ($notices as $wpicons_notice): ?>
                <div class="wpicons__notice wpicons__notice--<?= esc_attr($wpicons_notice['type']); ?>" role="<?= $wpicons_notice['type'] === 'error' ? 'alert' : 'status'; ?>">
                    <?php echo $icon($notice_icons[$wpicons_notice['type']], 18, 'wpicons__notice__icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                    <p class="wpicons__notice__message"><?= wp_kses_post($wpicons_notice['message']); ?></p>
                    <button type="button" class="wpicons__notice__dismiss" data-wpicons-dismiss aria-label="<?php esc_attr_e('Dismiss this notice', 'wpicons'); ?>">
                        <?php echo $icon('x', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="options.php" method="post" class="wpicons__settings" data-wpicons-settings-form>
        <?php settings_fields($group); ?>

        <section class="wpicons__card" aria-labelledby="wpicons-defaults-title">
            <header class="wpicons__card__header">
                <h2 id="wpicons-defaults-title" class="wpicons__card__title"><?php esc_html_e('Defaults', 'wpicons'); ?></h2>
                <p class="wpicons__card__description"><?php esc_html_e('Used when an icon block or shortcode doesn’t set its own value. Style and stroke defaults are set per library below.', 'wpicons'); ?></p>
            </header>

            <div class="wpicons__settings__defaults">
                <div class="wpicons__field">
                    <span class="wpicons__field__label" id="wpicons-default-library-label"><?php esc_html_e('Default library', 'wpicons'); ?></span>
                    <div class="wpicons__combobox" data-wpicons-combobox>
                        <input type="hidden" name="<?= esc_attr($option_name); ?>[default_library]" value="<?= esc_attr($default_row['id'] ?? $default_library); ?>" data-wpicons-combobox-value>
                        <button
                            type="button"
                            class="wpicons__combobox__button"
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            aria-controls="wpicons-default-library-list"
                            aria-labelledby="wpicons-default-library-label wpicons-default-library-current"
                            aria-describedby="wpicons-default-library-help"
                            data-wpicons-combobox-button
                        >
                            <span class="wpicons__combobox__glyph" aria-hidden="true" data-wpicons-combobox-glyph>
                                <?php echo $default_row['glyph'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built by the plugin from its bundled catalog. ?>
                            </span>
                            <span class="wpicons__combobox__label" id="wpicons-default-library-current" data-wpicons-combobox-label><?= esc_html($default_row['label'] ?? ''); ?></span>
                            <?php echo $icon('chevron-down', 16, 'wpicons__combobox__chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                        </button>
                        <div class="wpicons__combobox__popover" data-wpicons-combobox-popover hidden>
                            <p class="wpicons__combobox__section" aria-hidden="true"><?php esc_html_e('Enabled libraries', 'wpicons'); ?></p>
                            <ul id="wpicons-default-library-list" class="wpicons__combobox__list" role="listbox" tabindex="-1" aria-labelledby="wpicons-default-library-label" data-wpicons-combobox-list>
                                <?php foreach ($libraries as $library): ?>
                                    <li
                                        id="wpicons-default-library-option-<?= esc_attr($library['id']); ?>"
                                        class="wpicons__combobox__option"
                                        role="option"
                                        data-value="<?= esc_attr($library['id']); ?>"
                                        aria-selected="<?= ($default_row['id'] ?? '') === $library['id'] ? 'true' : 'false'; ?>"
                                        <?= $library['enabled'] ? '' : 'hidden'; ?>
                                    >
                                        <span class="wpicons__combobox__glyph" aria-hidden="true">
                                            <?php echo $library['glyph']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built by the plugin from its bundled catalog. ?>
                                        </span>
                                        <span class="wpicons__combobox__option-label"><?= esc_html($library['label']); ?></span>
                                        <span class="wpicons__combobox__count"><?= esc_html(number_format_i18n((int) $library['count'])); ?></span>
                                        <?php echo $icon('check', 14, 'wpicons__combobox__check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="wpicons__combobox__footer"><?php esc_html_e('Disabled libraries are managed below.', 'wpicons'); ?></p>
                        </div>
                    </div>
                    <p class="wpicons__help" id="wpicons-default-library-help"><?php esc_html_e('Only enabled libraries can be the default.', 'wpicons'); ?></p>
                </div>

                <div class="wpicons__field">
                    <div class="wpicons__field__label-row">
                        <label class="wpicons__field__label" for="wpicons-default-size"><?php esc_html_e('Default size', 'wpicons'); ?></label>
                        <span class="wpicons__field__hint"><?php esc_html_e('8–256 px', 'wpicons'); ?></span>
                    </div>
                    <div class="wpicons__slider-row">
                        <input
                            type="range"
                            class="wpicons__range"
                            min="8"
                            max="256"
                            step="1"
                            value="<?= esc_attr((string) $default_size); ?>"
                            data-wpicons-range-for="wpicons-default-size"
                            aria-label="<?php esc_attr_e('Default size', 'wpicons'); ?>"
                            style="--wpicons-range-fill: <?= esc_attr($range_fill($default_size, 8, 256)); ?>"
                        >
                        <input id="wpicons-default-size" class="wpicons__number" name="<?= esc_attr($option_name); ?>[default_size]" type="number" min="8" max="256" value="<?= esc_attr((string) $default_size); ?>">
                        <span class="wpicons__unit"><?php esc_html_e('px', 'wpicons'); ?></span>
                    </div>
                </div>

                <div class="wpicons__field" data-wpicons-color>
                    <span class="wpicons__field__label" id="wpicons-default-color-label"><?php esc_html_e('Default color', 'wpicons'); ?></span>
                    <div class="wpicons__color-controls">
                        <label class="wpicons__toggle">
                            <input type="checkbox" class="wpicons__toggle__input" data-wpicons-color-inherit <?php checked($inherit_color); ?>>
                            <span class="wpicons__toggle__track" aria-hidden="true"></span>
                            <span class="wpicons__toggle__label"><?php esc_html_e('Inherit text color', 'wpicons'); ?></span>
                        </label>
                        <span class="wpicons__color-custom" data-wpicons-color-custom>
                            <span class="wpicons__color-sep" aria-hidden="true"></span>
                            <input
                                type="color"
                                class="wpicons__swatch-input"
                                value="<?= esc_attr($picker_color); ?>"
                                data-wpicons-color-picker
                                aria-label="<?php esc_attr_e('Pick a color', 'wpicons'); ?>"
                            >
                            <label class="screen-reader-text" for="wpicons-default-color"><?php esc_html_e('Default color', 'wpicons'); ?></label>
                            <input id="wpicons-default-color" class="wpicons__input wpicons__input--mono wpicons__input--color" name="<?= esc_attr($option_name); ?>[default_color]" type="text" value="<?= esc_attr($default_color); ?>" data-wpicons-color-value aria-describedby="wpicons-default-color-help">
                        </span>
                    </div>
                    <p class="wpicons__help" id="wpicons-default-color-help"><?php esc_html_e('Use currentColor to inherit text color, or a CSS color.', 'wpicons'); ?></p>
                </div>
            </div>
        </section>
        <section class="wpicons__card" aria-labelledby="wpicons-libraries-title">
            <header class="wpicons__card__header">
                <div class="wpicons__card__title-row">
                    <h2 id="wpicons-libraries-title" class="wpicons__card__title"><?php esc_html_e('Icon libraries', 'wpicons'); ?></h2>
                    <span class="wpicons__card__meta" data-wpicons-enabled-summary data-format="<?= esc_attr($enabled_format); ?>">
                        <?= esc_html(sprintf($enabled_format, $enabled_count, $total_count)); ?>
                    </span>
                </div>
                <p class="wpicons__card__description"><?php esc_html_e('Icons are stored in the plugin. Choose CDN only if this plugin has not been updated and you need a newer catalog. CDN catalogs are cached locally and are never fetched during page views.', 'wpicons'); ?></p>
            </header>

            <div class="wpicons__libs__toolbar">
                <label class="wpicons__libs__search">
                    <?php echo $icon('search', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                    <span class="screen-reader-text"><?php esc_html_e('Search libraries', 'wpicons'); ?></span>
                    <input type="search" class="wpicons__libs__search-input" placeholder="<?php esc_attr_e('Search libraries', 'wpicons'); ?>" autocomplete="off" data-wpicons-lib-search>
                </label>
                <div class="wpicons__libs__filter" role="group" aria-label="<?php esc_attr_e('Show libraries', 'wpicons'); ?>">
                    <?php
                    $wpicons_filters = [
                        'all' => [__('All', 'wpicons'), $total_count],
                        'enabled' => [__('Enabled', 'wpicons'), $enabled_count],
                        'disabled' => [__('Disabled', 'wpicons'), $total_count - $enabled_count],
                    ];
                    foreach ($wpicons_filters as $wpicons_filter => [$wpicons_filter_label, $wpicons_filter_count]):
                        ?>
                        <button type="button" class="wpicons__libs__filter-button" aria-pressed="<?= $wpicons_filter === 'all' ? 'true' : 'false'; ?>" data-wpicons-lib-filter="<?= esc_attr($wpicons_filter); ?>">
                            <span><?= esc_html($wpicons_filter_label); ?></span>
                            <span class="wpicons__libs__filter-count" data-wpicons-lib-count="<?= esc_attr($wpicons_filter); ?>"><?= esc_html(number_format_i18n($wpicons_filter_count)); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="wpicons__libs" data-wpicons-libs>
                <?php foreach ($libraries as $library): ?>
                    <?php
                    $id = $library['id'];
                    $label = (string) $library['label'];
                    $source = $library['source'];
                    $cdn_version = $library['cdn_version'];
                    $versions = $library['cdn_versions'];
                    $row_id = 'wpicons-library-' . $id;
                    $select_id = "{$option_name}_{$id}_cdn_version";
                    $error_id = "{$select_id}_error";
                    $prefix = "{$option_name}[libraries][{$id}]";
                    $field_error = $field_errors[$id] ?? '';
                    if (!\in_array($cdn_version, $versions, true) && $cdn_version !== 'latest' && $cdn_version !== '') {
                        $versions = [...[$cdn_version], ...$versions];
                    }
                    $status = 'none';
                    if ($field_error !== '') {
                        $status = 'error';
                    } elseif (!empty($library['fetching'])) {
                        $status = 'fetching';
                    } elseif ($library['cached_version']) {
                        $status = 'cached';
                    }
                    $status_labels = [
                        'error' => __('Error', 'wpicons'),
                        'fetching' => __('Fetching…', 'wpicons'),
                        'cached' => __('Cached', 'wpicons'),
                        'none' => __('Not cached', 'wpicons'),
                    ];
                    $variants = (array) $library['variants'];
                    $has_variants = count($variants) > 1;
                    $has_defaults = $has_variants || !empty($library['has_stroke']) || !empty($library['brand_colors']);
                    $is_open = $field_error !== '';
                    $variant_label = $library['variant_param'] === 'weight' ? __('Default weight', 'wpicons') : __('Default style', 'wpicons');
                    ?>
                    <div
                        class="wpicons__librow<?= $library['enabled'] ? '' : ' is-disabled'; ?><?= $is_open ? ' is-open' : ''; ?><?= $field_error !== '' ? ' is-error' : ''; ?>"
                        data-wpicons-librow
                        data-library="<?= esc_attr($id); ?>"
                        data-search="<?= esc_attr(strtolower($label . ' ' . $library['package'] . ' ' . $id)); ?>"
                    >
                        <div class="wpicons__librow__header">
                            <span class="wpicons__librow__glyph" aria-hidden="true">
                                <?php echo $library['glyph']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built by the plugin from its bundled catalog. ?>
                            </span>
                            <div class="wpicons__librow__name">
                                <h3 class="wpicons__librow__title" id="<?= esc_attr($row_id); ?>-title"><?= esc_html($label); ?></h3>
                                <p class="wpicons__librow__meta">
                                    <?= esc_html(sprintf(
                                        /* translators: 1: npm package, 2: bundled version */
                                        __('%1$s · %2$s', 'wpicons'),
                                        $library['package'],
                                        $library['bundled_version']
                                    )); ?>
                                </p>
                            </div>
                            <div class="wpicons__librow__badges">
                                <span class="wpicons__tag" data-wpicons-source-badge="plugin" <?= $source === 'plugin' ? '' : 'hidden'; ?>><?php esc_html_e('Bundled', 'wpicons'); ?></span>
                                <span class="wpicons__tag wpicons__tag--accent" data-wpicons-source-badge="cdn" <?= $source === 'cdn' ? '' : 'hidden'; ?>><?php esc_html_e('CDN', 'wpicons'); ?></span>
                                <span class="wpicons__libstatus wpicons__libstatus--<?= esc_attr($status); ?>">
                                    <span class="wpicons__libstatus__dot" aria-hidden="true"></span>
                                    <span><?= esc_html($status_labels[$status]); ?></span>
                                </span>
                                <span class="wpicons__tag wpicons__tag--outline" data-wpicons-default-badge <?= ($default_row['id'] ?? '') === $id ? '' : 'hidden'; ?>>
                                    <?php echo $icon('star', 12); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                                    <span><?php esc_html_e('Default', 'wpicons'); ?></span>
                                </span>
                                <?php if (!empty($library['has_brands'])): ?>
                                    <span class="wpicons__tag">
                                        <?php echo $icon('info', 12); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                                        <span><?php esc_html_e('Brand icons', 'wpicons'); ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <label class="wpicons__toggle wpicons__librow__toggle">
                                <span class="screen-reader-text">
                                    <?= esc_html(sprintf(
                                        /* translators: %s: icon library label */
                                        __('Enable %s', 'wpicons'),
                                        $label
                                    )); ?>
                                </span>
                                <input type="checkbox" class="wpicons__toggle__input" name="<?= esc_attr($prefix); ?>[enabled]" value="1" data-wpicons-lib-enabled <?php checked($library['enabled']); ?>>
                                <span class="wpicons__toggle__track" aria-hidden="true"></span>
                            </label>
                            <button
                                type="button"
                                class="wpicons__librow__expand"
                                aria-expanded="<?= $is_open ? 'true' : 'false'; ?>"
                                aria-controls="<?= esc_attr($row_id); ?>-detail"
                                data-wpicons-librow-expand
                            >
                                <span class="screen-reader-text">
                                    <?= esc_html(sprintf(
                                        /* translators: %s: icon library label */
                                        __('%s settings', 'wpicons'),
                                        $label
                                    )); ?>
                                </span>
                                <?php echo $icon('chevron-down', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                            </button>
                        </div>

                        <div class="wpicons__librow__detail" id="<?= esc_attr($row_id); ?>-detail" role="region" aria-labelledby="<?= esc_attr($row_id); ?>-title" <?= $is_open ? '' : 'hidden'; ?>>
                            <?php if (!empty($library['has_brands'])): ?>
                                <div class="wpicons__brand-notice" role="note">
                                    <?php echo $icon('info', 14, 'wpicons__brand-notice__icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                                    <p class="wpicons__brand-notice__text"><?= esc_html($brand_notice); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="wpicons__librow__source">
                                <fieldset class="wpicons__field">
                                    <legend class="wpicons__field__label wpicons__field__label--sm"><?php esc_html_e('Icon source', 'wpicons'); ?></legend>
                                    <div class="wpicons__segmented">
                                        <label class="wpicons__choice">
                                            <input type="radio" class="wpicons__choice__input" name="<?= esc_attr($prefix); ?>[source]" value="plugin" <?php checked($source, 'plugin'); ?>>
                                            <span class="wpicons__segment"><?php esc_html_e('Plugin (bundled)', 'wpicons'); ?></span>
                                        </label>
                                        <label class="wpicons__choice">
                                            <input type="radio" class="wpicons__choice__input" name="<?= esc_attr($prefix); ?>[source]" value="cdn" <?php checked($source, 'cdn'); ?>>
                                            <span class="wpicons__segment"><?php esc_html_e('CDN (npm, cached)', 'wpicons'); ?></span>
                                        </label>
                                    </div>
                                    <p class="wpicons__help">
                                        <?= esc_html(sprintf(
                                            /* translators: 1: npm package, 2: bundled version */
                                            __('Bundled catalog is %1$s %2$s. CDN downloads a newer package in the background and stores it on this site.', 'wpicons'),
                                            $library['package'],
                                            $library['bundled_version']
                                        )); ?>
                                    </p>
                                </fieldset>

                                <div class="wpicons__field wpicons__field--tight wpicons__librow__version">
                                    <label class="wpicons__field__label wpicons__field__label--sm" for="<?= esc_attr($select_id); ?>"><?php esc_html_e('CDN version', 'wpicons'); ?></label>
                                    <?php if ($versions !== []): ?>
                                        <span class="wpicons__select-wrap">
                                            <select
                                                class="wpicons__cdn-version wpicons__select"
                                                id="<?= esc_attr($select_id); ?>"
                                                name="<?= esc_attr($prefix); ?>[cdn_version]"
                                                <?php disabled($source, 'plugin'); ?>
                                                <?php if ($field_error !== ''): ?>aria-invalid="true" aria-describedby="<?= esc_attr($error_id); ?>"<?php endif; ?>
                                            >
                                                <option value="latest" <?php selected($cdn_version, 'latest'); ?>><?php esc_html_e('latest', 'wpicons'); ?></option>
                                                <?php foreach ($versions as $version): ?>
                                                    <option value="<?= esc_attr($version); ?>" <?php selected($cdn_version, $version); ?>><?= esc_html($version); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php echo $icon('chevron-down', 16, 'wpicons__select-wrap__chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                                        </span>
                                    <?php else: ?>
                                        <input
                                            class="wpicons__cdn-version wpicons__input"
                                            id="<?= esc_attr($select_id); ?>"
                                            name="<?= esc_attr($prefix); ?>[cdn_version]"
                                            type="text"
                                            value="<?= esc_attr($cdn_version); ?>"
                                            <?php disabled($source, 'plugin'); ?>
                                            <?php if ($field_error !== ''): ?>aria-invalid="true" aria-describedby="<?= esc_attr($error_id); ?>"<?php endif; ?>
                                        >
                                        <p class="wpicons__help"><?php esc_html_e('Could not load versions from npm. Enter “latest” or a semver such as 0.468.0.', 'wpicons'); ?></p>
                                    <?php endif; ?>
                                    <?php if ($field_error !== ''): ?>
                                        <p class="wpicons__field-error" id="<?= esc_attr($error_id); ?>">
                                            <?php echo $icon('circle-alert', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                                            <span><?= esc_html($field_error); ?></span>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="wpicons__status wpicons__status--<?= esc_attr($status === 'error' ? 'none' : $status); ?>">
                                <span class="wpicons__status__dot" aria-hidden="true"></span>
                                <span>
                                    <?php if ($status === 'fetching'): ?>
                                        <?= esc_html(sprintf(
                                            /* translators: 1: npm package, 2: requested version */
                                            __('Fetching %1$s@%2$s in the background. The bundled catalog is used until it finishes.', 'wpicons'),
                                            $library['package'],
                                            $cdn_version
                                        )); ?>
                                    <?php elseif ($library['cached_version']): ?>
                                        <?= esc_html(sprintf(
                                            /* translators: 1: cached version, 2: icon count, 3: date */
                                            __('Cached CDN catalog: %1$s (%2$d icons)%3$s.', 'wpicons'),
                                            $library['cached_version'],
                                            (int) $library['cached_count'],
                                            $library['cached_at']
                                                ? ' — ' . wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $library['cached_at'])
                                                : ''
                                        )); ?>
                                    <?php else: ?>
                                        <?php esc_html_e('No CDN catalog cached yet. Save with CDN selected to fetch one in the background.', 'wpicons'); ?>
                                    <?php endif; ?>
                                </span>
                            </p>
                            <?php if ($has_defaults): ?>
                                <hr class="wpicons__divider">
                                <div class="wpicons__librow__defaults">
                                    <div class="wpicons__librow__defaults-header">
                                        <h4 class="wpicons__librow__defaults-title"><?php esc_html_e('Defaults for this library', 'wpicons'); ?></h4>
                                        <p class="wpicons__help">
                                            <?= esc_html(sprintf(
                                                /* translators: %s: icon library label */
                                                __('Override the global defaults for new icons from %s.', 'wpicons'),
                                                $label
                                            )); ?>
                                        </p>
                                    </div>
                                    <div class="wpicons__librow__controls">
                                        <?php if ($has_variants): ?>
                                            <?php if (count($variants) <= 4): ?>
                                                <fieldset class="wpicons__field wpicons__field--tight">
                                                    <legend class="wpicons__field__label wpicons__field__label--sm"><?= esc_html($variant_label); ?></legend>
                                                    <div class="wpicons__segmented">
                                                        <?php foreach ($variants as $variant_id => $variant_name): ?>
                                                            <label class="wpicons__choice">
                                                                <input type="radio" class="wpicons__choice__input" name="<?= esc_attr($prefix); ?>[variant]" value="<?= esc_attr((string) $variant_id); ?>" <?php checked($library['variant'], (string) $variant_id); ?>>
                                                                <span class="wpicons__segment"><?= esc_html((string) $variant_name); ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </fieldset>
                                            <?php else: ?>
                                                <div class="wpicons__field wpicons__field--tight">
                                                    <label class="wpicons__field__label wpicons__field__label--sm" for="<?= esc_attr($row_id); ?>-variant"><?= esc_html($variant_label); ?></label>
                                                    <span class="wpicons__select-wrap">
                                                        <select class="wpicons__select" id="<?= esc_attr($row_id); ?>-variant" name="<?= esc_attr($prefix); ?>[variant]">
                                                            <?php foreach ($variants as $variant_id => $variant_name): ?>
                                                                <option value="<?= esc_attr((string) $variant_id); ?>" <?php selected($library['variant'], (string) $variant_id); ?>><?= esc_html((string) $variant_name); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <?php echo $icon('chevron-down', 16, 'wpicons__select-wrap__chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if (!empty($library['has_stroke'])): ?>
                                            <?php $stroke = (float) $library['stroke']; ?>
                                            <div class="wpicons__field wpicons__field--tight wpicons__librow__stroke">
                                                <div class="wpicons__field__label-row">
                                                    <label class="wpicons__field__label wpicons__field__label--sm" for="<?= esc_attr($row_id); ?>-stroke"><?php esc_html_e('Default stroke width', 'wpicons'); ?></label>
                                                    <span class="wpicons__field__hint"><?php esc_html_e('0.25–4 · step 0.25', 'wpicons'); ?></span>
                                                </div>
                                                <div class="wpicons__slider-row">
                                                    <input
                                                        type="range"
                                                        class="wpicons__range"
                                                        min="0.25"
                                                        max="4"
                                                        step="0.25"
                                                        value="<?= esc_attr((string) $stroke); ?>"
                                                        data-wpicons-range-for="<?= esc_attr($row_id); ?>-stroke"
                                                        aria-label="<?php esc_attr_e('Default stroke width', 'wpicons'); ?>"
                                                        style="--wpicons-range-fill: <?= esc_attr($range_fill($stroke, 0.25, 4)); ?>"
                                                    >
                                                    <input id="<?= esc_attr($row_id); ?>-stroke" class="wpicons__number" name="<?= esc_attr($prefix); ?>[stroke]" type="number" min="0.25" max="4" step="0.25" value="<?= esc_attr((string) $stroke); ?>">
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($library['brand_colors'])): ?>
                                            <?php
                                            $color_mode = (string) $library['color_mode'];
                                            $library_color = (string) $library['color'];
                                            $color_modes = [
                                                'brand' => __('Brand color', 'wpicons'),
                                                'current' => __('Inherit text', 'wpicons'),
                                                'custom' => __('Custom', 'wpicons'),
                                            ];
                                            ?>
                                            <fieldset class="wpicons__field wpicons__field--tight" data-wpicons-color-mode>
                                                <legend class="wpicons__field__label wpicons__field__label--sm"><?php esc_html_e('Default color mode', 'wpicons'); ?></legend>
                                                <div class="wpicons__segmented">
                                                    <?php foreach ($color_modes as $mode => $mode_label): ?>
                                                        <label class="wpicons__choice">
                                                            <input type="radio" class="wpicons__choice__input" name="<?= esc_attr($prefix); ?>[color_mode]" value="<?= esc_attr($mode); ?>" <?php checked($color_mode, $mode); ?>>
                                                            <span class="wpicons__segment"><?= esc_html($mode_label); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                                <span class="wpicons__color-custom" data-wpicons-color-mode-custom <?= $color_mode === 'custom' ? '' : 'hidden'; ?>>
                                                    <input
                                                        type="color"
                                                        class="wpicons__swatch-input"
                                                        value="<?= esc_attr($normalize_hex($library_color)); ?>"
                                                        data-wpicons-color-mode-picker
                                                        aria-label="<?php esc_attr_e('Pick a color', 'wpicons'); ?>"
                                                    >
                                                    <label class="screen-reader-text" for="<?= esc_attr($row_id); ?>-color"><?php esc_html_e('Custom color', 'wpicons'); ?></label>
                                                    <input id="<?= esc_attr($row_id); ?>-color" class="wpicons__input wpicons__input--mono wpicons__input--color" name="<?= esc_attr($prefix); ?>[color]" type="text" value="<?= esc_attr($library_color !== '' ? $library_color : $picker_color); ?>" data-wpicons-color-mode-value <?php disabled($color_mode !== 'custom'); ?>>
                                                </span>
                                            </fieldset>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <p class="wpicons__libs__empty" data-wpicons-libs-empty hidden><?php esc_html_e('No libraries match.', 'wpicons'); ?></p>
            </div>
        </section>

        <p class="wpicons__licenses-note">
            <?php echo $icon('info', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
            <span>
                <?php esc_html_e('Icons are third-party works under their own licenses. You’re responsible for making sure your use complies with each icon’s license and the owner’s guidelines.', 'wpicons'); ?>
                <a href="<?= esc_url($licenses_url); ?>"><?php esc_html_e('View licenses', 'wpicons'); ?></a>
            </span>
        </p>

        <div class="wpicons__savebar" data-wpicons-savebar>
            <div class="wpicons__savebar__inner">
                <p class="wpicons__savebar__message" data-wpicons-savebar-clean>
                    <?php echo $icon('info', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                    <span><?php esc_html_e('Changes apply to new icons. Existing blocks keep their own settings.', 'wpicons'); ?></span>
                </p>
                <p
                    class="wpicons__savebar__message wpicons__savebar__message--dirty"
                    data-wpicons-savebar-dirty
                    data-one="<?= esc_attr(_n('%d unsaved change', '%d unsaved changes', 1, 'wpicons')); ?>"
                    data-other="<?= esc_attr(_n('%d unsaved change', '%d unsaved changes', 2, 'wpicons')); ?>"
                    hidden
                >
                    <strong data-wpicons-savebar-count><?php esc_html_e('You have unsaved changes', 'wpicons'); ?></strong>
                    <span class="wpicons__savebar__hint">· <?php esc_html_e('Changes apply to new icons. Existing blocks keep their own settings.', 'wpicons'); ?></span>
                </p>
                <span class="wpicons__savebar__spacer"></span>
                <button type="reset" class="wpicons__btn wpicons__btn--secondary"><?php esc_html_e('Cancel', 'wpicons'); ?></button>
                <button type="submit" name="submit" class="wpicons__btn wpicons__btn--primary"><?php esc_html_e('Save changes', 'wpicons'); ?></button>
            </div>
        </div>
    </form>    <script>
        (function () {
            var form = document.querySelector('[data-wpicons-settings-form]');
            if (!form) return;

            var rows = Array.prototype.slice.call(form.querySelectorAll('[data-wpicons-librow]'));
            var hex = /^#[0-9a-f]{6}$/i;

            // ---------- Library rows: source, expand, enabled ----------
            function syncSources() {
                rows.forEach(function (row) {
                    var checked = row.querySelector('input[type="radio"][name*="[source]"]:checked');
                    var cdn = !!checked && checked.value === 'cdn';
                    var version = row.querySelector('.wpicons__cdn-version');
                    if (version) version.disabled = !cdn;
                    row.querySelectorAll('[data-wpicons-source-badge]').forEach(function (badge) {
                        badge.hidden = badge.getAttribute('data-wpicons-source-badge') !== (cdn ? 'cdn' : 'plugin');
                    });
                });
            }

            function setOpen(row, open) {
                var button = row.querySelector('[data-wpicons-librow-expand]');
                var detail = button && document.getElementById(button.getAttribute('aria-controls'));
                if (!button || !detail) return;
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                detail.hidden = !open;
                row.classList.toggle('is-open', open);
            }
            rows.forEach(function (row) {
                var button = row.querySelector('[data-wpicons-librow-expand]');
                if (button) {
                    button.addEventListener('click', function () {
                        setOpen(row, button.getAttribute('aria-expanded') !== 'true');
                    });
                }
            });

            function isEnabled(row) {
                var input = row.querySelector('[data-wpicons-lib-enabled]');
                return !!input && input.checked;
            }

            // ---------- Search + filter ----------
            var search = form.querySelector('[data-wpicons-lib-search]');
            var filters = Array.prototype.slice.call(form.querySelectorAll('[data-wpicons-lib-filter]'));
            var empty = form.querySelector('[data-wpicons-libs-empty]');
            var summary = form.querySelector('[data-wpicons-enabled-summary]');
            var filter = 'all';

            function syncList() {
                var term = search ? search.value.trim().toLowerCase() : '';
                var enabled = 0;
                var visible = 0;
                rows.forEach(function (row) {
                    var on = isEnabled(row);
                    if (on) enabled++;
                    row.classList.toggle('is-disabled', !on);
                    var matchesFilter = filter === 'all' || (filter === 'enabled' ? on : !on);
                    var matchesTerm = term === '' || (row.getAttribute('data-search') || '').indexOf(term) !== -1;
                    row.hidden = !(matchesFilter && matchesTerm);
                    if (!row.hidden) visible++;
                });
                var counts = { all: rows.length, enabled: enabled, disabled: rows.length - enabled };
                form.querySelectorAll('[data-wpicons-lib-count]').forEach(function (el) {
                    el.textContent = String(counts[el.getAttribute('data-wpicons-lib-count')]);
                });
                if (summary) {
                    summary.textContent = (summary.getAttribute('data-format') || '%1$d / %2$d')
                        .replace('%1$d', String(enabled))
                        .replace('%2$d', String(rows.length));
                }
                if (empty) empty.hidden = visible > 0;
            }
            filters.forEach(function (button) {
                button.addEventListener('click', function () {
                    filter = button.getAttribute('data-wpicons-lib-filter');
                    filters.forEach(function (other) {
                        other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
                    });
                    syncList();
                });
            });
            if (search) {
                search.addEventListener('input', syncList);
                // Keep Enter in the search field from submitting the settings form.
                search.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') event.preventDefault();
                });
            }

            // ---------- Default library combobox ----------
            var combo = form.querySelector('[data-wpicons-combobox]');
            var comboValue = combo && combo.querySelector('[data-wpicons-combobox-value]');
            var comboButton = combo && combo.querySelector('[data-wpicons-combobox-button]');
            var comboPopover = combo && combo.querySelector('[data-wpicons-combobox-popover]');
            var comboList = combo && combo.querySelector('[data-wpicons-combobox-list]');
            var comboLabel = combo && combo.querySelector('[data-wpicons-combobox-label]');
            var comboGlyph = combo && combo.querySelector('[data-wpicons-combobox-glyph]');
            var activeOption = null;
            var initialDefault = comboValue ? comboValue.value : '';

            function options() {
                return comboList ? Array.prototype.slice.call(comboList.querySelectorAll('[role="option"]')) : [];
            }
            function visibleOptions() {
                return options().filter(function (option) { return !option.hidden; });
            }
            function setActive(option) {
                options().forEach(function (other) { other.classList.toggle('is-active', other === option); });
                activeOption = option;
                if (option) {
                    comboList.setAttribute('aria-activedescendant', option.id);
                    option.scrollIntoView({ block: 'nearest' });
                } else {
                    comboList.removeAttribute('aria-activedescendant');
                }
            }
            function selectValue(value, notify) {
                if (!comboValue) return;
                var match = null;
                options().forEach(function (option) {
                    var selected = option.getAttribute('data-value') === value;
                    option.setAttribute('aria-selected', selected ? 'true' : 'false');
                    if (selected) match = option;
                });
                if (!match) return;
                var changed = comboValue.value !== value;
                comboValue.value = value;
                if (comboLabel) comboLabel.textContent = match.querySelector('.wpicons__combobox__option-label').textContent;
                if (comboGlyph) comboGlyph.innerHTML = match.querySelector('.wpicons__combobox__glyph').innerHTML;
                rows.forEach(function (row) {
                    var badge = row.querySelector('[data-wpicons-default-badge]');
                    if (badge) badge.hidden = row.getAttribute('data-library') !== value;
                });
                if (changed && notify) comboValue.dispatchEvent(new Event('change', { bubbles: true }));
            }
            function openCombo() {
                if (!comboPopover) return;
                comboPopover.hidden = false;
                comboButton.setAttribute('aria-expanded', 'true');
                var selected = comboList.querySelector('[aria-selected="true"]:not([hidden])') || visibleOptions()[0] || null;
                comboList.focus();
                setActive(selected);
            }
            function closeCombo(focusButton) {
                if (!comboPopover || comboPopover.hidden) return;
                comboPopover.hidden = true;
                comboButton.setAttribute('aria-expanded', 'false');
                setActive(null);
                if (focusButton) comboButton.focus();
            }
            function syncComboOptions() {
                rows.forEach(function (row) {
                    var option = comboList && comboList.querySelector('[data-value="' + row.getAttribute('data-library') + '"]');
                    if (option) option.hidden = !isEnabled(row);
                });
                // The default must be an enabled library; fall back to the first enabled one.
                var current = comboList && comboList.querySelector('[data-value="' + (comboValue ? comboValue.value : '') + '"]');
                if (current && current.hidden) {
                    var first = visibleOptions()[0];
                    if (first) selectValue(first.getAttribute('data-value'), true);
                }
            }
            if (combo && comboButton && comboList) {
                comboButton.addEventListener('click', function () {
                    if (comboPopover.hidden) openCombo(); else closeCombo(true);
                });
                comboButton.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        openCombo();
                    }
                });
                comboList.addEventListener('keydown', function (event) {
                    var list = visibleOptions();
                    var index = list.indexOf(activeOption);
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        setActive(list[Math.min(list.length - 1, index + 1)] || list[0]);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        setActive(list[Math.max(0, index - 1)] || list[0]);
                    } else if (event.key === 'Home') {
                        event.preventDefault();
                        setActive(list[0]);
                    } else if (event.key === 'End') {
                        event.preventDefault();
                        setActive(list[list.length - 1]);
                    } else if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        if (activeOption) selectValue(activeOption.getAttribute('data-value'), true);
                        closeCombo(true);
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        closeCombo(true);
                    } else if (event.key === 'Tab') {
                        closeCombo(false);
                    }
                });
                comboList.addEventListener('click', function (event) {
                    var option = event.target.closest('[role="option"]');
                    if (!option || option.hidden) return;
                    selectValue(option.getAttribute('data-value'), true);
                    closeCombo(true);
                });
                comboList.addEventListener('mousemove', function (event) {
                    var option = event.target.closest('[role="option"]');
                    if (option && option !== activeOption) setActive(option);
                });
                document.addEventListener('mousedown', function (event) {
                    if (!combo.contains(event.target)) closeCombo(false);
                });
            }
            // ---------- Ranges ----------
            function setFill(range) {
                var min = parseFloat(range.min) || 0;
                var max = parseFloat(range.max) || 100;
                var value = parseFloat(range.value) || 0;
                var percent = max > min ? ((value - min) / (max - min)) * 100 : 0;
                range.style.setProperty('--wpicons-range-fill', Math.max(0, Math.min(100, percent)) + '%');
            }

            var ranges = form.querySelectorAll('[data-wpicons-range-for]');
            function syncRanges() {
                ranges.forEach(function (range) {
                    var number = document.getElementById(range.getAttribute('data-wpicons-range-for'));
                    if (number && number.value !== '') range.value = number.value;
                    setFill(range);
                });
            }
            ranges.forEach(function (range) {
                var number = document.getElementById(range.getAttribute('data-wpicons-range-for'));
                if (!number) return;
                range.addEventListener('input', function () {
                    number.value = range.value;
                    setFill(range);
                    number.dispatchEvent(new Event('input', { bubbles: true }));
                });
                number.addEventListener('input', function () {
                    if (number.value === '') return;
                    range.value = number.value;
                    setFill(range);
                });
            });

            // ---------- Global default color ----------
            var colorRoot = form.querySelector('[data-wpicons-color]');
            var inherit = colorRoot && colorRoot.querySelector('[data-wpicons-color-inherit]');
            var custom = colorRoot && colorRoot.querySelector('[data-wpicons-color-custom]');
            var picker = colorRoot && colorRoot.querySelector('[data-wpicons-color-picker]');
            var text = colorRoot && colorRoot.querySelector('[data-wpicons-color-value]');
            var lastCustom = text && text.value.toLowerCase() !== 'currentcolor' ? text.value : (picker ? picker.value : '#000000');

            function syncColor() {
                if (!inherit || !custom || !text) return;
                custom.hidden = inherit.checked;
                if (picker && hex.test(text.value)) picker.value = text.value;
            }
            if (inherit && text) {
                inherit.addEventListener('change', function () {
                    if (inherit.checked) {
                        if (text.value.toLowerCase() !== 'currentcolor') lastCustom = text.value;
                        text.value = 'currentColor';
                    } else {
                        text.value = lastCustom || '#000000';
                    }
                    syncColor();
                });
                if (picker) {
                    picker.addEventListener('input', function () {
                        text.value = picker.value;
                        lastCustom = picker.value;
                    });
                }
                text.addEventListener('input', function () {
                    if (hex.test(text.value)) picker.value = text.value;
                });
            }

            // ---------- Per-library color mode (brand libraries) ----------
            var colorModes = Array.prototype.slice.call(form.querySelectorAll('[data-wpicons-color-mode]'));
            function syncColorModes() {
                colorModes.forEach(function (root) {
                    var checked = root.querySelector('input[type="radio"]:checked');
                    var isCustom = !!checked && checked.value === 'custom';
                    var wrap = root.querySelector('[data-wpicons-color-mode-custom]');
                    var value = root.querySelector('[data-wpicons-color-mode-value]');
                    var swatch = root.querySelector('[data-wpicons-color-mode-picker]');
                    if (wrap) wrap.hidden = !isCustom;
                    // Only post the custom color while "Custom" is selected, so the stored value is left alone otherwise.
                    if (value) value.disabled = !isCustom;
                    if (swatch && value && hex.test(value.value)) swatch.value = value.value;
                });
            }
            colorModes.forEach(function (root) {
                var value = root.querySelector('[data-wpicons-color-mode-value]');
                var swatch = root.querySelector('[data-wpicons-color-mode-picker]');
                if (value && swatch) {
                    swatch.addEventListener('input', function () {
                        value.value = swatch.value;
                        value.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                    value.addEventListener('input', function () {
                        if (hex.test(value.value)) swatch.value = value.value;
                    });
                }
            });

            // ---------- Save bar ----------
            var savebar = form.querySelector('[data-wpicons-savebar]');
            var clean = savebar && savebar.querySelector('[data-wpicons-savebar-clean]');
            var dirty = savebar && savebar.querySelector('[data-wpicons-savebar-dirty]');
            var dirtyCount = savebar && savebar.querySelector('[data-wpicons-savebar-count]');
            var ignored = ['_wp_http_referer', '_wpnonce', 'option_page', 'action', 'submit'];
            function snapshot() {
                var map = {};
                new FormData(form).forEach(function (value, key) {
                    map[key] = (key in map ? map[key] + '\n' : '') + String(value);
                });
                // Unchecked boxes and disabled fields are absent from FormData; list every named field so they compare too.
                form.querySelectorAll('[name]').forEach(function (field) {
                    if (!(field.name in map)) map[field.name] = '';
                });
                return map;
            }
            var initial = snapshot();
            function syncDirty() {
                if (!savebar) return;
                var current = snapshot();
                var changes = 0;
                Object.keys(Object.assign({}, initial, current)).forEach(function (key) {
                    if (ignored.indexOf(key) !== -1) return;
                    if ((initial[key] || '') !== (current[key] || '')) changes++;
                });
                savebar.classList.toggle('is-dirty', changes > 0);
                if (clean) clean.hidden = changes > 0;
                if (dirty) dirty.hidden = changes === 0;
                if (dirtyCount && changes > 0) {
                    dirtyCount.textContent = (dirty.getAttribute(changes === 1 ? 'data-one' : 'data-other') || '%d').replace('%d', String(changes));
                }
            }

            function syncAll() {
                syncSources();
                syncList();
                syncComboOptions();
                syncColorModes();
                syncDirty();
            }

            form.addEventListener('change', syncAll);
            form.addEventListener('input', syncDirty);
            form.addEventListener('reset', function () {
                window.setTimeout(function () {
                    // Hidden inputs keep their current value on reset, so put the default library back by hand.
                    if (comboValue) selectValue(initialDefault, false);
                    syncRanges();
                    syncColor();
                    syncAll();
                }, 0);
            });

            document.querySelectorAll('[data-wpicons-dismiss]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var notice = button.closest('.wpicons__notice');
                    if (notice) notice.remove();
                });
            });

            syncRanges();
            syncColor();
            syncAll();
        })();
    </script>
</div>