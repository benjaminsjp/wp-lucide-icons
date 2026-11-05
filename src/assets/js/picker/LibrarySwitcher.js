import { useEffect, useId, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import LucideSvg from './icons';
import { formatCount, LibraryGlyph } from './libraries';

/**
 * Splits libraries into favorites (pinned, in pin order) and the rest (registry order).
 */
function sections(libraries, favorites, filter) {
    const query = filter.trim().toLowerCase();
    const matches = (library) => !query
        || library.label.toLowerCase().includes(query)
        || library.id.includes(query);
    const favs = favorites
        .map((id) => libraries.find((library) => library.id === id))
        .filter(Boolean)
        .filter(matches);
    const rest = libraries
        .filter((library) => !favorites.includes(library.id))
        .filter(matches);

    return { favs, rest };
}

function LibraryRow({ library, isAll = false, total = 0, current, active, pinned, onSelect, onHover, onTogglePin, id }) {
    const label = isAll ? __('All libraries', 'wpicons') : library.label;
    const count = isAll ? total : library.count;
    const rowId = isAll ? 'all' : library.id;

    return (
        <li
            id={id}
            role="option"
            aria-selected={current === rowId}
            className={`wpicons__libitem${current === rowId ? ' is-current' : ''}${active ? ' is-active' : ''}`}
            onMouseEnter={onHover}
            onMouseDown={(event) => event.preventDefault()}
            onClick={() => onSelect(rowId)}
        >
            {isAll ? (
                <span className="wpicons__lib-glyph" style={{ '--wpicons-glyph-size': '16px' }} aria-hidden="true">
                    <LucideSvg name="layers" size={16} />
                </span>
            ) : (
                <LibraryGlyph library={library} size={16} />
            )}
            <span className="wpicons__libitem__label">{label}</span>
            <span className="wpicons__libitem__count">{formatCount(count)}</span>
            {!isAll && onTogglePin ? (
                <button
                    type="button"
                    tabIndex={-1}
                    className={`wpicons__libitem__pin${pinned ? ' is-pinned' : ''}`}
                    aria-pressed={pinned}
                    aria-label={pinned
                        /* translators: %s: icon library name */
                        ? sprintf(__('Unpin %s', 'wpicons'), label)
                        /* translators: %s: icon library name */
                        : sprintf(__('Pin %s to favorites', 'wpicons'), label)}
                    title={pinned ? __('Unpin', 'wpicons') : __('Pin to favorites', 'wpicons')}
                    onClick={(event) => {
                        event.stopPropagation();
                        onTogglePin(library.id);
                    }}
                >
                    <LucideSvg name="star" size={14} />
                </button>
            ) : null}
        </li>
    );
}

/**
 * Filterable library list used by the switcher popover (picker) and the rail (Library page).
 * Arrow keys move, Enter selects, "*" pins the highlighted library.
 */
function LibraryList({
    libraries,
    total,
    value,
    favorites,
    onToggleFavorite,
    onSelect,
    allowAll = true,
    autoFocus = false,
    onEscape,
    className = '',
}) {
    const listId = useId();
    const [filter, setFilter] = useState('');
    const [activeIndex, setActiveIndex] = useState(-1);
    const { favs, rest } = useMemo(() => sections(libraries, favorites, filter), [libraries, favorites, filter]);
    const rows = useMemo(() => [
        ...(allowAll ? [{ id: 'all', isAll: true }] : []),
        ...favs.map((library) => ({ id: library.id, library })),
        ...rest.map((library) => ({ id: library.id, library })),
    ], [allowAll, favs, rest]);

    useEffect(() => {
        const index = rows.findIndex((row) => row.id === value);
        setActiveIndex(index >= 0 ? index : 0);
    }, [filter]);

    const onKeyDown = (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (!rows.length) {
                return;
            }
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActiveIndex((index) => (index + step + rows.length) % rows.length);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const row = rows[activeIndex] || rows[0];
            if (row) {
                onSelect(row.id);
            }
        } else if (event.key === '*' && filter === '') {
            const row = rows[activeIndex];
            if (row && !row.isAll && onToggleFavorite) {
                event.preventDefault();
                onToggleFavorite(row.id);
            }
        } else if (event.key === 'Escape' && onEscape) {
            event.preventDefault();
            event.stopPropagation();
            onEscape();
        }
    };

    const optionId = (index) => `${listId}-opt-${index}`;
    const renderRow = (row, index) => (
        <LibraryRow
            key={row.id}
            id={optionId(index)}
            library={row.library}
            isAll={Boolean(row.isAll)}
            total={total}
            current={value}
            active={index === activeIndex}
            pinned={!row.isAll && favorites.includes(row.id)}
            onHover={() => setActiveIndex(index)}
            onSelect={onSelect}
            onTogglePin={onToggleFavorite}
        />
    );

    const favOffset = allowAll ? 1 : 0;

    return (
        <div className={`wpicons__liblist${className ? ` ${className}` : ''}`}>
            <label className="wpicons__liblist__filter">
                <span className="screen-reader-text">{__('Filter libraries', 'wpicons')}</span>
                <LucideSvg name="search" size={16} />
                <input
                    type="search"
                    value={filter}
                    placeholder={__('Filter libraries', 'wpicons')}
                    autoFocus={autoFocus}
                    role="combobox"
                    aria-expanded="true"
                    aria-controls={listId}
                    aria-activedescendant={activeIndex >= 0 ? optionId(activeIndex) : undefined}
                    onChange={(event) => setFilter(event.target.value)}
                    onKeyDown={onKeyDown}
                />
            </label>
            <ul id={listId} role="listbox" className="wpicons__liblist__list" aria-label={__('Icon libraries', 'wpicons')}>
                {allowAll ? renderRow(rows[0], 0) : null}
                {favs.length ? (
                    <li role="presentation" className="wpicons__liblist__section">{__('Favorites', 'wpicons')}</li>
                ) : null}
                {favs.map((library, index) => renderRow({ id: library.id, library }, favOffset + index))}
                {rest.length ? (
                    <li role="presentation" className="wpicons__liblist__section">{__('All', 'wpicons')}</li>
                ) : null}
                {rest.map((library, index) => renderRow({ id: library.id, library }, favOffset + favs.length + index))}
                {!favs.length && !rest.length ? (
                    <li role="presentation" className="wpicons__liblist__empty">{__('No libraries match.', 'wpicons')}</li>
                ) : null}
            </ul>
        </div>
    );
}

/** Library rail for the Library admin page. */
export function LibraryRail(props) {
    return (
        <nav className="wpicons__rail" aria-label={__('Icon libraries', 'wpicons')}>
            <LibraryList {...props} className="wpicons__liblist--rail" />
        </nav>
    );
}

/**
 * Switcher button + popover (picker dialog).
 */
export default function LibrarySwitcher({
    libraries,
    total,
    value,
    favorites,
    onToggleFavorite,
    onChange,
    open,
    onOpenChange,
    allowAll = true,
}) {
    const rootRef = useRef(null);
    const buttonRef = useRef(null);
    const current = libraries.find((library) => library.id === value);

    useEffect(() => {
        if (!open || !rootRef.current) {
            return undefined;
        }
        const doc = rootRef.current.ownerDocument;
        const onDown = (event) => {
            if (rootRef.current && !rootRef.current.contains(event.target)) {
                onOpenChange(false);
            }
        };
        doc.addEventListener('mousedown', onDown);
        return () => doc.removeEventListener('mousedown', onDown);
    }, [open]);

    const close = () => {
        onOpenChange(false);
        buttonRef.current?.focus();
    };

    return (
        <div className="wpicons__switcher" ref={rootRef} data-wpicons-popover={open ? 'open' : undefined}>
            <button
                ref={buttonRef}
                type="button"
                className={`wpicons__switcher__button${open ? ' is-open' : ''}`}
                aria-haspopup="listbox"
                aria-expanded={open}
                onClick={() => onOpenChange(!open)}
            >
                {value === 'all' ? (
                    <span className="wpicons__lib-glyph" style={{ '--wpicons-glyph-size': '18px' }} aria-hidden="true">
                        <LucideSvg name="layers" size={18} />
                    </span>
                ) : (
                    <LibraryGlyph library={current} size={18} />
                )}
                <span className="wpicons__switcher__label">
                    {value === 'all' ? __('All libraries', 'wpicons') : (current?.label || '')}
                </span>
                <LucideSvg name={open ? 'chevron-up' : 'chevron-down'} size={16} />
            </button>
            {open ? (
                <div className="wpicons__switcher__popover">
                    <LibraryList
                        libraries={libraries}
                        total={total}
                        value={value}
                        favorites={favorites}
                        onToggleFavorite={onToggleFavorite}
                        allowAll={allowAll}
                        autoFocus
                        onEscape={close}
                        onSelect={(id) => {
                            onChange(id);
                            close();
                        }}
                    />
                    <p className="wpicons__switcher__hint">{__('↑↓ to move · Enter to select · ★ to pin', 'wpicons')}</p>
                </div>
            ) : null}
        </div>
    );
}

/**
 * Favorite libraries as one-click pills, plus "+N more" that opens the switcher.
 */
export function QuickPills({ libraries, favorites, value, onChange, onMore }) {
    const pills = favorites
        .map((id) => libraries.find((library) => library.id === id))
        .filter(Boolean)
        .slice(0, 4);
    const current = libraries.find((library) => library.id === value);
    if (current && !pills.some((library) => library.id === current.id)) {
        pills.unshift(current);
    }
    const more = Math.max(0, libraries.length - pills.length);

    return (
        <div className="wpicons__pills" role="group" aria-label={__('Favorite libraries', 'wpicons')}>
            {pills.map((library) => (
                <button
                    key={library.id}
                    type="button"
                    className={`wpicons__pills__pill${library.id === value ? ' is-active' : ''}`}
                    aria-pressed={library.id === value}
                    onClick={() => onChange(library.id)}
                >
                    <LibraryGlyph library={library} size={14} />
                    <span>{library.label}</span>
                </button>
            ))}
            {more > 0 ? (
                <button type="button" className="wpicons__pills__pill wpicons__pills__pill--more" onClick={onMore}>
                    {/* translators: %d: number of other icon libraries */}
                    {sprintf(__('+%d more', 'wpicons'), more)}
                </button>
            ) : null}
        </div>
    );
}