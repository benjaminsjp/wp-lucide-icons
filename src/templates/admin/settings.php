<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $settings */
/** @var string $option_name */
/** @var string $group */
/** @var array<int, array<string, mixed>> $libraries */

$default_library = $settings['default_library'];
$default_size = (int) $settings['default_size'];
$default_color = (string) $settings['default_color'];
$default_stroke = (float) $settings['default_stroke'];
// True only when an enabled CDN library still has a background fetch queued.
$cdn_fetch_pending = array_filter(array_column($libraries, 'fetching')) !== [];

$inherit_color = strtolower($default_color) === 'currentcolor';
$picker_color = '#000000';
if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $default_color, $wpicons_hex)) {
    $wpicons_raw = strtolower($wpicons_hex[1]);
    if (strlen($wpicons_raw) === 3) {
        $wpicons_raw = $wpicons_raw[0] . $wpicons_raw[0] . $wpicons_raw[1] . $wpicons_raw[1] . $wpicons_raw[2] . $wpicons_raw[2];
    }
    $picker_color = '#' . $wpicons_raw;
}

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
                <p class="wpicons__card__description"><?php esc_html_e('Used when an icon block or shortcode doesn’t set its own value.', 'wpicons'); ?></p>
            </header>

            <div class="wpicons__settings__defaults">
                <fieldset class="wpicons__field">
                    <legend class="wpicons__field__label"><?php esc_html_e('Default library', 'wpicons'); ?></legend>
                    <div class="wpicons__choices">
                        <?php foreach ($libraries as $library): ?>
                            <label class="wpicons__choice">
                                <input
                                    type="radio"
                                    class="wpicons__choice__input"
                                    name="<?= esc_attr($option_name); ?>[default_library]"
                                    value="<?= esc_attr($library['id']); ?>"
                                    <?php checked($default_library, $library['id']); ?>
                                >
                                <span class="wpicons__pill"><?= esc_html($library['label']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

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

                <div class="wpicons__field">
                    <div class="wpicons__field__label-row">
                        <label class="wpicons__field__label" for="wpicons-default-stroke"><?php esc_html_e('Default stroke width', 'wpicons'); ?></label>
                        <span class="wpicons__field__hint"><?php esc_html_e('0.25–4 · step 0.25', 'wpicons'); ?></span>
                    </div>
                    <div class="wpicons__slider-row">
                        <input
                            type="range"
                            class="wpicons__range"
                            min="0.25"
                            max="4"
                            step="0.25"
                            value="<?= esc_attr((string) $default_stroke); ?>"
                            data-wpicons-range-for="wpicons-default-stroke"
                            aria-label="<?php esc_attr_e('Default stroke width', 'wpicons'); ?>"
                            style="--wpicons-range-fill: <?= esc_attr($range_fill($default_stroke, 0.25, 4)); ?>"
                        >
                        <input id="wpicons-default-stroke" class="wpicons__number" name="<?= esc_attr($option_name); ?>[default_stroke]" type="number" min="0.25" max="4" step="0.25" value="<?= esc_attr((string) $default_stroke); ?>" aria-describedby="wpicons-default-stroke-help">
                    </div>
                    <p class="wpicons__help" id="wpicons-default-stroke-help"><?php esc_html_e('Used by stroke-based libraries such as Lucide.', 'wpicons'); ?></p>
                </div>
            </div>
        </section>

        <section class="wpicons__card" aria-labelledby="wpicons-libraries-title">
            <header class="wpicons__card__header">
                <h2 id="wpicons-libraries-title" class="wpicons__card__title"><?php esc_html_e('Icon libraries', 'wpicons'); ?></h2>
                <p class="wpicons__card__description"><?php esc_html_e('Icons are stored in the plugin. Choose CDN only if this plugin has not been updated and you need a newer catalog. CDN catalogs are cached locally and are never fetched during page views.', 'wpicons'); ?></p>
            </header>

            <div class="wpicons__settings__libraries">
                <?php foreach ($libraries as $library): ?>
                    <?php
                    $id = $library['id'];
                    $source = $library['source'];
                    $cdn_version = $library['cdn_version'];
                    $versions = $library['cdn_versions'];
                    $select_id = "{$option_name}_{$id}_cdn_version";
                    $error_id = "{$select_id}_error";
                    $enabled_id = "{$option_name}_{$id}_enabled";
                    $prefix = "{$option_name}[libraries][{$id}]";
                    $field_error = $field_errors[$id] ?? '';
                    if (!\in_array($cdn_version, $versions, true) && $cdn_version !== 'latest' && $cdn_version !== '') {
                        $versions = [...[$cdn_version], ...$versions];
                    }
                    $status = 'none';
                    if (!empty($library['fetching'])) {
                        $status = 'fetching';
                    } elseif ($library['cached_version']) {
                        $status = 'cached';
                    }
                    ?>
                    <div class="wpicons__settings__library" data-library="<?= esc_attr($id); ?>">
                        <div class="wpicons__settings__library__header">
                            <span class="wpicons__settings__library__icon">
                                <?php echo $icon($id === 'lucide' ? 'pen-tool' : 'shapes', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                            </span>
                            <div class="wpicons__settings__library__heading">
                                <h3 class="wpicons__settings__library__name"><?= esc_html($library['label']); ?></h3>
                                <p class="wpicons__settings__library__meta">
                                    <?= esc_html(sprintf(
                                        /* translators: 1: npm package, 2: bundled version */
                                        __('%1$s · bundled %2$s', 'wpicons'),
                                        $library['package'],
                                        $library['bundled_version']
                                    )); ?>
                                </p>
                            </div>
                        </div>

                        <div class="wpicons__settings__library__enabled">
                            <p class="wpicons__help" id="<?= esc_attr($enabled_id); ?>_desc"><?php esc_html_e('Show this library in the picker and allow rendering its icons.', 'wpicons'); ?></p>
                            <label class="wpicons__toggle">
                                <span class="wpicons__toggle__label wpicons__toggle__label--strong"><?php esc_html_e('Enabled', 'wpicons'); ?></span>
                                <input type="checkbox" class="wpicons__toggle__input" id="<?= esc_attr($enabled_id); ?>" name="<?= esc_attr($prefix); ?>[enabled]" value="1" aria-describedby="<?= esc_attr($enabled_id); ?>_desc" <?php checked($library['enabled']); ?>>
                                <span class="wpicons__toggle__track" aria-hidden="true"></span>
                            </label>
                        </div>

                        <hr class="wpicons__divider">

                        <fieldset class="wpicons__field">
                            <legend class="wpicons__field__label wpicons__field__label--sm"><?php esc_html_e('Icon source', 'wpicons'); ?></legend>
                            <div class="wpicons__segmented">
                                <label class="wpicons__choice">
                                    <input type="radio" class="wpicons__choice__input" name="<?= esc_attr($prefix); ?>[source]" value="plugin" <?php checked($source, 'plugin'); ?>>
                                    <span class="wpicons__segment"><?php esc_html_e('Plugin (bundled)', 'wpicons'); ?></span>
                                </label>
                                <label class="wpicons__choice">
                                    <input type="radio" class="wpicons__choice__input" name="<?= esc_attr($prefix); ?>[source]" value="cdn" <?php checked($source, 'cdn'); ?>>
                                    <span class="wpicons__segment"><?php esc_html_e('CDN (jsDelivr / npm, cached)', 'wpicons'); ?></span>
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

                        <div class="wpicons__field wpicons__field--tight">
                            <div class="wpicons__settings__version-row">
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
                                            <option value="latest" <?php selected($cdn_version, 'latest'); ?>>
                                                <?php esc_html_e('latest', 'wpicons'); ?>
                                            </option>
                                            <?php foreach ($versions as $version): ?>
                                                <option value="<?= esc_attr($version); ?>" <?php selected($cdn_version, $version); ?>>
                                                    <?= esc_html($version); ?>
                                                </option>
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
                                <?php endif; ?>
                            </div>
                            <?php if ($versions === []): ?>
                                <p class="wpicons__help"><?php esc_html_e('Could not load versions from npm. Enter “latest” or a semver such as 0.468.0.', 'wpicons'); ?></p>
                            <?php endif; ?>
                            <?php if ($field_error !== ''): ?>
                                <p class="wpicons__field-error" id="<?= esc_attr($error_id); ?>">
                                    <?php echo $icon('circle-alert', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                                    <span><?= esc_html($field_error); ?></span>
                                </p>
                            <?php endif; ?>
                        </div>

                        <p class="wpicons__status wpicons__status--<?= esc_attr($status); ?>">
                            <span class="wpicons__status__dot" aria-hidden="true"></span>
                            <span>
                                <?php if ($status === 'fetching'): ?>
                                    <?= esc_html(sprintf(
                                        /* translators: 1: npm package, 2: requested version */
                                        __('Fetching %1$s@%2$s in the background. The bundled catalog is used until it finishes.', 'wpicons'),
                                        $library['package'],
                                        $cdn_version
                                    )); ?>
                                <?php elseif ($status === 'cached'): ?>
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
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="wpicons__savebar" data-wpicons-savebar>
            <div class="wpicons__savebar__inner">
                <p class="wpicons__savebar__message" data-wpicons-savebar-clean>
                    <?php echo $icon('info', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
                    <span><?php esc_html_e('Changes apply to new icons. Existing blocks keep their own settings.', 'wpicons'); ?></span>
                </p>
                <p class="wpicons__savebar__message wpicons__savebar__message--dirty" data-wpicons-savebar-dirty hidden>
                    <?php esc_html_e('You have unsaved changes', 'wpicons'); ?>
                </p>
                <span class="wpicons__savebar__spacer"></span>
                <button type="reset" class="wpicons__btn wpicons__btn--secondary"><?php esc_html_e('Cancel', 'wpicons'); ?></button>
                <button type="submit" name="submit" class="wpicons__btn wpicons__btn--primary"><?php esc_html_e('Save changes', 'wpicons'); ?></button>
            </div>
        </div>
    </form>
    <script>
        (function () {
            var form = document.querySelector('[data-wpicons-settings-form]');
            if (!form) return;

            function syncSources() {
                form.querySelectorAll('.wpicons__settings__library').forEach(function (root) {
                    var radios = root.querySelectorAll('input[type="radio"][name*="[source]"]');
                    var version = root.querySelector('.wpicons__cdn-version');
                    if (!version || !radios.length) return;
                    var cdn = Array.prototype.some.call(radios, function (radio) {
                        return radio.checked && radio.value === 'cdn';
                    });
                    version.disabled = !cdn;
                });
            }

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

            var colorRoot = form.querySelector('[data-wpicons-color]');
            var inherit = colorRoot && colorRoot.querySelector('[data-wpicons-color-inherit]');
            var custom = colorRoot && colorRoot.querySelector('[data-wpicons-color-custom]');
            var picker = colorRoot && colorRoot.querySelector('[data-wpicons-color-picker]');
            var text = colorRoot && colorRoot.querySelector('[data-wpicons-color-value]');
            var lastCustom = text && text.value.toLowerCase() !== 'currentcolor' ? text.value : (picker ? picker.value : '#000000');
            var hex = /^#[0-9a-f]{6}$/i;

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

            var savebar = form.querySelector('[data-wpicons-savebar]');
            var clean = savebar && savebar.querySelector('[data-wpicons-savebar-clean]');
            var dirty = savebar && savebar.querySelector('[data-wpicons-savebar-dirty]');
            function serialize() {
                return new URLSearchParams(new FormData(form)).toString();
            }
            var initial = serialize();
            function syncDirty() {
                if (!savebar) return;
                var isDirty = serialize() !== initial;
                savebar.classList.toggle('is-dirty', isDirty);
                if (clean) clean.hidden = isDirty;
                if (dirty) dirty.hidden = !isDirty;
            }

            form.addEventListener('change', function () {
                syncSources();
                syncDirty();
            });
            form.addEventListener('input', syncDirty);
            form.addEventListener('reset', function () {
                window.setTimeout(function () {
                    syncSources();
                    syncRanges();
                    syncColor();
                    syncDirty();
                }, 0);
            });

            document.querySelectorAll('[data-wpicons-dismiss]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var notice = button.closest('.wpicons__notice');
                    if (notice) notice.remove();
                });
            });

            syncSources();
            syncRanges();
            syncColor();
            syncDirty();
        })();
    </script>
</div>
