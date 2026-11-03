import { useId, useLayoutEffect, useRef, useState } from '@wordpress/element';
import { RangeControl, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import LucideSvg from './icons';
import { isLowContrast, useThemeVersion } from './contrast';

/** Brand color chip + hex. Gets a stronger ring when the color has < 3:1 contrast with its background. */
export function BrandSwatch({ hex }) {
    const chipRef = useRef(null);
    const version = useThemeVersion(chipRef);
    const [lowContrast, setLowContrast] = useState(false);
    useLayoutEffect(() => {
        setLowContrast(isLowContrast(`#${hex}`, chipRef.current?.parentElement));
    }, [hex, version]);

    return (
        <span className="wpicons__brand-swatch">
            <span
                ref={chipRef}
                className={`wpicons__brand-swatch__chip${lowContrast ? ' is-low-contrast' : ''}`}
                style={{ background: `#${hex}` }}
                aria-hidden="true"
            />
            <code>{`#${String(hex).toUpperCase()}`}</code>
            <span>{__('Official brand color', 'wpicons')}</span>
        </span>
    );
}

function themeColors() {
    const colors = window.WPIconsAdmin?.themeColors;
    return Array.isArray(colors) ? colors : [];
}

function ThemeColorSwatches({ value, onSelect }) {
    const colors = themeColors();
    if (!colors.length) {
        return null;
    }

    const current = String(value || '').trim().toLowerCase();

    return (
        <div className="wpicons__swatches" role="list">
            {colors.map((swatch) => {
                const selected = String(swatch.value || '').trim().toLowerCase() === current;

                return (
                    <button
                        key={swatch.id}
                        type="button"
                        role="listitem"
                        className={selected ? 'wpicons__swatch is-selected' : 'wpicons__swatch'}
                        title={swatch.label}
                        aria-label={swatch.label}
                        aria-pressed={selected}
                        style={{ background: swatch.preview }}
                        onClick={() => onSelect(swatch.value)}
                    />
                );
            })}
        </div>
    );
}

/**
 * Segmented control (radio group) for up to four options.
 */
export function Segmented({ label, options, value, onChange, className = '' }) {
    const labelId = useId();

    return (
        <div className={`wpicons__seg-field${className ? ` ${className}` : ''}`}>
            <span id={labelId} className="wpicons__seg-field__label">{label}</span>
            <div className="wpicons__seg" role="radiogroup" aria-labelledby={labelId}>
                {options.map((option) => (
                    <button
                        key={option.id}
                        type="button"
                        role="radio"
                        aria-checked={option.id === value}
                        className={`wpicons__seg__option${option.id === value ? ' is-active' : ''}`}
                        onClick={() => onChange(option.id)}
                    >
                        {option.label}
                    </button>
                ))}
            </div>
        </div>
    );
}

/**
 * Library style: a segmented control for up to four options, a select for more (Phosphor weights).
 * Not rendered at all for libraries with a single style.
 */
export function StyleControl({ label, options, value, onChange }) {
    const selectId = useId();
    if (!Array.isArray(options) || options.length < 2) {
        return null;
    }

    if (options.length <= 4) {
        return <Segmented label={label} options={options} value={value} onChange={onChange} className="wpicons__style-variant" />;
    }

    return (
        <div className="wpicons__seg-field wpicons__style-variant">
            <label className="wpicons__seg-field__label" htmlFor={selectId}>{label}</label>
            <span className="wpicons__select-control">
                <select id={selectId} value={value} onChange={(event) => onChange(event.target.value)}>
                    {options.map((option) => (
                        <option key={option.id} value={option.id}>{option.label}</option>
                    ))}
                </select>
                <LucideSvg name="chevron-down" size={16} />
            </span>
        </div>
    );
}

export default function IconStyleControls({
    size,
    color,
    stroke,
    title,
    supportsStroke = false,
    allowMatchText = false,
    matchText = false,
    styleOptions = [],
    styleValue = '',
    styleLabel = '',
    brandColors = false,
    brandHex = '',
    onChange,
}) {
    const inherit = color == null || String(color).toLowerCase() === 'currentcolor';
    const isBrand = String(color || '').toLowerCase() === 'brand';
    const pixelSize = Number(size) > 0 ? Number(size) : 24;
    const colorMode = isBrand ? 'brand' : (inherit ? 'current' : 'custom');
    const showCustom = brandColors ? colorMode === 'custom' : !inherit;

    return (
        <div className="wpicons__style">
            <StyleControl
                label={styleLabel || __('Style', 'wpicons')}
                options={styleOptions}
                value={styleValue}
                onChange={(next) => onChange?.({ variant: next })}
            />
            {allowMatchText ? (
                <ToggleControl
                    label={__('Match text size', 'wpicons')}
                    checked={Boolean(matchText)}
                    __nextHasNoMarginBottom
                    onChange={(checked) => onChange?.({ matchText: checked })}
                />
            ) : null}
            {!allowMatchText || !matchText ? (
                <RangeControl
                    label={__('Size', 'wpicons')}
                    value={pixelSize}
                    min={8}
                    max={256}
                    showTooltip={false}
                    __nextHasNoMarginBottom
                    onChange={(value) => {
                        if (typeof value !== 'number') {
                            return;
                        }
                        onChange?.({ size: value, matchText: false });
                    }}
                />
            ) : null}
            {brandColors ? (
                <>
                    <Segmented
                        label={__('Color', 'wpicons')}
                        options={[
                            { id: 'brand', label: __('Brand', 'wpicons') },
                            { id: 'current', label: __('Inherit', 'wpicons') },
                            { id: 'custom', label: __('Custom', 'wpicons') },
                        ]}
                        value={colorMode}
                        onChange={(mode) => {
                            if (mode === 'brand') {
                                onChange?.({ color: 'brand' });
                            } else if (mode === 'current') {
                                onChange?.({ color: 'currentColor' });
                            } else {
                                onChange?.({ color: brandHex ? `#${String(brandHex).toLowerCase()}` : '#000000' });
                            }
                        }}
                    />
                    {isBrand && brandHex ? (
                        <BrandSwatch hex={brandHex} />

                    ) : null}
                </>
            ) : (
                <ToggleControl
                    label={__('Inherit text color', 'wpicons')}
                    checked={inherit}
                    __nextHasNoMarginBottom
                    onChange={(checked) => {
                        const nextColor = checked ? 'currentColor' : '#000000';
                        onChange?.({ color: nextColor });
                    }}
                />
            )}
            {showCustom ? (
                <div className="wpicons__style-color">
                    <TextControl
                        label={__('Color', 'wpicons')}
                        value={color || ''}
                        __nextHasNoMarginBottom
                        onChange={(value) => {
                            onChange?.({ color: value });
                        }}
                    />
                    <ThemeColorSwatches
                        value={color || ''}
                        onSelect={(next) => onChange?.({ color: next })}
                    />
                </div>
            ) : null}
            {supportsStroke ? (
                <RangeControl
                    label={__('Stroke width', 'wpicons')}
                    value={Number(stroke) || 2}
                    min={0.5}
                    max={3}
                    step={0.25}
                    showTooltip={false}
                    __nextHasNoMarginBottom
                    onChange={(value) => {
                        if (typeof value !== 'number') {
                            return;
                        }
                        onChange?.({ stroke: value });
                    }}
                />
            ) : null}
            <TextControl
                label={__('Accessible title', 'wpicons')}
                help={__('Leave empty for decorative icons (hidden from assistive tech).', 'wpicons')}
                value={title || ''}
                __nextHasNoMarginBottom
                onChange={(value) => onChange?.({ title: value })}
            />
        </div>
    );
}

/**
 * Full trademark note shown when a brand icon is selected, with the owner's guidelines link.
 */
export function BrandNotice({ guidelines }) {
    return (
        <div className="wpicons__brand-notice" role="note">
            <LucideSvg name="tag" size={14} />
            <p>
                {__('Brand icons are trademarks of their owners and may have their own usage guidelines. Using them doesn’t imply endorsement.', 'wpicons')}
                {guidelines ? (
                    <>
                        {' '}
                        <a href={guidelines} target="_blank" rel="noopener noreferrer" className="wpicons__brand-notice__link">
                            {__('Guidelines', 'wpicons')}
                            <LucideSvg name="arrow-up-right" size={12} />
                            <span className="screen-reader-text">{__('(opens in a new tab)', 'wpicons')}</span>
                        </a>
                    </>
                ) : null}
            </p>
        </div>
    );
}