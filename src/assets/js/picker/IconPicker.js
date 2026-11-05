import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';
import { fetchIcons, searchIcons } from './api';
import LucideSvg from './icons';
import LibrarySwitcher, { LibraryRail, QuickPills } from './LibrarySwitcher';
import { backdropFor, useHoverContrast, useTileSurfaces } from './contrast';
import {
    brandNoteDismissed,
    dismissBrandNote,
    formatCount,
    LibraryGlyph,
    libraryVariants,
    resolveVariant,
    useFavorites,
    useLibraries,
} from './libraries';

const pageCache = new Map();
const searchCache = new Map();

function pageCacheKey(library, styleVariant, search, page) {
    return `${library}|${styleVariant}|${search}|${page}`;
}

function loadPage(library, styleVariant, search, page) {
    const key = pageCacheKey(library, styleVariant, search, page);
    if (pageCache.has(key)) {
        return Promise.resolve(pageCache.get(key));
    }

    return fetchIcons({ library, variant: styleVariant, search, page, perPage: 200 }).then((data) => {
        pageCache.set(key, data);
        return data;
    });
}

function prefetchPage(library, styleVariant, search, page, totalPages) {
    if (!library || page < 1 || page > totalPages) {
        return;
    }
    if (pageCache.has(pageCacheKey(library, styleVariant, search, page))) {
        return;
    }
    loadPage(library, styleVariant, search, page).catch(() => {});
}

function loadSearch(search) {
    if (searchCache.has(search)) {
        return Promise.resolve(searchCache.get(search));
    }

    return searchIcons({ search, perLibrary: 24 }).then((data) => {
        searchCache.set(search, data);
        return data;
    });
}

function scrollParent(node) {
    let el = node.parentElement;
    while (el) {
        const { overflowY } = window.getComputedStyle(el);
        if (
            (overflowY === 'auto' || overflowY === 'scroll')
            && el.scrollHeight > el.clientHeight + 1
        ) {
            return el;
        }
        el = el.parentElement;
    }
    return null;
}

function usesBrandColor(library, colorModes) {
    if (!library?.brandColors) {
        return false;
    }
    const mode = colorModes?.[library.id] ?? library.defaultColor;

    return String(mode || '').toLowerCase() === 'brand';
}

function IconTile({ item, library, selected, showLibraryBadge, brandColor, surfaces, onPick }) {
    // Every Simple Icons entry is a brand, so the tag would be noise there.
    const showBrandBadge = Boolean(item.brand) && library?.id !== 'simple-icons';
    const paint = brandColor && item.hex ? `#${item.hex}` : '';
    const glyphStyle = paint ? { color: paint } : undefined;
    // Runtime WCAG check of the displayed color (brand hex, or the tile's rendered ink) against the
    // rendered tile backgrounds (normal + selected); below 3:1 the glyph gets a contrasting chip.
    const backdrop = backdropFor(paint || surfaces?.ink, surfaces?.surfaces);
    const title = [
        item.title || item.name,
        showLibraryBadge && library ? library.label : '',
        item.brand ? __('Brand', 'wpicons') : '',
    ].filter(Boolean).join(' \u00b7 ');

    return (
        <button
            type="button"
            className={`wpicons__picker__item${selected ? ' is-selected' : ''}${item.brand ? ' is-brand' : ''}`}
            onClick={onPick}
            title={title}
            aria-pressed={selected}
        >
            <span
                className="wpicons__picker__glyph"
                style={glyphStyle}
                data-low-contrast={backdrop || undefined}
                dangerouslySetInnerHTML={{ __html: item.svg }}
            />
            <span className="wpicons__picker__name">{item.name}</span>
            {showBrandBadge ? (
                <span className="wpicons__picker__badge wpicons__picker__badge--brand" aria-label={__('Brand icon', 'wpicons')}>
                    <LucideSvg name="tag" size={9} />
                </span>
            ) : null}
            {showLibraryBadge && library ? (
                <span className="wpicons__picker__badge wpicons__picker__badge--library" aria-hidden="true">
                    <LibraryGlyph library={library} size={12} />
                </span>
            ) : null}
        </button>
    );
}

export default function IconPicker({
    library,
    name,
    selectedLibrary,
    onLibraryChange,
    onSelect,
    allowedLibraries,
    variant = 'default',
    styleVariants = {},
    colorModes = {},
}) {
    const isDialog = variant === 'dialog';
    const isLibrary = variant === 'library';
    const isRich = isDialog || isLibrary;
    const gridRef = useRef(null);
    const tileSurfaces = useTileSurfaces(gridRef, 'wpicons__picker__item', 'wpicons__picker__glyph');
    useHoverContrast(gridRef, '.wpicons__picker__item', '.wpicons__picker__glyph');
    const sentinelRef = useRef(null);
    const filterRef = useRef({ library, styleVariant: '', search: '' });
    const loadingRef = useRef(false);
    const pageRef = useRef(1);
    const totalPagesRef = useRef(1);
    const { libraries: allLibraries, total: allTotal, loaded, error: librariesError } = useLibraries();
    const [favorites, toggleFavorite] = useFavorites();
    const [switcherOpen, setSwitcherOpen] = useState(false);
    const [noteHidden, setNoteHidden] = useState(brandNoteDismissed);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [items, setItems] = useState([]);
    const [groups, setGroups] = useState([]);
    const [total, setTotal] = useState(0);
    const [totalPages, setTotalPages] = useState(1);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    pageRef.current = page;
    totalPagesRef.current = totalPages;

    const libraries = useMemo(() => allLibraries.filter((item) => (
        !allowedLibraries || allowedLibraries.length === 0 || allowedLibraries.includes(item.id)
    )), [allLibraries, allowedLibraries]);
    const libraryTotal = allowedLibraries && allowedLibraries.length
        ? libraries.reduce((sum, item) => sum + (Number(item.count) || 0), 0)
        : allTotal;

    const isAll = isRich && library === 'all';
    const currentLibrary = useMemo(
        () => libraries.find((item) => item.id === library),
        [libraries, library],
    );
    const styleVariant = currentLibrary ? resolveVariant(currentLibrary, styleVariants[currentLibrary.id]) : '';
    const selectedIn = selectedLibrary || library;

    // Fall back to the first library when the requested one is disabled or unknown.
    useEffect(() => {
        if (!loaded || !libraries.length) {
            return;
        }
        if (library === 'all' && isRich) {
            return;
        }
        if (!libraries.some((item) => item.id === library)) {
            onLibraryChange?.(libraries[0].id);
        }
    }, [loaded, libraries, library]);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setSearch(searchInput.trim());
            setPage(1);
        }, 250);

        return () => window.clearTimeout(timer);
    }, [searchInput]);

    // One library: paged, cached per style.
    useEffect(() => {
        if (!library || isAll || !loaded || !currentLibrary) {
            return undefined;
        }

        const previous = filterRef.current;
        const filterChanged = previous.library !== library
            || previous.search !== search
            || previous.styleVariant !== styleVariant;
        filterRef.current = { library, styleVariant, search };

        if (filterChanged && page !== 1) {
            loadingRef.current = true;
            setItems([]);
            setPage(1);
            return undefined;
        }

        const append = isRich && !filterChanged && page > 1;
        const cached = pageCache.get(pageCacheKey(library, styleVariant, search, page));

        const apply = (data) => {
            setTotal(data.total || 0);
            setTotalPages(data.totalPages || 1);
            setError('');
            const next = (data.items || []).map((item) => ({ ...item, library }));
            if (append) {
                setItems((current) => {
                    const seen = new Set(current.map((item) => item.name));
                    return [...current, ...next.filter((item) => !seen.has(item.name))];
                });
            } else {
                setItems(next);
            }
            prefetchPage(library, styleVariant, search, page + 1, data.totalPages || 1);
        };

        if (cached) {
            apply(cached);
            loadingRef.current = false;
            setLoading(false);
            return undefined;
        }

        let cancelled = false;
        loadingRef.current = true;
        setLoading(true);
        setError('');
        loadPage(library, styleVariant, search, page)
            .then((data) => {
                if (!cancelled) {
                    apply(data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError(__('Could not load icons.', 'wpicons'));
                    if (!append) {
                        setItems([]);
                    }
                }
            })
            .finally(() => {
                if (!cancelled) {
                    loadingRef.current = false;
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [isRich, isAll, loaded, currentLibrary, library, styleVariant, search, page]);

    // All libraries: one grouped search request.
    useEffect(() => {
        if (!isAll) {
            setGroups([]);
            return undefined;
        }
        filterRef.current = { library, styleVariant: '', search };
        setItems([]);
        if (!search) {
            setGroups([]);
            setTotal(0);
            setLoading(false);
            return undefined;
        }

        let cancelled = false;
        setLoading(true);
        setError('');
        loadSearch(search)
            .then((data) => {
                if (!cancelled) {
                    setGroups(Array.isArray(data.groups) ? data.groups : []);
                    setTotal(Number(data.total) || 0);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setGroups([]);
                    setError(__('Could not load icons.', 'wpicons'));
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [isAll, search]);

    useEffect(() => {
        if (page === 1 && gridRef.current) {
            gridRef.current.scrollTop = 0;
        }
    }, [library, search, page, styleVariant]);

    useEffect(() => {
        if (!isDialog || isAll || !sentinelRef.current) {
            return undefined;
        }

        const node = sentinelRef.current;
        const observer = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) {
                return;
            }
            if (loadingRef.current || pageRef.current >= totalPagesRef.current) {
                return;
            }
            loadingRef.current = true;
            setPage((value) => {
                if (value >= totalPagesRef.current) {
                    loadingRef.current = false;
                    return value;
                }
                return value + 1;
            });
        }, {
            root: scrollParent(node),
            rootMargin: '200px',
        });

        observer.observe(node);
        return () => observer.disconnect();
    }, [isDialog, isAll, items.length, loading, page, totalPages]);

    // Favorites first, then registry order.
    const orderedGroups = useMemo(() => {
        const rank = (id) => {
            const fav = favorites.indexOf(id);
            if (fav >= 0) {
                return fav;
            }
            const index = libraries.findIndex((item) => item.id === id);
            return 100 + (index >= 0 ? index : 99);
        };
        return groups
            .filter((group) => libraries.some((item) => item.id === group.library))
            .sort((a, b) => rank(a.library) - rank(b.library));
    }, [groups, favorites, libraries]);

    const visibleTotal = isAll
        ? orderedGroups.reduce((sum, group) => sum + (Number(group.total) || 0), 0)
        : total;
    const hasBrand = isAll
        ? orderedGroups.some((group) => (group.items || []).some((item) => item.brand))
        : items.some((item) => item.brand);

    const initialLoad = loading && page === 1 && (!isAll || Boolean(search));
    const searchPlaceholder = isRich
        ? __('Search for an icon, e.g. "arrow" or "home"', 'wpicons')
        : __('Search icons', 'wpicons');

    const changeLibrary = (id) => {
        onLibraryChange?.(id);
        setPage(1);
    };

    const pick = (item, itemLibrary) => {
        onSelect?.({
            library: itemLibrary,
            name: item.name,
            svg: item.svg,
            brand: Boolean(item.brand),
            hex: item.hex || '',
            title: item.title || '',
            guidelines: item.guidelines || '',
        });
    };

    const tabs = (
        <div className="wpicons__picker__tabs" role="tablist">
            {libraries.map((item) => (
                <button
                    key={item.id}
                    type="button"
                    className={`wpicons__picker__tab${item.id === library ? ' is-active' : ''}`}
                    disabled={initialLoad}
                    onClick={() => changeLibrary(item.id)}
                >
                    {item.label}
                    <span className="wpicons__picker__count">{item.count}</span>
                </button>
            ))}
        </div>
    );

    const searchField = (
        <label className="wpicons__picker__search">
            <span className="screen-reader-text">{__('Search icons', 'wpicons')}</span>
            {isRich ? <LucideSvg name="search" size={16} className="wpicons__picker__search-icon" /> : null}
            <input
                type="search"
                value={searchInput}
                placeholder={searchPlaceholder}
                autoFocus={isDialog}
                onChange={(event) => setSearchInput(event.target.value)}
            />
        </label>
    );

    const brandNote = hasBrand && !noteHidden && isRich ? (
        <div className="wpicons__picker__note" role="note">
            <LucideSvg name="tag" size={14} />
            <span>{__('Includes brand icons — trademarks of their owners; no endorsement implied.', 'wpicons')}</span>
            <button
                type="button"
                className="wpicons__picker__note-dismiss"
                aria-label={__('Dismiss', 'wpicons')}
                title={__('Dismiss', 'wpicons')}
                onClick={() => {
                    dismissBrandNote();
                    setNoteHidden(true);
                }}
            >
                <LucideSvg name="x" size={14} />
            </button>
        </div>
    ) : null;

    const contextRow = isLibrary ? (
        <div className="wpicons__picker__context">
            {isAll ? (
                <>
                    <span className="wpicons__lib-glyph" style={{ '--wpicons-glyph-size': '18px' }} aria-hidden="true">
                        <LucideSvg name="layers" size={18} />
                    </span>
                    <strong>{__('All libraries', 'wpicons')}</strong>
                    <span className="wpicons__picker__context-meta">
                        {sprintf(
                            /* translators: 1: number of icons, 2: number of libraries */
                            __('%1$s icons · %2$d libraries', 'wpicons'),
                            formatCount(libraryTotal),
                            libraries.length,
                        )}
                    </span>
                </>
            ) : currentLibrary ? (
                <>
                    <LibraryGlyph library={currentLibrary} size={18} />
                    <strong>{currentLibrary.label}</strong>
                    <span className="wpicons__picker__context-meta">
                        {sprintf(
                            /* translators: 1: npm package name, 2: version, 3: number of icons */
                            __('%1$s %2$s · %3$s icons', 'wpicons'),
                            currentLibrary.package || '',
                            currentLibrary.version || '',
                            formatCount(search ? currentLibrary.count : (total || currentLibrary.count)),
                        )}
                    </span>
                    {styleVariant && libraryVariants(currentLibrary).length ? (
                        <span className="wpicons__picker__style-badge">
                            <LucideSvg name="paintbrush" size={12} />
                            {libraryVariants(currentLibrary).find((option) => option.id === styleVariant)?.label || styleVariant}
                        </span>
                    ) : null}
                </>
            ) : null}
        </div>
    ) : null;

    const singleGrid = (
        <>
            {items.map((item) => (
                <IconTile
                    surfaces={tileSurfaces}
                    key={`${library}-${item.name}`}
                    item={item}
                    library={currentLibrary}
                    selected={item.name === name && selectedIn === library}
                    brandColor={usesBrandColor(currentLibrary, colorModes)}
                    onPick={() => pick(item, library)}
                />
            ))}
            {!initialLoad && items.length === 0 && !error ? (
                <p className="wpicons__picker__empty">{__('No icons match that search.', 'wpicons')}</p>
            ) : null}
            {isDialog ? (
                <div ref={sentinelRef} className="wpicons__picker__more">
                    {loading && page > 1 ? <Spinner /> : null}
                </div>
            ) : null}
        </>
    );

    const groupedGrid = (
        <>
            {!search ? (
                <div className="wpicons__picker__prompt">
                    <LucideSvg name="search" size={20} />
                    <p>
                        {sprintf(
                            /* translators: 1: number of icons, 2: number of libraries */
                            __('Type to search %1$s icons across %2$d libraries.', 'wpicons'),
                            formatCount(libraryTotal),
                            libraries.length,
                        )}
                    </p>
                </div>
            ) : null}
            {orderedGroups.map((group) => {
                const groupLibrary = libraries.find((item) => item.id === group.library);
                const brandColor = usesBrandColor(groupLibrary, colorModes);
                return (
                    <section key={group.library} className="wpicons__picker__group" aria-label={groupLibrary?.label || group.label}>
                        <header className="wpicons__picker__group-header">
                            <LibraryGlyph library={groupLibrary} size={14} />
                            <strong>{groupLibrary?.label || group.label}</strong>
                            <span className="wpicons__picker__group-count">
                                {'\u00b7 '}
                                {sprintf(
                                    /* translators: %s: number of matching icons */
                                    _n('%s result', '%s results', Number(group.total) || 0, 'wpicons'),
                                    formatCount(group.total),
                                )}
                            </span>
                            <button
                                type="button"
                                className="wpicons__picker__see-all"
                                onClick={() => changeLibrary(group.library)}
                            >
                                {__('See all', 'wpicons')}
                                <LucideSvg name="arrow-right" size={12} />
                            </button>
                        </header>
                        <div className="wpicons__picker__group-grid">
                            {(group.items || []).map((item) => (
                                <IconTile
                    surfaces={tileSurfaces}
                                    key={`${group.library}-${item.name}`}
                                    item={item}
                                    library={groupLibrary}
                                    selected={item.name === name && selectedIn === group.library}
                                    showLibraryBadge
                                    brandColor={brandColor}
                                    onPick={() => pick(item, group.library)}
                                />
                            ))}
                        </div>
                    </section>
                );
            })}
            {search && !initialLoad && orderedGroups.length === 0 && !error ? (
                <p className="wpicons__picker__empty">{__('No icons match that search.', 'wpicons')}</p>
            ) : null}
        </>
    );

    const grid = (
        <div
            ref={gridRef}
            className={`wpicons__picker__grid${isAll ? ' is-grouped' : ''}${initialLoad ? ' is-loading' : ''}`}
            aria-busy={loading}
        >
            {initialLoad ? (
                <div className="wpicons__picker__loading">
                    <Spinner />
                    <span>{__('Loading icons', 'wpicons')}</span>
                </div>
            ) : null}
            {isAll ? groupedGrid : singleGrid}
        </div>
    );

    const groupedStatus = isAll && search && orderedGroups.length ? (
        <p className="wpicons__picker__status">
            {sprintf(
                /* translators: 1: number of results, 2: number of libraries */
                _n(
                    '%1$s result across %2$d libraries · grouped by library, favorites first',
                    '%1$s results across %2$d libraries · grouped by library, favorites first',
                    visibleTotal,
                    'wpicons',
                ),
                formatCount(visibleTotal),
                orderedGroups.length,
            )}
        </p>
    ) : null;

    const errorMessage = error || librariesError;

    if (isLibrary) {
        return (
            <div className="wpicons__picker wpicons__picker--library">
                <LibraryRail
                    libraries={libraries}
                    total={libraryTotal}
                    value={library}
                    favorites={favorites}
                    onToggleFavorite={toggleFavorite}
                    onSelect={changeLibrary}
                />
                <div className="wpicons__picker__main">
                    <div className="wpicons__picker__toolbar">{searchField}</div>
                    {contextRow}
                    {brandNote}
                    {errorMessage ? <p className="wpicons__picker__error">{errorMessage}</p> : null}
                    {grid}
                    <div className="wpicons__picker__footer">
                        {isAll ? groupedStatus : (
                            <>
                                <p className="wpicons__picker__status">
                                    {total ? sprintf(
                                        /* translators: 1: number of icons currently listed, 2: total matching icons */
                                        __('%1$s of %2$s icons', 'wpicons'),
                                        formatCount(items.length),
                                        formatCount(total),
                                    ) : ''}
                                </p>
                                {page < totalPages ? (
                                    <button
                                        type="button"
                                        className="wpicons__btn wpicons__btn--ghost"
                                        disabled={loading}
                                        onClick={() => setPage((value) => Math.min(value + 1, totalPagesRef.current))}
                                    >
                                        <LucideSvg name="chevron-down" size={16} />
                                        <span>{loading ? __('Loading icons', 'wpicons') : __('Load more', 'wpicons')}</span>
                                    </button>
                                ) : null}
                            </>
                        )}
                    </div>
                </div>
            </div>
        );
    }

    if (isDialog) {
        return (
            <div className="wpicons__picker wpicons__picker--dialog">
                <div className="wpicons__picker__toolbar">
                    <div className="wpicons__picker__search-row">
                        <LibrarySwitcher
                            libraries={libraries}
                            total={libraryTotal}
                            value={library}
                            favorites={favorites}
                            onToggleFavorite={toggleFavorite}
                            onChange={changeLibrary}
                            open={switcherOpen}
                            onOpenChange={setSwitcherOpen}
                        />
                        {searchField}
                    </div>
                    <QuickPills
                        libraries={libraries}
                        favorites={favorites}
                        value={library}
                        onChange={changeLibrary}
                        onMore={() => setSwitcherOpen(true)}
                    />
                </div>
                {brandNote}
                {errorMessage ? <p className="wpicons__picker__error">{errorMessage}</p> : null}
                {grid}
                {isAll ? groupedStatus : (total ? (
                    <p className="wpicons__picker__status">
                        {sprintf(
                            /* translators: 1: number of icons currently listed, 2: total matching icons */
                            __('%1$s of %2$s icons', 'wpicons'),
                            formatCount(items.length),
                            formatCount(total),
                        )}
                    </p>
                ) : null)}
            </div>
        );
    }

    return (
        <div className="wpicons__picker">
            <div className="wpicons__picker__toolbar">
                {tabs}
                {searchField}
            </div>
            {currentLibrary ? (
                <p className="wpicons__picker__meta">
                    {__('Version', 'wpicons')}: {currentLibrary.version}
                    {total ? ` · ${total}` : ''}
                </p>
            ) : null}
            {errorMessage ? <p className="wpicons__picker__error">{errorMessage}</p> : null}
            {grid}
            {totalPages > 1 ? (
                <div className="wpicons__picker__pager">
                    <button type="button" disabled={loading || page <= 1} onClick={() => setPage((value) => value - 1)}>
                        {__('Previous', 'wpicons')}
                    </button>
                    <span>
                        {page} / {totalPages}
                    </span>
                    <button
                        type="button"
                        disabled={loading || page >= totalPages}
                        onClick={() => setPage((value) => value + 1)}
                    >
                        {__('Next', 'wpicons')}
                    </button>
                </div>
            ) : null}
        </div>
    );
}