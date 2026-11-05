(function () {
    var label = (window.WPIconsFlatsome && WPIconsFlatsome.choose) || 'Choose icon';

    function optionName(option) {
        if (!option) {
            return '';
        }
        return option.$orgName || option.$name || option.name || '';
    }

    function isolate(node) {
        if (!window.angular || !node) {
            return null;
        }
        return window.angular.element(node).isolateScope() || null;
    }

    function isIconPanel(node) {
        var scope = isolate(node);
        var shortcode = scope && scope.$ctrl && scope.$ctrl.shortcode;
        if (!shortcode) {
            return false;
        }
        var tag = shortcode.tag || (shortcode.data && shortcode.data.tag) || '';
        return tag === 'wp_icon';
    }

    function panels() {
        var nodes = document.querySelectorAll('ux-options');
        var found = [];
        var i;
        for (i = 0; i < nodes.length; i++) {
        if (!isIconPanel(nodes[i])) {
            continue;
        }
        if (nodes[i].parentElement && nodes[i].parentElement.closest('ux-options')) {
            continue;
        }
        found.push(nodes[i]);
        }
        return found;
    }

    function modelObject(panel) {
        var scope = isolate(panel);
        var model = scope && scope.$ctrl && scope.$ctrl.model;
        return model && typeof model === 'object' ? model : null;
    }

    function readModel(panel, name) {
        var model = modelObject(panel);
        if (model && model[name] != null && model[name] !== '') {
            return model[name];
        }

        var nodes = panel.querySelectorAll('ux-option');
        var i;
        for (i = 0; i < nodes.length; i++) {
            var scope = isolate(nodes[i]);
            if (!scope || !scope.$ctrl || optionName(scope.$ctrl.option) !== name) {
                continue;
            }
            return scope.$ctrl.model;
        }
        return '';
    }

    function writeOptions(panel, values) {
        var scope = isolate(panel);
        var model = modelObject(panel);
        if (!scope || !model) {
            return;
        }

        scope.$apply(function () {
            Object.keys(values).forEach(function (name) {
                var next = values[name];
                if (typeof model[name] === 'number') {
                    var numeric = Number(next);
                    next = Number.isFinite(numeric) ? numeric : model[name];
                }
                model[name] = next;
            });
        });
    }

    function openPicker(panel) {
        if (!window.wpIcons || typeof window.wpIcons.openPicker !== 'function') {
            return;
        }
        var size = readModel(panel, 'size');
        var stroke = readModel(panel, 'stroke');
        window.wpIcons.openPicker({
            value: {
                library: readModel(panel, 'library') || '',
                name: readModel(panel, 'name') || '',
                variant: readModel(panel, 'variant') || '',
                color: readModel(panel, 'color') || '',
                stroke: stroke === '' || stroke == null ? undefined : stroke,
                size: size === '' || size == null ? undefined : Number(size),
                title: readModel(panel, 'title') || '',
            },
        }).then(function (icon) {
            if (!icon || !icon.name) {
                return;
            }
            writeOptions(panel, {
                library: icon.library || '',
                name: icon.name || '',
                variant: icon.variant || '',
                size: icon.size ? String(icon.size) : '',
                color: icon.color || '',
                stroke: icon.stroke == null ? '' : String(icon.stroke),
                title: icon.title || '',
            });
        });
    }

    function ensureButton(panel) {
        if (panel.querySelector('.wpicons-ux-choose')) {
            return;
        }
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'button button-primary wpicons-ux-choose';
        button.textContent = label;
        button.addEventListener('click', function (event) {
            event.preventDefault();
            openPicker(panel);
        });
        panel.insertBefore(button, panel.firstChild);
    }

    var scheduled = false;
    function scan() {
        panels().forEach(ensureButton);
    }

    function schedule() {
        if (scheduled) {
            return;
        }
        scheduled = true;
        window.requestAnimationFrame(function () {
            scheduled = false;
            scan();
        });
    }

    if (window.MutationObserver && document.documentElement) {
        var observer = new MutationObserver(schedule);
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
    schedule();
})();
