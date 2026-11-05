(function () {
    var extraElements = 'img[class|src|alt|title|width|height|style|data-src|data-library|data-name|data-color|data-hex|data-stroke|data-size|data-title|data-variant],span[class|style|data-src|data-library|data-name|data-color|data-hex|data-stroke|data-size|data-title|data-variant]';

    function hasToken(list, token) {
        return String(list)
            .split(/[\s,]+/)
            .indexOf(token) !== -1;
    }

    function appendToken(list, token) {
        var value = String(list || '');
        if (!value) {
            return token;
        }
        if (hasToken(value, token)) {
            return value;
        }
        var separator = value.indexOf(',') !== -1 ? ',' : ' ';
        return value + separator + token;
    }

    function referenceEditor() {
        var init = window.tinyMCEPreInit && tinyMCEPreInit.mceInit;
        if (!init) {
            return null;
        }
        var keys = Object.keys(init);
        var i;
        for (i = 0; i < keys.length; i++) {
            var editor = init[keys[i]];
            if (editor && editor.external_plugins && editor.external_plugins.wpicons) {
                return editor;
            }
        }
        return null;
    }

    function pluginUrl() {
        var reference = referenceEditor();
        if (reference) {
            return reference.external_plugins.wpicons;
        }
        return window.WPIconsElementor && WPIconsElementor.pluginUrl ? WPIconsElementor.pluginUrl : '';
    }

    function cssUrl() {
        var reference = referenceEditor();
        var css = reference && reference.content_css;
        var list = Array.isArray(css) ? css.join(',') : String(css || '');
        var parts = list.split(',');
        var i;
        for (i = 0; i < parts.length; i++) {
            if (parts[i].indexOf('wpicons.css') !== -1) {
                return parts[i];
            }
        }
        return window.WPIconsElementor && WPIconsElementor.cssUrl ? WPIconsElementor.cssUrl : '';
    }

    function appendList(value, token) {
        if (Object.prototype.toString.call(value) === '[object Array]') {
            if (value.indexOf(token) === -1) {
                value.push(token);
            }
            return value;
        }
        return appendToken(value, token);
    }

    function appendIfList(settings, key) {
        var value = settings[key];
        if (typeof value !== 'string' && Object.prototype.toString.call(value) !== '[object Array]') {
            return;
        }
        settings[key] = appendList(value, 'wpicons');
    }

    function appendStylesheet(value, url) {
        if (!url) {
            return value;
        }
        if (Object.prototype.toString.call(value) === '[object Array]') {
            if (value.join(',').indexOf('wpicons.css') === -1) {
                value.push(url);
            }
            return value;
        }
        var current = typeof value === 'string' ? value : '';
        if (current.indexOf('wpicons.css') !== -1) {
            return current;
        }
        return current ? current + ',' + url : url;
    }

    function allowMaskStyle(styles) {
        var properties = ['--wpicons-src', 'background-color', 'width', 'height'];
        if (typeof styles !== 'string' || styles.indexOf('{') === -1) {
            return styles;
        }
        var match = styles.match(/"\*"\s*:\s*"([^"]*)"/);
        if (match) {
            var existing = match[1] === '' ? [] : match[1].split(',');
            var i;
            for (i = 0; i < existing.length; i++) {
                existing[i] = existing[i].replace(/^\s+|\s+$/g, '');
            }
            for (i = 0; i < properties.length; i++) {
                if (existing.indexOf(properties[i]) === -1) {
                    existing.push(properties[i]);
                }
            }
            return styles.replace(match[0], '"*":"' + existing.join(',') + '"');
        }
        return styles.replace(/\}\s*$/, '') + ',"*":"' + properties.join(',') + '"}';
    }

    function ensureElements(settings) {
        if (typeof settings.extended_valid_elements !== 'string') {
            settings.extended_valid_elements = extraElements;
            return;
        }
        if (settings.extended_valid_elements.indexOf('data-library') === -1) {
            settings.extended_valid_elements += ',' + extraElements;
        }
    }

    function preloadPlugin() {
        var url = pluginUrl();
        if (!url || preloadPlugin.done || !window.tinymce || !tinymce.PluginManager || typeof tinymce.PluginManager.load !== 'function') {
            return;
        }
        preloadPlugin.done = true;
        tinymce.PluginManager.load('wpicons', url);
    }

    // The final init, after WordPress or Elementor has merged defaults.
    function applySharedInit(settings) {
        if (!settings || typeof settings !== 'object') {
            return settings;
        }

        appendIfList(settings, 'plugins');
        if (typeof settings.plugins !== 'string' && Object.prototype.toString.call(settings.plugins) !== '[object Array]') {
            var reference = referenceEditor();
            if (reference && typeof reference.plugins === 'string') {
                settings.plugins = appendToken(reference.plugins, 'wpicons');
            }
        }
        appendIfList(settings, 'toolbar1');
        appendIfList(settings, 'toolbar');
        ensureElements(settings);
        settings.allow_html_data_urls = true;
        if (typeof settings.valid_styles === 'string') {
            settings.valid_styles = allowMaskStyle(settings.valid_styles);
        }
        if (typeof settings.content_css === 'string' || Object.prototype.toString.call(settings.content_css) === '[object Array]') {
            settings.content_css = appendStylesheet(settings.content_css, cssUrl());
        } else if (!settings.content_css && cssUrl()) {
            settings.content_css = cssUrl();
        }

        var url = pluginUrl();
        if (url) {
            if (!settings.external_plugins || typeof settings.external_plugins !== 'object') {
                settings.external_plugins = {};
            }
            if (!settings.external_plugins.wpicons) {
                settings.external_plugins.wpicons = url;
            }
        }
        preloadPlugin();

        return settings;
    }

    // Incoming wp.editor settings are merged over the defaults. Only fill keys
    // Elementor already set, so a new external_plugins map does not replace the others.
    function patchTinymce(settings) {
        if (!settings || !settings.tinymce || typeof settings.tinymce !== 'object') {
            return settings;
        }

        var tinymceSettings = settings.tinymce;
        appendIfList(tinymceSettings, 'plugins');
        appendIfList(tinymceSettings, 'toolbar1');
        appendIfList(tinymceSettings, 'toolbar');
        if (typeof tinymceSettings.extended_valid_elements === 'string' && tinymceSettings.extended_valid_elements.indexOf('data-library') === -1) {
            tinymceSettings.extended_valid_elements += ',' + extraElements;
        }
        tinymceSettings.allow_html_data_urls = true;
        if (typeof tinymceSettings.valid_styles === 'string') {
            tinymceSettings.valid_styles = allowMaskStyle(tinymceSettings.valid_styles);
        }
        if (typeof tinymceSettings.content_css === 'string' || Object.prototype.toString.call(tinymceSettings.content_css) === '[object Array]') {
            tinymceSettings.content_css = appendStylesheet(tinymceSettings.content_css, cssUrl());
        }
        if (tinymceSettings.external_plugins && typeof tinymceSettings.external_plugins === 'object' && !tinymceSettings.external_plugins.wpicons && pluginUrl()) {
            tinymceSettings.external_plugins.wpicons = pluginUrl();
        }

        return settings;
    }

    function installEditorPatch() {
        if (!window.wp || !wp.editor || typeof wp.editor.initialize !== 'function' || wp.editor.initialize.__wpicons) {
            return;
        }

        var original = wp.editor.initialize;
        var wrapped = function (id, settings) {
            return original.call(this, id, patchTinymce(settings || {}));
        };
        wrapped.__wpicons = true;
        wp.editor.initialize = wrapped;
    }

    function installTinymcePatch() {
        preloadPlugin();
        if (!window.tinymce || typeof tinymce.init !== 'function' || tinymce.init.__wpicons) {
            return;
        }

        var original = tinymce.init;
        var wrapped = function (settings) {
            return original.call(this, applySharedInit(settings));
        };
        wrapped.__wpicons = true;
        tinymce.init = wrapped;
    }

    function iconValue(control) {
        var current = control.getControlValue() || {};
        if (typeof current === 'string') {
            try {
                current = JSON.parse(current);
            } catch (error) {
                current = {};
            }
        }
        return current && typeof current === 'object' ? current : {};
    }

    function registerControl() {
        if (!window.elementor || !elementor.modules || !elementor.modules.controls || !elementor.modules.controls.BaseData || typeof elementor.addControlView !== 'function') {
            return;
        }
        if (registerControl.done) {
            return;
        }
        registerControl.done = true;

        var Control = elementor.modules.controls.BaseData.extend({
            onReady: function () {
                var self = this;
                this.$el.find('.wpicons-elementor-choose').off('click.wpicons').on('click.wpicons', function (event) {
                    event.preventDefault();
                    self.openPicker();
                });
            },
            openPicker: function () {
                if (!window.wpIcons || typeof window.wpIcons.openPicker !== 'function') {
                    return;
                }
                var current = iconValue(this);
                var self = this;
                window.wpIcons.openPicker({ value: current }).then(function (icon) {
                    if (!icon || !icon.name) {
                        return;
                    }
                    self.setValue({
                        library: icon.library || '',
                        name: icon.name || '',
                        variant: icon.variant || '',
                        size: icon.size || current.size || 24,
                        color: icon.color || '',
                        stroke: icon.stroke == null ? '' : icon.stroke,
                        title: icon.title || '',
                    });
                });
            },
        });

        elementor.addControlView('wpicons_icon', Control);
    }

    installEditorPatch();
    installTinymcePatch();
    registerControl();

    if (window.jQuery) {
        window.jQuery(window).on('elementor:init', function () {
            registerControl.done = false;
            installEditorPatch();
            installTinymcePatch();
            registerControl();
        });
    }

    var tries = 0;
    var timer = window.setInterval(function () {
        installEditorPatch();
        installTinymcePatch();
        registerControl();
        var editorPatched = window.wp && wp.editor && wp.editor.initialize && wp.editor.initialize.__wpicons;
        var tinymcePatched = window.tinymce && tinymce.init && tinymce.init.__wpicons;
        if ((editorPatched && tinymcePatched && registerControl.done) || ++tries > 40) {
            window.clearInterval(timer);
        }
    }, 250);
})();
