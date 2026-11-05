import { createRoot, render, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import IconPicker from '../../picker/IconPicker';
import LucideSvg from '../../picker/icons';
import { BrandNotice, BrandSwatch, Segmented, StyleControl } from '../../picker/IconStyleControls';
import { usePreviewBackdrop } from '../../picker/contrast';
import { fetchIcon } from '../../picker/api';
import {
    brandPaint,
    LibraryGlyph,
    libraryVariants,
    resolveVariant,
    supportsStrokeFor,
    useLibraries,
    variantLabel,
} from '../../picker/libraries';

const pluginDefaults = window.WPIconsAdmin?.defaults || {
    library: 'lucide',
    size: 24,
    color: 'currentColor',
    stroke: 2,
};

function paintIcon(svg, { size, color, hex, stroke, supportsStroke }) {
    if (!svg || typeof DOMParser === 'undefined') {
        return svg || '';
    }

    const doc = new DOMParser().parseFromString(svg, 'image/svg+xml');
    const root = doc.querySelector('svg');
    if (!root || doc.querySelector('parsererror')) {
        return svg;
    }

    const pixels = Number(size) > 0 ? String(Math.round(Number(size))) : '24';
    root.setAttribute('width', pixels);
    root.setAttribute('height', pixels);

    const paint = brandPaint(color, hex) || color || 'currentColor';
    const strokeAttr = root.getAttribute('stroke');
    const fill = root.getAttribute('fill');
    if (strokeAttr && strokeAttr !== 'none') {
        root.setAttribute('stroke', paint);
    }
    if (fill && fill !== 'none') {
        root.setAttribute('fill', paint);
    }
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

function shortcodeValue(value) {
    return String(value ?? '').replace(/["\]\[]/g, '');
}

function RangeField({ id, label, hint, value, min, max, step = 1, onChange }) {
    const [draft, setDraft] = useState(String(value));

    useEffect(() => {
        setDraft(String(value));
    }, [value]);

    const numeric = Number(value);
    const fill = max > min ? Math.min(100, Math.max(0, ((numeric - min) / (max - min)) * 100)) : 0;

    const commit = (raw) => {
        setDraft(raw);
        if (raw === '') {
            return;
        }
        const next = Number(raw);
        if (!Number.isNaN(next) && next >= min && next <= max) {
            onChange(next);
        }
    };

    return (
        <div className="wpicons__field">
            <div className="wpicons__field__label-row">
                <label className="wpicons__field__label" htmlFor={id}>{label}</label>
                {hint ? <span className="wpicons__field__hint">{hint}</span> : null}
            </div>
            <div className="wpicons__slider-row">
                <input
                    type="range"
                    className="wpicons__range"
                    min={min}
                    max={max}
                    step={step}
                    value={numeric}
                    aria-label={label}
                    style={{ '--wpicons-range-fill': `${fill}%` }}
                    onChange={(event) => onChange(Number(event.target.value))}
                />
                <input
                    id={id}
                    type="number"
                    className="wpicons__number"
                    min={min}
                    max={max}
                    step={step}
                    value={draft}
                    onChange={(event) => commit(event.target.value)}
                    onBlur={() => setDraft(String(value))}
                />
            </div>
        </div>
    );
}

function LibraryApp() {
    const { libraries } = useLibraries();
    const [library, setLibrary] = useState(pluginDefaults.library);
    const [selected, setSelected] = useState(null);
    const [variants, setVariants] = useState({});
    const [size, setSize] = useState(pluginDefaults.size);
    const [color, setColor] = useState(pluginDefaults.color);
    const [customColor, setCustomColor] = useState('#1e1e1e');
    const [stroke, setStroke] = useState(pluginDefaults.stroke);
    const [copied, setCopied] = useState('');

    const selectedLibrary = libraries.find((item) => item.id === selected?.library);
    const browseLibrary = libraries.find((item) => item.id === library);
    const styleVariants = useMemo(() => {
        const map = {};
        libraries.forEach((item) => {
            map[item.id] = resolveVariant(item, variants[item.id]);
        });
        return map;
    }, [libraries, variants]);
    const controlLibrary = selectedLibrary || browseLibrary;
    const variant = controlLibrary ? resolveVariant(controlLibrary, variants[controlLibrary.id]) : '';
    const selectedVariant = selectedLibrary ? resolveVariant(selectedLibrary, variants[selectedLibrary.id]) : '';
    const supportsStroke = supportsStrokeFor(selectedLibrary, selectedVariant);
    const brandColors = Boolean(selectedLibrary?.brandColors);
    const isBrandColor = brandColors && color === 'brand';
    const useCurrentColor = color === 'currentColor';
    const effectiveColor = isBrandColor ? 'brand' : (useCurrentColor ? 'currentColor' : customColor);

    const shortcode = useMemo(() => {
        if (!selected) {
            return '';
        }
        const attrs = [
            `library="${shortcodeValue(selected.library)}"`,
            `name="${shortcodeValue(selected.name)}"`,
        ];
        if (selectedVariant && selectedLibrary?.variantParam) {
            attrs.push(`${selectedLibrary.variantParam}="${shortcodeValue(selectedVariant)}"`);
        }
        attrs.push(`size="${shortcodeValue(size)}"`);
        attrs.push(`color="${shortcodeValue(effectiveColor)}"`);
        if (supportsStroke) {
            attrs.push(`stroke="${shortcodeValue(stroke)}"`);
        }
        return `[wp_icon ${attrs.join(' ')}]`;
    }, [selected, selectedLibrary, selectedVariant, size, effectiveColor, stroke, supportsStroke]);

    const preview = useMemo(() => paintIcon(selected?.svg || '', {
        size,
        color: effectiveColor,
        hex: selected?.hex,
        stroke,
        supportsStroke,
    }), [selected?.svg, selected?.hex, size, effectiveColor, stroke, supportsStroke]);

    // Preview chip when the displayed color (brand or custom) has < 3:1 contrast with the preview.
    const previewGlyphRef = useRef(null);
    const previewBackdrop = usePreviewBackdrop(previewGlyphRef, selected?.name ? preview : '');

    // Re-load the selected icon when its style changes.
    useEffect(() => {
        if (!selected?.name || selected.svg) {
            return undefined;
        }

        let cancelled = false;
        fetchIcon({
            library: selected.library,
            name: selected.name,
            size: 24,
            color: 'currentColor',
            stroke: 2,
            variant: selectedVariant,
        })
            .then((data) => {
                if (!cancelled && data.svg) {
                    setSelected((current) => (
                        current && current.name === data.name && current.library === data.library
                            ? { ...current, svg: data.svg }
                            : current
                    ));
                }
            })
            .catch(() => {});

        return () => {
            cancelled = true;
        };
    }, [selected?.library, selected?.name, selected?.svg, selectedVariant]);

    async function copyText(text, key) {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(key);
            window.setTimeout(() => setCopied(''), 2000);
        } catch (error) {
            setCopied('');
        }
    }

    const hasIcon = Boolean(selected?.name);
    const variantName = libraryVariants(selectedLibrary).find((option) => option.id === selectedVariant)?.label;
    const caption = [
        /* translators: %s: icon size in pixels */
        sprintf(__('%s px', 'wpicons'), size),
        variantName ? variantName.toLowerCase() : null,
        /* translators: %s: stroke width */
        supportsStroke ? sprintf(__('stroke %s', 'wpicons'), stroke) : null,
        isBrandColor && selected?.hex ? `#${String(selected.hex).toUpperCase()}` : effectiveColor,
    ].filter(Boolean).join(' \u00b7 ');

    return (
        <div className={`wpicons__library__app${hasIcon ? '' : ' is-empty'}`}>
            <section className="wpicons__library__picker wpicons__panel" aria-label={__('Icons', 'wpicons')}>
                <IconPicker
                    variant="library"
                    library={library}
                    name={selected?.name || ''}
                    selectedLibrary={selected?.library || ''}
                    styleVariants={styleVariants}
                    colorModes={selectedLibrary?.brandColors ? { [selectedLibrary.id]: isBrandColor ? 'brand' : 'other' } : {}}
                    onLibraryChange={setLibrary}
                    onSelect={(icon) => {
                        const nextLibrary = libraries.find((item) => item.id === icon.library);
                        if (nextLibrary && icon.library !== selected?.library) {
                            if (typeof nextLibrary.defaultStroke === 'number') {
                                setStroke(nextLibrary.defaultStroke);
                            }
                            if (nextLibrary.brandColors) {
                                setColor(nextLibrary.defaultColor === 'brand' ? 'brand' : 'currentColor');
                            } else if (color === 'brand') {
                                setColor('currentColor');
                            }
                        }
                        setSelected(icon);
                    }}
                />
            </section>
            <aside className="wpicons__library__detail wpicons__panel" aria-labelledby="wpicons-selected-icon-title">
                <h2 id="wpicons-selected-icon-title" className="wpicons__panel__title">
                    {__('Selected icon', 'wpicons')}
                </h2>
                {hasIcon ? (
                    <>
                        <div className="wpicons__library__preview">
                            <div
                                ref={previewGlyphRef}
                                className="wpicons__library__preview-glyph"
                                data-low-contrast={previewBackdrop || undefined}
                                dangerouslySetInnerHTML={{ __html: preview }}
                            />
                            <span className="wpicons__library__preview-caption">{caption}</span>
                        </div>
                        <div className="wpicons__library__name-row">
                            <div className="wpicons__library__name">
                                <span className="wpicons__library__name-title">{selected.title || humanizeIconName(selected.name)}</span>
                                <code className="wpicons__library__name-slug">{selected.name}</code>
                            </div>
                            {selectedLibrary ? (
                                <span className="wpicons__lib-chip">
                                    <LibraryGlyph library={selectedLibrary} size={12} />
                                    {selectedLibrary.label}
                                </span>
                            ) : null}
                        </div>
                        {selected.brand ? <BrandNotice guidelines={selected.guidelines} /> : null}
                    </>
                ) : (
                    <div className="wpicons__library__empty">
                        <span className="wpicons__library__empty-icon">
                            <LucideSvg name="mouse-pointer-click" size={24} />
                        </span>
                        <strong className="wpicons__library__empty-title">{__('No icon selected', 'wpicons')}</strong>
                        <p className="wpicons__library__empty-text">
                            {__('Pick an icon from the grid to preview and copy its shortcode.', 'wpicons')}
                        </p>
                    </div>
                )}
                <hr className="wpicons__divider" />
                <fieldset className="wpicons__library__controls" disabled={!hasIcon}>
                    <legend className="screen-reader-text">{__('Icon options', 'wpicons')}</legend>
                    <StyleControl
                        label={variantLabel(controlLibrary)}
                        options={libraryVariants(controlLibrary)}
                        value={variant}
                        onChange={(next) => {
                            if (!controlLibrary) {
                                return;
                            }
                            setVariants((current) => ({ ...current, [controlLibrary.id]: next }));
                            if (selected?.library === controlLibrary.id) {
                                setSelected((current) => (current ? { ...current, svg: '' } : current));
                            }
                        }}
                    />
                    <RangeField
                        id="wpicons-library-size"
                        label={__('Size', 'wpicons')}
                        hint={__('8\u2013256 px', 'wpicons')}
                        value={size}
                        min={8}
                        max={256}
                        onChange={setSize}
                    />
                    {brandColors ? (
                        <div className="wpicons__field">
                            <Segmented
                                label={__('Color', 'wpicons')}
                                options={[
                                    { id: 'brand', label: __('Brand', 'wpicons') },
                                    { id: 'current', label: __('Inherit', 'wpicons') },
                                    { id: 'custom', label: __('Custom', 'wpicons') },
                                ]}
                                value={isBrandColor ? 'brand' : (useCurrentColor ? 'current' : 'custom')}
                                onChange={(mode) => {
                                    if (mode === 'brand') {
                                        setColor('brand');
                                    } else if (mode === 'current') {
                                        setColor('currentColor');
                                    } else {
                                        if (selected?.hex) {
                                            setCustomColor(`#${String(selected.hex).toLowerCase()}`);
                                        }
                                        setColor('custom');
                                    }
                                }}
                            />
                            {isBrandColor && selected?.hex ? (
                                <BrandSwatch hex={selected.hex} />

                            ) : null}
                        </div>
                    ) : (
                        <div className="wpicons__field">
                            <label className="wpicons__toggle wpicons__toggle--between">
                                <span className="wpicons__toggle__label">{__('Inherit text color', 'wpicons')}</span>
                                <input
                                    type="checkbox"
                                    className="wpicons__toggle__input"
                                    checked={useCurrentColor}
                                    onChange={(event) => setColor(event.target.checked ? 'currentColor' : 'custom')}
                                />
                                <span className="wpicons__toggle__track" aria-hidden="true" />
                            </label>
                            <p className="wpicons__help">{__('Uses currentColor to match the surrounding text.', 'wpicons')}</p>
                        </div>
                    )}
                    {!useCurrentColor && !isBrandColor ? (
                        <div className="wpicons__field wpicons__field--nested">
                            <label className="wpicons__field__label" htmlFor="wpicons-library-color">
                                {__('Color', 'wpicons')}
                            </label>
                            <input
                                id="wpicons-library-color"
                                type="text"
                                className="wpicons__input wpicons__input--mono"
                                value={customColor}
                                onChange={(event) => setCustomColor(event.target.value)}
                            />
                        </div>
                    ) : null}
                    {supportsStroke ? (
                        <RangeField
                            id="wpicons-library-stroke"
                            label={__('Stroke width', 'wpicons')}
                            hint={__('0.5\u20133', 'wpicons')}
                            value={stroke}
                            min={0.5}
                            max={3}
                            step={0.25}
                            onChange={setStroke}
                        />
                    ) : null}
                </fieldset>
                <div className={`wpicons__field wpicons__library__shortcode${hasIcon ? '' : ' is-disabled'}`}>
                    <span className="wpicons__field__label" id="wpicons-library-shortcode-label">
                        {__('Shortcode', 'wpicons')}
                    </span>
                    <div className="wpicons__code" aria-labelledby="wpicons-library-shortcode-label">
                        <code className={hasIcon ? '' : 'is-placeholder'}>
                            {hasIcon ? shortcode : __('Select an icon to generate a shortcode.', 'wpicons')}
                        </code>
                        <button
                            type="button"
                            className="wpicons__icon-button"
                            disabled={!hasIcon}
                            aria-label={__('Copy shortcode', 'wpicons')}
                            title={__('Copy shortcode', 'wpicons')}
                            onClick={() => copyText(shortcode, 'shortcode')}
                        >
                            <LucideSvg name={copied === 'shortcode' ? 'check' : 'copy'} size={14} />
                        </button>
                    </div>
                </div>
                <button
                    type="button"
                    className="wpicons__btn wpicons__btn--primary wpicons__btn--block"
                    disabled={!hasIcon}
                    onClick={() => copyText(shortcode, 'shortcode')}
                >
                    <LucideSvg name={copied === 'shortcode' ? 'check' : 'copy'} size={16} />
                    <span>{copied === 'shortcode' ? __('Copied', 'wpicons') : __('Copy shortcode', 'wpicons')}</span>
                </button>
                <p className="wpicons__help">
                    {__('In the block editor, insert the Icon block and choose the same library and name.', 'wpicons')}
                </p>
                <span className="screen-reader-text" aria-live="polite">
                    {copied === 'shortcode' ? __('Shortcode copied to clipboard.', 'wpicons') : ''}
                </span>
            </aside>
        </div>
    );
}

const rootEl = document.getElementById('wpicons-library-root');
if (rootEl) {
    if (typeof createRoot === 'function') {
        createRoot(rootEl).render(<LibraryApp />);
    } else {
        render(<LibraryApp />, rootEl);
    }
}