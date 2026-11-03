import { createRoot, render, useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import IconPicker from '../../picker/IconPicker';
import LucideSvg from '../../picker/icons';
import { fetchIcon, fetchLibraries } from '../../picker/api';

const pluginDefaults = window.WPIconsAdmin?.defaults || {
    library: 'lucide',
    size: 24,
    color: 'currentColor',
    stroke: 2,
};

function paintIcon(svg, { size, color, stroke, supportsStroke }) {
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

    const paint = color || 'currentColor';
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
    const [library, setLibrary] = useState(pluginDefaults.library);
    const [name, setName] = useState('');
    const [size, setSize] = useState(pluginDefaults.size);
    const [color, setColor] = useState(pluginDefaults.color);
    const [stroke, setStroke] = useState(pluginDefaults.stroke);
    const [useCurrentColor, setUseCurrentColor] = useState(true);
    const [supportsStroke, setSupportsStroke] = useState(true);
    const [libraryLabel, setLibraryLabel] = useState('');
    const [sourceSvg, setSourceSvg] = useState('');
    const [copied, setCopied] = useState('');

    const shortcode = useMemo(() => {
        const attrs = [
            `library="${library}"`,
            `name="${name}"`,
            `size="${size}"`,
            `color="${useCurrentColor ? 'currentColor' : color}"`,
        ];
        if (supportsStroke) {
            attrs.push(`stroke="${stroke}"`);
        }
        return `[wp_icon ${attrs.join(' ')}]`;
    }, [library, name, size, color, stroke, useCurrentColor, supportsStroke]);

    const preview = useMemo(() => paintIcon(sourceSvg, {
        size,
        color: useCurrentColor ? 'currentColor' : color,
        stroke,
        supportsStroke,
    }), [sourceSvg, size, color, stroke, useCurrentColor, supportsStroke]);

    useEffect(() => {
        fetchLibraries()
            .then((data) => {
                const current = (data.libraries || []).find((item) => item.id === library);
                setSupportsStroke(Boolean(current?.supports?.includes('stroke')));
                setLibraryLabel(current?.label || '');
            })
            .catch(() => {});
    }, [library]);

    useEffect(() => {
        if (!name || sourceSvg) {
            return undefined;
        }

        let cancelled = false;
        fetchIcon({
            library,
            name,
            size: 24,
            color: 'currentColor',
            stroke: 2,
        })
            .then((data) => {
                if (!cancelled && data.svg) {
                    setSourceSvg(data.svg);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setSourceSvg('');
                }
            });

        return () => {
            cancelled = true;
        };
    }, [library, name, sourceSvg]);

    async function copyText(text, key) {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(key);
            window.setTimeout(() => setCopied(''), 2000);
        } catch (error) {
            setCopied('');
        }
    }

    const hasIcon = Boolean(name);
    const colorLabel = useCurrentColor ? 'currentColor' : color;
    const caption = [
        /* translators: %s: icon size in pixels */
        sprintf(__('%s px', 'wpicons'), size),
        /* translators: %s: stroke width */
        supportsStroke ? sprintf(__('stroke %s', 'wpicons'), stroke) : null,
        colorLabel,
    ].filter(Boolean).join(' \u00b7 ');

    return (
        <div className={`wpicons__library__app${hasIcon ? '' : ' is-empty'}`}>
            <section className="wpicons__library__picker wpicons__panel" aria-label={__('Icons', 'wpicons')}>
                <IconPicker
                    variant="library"
                    library={library}
                    name={name}
                    onLibraryChange={(nextLibrary) => {
                        setLibrary(nextLibrary);
                        setSourceSvg('');
                    }}
                    onSelect={(icon) => {
                        setLibrary(icon.library);
                        setName(icon.name);
                        setSourceSvg(icon.svg || '');
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
                                className="wpicons__library__preview-glyph"
                                dangerouslySetInnerHTML={{ __html: preview }}
                            />
                            <span className="wpicons__library__preview-caption">{caption}</span>
                        </div>
                        <div className="wpicons__library__name-row">
                            <div className="wpicons__library__name">
                                <span className="wpicons__library__name-title">{humanizeIconName(name)}</span>
                                <code className="wpicons__library__name-slug">{name}</code>
                            </div>
                            {libraryLabel ? <span className="wpicons__chip">{libraryLabel}</span> : null}
                        </div>
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
                    <RangeField
                        id="wpicons-library-size"
                        label={__('Size', 'wpicons')}
                        hint={__('8\u2013256 px', 'wpicons')}
                        value={size}
                        min={8}
                        max={256}
                        onChange={setSize}
                    />
                    <div className="wpicons__field">
                        <label className="wpicons__toggle wpicons__toggle--between">
                            <span className="wpicons__toggle__label">{__('Inherit text color', 'wpicons')}</span>
                            <input
                                type="checkbox"
                                className="wpicons__toggle__input"
                                checked={useCurrentColor}
                                onChange={(event) => {
                                    const checked = event.target.checked;
                                    setUseCurrentColor(checked);
                                    if (checked) {
                                        setColor('currentColor');
                                    } else if (color === 'currentColor') {
                                        setColor('#1e1e1e');
                                    }
                                }}
                            />
                            <span className="wpicons__toggle__track" aria-hidden="true" />
                        </label>
                        <p className="wpicons__help">{__('Uses currentColor to match the surrounding text.', 'wpicons')}</p>
                        {!useCurrentColor ? (
                            <div className="wpicons__field wpicons__field--nested">
                                <label className="wpicons__field__label" htmlFor="wpicons-library-color">
                                    {__('Color', 'wpicons')}
                                </label>
                                <input
                                    id="wpicons-library-color"
                                    type="text"
                                    className="wpicons__input wpicons__input--mono"
                                    value={color}
                                    onChange={(event) => setColor(event.target.value)}
                                />
                            </div>
                        ) : null}
                    </div>
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
