import { fetchIcon } from '../picker/api';

export const INLINE_CLASS = 'wpicons-inline';

export const TRANSPARENT_SRC =
    'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

export function pluginDefaults() {
    return (
        window.WPIconsAdmin?.defaults || {
            library: 'lucide',
            size: 24,
            color: 'currentColor',
            stroke: 2,
        }
    );
}

export function svgToDataUri(svg) {
    const bytes = new TextEncoder().encode(String(svg || '').trim());
    let binary = '';
    bytes.forEach((byte) => {
        binary += String.fromCharCode(byte);
    });

    return 'data:image/svg+xml;base64,' + window.btoa(binary);
}

export function isInheritColor(color) {
    const value = String(color ?? '').trim();
    return value === '' || value.toLowerCase() === 'currentcolor';
}

export function safeColor(color) {
    const value = String(color || '').trim();
    if (isInheritColor(value)) {
        return 'currentColor';
    }
    if (/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(value)) {
        return value;
    }
    if (/^(?:rgb|hsl)a?\([0-9.,%\s/]+\)$/i.test(value)) {
        return value.replace(/[;"\\]/g, '');
    }
    if (/^[a-z]{3,20}$/i.test(value)) {
        return value.toLowerCase();
    }
    const cssVar = value.match(/^var\(\s*(--[a-zA-Z0-9_-]+)\s*\)$/);
    if (cssVar) {
        return `var(${cssVar[1]})`;
    }
    return 'currentColor';
}

export function explicitSize(size) {
    const value = Number(size);
    if (!Number.isFinite(value) || value <= 0) {
        return '';
    }
    return String(Math.round(value));
}

export function iconMaskStyle(url) {
    return `--wpicons-src:url(${String(url).replace(/["'()\\]/g, '')})`;
}

export function iconPreviewStyle(url, color, size) {
    const parts = [iconMaskStyle(url)];
    const resolved = safeColor(color);
    if (resolved !== 'currentColor' && resolved !== 'brand') {
        parts.push(`background-color:${resolved}`);
    }
    const pixels = explicitSize(size);
    if (pixels) {
        parts.push(`width:${pixels}px`);
        parts.push(`height:${pixels}px`);
    }
    return parts.join(';');
}

function previousPreviewColor(style) {
    const match = String(style || '').match(/background-color:\s*([^;]+)/i);
    return match ? match[1].trim() : 'currentColor';
}

export function placeholderAttributes({
    library,
    name,
    svg,
    url,
    color,
    stroke,
    size,
    title,
    variant = '',
    hex = '',
    keepTypedColor = false,
    previousStyle = '',
}) {
    const defaults = pluginDefaults();
    const resolvedUrl = url || svgToDataUri(svg);
    const resolvedSize = explicitSize(size);
    const resolvedTitle = String(title || '').replace(/[\r\n]+/g, ' ').trim();
    const typed = String(color ?? '').trim();
    let resolvedColor = safeColor(color || defaults.color || 'currentColor');
    let paint = resolvedColor;
    if (keepTypedColor) {
        resolvedColor = isInheritColor(typed) ? 'currentColor' : typed;
        const safe = safeColor(typed);
        paint = !isInheritColor(typed) && safe === 'currentColor'
            ? previousPreviewColor(previousStyle)
            : safe;
    }
    // Simple Icons "brand" color: stored as "brand" (rendered with the official color on the
    // front end), previewed in the editor with the brand hex.
    if (String(color ?? '').trim().toLowerCase() === 'brand') {
        resolvedColor = 'brand';
        paint = /^[0-9a-f]{6}$/i.test(String(hex || '')) ? `#${hex}` : previousPreviewColor(previousStyle);
    }
    const resolvedVariant = /^[a-z0-9-]{1,20}$/.test(String(variant || '')) ? String(variant) : '';

    const attributes = {
        url: resolvedUrl,
        src: TRANSPARENT_SRC,
        alt: resolvedTitle,
        iconStyle: iconPreviewStyle(resolvedUrl, paint, resolvedSize),
        library: String(library || defaults.library || 'lucide'),
        iconName: String(name || ''),
        color: resolvedColor,
        stroke: String(stroke ?? defaults.stroke ?? 2),
        size: resolvedSize,
        title: resolvedTitle,
    };
    if (resolvedVariant) {
        attributes.variant = resolvedVariant;
    }

    return attributes;
}

export function placeholderHtml(icon) {
    const attrs = placeholderAttributes(icon);
    const sizeStyle = attrs.size ? `width:${attrs.size}px;height:${attrs.size}px` : '';

    return (
        `<img class="${INLINE_CLASS}"` +
        ` src="${escapeAttr(attrs.url)}"` +
        ` data-src="${escapeAttr(attrs.url)}"` +
        (sizeStyle ? ` style="${escapeAttr(sizeStyle)}"` : '') +
        ` data-library="${escapeAttr(attrs.library)}"` +
        ` data-name="${escapeAttr(attrs.iconName)}"` +
        ` data-color="${escapeAttr(attrs.color)}"` +
        ` data-stroke="${escapeAttr(attrs.stroke)}"` +
        optionalAttr('data-size', attrs.size) +
        optionalAttr('data-title', attrs.title) +
        optionalAttr('data-variant', attrs.variant) +
        ` alt="${escapeAttr(attrs.alt)}" />`
    );
}

export async function resolveMaskSvg({ library, name, svg, stroke, variant = '' }) {
    const strokeValue = Number(stroke ?? 2);
    if (svg && strokeValue === 2) {
        return svg;
    }

    try {
        const data = await fetchIcon({
            library,
            name,
            size: 24,
            color: 'currentColor',
            stroke: strokeValue,
            variant,
        });
        return data.svg || svg || '';
    } catch (error) {
        return svg || '';
    }
}

export function migrateInlineIconHtml(html) {
    if (!html || !html.includes('wpicons-inline') || !html.includes('<span')) {
        return html;
    }

    return html.replace(/<span\b(?=[^>]*\bwpicons-inline\b)[^>]*>[\s\S]*?<\/span>/gi, (full) => {
        const openEnd = full.indexOf('>');
        const open = full.slice(0, openEnd + 1);
        const inner = full.slice(openEnd + 1, full.length - '</span>'.length);
        const url = iconUrl(open);
        const iconName = readAttr(open, 'data-name');
        if (!url || !iconName) {
            return full;
        }

        const defaults = pluginDefaults();
        const color = readAttr(open, 'data-color') || defaults.color || 'currentColor';
        const size = readAttr(open, 'data-size');
        const title = readAttr(open, 'data-title');

        return (
            `<img class="${INLINE_CLASS}"` +
            ` src="${escapeAttr(TRANSPARENT_SRC)}"` +
            ` data-src="${escapeAttr(url)}"` +
            ` style="${escapeAttr(iconPreviewStyle(url, color, size))}"` +
            ` data-library="${escapeAttr(readAttr(open, 'data-library') || defaults.library || 'lucide')}"` +
            ` data-name="${escapeAttr(iconName)}"` +
            ` data-color="${escapeAttr(safeColor(color))}"` +
            ` data-stroke="${escapeAttr(readAttr(open, 'data-stroke') || String(defaults.stroke ?? 2))}"` +
            optionalAttr('data-size', explicitSize(size)) +
            optionalAttr('data-title', title) +
            optionalAttr('data-variant', readAttr(open, 'data-variant')) +
            ` alt="${escapeAttr(title)}" />` +
            inner
        );
    });
}

function iconUrl(tag) {
    const src = readAttr(tag, 'data-src') || readAttr(tag, 'src');
    if (src && src.startsWith('data:image/svg+xml')) {
        return src;
    }

    const style = readAttr(tag, 'style');
    const match = style.match(/url\((?:&quot;|"|')?(data:image\/svg\+xml[^"')]+)/i);
    return match ? match[1] : src;
}

function readAttr(tag, name) {
    const match = tag.match(new RegExp(`\\s${name}\\s*=\\s*("|')([\\s\\S]*?)\\1`, 'i'));
    return match ? match[2] : '';
}

function optionalAttr(name, value) {
    if (value === undefined || value === null || value === '') {
        return '';
    }
    return ` ${name}="${escapeAttr(value)}"`;
}

function escapeAttr(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}
