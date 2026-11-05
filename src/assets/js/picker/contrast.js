/**
 * Contrast helpers for admin icon previews (picker tiles, preview panels, swatches).
 *
 * Brand colors (Simple Icons) and custom colors can be almost invisible on the current theme's
 * background, e.g. GitHub #181717 on the dark picker. These helpers measure the background that is
 * actually rendered (theme tokens resolved by the browser, alpha layers composited), compute the WCAG
 * contrast ratio, and suggest a backdrop chip when it is below 3:1 (WCAG 1.4.11, non-text graphics).
 *
 * UI previews only: front-end output never uses this.
 */
import { useEffect, useLayoutEffect, useState } from '@wordpress/element';

/** WCAG 2.x minimum contrast for graphical objects (1.4.11). */
export const MIN_GRAPHIC_CONTRAST = 3;

// Fixed, theme-independent chip colors, so the icon always sits on a known surface.
const CHIP = {
    light: [245, 245, 247],
    dark: [22, 20, 29],
};

const luminanceCache = new Map();
const colorCache = new Map();
let canvasContext = null;

function channel(value) {
    const c = value / 255;
    return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
}

/** WCAG relative luminance of an [r, g, b] color (0..255). */
export function relativeLuminance(rgb) {
    const key = rgb.join(',');
    if (luminanceCache.has(key)) {
        return luminanceCache.get(key);
    }
    const value = (0.2126 * channel(rgb[0])) + (0.7152 * channel(rgb[1])) + (0.0722 * channel(rgb[2]));
    luminanceCache.set(key, value);

    return value;
}

export function contrastRatio(a, b) {
    const la = relativeLuminance(a);
    const lb = relativeLuminance(b);

    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}

function parseHex(value) {
    const match = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(String(value || '').trim());
    if (!match) {
        return null;
    }
    let hex = match[1];
    if (hex.length === 3) {
        hex = hex.split('').map((c) => c + c).join('');
    }

    return [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16)).concat(1);
}

/**
 * Any CSS color string (hex, rgb(), oklch(), color(), named) as [r, g, b, a] in sRGB, or null.
 * Non-hex values are normalized by painting them on a 1x1 canvas, so every syntax the browser
 * supports works. Unresolvable values (currentColor, var(), empty) return null.
 */
export function cssColorToRgba(value) {
    const input = String(value || '').trim();
    if (!input || /^(currentcolor|inherit|initial|unset|var\()/i.test(input)) {
        return null;
    }
    const hex = parseHex(input);
    if (hex) {
        return hex;
    }
    if (colorCache.has(input)) {
        return colorCache.get(input);
    }
    if (typeof document === 'undefined') {
        return null;
    }
    if (!canvasContext) {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        canvasContext = canvas.getContext('2d', { willReadFrequently: true });
    }
    if (!canvasContext) {
        return null;
    }
    // An invalid value leaves fillStyle unchanged; use a sentinel to detect that.
    canvasContext.fillStyle = '#010203';
    canvasContext.fillStyle = input;
    if (String(canvasContext.fillStyle).toLowerCase() === '#010203' && !/^#?010203$/i.test(input)) {
        colorCache.set(input, null);
        return null;
    }
    canvasContext.clearRect(0, 0, 1, 1);
    canvasContext.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = canvasContext.getImageData(0, 0, 1, 1).data;
    const rgba = [r, g, b, a / 255];
    colorCache.set(input, rgba);

    return rgba;
}

/**
 * The opaque background color actually shown behind an element: its own background-color and its
 * ancestors' are composited (alpha) until an opaque layer is found. Falls back to white.
 */
export function effectiveBackground(element) {
    const layers = [];
    let node = element;
    while (node && node.nodeType === 1) {
        const view = node.ownerDocument?.defaultView || window;
        const rgba = cssColorToRgba(view.getComputedStyle(node).backgroundColor);
        if (rgba && rgba[3] > 0) {
            layers.push(rgba);
            if (rgba[3] >= 1) {
                break;
            }
        }
        node = node.parentElement;
    }

    let result = [255, 255, 255];
    for (let i = layers.length - 1; i >= 0; i--) {
        const [r, g, b, a] = layers[i];
        result = [
            Math.round((r * a) + (result[0] * (1 - a))),
            Math.round((g * a) + (result[1] * (1 - a))),
            Math.round((b * a) + (result[2] * (1 - a))),
        ];
    }

    return result;
}

/**
 * "light" / "dark" backdrop for an icon painted in `color` over the given surfaces, or null when it
 * already has at least 3:1 contrast against every surface (e.g. a tile and its selected state).
 */
const decisionCache = new Map();

export function backdropFor(color, surfaces) {
    const list = (surfaces || []).filter(Boolean);
    const key = `${color}|${list.map((s) => s.join(',')).join('|')}`;
    if (decisionCache.has(key)) {
        return decisionCache.get(key);
    }
    const decision = computeBackdrop(cssColorToRgba(color), list);
    decisionCache.set(key, decision);

    return decision;
}

function computeBackdrop(rgba, list) {
    if (!rgba || !list.length) {
        return null;
    }
    const rgb = rgba.slice(0, 3);
    const worst = Math.min(...list.map((surface) => contrastRatio(rgb, surface)));
    if (worst >= MIN_GRAPHIC_CONTRAST) {
        return null;
    }

    return contrastRatio(rgb, CHIP.light) >= contrastRatio(rgb, CHIP.dark) ? 'light' : 'dark';
}

/** True when a color has less than 3:1 contrast with the background behind `element`. */
export function isLowContrast(color, element) {
    const rgba = cssColorToRgba(color);
    if (!rgba || !element) {
        return false;
    }

    return contrastRatio(rgba.slice(0, 3), effectiveBackground(element)) < MIN_GRAPHIC_CONTRAST;
}

/** The color an element actually renders with (resolves currentColor, inherited and custom colors). */
export function renderedColor(element) {
    if (!element) {
        return '';
    }
    const target = element.querySelector?.('svg') || element;
    const view = target.ownerDocument?.defaultView || window;

    return view.getComputedStyle(target).color;
}

const THEME_ATTRIBUTES = ['theme', 'data-theme', 'class', 'data-color-scheme', 'style'];

/**
 * Bumps when anything that can change rendered colors changes: the OS/browser color scheme
 * (matchMedia), or a theme attribute/class on <html>, <body> or the nearest [theme] ancestor of `ref`.
 */
export function useThemeVersion(ref) {
    const [version, setVersion] = useState(0);
    useEffect(() => {
        const element = ref?.current;
        const doc = element?.ownerDocument || (typeof document !== 'undefined' ? document : null);
        const view = doc?.defaultView || (typeof window !== 'undefined' ? window : null);
        if (!doc || !view) {
            return undefined;
        }
        let frame = 0;
        const bump = () => {
            view.cancelAnimationFrame(frame);
            frame = view.requestAnimationFrame(() => setVersion((value) => value + 1));
        };
        const queries = [doc.defaultView, view === window ? null : window]
            .filter(Boolean)
            .map((w) => w.matchMedia?.('(prefers-color-scheme: dark)'))
            .filter(Boolean);
        queries.forEach((query) => query.addEventListener?.('change', bump));
        const observer = new view.MutationObserver(bump);
        const targets = new Set([doc.documentElement, doc.body, element?.closest?.('[theme]')].filter(Boolean));
        targets.forEach((target) => observer.observe(target, { attributes: true, attributeFilter: THEME_ATTRIBUTES }));

        return () => {
            view.cancelAnimationFrame(frame);
            observer.disconnect();
            queries.forEach((query) => query.removeEventListener?.('change', bump));
        };
    }, [ref]);

    return version;
}

/**
 * Rendered backgrounds of an icon tile (normal and selected) inside `containerRef`, plus the
 * rendered ink of a tile glyph without a brand color. Measured with hidden probe tiles, so neither
 * the current hover nor the current selection skews the result; re-measured on theme changes.
 */
export function useTileSurfaces(containerRef, tileClass, glyphClass, selectedClass = 'is-selected') {
    const version = useThemeVersion(containerRef);
    const [state, setState] = useState({ surfaces: [], ink: '' });

    useLayoutEffect(() => {
        const container = containerRef.current;
        if (!container) {
            return;
        }
        const doc = container.ownerDocument;
        const probe = (extra) => {
            const tile = doc.createElement('span');
            tile.className = `${tileClass}${extra ? ` ${extra}` : ''}`;
            tile.setAttribute('aria-hidden', 'true');
            tile.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none;width:1px;height:1px;';
            const glyph = doc.createElement('span');
            glyph.className = glyphClass;
            tile.appendChild(glyph);
            container.appendChild(tile);
            const result = { surface: effectiveBackground(glyph), ink: renderedColor(glyph) };
            tile.remove();
            return result;
        };
        const normal = probe('');
        const selected = probe(selectedClass);
        const next = { surfaces: [normal.surface, selected.surface], ink: normal.ink };
        setState((current) => (JSON.stringify(current) === JSON.stringify(next) ? current : next));
    }, [containerRef, tileClass, glyphClass, selectedClass, version]);

    return state;
}

/**
 * Live hover/focus check: when a tile is hovered or focused, its real rendered background and glyph
 * color are measured and `data-low-contrast-hover` is set on the glyph if they fail 3:1.
 */
export function useHoverContrast(containerRef, tileSelector, glyphSelector) {
    useEffect(() => {
        const container = containerRef.current;
        if (!container) {
            return undefined;
        }
        const view = container.ownerDocument.defaultView;
        const clear = (glyph) => glyph?.removeAttribute('data-low-contrast-hover');
        const check = (event) => {
            const tile = event.target.closest?.(tileSelector);
            const glyph = tile?.querySelector(glyphSelector);
            if (!glyph || glyph.hasAttribute('data-low-contrast')) {
                return;
            }
            // Wait a frame so :hover / :focus styles (and their transitions' end state) apply.
            view.requestAnimationFrame(() => {
                const decision = backdropFor(renderedColor(glyph), [effectiveBackground(glyph)]);
                if (decision) {
                    glyph.setAttribute('data-low-contrast-hover', decision);
                } else {
                    clear(glyph);
                }
            });
        };
        const leave = (event) => {
            const tile = event.target.closest?.(tileSelector);
            if (tile && !tile.contains(event.relatedTarget)) {
                clear(tile.querySelector(glyphSelector));
            }
        };
        container.addEventListener('pointerover', check);
        container.addEventListener('focusin', check);
        container.addEventListener('pointerout', leave);
        container.addEventListener('focusout', leave);

        return () => {
            container.removeEventListener('pointerover', check);
            container.removeEventListener('focusin', check);
            container.removeEventListener('pointerout', leave);
            container.removeEventListener('focusout', leave);
        };
    }, [containerRef, tileSelector, glyphSelector]);
}

/**
 * Backdrop ("light" / "dark" / null) for a preview glyph: its rendered color (brand, custom or
 * currentColor) against the rendered background of its container. `trigger` should change whenever
 * the icon or its color changes (live custom-color updates re-evaluate immediately).
 */
export function usePreviewBackdrop(glyphRef, trigger = '') {
    const version = useThemeVersion(glyphRef);
    const [backdrop, setBackdrop] = useState(null);

    useLayoutEffect(() => {
        const glyph = glyphRef.current;
        const parent = glyph?.parentElement;
        setBackdrop(parent ? backdropFor(renderedColor(glyph), [effectiveBackground(parent)]) : null);
    }, [glyphRef, trigger, version]);

    return backdrop;
}