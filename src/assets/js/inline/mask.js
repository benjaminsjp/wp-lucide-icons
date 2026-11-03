(function () {
    var TRANSPARENT_SRC =
        'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    var applied = typeof WeakMap === 'function' ? new WeakMap() : null;

    function signature(node, src) {
        return [
            src,
            node.getAttribute('data-color') || '',
            node.getAttribute('data-size') || '',
        ].join('|');
    }

    function applyPresentation(node) {
        var color = node.getAttribute('data-color') || '';
        if (color.toLowerCase() === 'brand') {
            // Brand color (Simple Icons) is already in the inline style.
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
            attributeFilter: ['src', 'data-src', 'data-color', 'data-size', 'class', 'style'],
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
