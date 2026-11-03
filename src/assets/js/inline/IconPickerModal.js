import { createPortal, useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import IconPicker from '../picker/IconPicker';
import IconStyleControls, { BrandNotice } from '../picker/IconStyleControls';
import { fetchIcon } from '../picker/api';
import { usePreviewBackdrop } from '../picker/contrast';
import {
    brandPaint,
    LibraryGlyph,
    libraryVariants,
    resolveVariant,
    supportsStrokeFor,
    useLibraries,
    variantLabel,
} from '../picker/libraries';
import { pluginDefaults } from './placeholder';
import { ensurePickerStyles, getMountDocument } from './mount-document';

function paintPreviewSvg(svg, { color, hex, stroke, supportsStroke }) {
    if (!svg || typeof DOMParser === 'undefined') {
        return svg || '';
    }

    const doc = new DOMParser().parseFromString(svg, 'image/svg+xml');
    const root = doc.querySelector('svg');
    if (!root || doc.querySelector('parsererror')) {
        return svg;
    }

    root.setAttribute('width', '48');
    root.setAttribute('height', '48');

    const paint = brandPaint(color, hex)
        || (!color || String(color).toLowerCase() === 'currentcolor' ? 'currentColor' : color);
    const strokeAttr = root.getAttribute('stroke');
    const fill = root.getAttribute('fill');

    if (strokeAttr && strokeAttr !== 'none') {
        root.setAttribute('stroke', paint);
    }
    if (fill && fill !== 'none') {
        root.setAttribute('fill', paint);
    }
    // Package icons paint inner elements with currentColor (duotone, outlines).
    if (paint !== 'currentColor') {
        root.setAttribute('style', `color:${paint}`);
    } else {
        root.removeAttribute('style');
    }
    if (supportsStroke && root.hasAttribute('stroke-width')) {
        root.setAttribute('stroke-width', String(stroke ?? 2));
    }

    return root.outerHTML;
}

function humanizeIconName(name) {
    return String(name || '')
        .replace(/[-_]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function getFocusable(root) {
    return [...root.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )].filter((node) => node.getAttribute('aria-hidden') !== 'true');
}

export default function IconPickerModal({
    library,
    name,
    variant = '',
    size,
    color,
    stroke,
    title,
    matchText = true,
    allowMatchText = true,
    // Kept for callers; browsing libraries inside the dialog no longer changes the caller's library
    // (Cancel used to leave the block/format pointing at whatever library was browsed last).
    onLibraryChange, // eslint-disable-line no-unused-vars
    onSelect,
    onClose,
    className = '',
}) {
    const defaults = pluginDefaults();
    const titleId = useId();
    const dialogRef = useRef(null);
    const previousFocus = useRef(typeof document !== 'undefined' ? document.activeElement : null);
    const isEditing = Boolean(name);
    const initialLibrary = library || defaults.library || 'lucide';
    const { libraries } = useLibraries();
    const [browse, setBrowse] = useState(initialLibrary);
    const [variants, setVariants] = useState(() => (variant ? { [initialLibrary]: variant } : {}));
    const [pending, setPending] = useState(() => (
        name ? { library: initialLibrary, name, svg: '' } : null
    ));
    const [style, setStyle] = useState(() => ({
        size: size === undefined || size === null || size === '' ? (defaults.size || 24) : Number(size),
        color: color || defaults.color || 'currentColor',
        stroke: stroke === undefined || stroke === null || stroke === '' ? (defaults.stroke ?? 2) : Number(stroke),
        title: title || '',
        matchText: allowMatchText ? Boolean(matchText) : false,
    }));

    const mountDoc = getMountDocument();

    const pendingLibrary = libraries.find((item) => item.id === pending?.library);
    const browseLibrary = libraries.find((item) => item.id === browse);
    // The side panel edits the selected icon; before one is picked it shows the browsed library.
    const controlLibrary = pendingLibrary || browseLibrary;
    const variantOf = (lib) => resolveVariant(lib, variants[lib?.id]);
    const controlVariant = controlLibrary ? variantOf(controlLibrary) : '';
    const pendingVariant = pendingLibrary ? variantOf(pendingLibrary) : '';
    const supportsStroke = controlLibrary
        ? supportsStrokeFor(controlLibrary, controlVariant)
        : browse === 'lucide';

    const styleVariants = useMemo(() => {
        const map = {};
        libraries.forEach((item) => {
            map[item.id] = resolveVariant(item, variants[item.id]);
        });
        return map;
    }, [libraries, variants]);

    const colorModes = useMemo(() => {
        const map = {};
        if (pendingLibrary?.brandColors) {
            map[pendingLibrary.id] = String(style.color).toLowerCase() === 'brand' ? 'brand' : 'other';
        }
        return map;
    }, [pendingLibrary, style.color]);

    useLayoutEffect(() => {
        ensurePickerStyles(mountDoc);
    }, [mountDoc]);

    useEffect(() => {
        const body = mountDoc.body;
        const previousOverflow = body.style.overflow;
        body.style.overflow = 'hidden';

        return () => {
            body.style.overflow = previousOverflow;
            if (previousFocus.current && typeof previousFocus.current.focus === 'function') {
                previousFocus.current.focus();
            }
        };
    }, [mountDoc]);

    // Load (or re-load after a style change) the selected icon, with its brand metadata.
    useEffect(() => {
        if (!pending?.name || pending.svg) {
            return undefined;
        }

        let cancelled = false;
        fetchIcon({
            library: pending.library,
            name: pending.name,
            size: 24,
            color: 'currentColor',
            stroke: style.stroke,
            variant: pendingVariant,
        })
            .then((data) => {
                if (cancelled || !data.svg) {
                    return;
                }
                const meta = data.meta && typeof data.meta === 'object' ? data.meta : {};
                setPending((current) => (
                    current && current.name === data.name && current.library === data.library
                        ? {
                            ...current,
                            svg: data.svg,
                            brand: Boolean(data.brand),
                            hex: meta.hex || current.hex || '',
                            title: meta.title || current.title || '',
                            guidelines: meta.guidelines || current.guidelines || '',
                        }
                        : current
                ));
            })
            .catch(() => {});

        return () => {
            cancelled = true;
        };
    }, [pending?.library, pending?.name, pending?.svg, pendingVariant, style.stroke]);

    useEffect(() => {
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                // The library switcher popover closes itself first.
                if (event.target && typeof event.target.closest === 'function' && event.target.closest('[data-wpicons-popover]')) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                onClose?.();
                return;
            }

            if (event.key !== 'Tab' || !dialogRef.current) {
                return;
            }

            const nodes = getFocusable(dialogRef.current);
            if (!nodes.length) {
                return;
            }

            const first = nodes[0];
            const last = nodes[nodes.length - 1];
            const active = dialogRef.current.ownerDocument.activeElement;

            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        };

        const docs = [mountDoc];
        if (document !== mountDoc) {
            docs.push(document);
        }

        docs.forEach((doc) => doc.addEventListener('keydown', onKeyDown, true));
        return () => {
            docs.forEach((doc) => doc.removeEventListener('keydown', onKeyDown, true));
        };
    }, [mountDoc, onClose]);

    const previewSvg = useMemo(
        () => paintPreviewSvg(pending?.svg || '', {
            color: style.color,
            hex: pending?.hex,
            stroke: style.stroke,
            supportsStroke,
        }),
        [pending?.svg, pending?.hex, style.color, style.stroke, supportsStroke],
    );

    // Preview chip when the displayed color (brand or custom) has < 3:1 contrast with the panel.
    const previewGlyphRef = useRef(null);
    const previewBackdrop = usePreviewBackdrop(previewGlyphRef, pending?.name ? previewSvg : '');

    const pick = (icon) => {
        const nextLibrary = libraries.find((item) => item.id === icon.library);
        if (nextLibrary && icon.library !== pending?.library) {
            // New library: start from its own defaults (stroke, brand color).
            setStyle((current) => {
                const next = { ...current };
                if (typeof nextLibrary.defaultStroke === 'number') {
                    next.stroke = nextLibrary.defaultStroke;
                }
                const wasBrand = String(current.color).toLowerCase() === 'brand';
                if (nextLibrary.brandColors || wasBrand) {
                    next.color = nextLibrary.defaultColor || 'currentColor';
                    if (!nextLibrary.brandColors && next.color === 'brand') {
                        next.color = 'currentColor';
                    }
                }
                return next;
            });
        }
        setPending({ ...icon });
    };

    const emit = (icon) => {
        if (!icon?.name) {
            return;
        }

        const pixel = Number(style.size) > 0 ? Number(style.size) : (defaults.size || 24);
        const rawColor = String(style.color || '');
        let nextColor = !rawColor || rawColor.toLowerCase() === 'currentcolor' ? 'currentColor' : rawColor;
        if (nextColor.toLowerCase() === 'brand') {
            nextColor = pendingLibrary?.brandColors ? 'brand' : 'currentColor';
        }

        onSelect?.({
            library: icon.library || browse,
            name: icon.name,
            svg: icon.svg,
            variant: pendingVariant,
            hex: icon.hex || '',
            size: allowMatchText && style.matchText ? '' : pixel,
            color: nextColor,
            stroke: Number(style.stroke) || defaults.stroke || 2,
            title: style.title || '',
            matchText: Boolean(allowMatchText && style.matchText),
        });
    };

    const overlay = (
        <div
            className="wpicons__picker-overlay"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) {
                    onClose?.();
                }
            }}
        >
            <div
                ref={dialogRef}
                className={`wpicons wpicons__picker-dialog${className ? ` ${className}` : ''}`}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                onMouseDown={(event) => event.stopPropagation()}
            >
                <header className="wpicons__picker-dialog__header">
                    <div id={titleId} className="wpicons__picker-dialog__title">
                        {__('Choose icon', 'wpicons')}
                    </div>
                    <button
                        type="button"
                        className="wpicons__picker-dialog__close"
                        onClick={onClose}
                        aria-label={__('Close', 'wpicons')}
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                            <path d="M18 6 6 18M6 6l12 12" />
                        </svg>
                    </button>
                </header>
                <div className="wpicons__picker-dialog__body">
                    <div className="wpicons__picker-dialog__main">
                        <IconPicker
                            variant="dialog"
                            library={browse}
                            name={pending?.name || ''}
                            selectedLibrary={pending?.library || ''}
                            styleVariants={styleVariants}
                            colorModes={colorModes}
                            onLibraryChange={setBrowse}
                            onSelect={pick}
                        />
                    </div>
                    <aside className="wpicons__picker-dialog__side">
                        <div className={`wpicons__picker-dialog__preview${pending?.name ? '' : ' is-empty'}`}>
                            {pending?.name ? (
                                <>
                                    <span className="wpicons__picker-dialog__preview-heading">
                                        <span
                                            ref={previewGlyphRef}
                                            className="wpicons__picker-dialog__preview-glyph"
                                            data-low-contrast={previewBackdrop || undefined}
                                            dangerouslySetInnerHTML={{ __html: previewSvg }}
                                        />
                                        <span className="wpicons__picker-dialog__preview-name">
                                            {pending.title || humanizeIconName(pending.name)}
                                        </span>
                                    </span>
                                    <span className="wpicons__picker-dialog__preview-meta">
                                        <span className="wpicons__picker-dialog__preview-slug">{pending.name}</span>
                                        {pendingLibrary ? (
                                            <span className="wpicons__lib-chip">
                                                <LibraryGlyph library={pendingLibrary} size={12} />
                                                {pendingLibrary.label}
                                            </span>
                                        ) : null}
                                    </span>
                                </>
                            ) : (
                                <span>{__('None selected', 'wpicons')}</span>
                            )}
                        </div>
                        {pending?.brand ? <BrandNotice guidelines={pending.guidelines} /> : null}
                        <IconStyleControls
                            size={style.size}
                            color={style.color}
                            stroke={style.stroke}
                            title={style.title}
                            supportsStroke={supportsStroke}
                            allowMatchText={allowMatchText}
                            matchText={style.matchText}
                            styleOptions={libraryVariants(controlLibrary)}
                            styleValue={controlVariant}
                            styleLabel={variantLabel(controlLibrary)}
                            brandColors={Boolean(pendingLibrary?.brandColors)}
                            brandHex={pending?.hex || ''}
                            onChange={(patch) => {
                                if (Object.prototype.hasOwnProperty.call(patch, 'variant')) {
                                    if (controlLibrary) {
                                        setVariants((current) => ({ ...current, [controlLibrary.id]: patch.variant }));
                                        if (pending?.library === controlLibrary.id) {
                                            setPending((current) => (current ? { ...current, svg: '' } : current));
                                        }
                                    }
                                    return;
                                }
                                setStyle((current) => ({ ...current, ...patch }));
                            }}
                        />
                    </aside>
                </div>
                <footer className="wpicons__picker-dialog__footer">
                    <Button variant="secondary" onClick={onClose}>
                        {__('Cancel', 'wpicons')}
                    </Button>
                    <Button variant="primary" disabled={!pending?.name || !pending?.svg} onClick={() => emit(pending)}>
                        {isEditing ? __('Update', 'wpicons') : __('Choose an icon', 'wpicons')}
                    </Button>
                </footer>
            </div>
        </div>
    );

    return createPortal(overlay, mountDoc.body);
}