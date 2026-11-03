(function () {
    var TRANSPARENT_SRC =
        'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    var applied = typeof WeakMap === 'function' ? new WeakMap() : null;

    function signature(node, src) {
        return [
            src,
            node.getAttribute('data-color') || '',
            node.getAttribute('data-hex') || '',
            node.getAttribute('data-size') || '',
        ].join('|');
    }

    function stripBrandBackground(node) {
        node.style.removeProperty('background-color');
        var style = node.getAttribute('style') || '';
        if (!/(?:^|;)\s*background-color\s*:\s*brand\s*(?:;|$)/i.test(style)) {
            return;
        }
        var next = style
            .replace(/(?:^|;)\s*background-color\s*:\s*brand\s*(?=;|$)/ig, '')
            .replace(/;{2,}/g, ';')
            .replace(/^;+|;+$/g, '');
        if (next) {
            node.setAttribute('style', next);
        } else {
            node.removeAttribute('style');
        }
    }

    var brandHex = {};
    var brandWait = {};

    function plainHex(value) {
        var hex = String(value || '').trim();
        return /^[0-9a-f]{6}$/i.test(hex) ? hex.toLowerCase() : '';
    }

    function cssIdent(value) {
        return /^[a-z0-9_-]{1,80}$/i.test(value) ? value : '';
    }

    function paintBrandSpans(library, name, hex) {
        var nodes = document.querySelectorAll('span.wpicons-inline');
        var i;
        for (i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if ((node.getAttribute('data-color') || '').toLowerCase() !== 'brand') {
                continue;
            }
            if ((node.getAttribute('data-library') || '').toLowerCase() !== library.toLowerCase()) {
                continue;
            }
            if ((node.getAttribute('data-name') || '').toLowerCase() !== name.toLowerCase()) {
                continue;
            }
            if (node.getAttribute('data-hex') !== hex) {
                node.setAttribute('data-hex', hex);
            }
            node.style.setProperty('background-color', '#' + hex);
        }
    }

    function lookupBrand(node) {
        var library = cssIdent(node.getAttribute('data-library') || '');
        var name = cssIdent(node.getAttribute('data-name') || '');
        if (!library || !name) {
            stripBrandBackground(node);
            return;
        }
        var key = library.toLowerCase() + ':' + name.toLowerCase();
        if (Object.prototype.hasOwnProperty.call(brandHex, key)) {
            if (brandHex[key]) {
                paintBrandSpans(library, name, brandHex[key]);
            } else {
                stripBrandBackground(node);
            }
            return;
        }
        var admin = window.WPIconsAdmin;
        if (!admin || !admin.restUrl || typeof window.fetch !== 'function' || brandWait[key]) {
            return;
        }
        brandWait[key] = true;
        var url = String(admin.restUrl).replace(/\/?$/, '/') + 'icon?library='
            + encodeURIComponent(library) + '&name=' + encodeURIComponent(name);
        window.fetch(url, {
            credentials: 'same-origin',
            headers: admin.nonce ? { 'X-WP-Nonce': admin.nonce } : {},
        }).then(function (response) {
            return response.ok ? response.json() : null;
        }).then(function (data) {
            var hex = plainHex(data && data.meta && data.meta.hex);
            brandHex[key] = hex;
            if (hex) {
                paintBrandSpans(library, name, hex);
            } else {
                stripBrandBackground(node);
            }
        }).catch(function () {
            brandHex[key] = '';
            stripBrandBackground(node);
        });
    }

    function applyPresentation(node) {
        var color = node.getAttribute('data-color') || '';
        if (color.toLowerCase() === 'brand') {
            if (node.tagName === 'SPAN') {
                var hex = plainHex(node.getAttribute('data-hex'));
                if (hex) {
                    node.style.setProperty('background-color', '#' + hex);
                } else {
                    lookupBrand(node);
                }
            }
        } else if (color && color.toLowerCase() !== 'currentcolor') {
            node.style.setProperty('background-color', color);
        } else {
            node.style.removeProperty('background-color');
        }

        var size = node.getAttribute('data-size') || '';
        if (/^\d+$/.test(size)) {
            node.style.setProperty('width', size + 'px');
            node.style.setProperty('height', size + 'px');
        } else {
            node.style.removeProperty('width');
            node.style.removeProperty('height');
        }
    }

    function alreadyApplied(node, src) {
        if (!applied) {
            return false;
        }
        return applied.get(node) === signature(node, src);
    }

    function markApplied(node, src) {
        if (applied) {
            applied.set(node, signature(node, src));
        }
    }

    function svgUrl(node) {
        var dataSrc = node.getAttribute('data-src') || '';
        var src = node.getAttribute('src') || '';
        if (dataSrc.indexOf('data:image/svg+xml') === 0) {
            return dataSrc;
        }
        if (src.indexOf('data:image/svg+xml') === 0) {
            return src;
        }
        return '';
    }

    function maskSpan(node) {
        var src = node.getAttribute('data-src');
        if (!src) {
            return;
        }
        if (alreadyApplied(node, src)) {
            return;
        }
        markApplied(node, src);
        node.style.setProperty('--wpicons-src', 'url("' + src.replace(/"/g, '') + '")');
        applyPresentation(node);
    }

    function maskImage(node) {
        var src = svgUrl(node);
        if (!src) {
            return;
        }
        var visible = node.getAttribute('src') || '';
        if (alreadyApplied(node, src) && visible === TRANSPARENT_SRC) {
            return;
        }
        markApplied(node, src);
        node.setAttribute('data-src', src);
        node.style.setProperty('--wpicons-src', 'url("' + src.replace(/"/g, '') + '")');
        applyPresentation(node);
        if (visible !== TRANSPARENT_SRC) {
            node.setAttribute('src', TRANSPARENT_SRC);
        }
    }

    function apply(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var spans = scope.querySelectorAll('span.wpicons-inline[data-src]');
        var images = scope.querySelectorAll('img.wpicons-inline');
        var i;
        for (i = 0; i < spans.length; i++) {
            maskSpan(spans[i]);
        }
        for (i = 0; i < images.length; i++) {
            maskImage(images[i]);
        }
    }

    function boot() {
        apply(document);
        if (!window.MutationObserver || !document.documentElement) {
            return;
        }

        var scheduled = false;
        var observer = new MutationObserver(function () {
            if (scheduled) {
                return;
            }
            scheduled = true;
            window.requestAnimationFrame(function () {
                scheduled = false;
                apply(document);
            });
        });
        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['src', 'data-src', 'data-color', 'data-hex', 'data-size', 'class', 'style'],
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
