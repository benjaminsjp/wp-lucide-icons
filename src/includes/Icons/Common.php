<?php

namespace WPIcons\Icons;

if (!\defined('ABSPATH')) {
    exit;
}

class Common
{
    /**
     * Accessibility attributes for an icon.
     *
     * A title exposes the icon as an image with that name. Anything else is decorative:
     * hidden from assistive tech and not focusable. Interactive icons should stay decorative
     * and the control around them should carry the name.
     *
     * @since 2.0.0
     */
    public static function hidden(string|bool $hiddenOrTitle, bool $do_echo = false): string
    {
        if (\is_string($hiddenOrTitle) && trim($hiddenOrTitle) !== '') {
            $result = 'role="img" aria-label="' . esc_attr($hiddenOrTitle) . '"';
        } else {
            $result = 'aria-hidden="true" focusable="false"';
        }

        if ($do_echo) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes are escaped above.
            echo $result;
        }

        return $result;
    }
    public static function title(string|bool $text, bool $do_echo = false): string
    {
        if ($text === true || $text === false) {
            return '';
        }

        $clean_text = esc_html($text);
        $out = <<<HTML
        <title>$clean_text</title>
        HTML;

        if ($do_echo) {
            echo $out;
        }

        return $out;
    }
}