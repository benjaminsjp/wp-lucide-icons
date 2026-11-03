import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';
import { fetchIcons, fetchLibraries } from './api';
import LucideSvg from './icons';

const pageCache = new Map();

function pageCacheKey(library, search, page) {
    return `${library}|${search}|${page}`;
}

function loadPage(library, search, page) {
    const key = pageCacheKey(library, search, page);
    if (pageCache.has(key)) {
        return Promise.resolve(pageCache.get(key));
    }

    return fetchIcons({ library, search, page, perPage: 200 }).then((data) => {
        pageCache.set(key, data);
        return data;
    });
}

function prefetchPage(library, search, page, totalPages) {
    if (!library || page < 1 || page > totalPages) {
        return;
    }
    if (pageCache.has(pageCacheKey(library, search, page))) {
        return;
    }
    loadPage(library, search, page).catch(() => {});
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

export default function IconPicker({
    library,
    name,
    onLibraryChange,
    onSelect,
    allowedLibraries,
    variant = 'default',
}) {
    const isDialog = variant === 'dialog';
    const isLibrary = variant === 'library';
    const gridRef = useRef(null);
    const sentinelRef = useRef(null);
    const filterRef = useRef({ library, search: '' });
    const loadingRef = useRef(false);
    const pageRef = useRef(1);
    const totalPagesRef = useRef(1);
    const [libraries, setLibraries] = useState([]);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [items, setItems] = useState([]);
    const [total, setTotal] = useState(0);
    const [totalPages, setTotalPages] = useState(1);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    pageRef.current = page;
    totalPagesRef.current = totalPages;

    useEffect(() => {
        let cancelled = false;
        fetchLibraries()
            .then((data) => {
                if (cancelled) {
                    return;
                }
                const list = (data.libraries || []).filter((item) => {
                    if (!allowedLibraries || allowedLibraries.length === 0) {
                        return true;
                    }
                    return allowedLibraries.includes(item.id);
                });
                setLibraries(list);
                if (list.length && !list.some((item) => item.id === library)) {
                    onLibraryChange?.(list[0].id);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError(__('Could not load icon libraries.', 'wpicons'));
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setSearch(searchInput.trim());
            setPage(1);
        }, 250);

        return () => window.clearTimeout(timer);
    }, [searchInput]);

    useEffect(() => {
        if (!library) {
            return undefined;
        }

        const filterChanged = filterRef.current.library !== library || filterRef.current.search !== search;
        filterRef.current = { library, search };

        if (filterChanged && page !== 1) {
            loadingRef.current = true;
            setItems([]);
            setPage(1);
            return undefined;
        }

        const append = (isDialog || isLibrary) && !filterChanged && page > 1;
        const cached = pageCache.get(pageCacheKey(library, search, page));

        const apply = (data) => {
            setTotal(data.total || 0);
            setTotalPages(data.totalPages || 1);
            setError('');
            const next = data.items || [];
            if (append) {
                setItems((current) => {
                    const seen = new Set(current.map((item) => item.name));
                    return [...current, ...next.filter((item) => !seen.has(item.name))];
                });
            } else {
                setItems(next);
            }
            prefetchPage(library, search, page + 1, data.totalPages || 1);
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
        loadPage(library, search, page)
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
    }, [isDialog, library, search, page]);

    useEffect(() => {
        if (page === 1 && gridRef.current) {
            gridRef.current.scrollTop = 0;
        }
    }, [library, search, page]);

    useEffect(() => {
        if (!isDialog || !sentinelRef.current) {
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
    }, [isDialog, items.length, loading, page, totalPages]);

    const currentLibrary = useMemo(
        () => libraries.find((item) => item.id === library),
        [libraries, library],
    );

    const initialLoad = loading && page === 1;
    const searchPlaceholder = isDialog || isLibrary
        ? __('Search for an icon, e.g. "arrow" or "home"', 'wpicons')
        : __('Search icons', 'wpicons');

    const tabs = (
        <div className="wpicons__picker__tabs" role="tablist">
            {libraries.map((item) => (
                <button
                    key={item.id}
                    type="button"
                    className={`wpicons__picker__tab${item.id === library ? ' is-active' : ''}`}
                    disabled={initialLoad}
                    onClick={() => {
                        onLibraryChange?.(item.id);
                        setPage(1);
                    }}
                >
                    {item.label}
                    {isDialog || isLibrary ? null : (
                        <span className="wpicons__picker__count">{item.count}</span>
                    )}
                </button>
            ))}
        </div>
    );

    const searchField = (
        <label className="wpicons__picker__search">
            <span className="screen-reader-text">{__('Search icons', 'wpicons')}</span>
            {isLibrary ? <LucideSvg name="search" size={16} className="wpicons__picker__search-icon" /> : null}
            <input
                type="search"
                value={searchInput}
                placeholder={searchPlaceholder}
                autoFocus={isDialog}
                onChange={(event) => setSearchInput(event.target.value)}
            />
        </label>
    );

    return (
        <div className={`wpicons__picker${isDialog ? ' wpicons__picker--dialog' : ''}${isLibrary ? ' wpicons__picker--library' : ''}`}>
            <div className="wpicons__picker__toolbar">
                {isLibrary ? (
                    <>
                        {searchField}
                        <div className="wpicons__picker__tabs-row">
                            {tabs}
                            {currentLibrary ? (
                                <span className="wpicons__picker__meta">
                                    {__('Version', 'wpicons')}: {currentLibrary.version}
                                </span>
                            ) : null}
                        </div>
                    </>
                ) : (
                    <>
                        {isDialog ? searchField : tabs}
                        {isDialog ? tabs : searchField}
                    </>
                )}
            </div>
            {!isDialog && !isLibrary && currentLibrary ? (
                <p className="wpicons__picker__meta">
                    {__('Version', 'wpicons')}: {currentLibrary.version}
                    {total ? ` · ${total}` : ''}
                </p>
            ) : null}
            {error ? <p className="wpicons__picker__error">{error}</p> : null}
            <div
                ref={gridRef}
                className={`wpicons__picker__grid${initialLoad ? ' is-loading' : ''}`}
                aria-busy={loading}
            >
                {initialLoad ? (
                    <div className="wpicons__picker__loading">
                        <Spinner />
                        <span>{__('Loading icons', 'wpicons')}</span>
                    </div>
                ) : null}
                {items.map((item) => (
                    <button
                        key={`${library}-${item.name}`}
                        type="button"
                        className={`wpicons__picker__item${item.name === name ? ' is-selected' : ''}`}
                        onClick={() => onSelect?.({ library, name: item.name, svg: item.svg })}
                        title={item.name}
                    >
                        <span
                            className="wpicons__picker__glyph"
                            dangerouslySetInnerHTML={{ __html: item.svg }}
                        />
                        <span className="wpicons__picker__name">{item.name}</span>
                    </button>
                ))}
                {!initialLoad && items.length === 0 ? (
                    <p className="wpicons__picker__empty">{__('No icons match that search.', 'wpicons')}</p>
                ) : null}
                {isDialog ? (
                    <div ref={sentinelRef} className="wpicons__picker__more">
                        {loading && page > 1 ? <Spinner /> : null}
                    </div>
                ) : null}
            </div>
            {isLibrary ? (
                <div className="wpicons__picker__footer">
                    <p className="wpicons__picker__status">
                        {total ? sprintf(
                            /* translators: 1: number of icons currently listed, 2: total matching icons */
                            __('%1$d of %2$d icons', 'wpicons'),
                            items.length,
                            total,
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
                </div>
            ) : null}
            {isDialog && total ? (
                <p className="wpicons__picker__status">
                    {sprintf(
                        /* translators: 1: number of icons currently listed, 2: total matching icons */
                        __('%1$d of %2$d icons', 'wpicons'),
                        items.length,
                        total,
                    )}
                </p>
            ) : null}
            {!isDialog && !isLibrary && totalPages > 1 ? (
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
