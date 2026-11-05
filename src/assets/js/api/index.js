import { createRoot, render, unmountComponentAtNode, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import IconPickerModal from '../inline/IconPickerModal';

const svgCache = new Map();
const inflight = new Map();

const EMPTY_REF = { library: '', name: '', variant: '' };

function pluginDefaults() {
    const defaults = window.WPIconsAdmin && window.WPIconsAdmin.defaults;
    return defaults && typeof defaults === 'object' ? defaults : {};
}

/**
 * Splits 'library:name[:variant]' or a bare icon name.
 * An invalid reference comes back with empty strings.
 */
export function parseRef(ref) {
    const value = String(ref || '').trim().toLowerCase();
    if (!value) {
        return { ...EMPTY_REF };
    }

    const parts = value.split(':');
    if (parts.length === 1 && /^[a-z0-9_-]+$/.test(parts[0])) {
        return { library: '', name: parts[0], variant: '' };
    }

    const variantOk = parts.length === 2 || (parts.length === 3 && /^[a-z0-9-]{1,20}$/.test(parts[2]));
    if (
        (parts.length === 2 || parts.length === 3)
        && /^[a-z0-9-]+$/.test(parts[0])
        && /^[a-z0-9_-]+$/.test(parts[1])
        && variantOk
    ) {
        return { library: parts[0], name: parts[1], variant: parts[2] || '' };
    }

    return { ...EMPTY_REF };
}

/**
 * SVG markup from the REST icon route. Results are cached for the page, and identical
 * requests in flight share one call. Resolves to '' when the icon cannot be rendered.
 */
export function getIcon(ref, args = {}) {
    const parsed = parseRef(ref);
    if (!parsed.name) {
        return Promise.resolve('');
    }

    const defaults = pluginDefaults();
    const library = parsed.library || defaults.library || 'lucide';
    const variant = args.variant ?? parsed.variant ?? '';
    const size = args.size ?? defaults.size ?? 24;
    const color = args.color || defaults.color || 'currentColor';
    const stroke = args.stroke ?? defaults.stroke ?? 2;
    const key = JSON.stringify({
        library,
        name: parsed.name,
        variant,
        size,
        color,
        stroke,
    });

    if (svgCache.has(key)) {
        return Promise.resolve(svgCache.get(key));
    }
    if (inflight.has(key)) {
        return inflight.get(key);
    }

    const params = new URLSearchParams({
        library,
        name: parsed.name,
        size: String(size),
        color: String(color),
        stroke: String(stroke),
    });
    if (variant) {
        params.set('variant', variant);
    }

    const promise = apiFetch({ path: `/wpicons/v1/icon?${params.toString()}` })
        .then((data) => {
            const svg = data && typeof data.svg === 'string' ? data.svg : '';
            svgCache.set(key, svg);
            return svg;
        })
        .catch(() => '')
        .finally(() => {
            inflight.delete(key);
        });

    inflight.set(key, promise);
    return promise;
}

function withTitle(svg, title) {
    if (!svg || !title || typeof DOMParser === 'undefined') {
        return svg;
    }

    const doc = new DOMParser().parseFromString(svg, 'image/svg+xml');
    const root = doc.querySelector('svg');
    if (!root || doc.querySelector('parsererror')) {
        return svg;
    }

    root.setAttribute('role', 'img');
    root.setAttribute('aria-label', title);
    root.removeAttribute('aria-hidden');
    root.removeAttribute('focusable');

    let titleEl = root.querySelector('title');
    if (!titleEl) {
        titleEl = doc.createElementNS('http://www.w3.org/2000/svg', 'title');
        root.insertBefore(titleEl, root.firstChild);
    }
    titleEl.textContent = title;

    return root.outerHTML;
}

/**
 * Renders one icon. `icon` is a 'library:name[:variant]' reference.
 */
export function Icon({
    icon,
    size,
    color,
    stroke,
    title,
    className,
}) {
    const [svg, setSvg] = useState('');

    useEffect(() => {
        let cancelled = false;
        getIcon(icon, { size, color, stroke }).then((markup) => {
            if (!cancelled) {
                setSvg(markup || '');
            }
        });

        return () => {
            cancelled = true;
        };
    }, [icon, size, color, stroke]);

    if (!svg) {
        return null;
    }

    return (
        <span
            className={className || undefined}
            dangerouslySetInnerHTML={{ __html: withTitle(svg, title) }}
        />
    );
}

let rootEl = null;
let root = null;
let pendingResolve = null;

function unmountPicker() {
    if (root) {
        root.render(null);
        return;
    }

    if (rootEl && typeof unmountComponentAtNode === 'function') {
        unmountComponentAtNode(rootEl);
    }
}

function mountPicker(app) {
    if (!rootEl) {
        rootEl = document.createElement('div');
        rootEl.id = 'wpicons-api-picker-root';
        document.body.appendChild(rootEl);
    }

    if (typeof createRoot === 'function') {
        if (!root) {
            root = createRoot(rootEl);
        }
        root.render(app);
        return;
    }

    render(app, rootEl);
}

function settlePicker(value) {
    const resolve = pendingResolve;
    pendingResolve = null;
    unmountPicker();
    if (resolve) {
        resolve(value);
    }
}

function initialValue(value) {
    if (typeof value === 'string') {
        return parseRef(value);
    }

    return value && typeof value === 'object' ? value : {};
}

/**
 * Opens the icon picker. Resolves with the selection, or null when the dialog is closed.
 * `libraries` limits which libraries can be browsed.
 */
export function openPicker({ value, libraries } = {}) {
    if (pendingResolve) {
        const previous = pendingResolve;
        pendingResolve = null;
        previous(null);
    }

    const initial = initialValue(value);

    return new Promise((resolve) => {
        pendingResolve = resolve;
        mountPicker(
            <IconPickerModal
                library={initial.library || ''}
                name={initial.name || ''}
                variant={initial.variant || ''}
                size={initial.size}
                color={initial.color}
                stroke={initial.stroke}
                title={initial.title || ''}
                allowedLibraries={Array.isArray(libraries) ? libraries : undefined}
                onClose={() => settlePicker(null)}
                onSelect={(icon) => {
                    settlePicker({
                        library: icon.library,
                        name: icon.name,
                        variant: icon.variant || '',
                        size: icon.size,
                        color: icon.color,
                        stroke: icon.stroke,
                        title: icon.title || '',
                    });
                }}
            />,
        );
    });
}

window.wpIcons = {
    getIcon,
    Icon,
    openPicker,
    parseRef,
};
