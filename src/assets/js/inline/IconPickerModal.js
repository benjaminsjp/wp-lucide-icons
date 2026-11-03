import { createPortal, useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import IconPicker from '../picker/IconPicker';
import IconStyleControls from '../picker/IconStyleControls';
import { fetchIcon, fetchLibraries } from '../picker/api';
import { pluginDefaults } from './placeholder';
import { ensurePickerStyles, getMountDocument } from './mount-document';

function paintPreviewSvg(svg, { color, stroke, supportsStroke }) {
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

    const paint = !color || String(color).toLowerCase() === 'currentcolor'
        ? 'currentColor'
        : color;
    const strokeAttr = root.getAttribute('stroke');
    const fill = root.getAttribute('fill');

    if (strokeAttr && strokeAttr !== 'none') {
        root.setAttribute('stroke', paint);
    }
    if (fill && fill !== 'none') {
        root.setAttribute('fill', paint);
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
    size,
    color,
    stroke,
    title,
    matchText = true,
    allowMatchText = true,
    onLibraryChange,
    onSelect,
    onClose,
    className = '',
}) {
    const defaults = pluginDefaults();
    const titleId = useId();
    const dialogRef = useRef(null);
    const previousFocus = useRef(typeof document !== 'undefined' ? document.activeElement : null);
    const isEditing = Boolean(name);
    const [supportsStroke, setSupportsStroke] = useState(library === 'lucide');
    const [pending, setPending] = useState(() => (
        name ? { library, name, svg: '' } : null
    ));
    const [style, setStyle] = useState(() => ({
        size: size === undefined || size === null || size === '' ? (defaults.size || 24) : Number(size),
        color: color || defaults.color || 'currentColor',
        stroke: stroke === undefined || stroke === null || stroke === '' ? (defaults.stroke ?? 2) : Number(stroke),
        title: title || '',
        matchText: allowMatchText ? Boolean(matchText) : false,
    }));

    const mountDoc = getMountDocument();

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

    useEffect(() => {
        let cancelled = false;
        fetchLibraries()
            .then((data) => {
                if (cancelled) {
                    return;
                }
                const current = (data.libraries || []).find((item) => item.id === library);
                setSupportsStroke(Boolean(current?.supports?.includes('stroke')));
            })
            .catch(() => {});

        return () => {
            cancelled = true;
        };
    }, [library]);

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
        })
            .then((data) => {
                if (cancelled || !data.svg) {
                    return;
                }
                setPending((current) => (
                    current && current.name === data.name && current.library === data.library
                        ? { ...current, svg: data.svg }
                        : current
                ));
            })
            .catch(() => {});

        return () => {
            cancelled = true;
        };
    }, [pending?.library, pending?.name, pending?.svg, style.stroke]);

    useEffect(() => {
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
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
            stroke: style.stroke,
            supportsStroke,
        }),
        [pending?.svg, style.color, style.stroke, supportsStroke],
    );

    const emit = (icon) => {
        if (!icon?.name) {
            return;
        }

        const pixel = Number(style.size) > 0 ? Number(style.size) : (defaults.size || 24);
        const nextColor = !style.color || String(style.color).toLowerCase() === 'currentcolor'
            ? 'currentColor'
            : style.color;

        onSelect?.({
            library: icon.library || library,
            name: icon.name,
            svg: icon.svg,
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
                theme="dark"
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
                            library={library}
                            name={pending?.library === library ? pending.name : ''}
                            onLibraryChange={onLibraryChange}
                            onSelect={setPending}
                        />
                    </div>
                    <aside className="wpicons__picker-dialog__side">
                        <div className={`wpicons__picker-dialog__preview${pending?.name ? '' : ' is-empty'}`}>
                            {pending?.name ? (
                                <>
                                    <span className="wpicons__picker-dialog__preview-heading">
                                        <span
                                            className="wpicons__picker-dialog__preview-glyph"
                                            dangerouslySetInnerHTML={{ __html: previewSvg }}
                                        />
                                        <span className="wpicons__picker-dialog__preview-name">
                                            {humanizeIconName(pending.name)}
                                        </span>
                                    </span>
                                    <span className="wpicons__picker-dialog__preview-slug">{pending.name}</span>
                                </>
                            ) : (
                                <span>{__('None selected', 'wpicons')}</span>
                            )}
                        </div>
                        <IconStyleControls
                            size={style.size}
                            color={style.color}
                            stroke={style.stroke}
                            title={style.title}
                            supportsStroke={supportsStroke}
                            allowMatchText={allowMatchText}
                            matchText={style.matchText}
                            onChange={(patch) => setStyle((current) => ({ ...current, ...patch }))}
                        />
                    </aside>
                </div>
                <footer className="wpicons__picker-dialog__footer">
                    <Button variant="secondary" onClick={onClose}>
                        {__('Cancel', 'wpicons')}
                    </Button>
                    <Button variant="primary" disabled={!pending?.name} onClick={() => emit(pending)}>
                        {isEditing ? __('Update', 'wpicons') : __('Choose an icon', 'wpicons')}
                    </Button>
                </footer>
            </div>
        </div>
    );

    return createPortal(overlay, mountDoc.body);
}
