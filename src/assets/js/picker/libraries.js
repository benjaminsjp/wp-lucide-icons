import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fetchLibraries } from './api';

let librariesPromise = null;

/**
 * Library metadata is fetched once per page load and shared by every picker instance.
 */
export function loadLibraries() {
    if (!librariesPromise) {
        librariesPromise = fetchLibraries().catch((error) => {
            librariesPromise = null;
            throw error;
        });
    }

    return librariesPromise;
}

export function useLibraries() {
    const [state, setState] = useState({
        libraries: [],
        total: 0,
        licensesUrl: '',
        loaded: false,
        error: '',
    });

    useEffect(() => {
        let cancelled = false;
        loadLibraries()
            .then((data) => {
                if (cancelled) {
                    return;
                }
                setState({
                    libraries: Array.isArray(data?.libraries) ? data.libraries : [],
                    total: Number(data?.total) || 0,
                    licensesUrl: data?.licensesUrl || '',
                    loaded: true,
                    error: '',
                });
            })
            .catch(() => {
                if (!cancelled) {
                    setState((current) => ({
                        ...current,
                        loaded: true,
                        error: __('Could not load icon libraries.', 'wpicons'),
                    }));
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    return state;
}

const FAVORITES_KEY = 'wpicons.favoriteLibraries';
const DEFAULT_FAVORITES = ['lucide', 'material-symbols', 'phosphor'];
const BRAND_NOTE_KEY = 'wpicons.brandNoteDismissed';

function storage() {
    try {
        return window.localStorage;
    } catch (error) {
        return null;
    }
}

export function readFavorites() {
    try {
        const raw = storage()?.getItem(FAVORITES_KEY);
        if (raw) {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                return parsed.filter((id) => typeof id === 'string');
            }
        }
    } catch (error) {
        // Ignore unreadable storage.
    }

    return DEFAULT_FAVORITES.slice();
}

export function useFavorites() {
    const [favorites, setFavorites] = useState(readFavorites);

    const toggle = (id) => {
        setFavorites((current) => {
            const next = current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id];
            try {
                storage()?.setItem(FAVORITES_KEY, JSON.stringify(next));
            } catch (error) {
                // Private mode: favorites last for this page view only.
            }
            return next;
        });
    };

    return [favorites, toggle];
}

export function brandNoteDismissed() {
    try {
        return storage()?.getItem(BRAND_NOTE_KEY) === '1';
    } catch (error) {
        return false;
    }
}

export function dismissBrandNote() {
    try {
        storage()?.setItem(BRAND_NOTE_KEY, '1');
    } catch (error) {
        // Ignore.
    }
}

export function formatCount(value) {
    const number = Number(value) || 0;
    try {
        return number.toLocaleString();
    } catch (error) {
        return String(number);
    }
}

export function libraryVariants(library) {
    return Array.isArray(library?.variants) ? library.variants : [];
}

/** The library's style ("weight" for Phosphor) label for controls. */
export function variantLabel(library) {
    return library?.variantParam === 'weight' ? __('Weight', 'wpicons') : __('Style', 'wpicons');
}

export function resolveVariant(library, wanted) {
    const options = libraryVariants(library);
    if (!options.length) {
        return '';
    }
    if (wanted && options.some((option) => option.id === wanted)) {
        return wanted;
    }

    return library.defaultVariant || library.nativeVariant || options[0].id;
}

export function supportsStrokeFor(library, variant) {
    if (!library) {
        return false;
    }
    const strokeVariants = Array.isArray(library.strokeVariants) ? library.strokeVariants : [];

    return strokeVariants.includes(libraryVariants(library).length ? variant : '');
}

/** Brand color for Simple Icons ("brand" color mode), otherwise null. */
export function brandPaint(color, hex) {
    if (String(color || '').toLowerCase() !== 'brand') {
        return null;
    }

    return /^[0-9a-f]{6}$/i.test(String(hex || '')) ? `#${hex}` : 'currentColor';
}

/**
 * The library's own icon, rendered server-side (sanitized SVG from the bundled catalog).
 */
export function LibraryGlyph({ library, size = 18, className = '' }) {
    const style = { '--wpicons-glyph-size': `${size}px` };
    if (!library?.glyph) {
        return <span className={`wpicons__lib-glyph${className ? ` ${className}` : ''}`} style={style} aria-hidden="true" />;
    }

    return (
        <span
            className={`wpicons__lib-glyph${className ? ` ${className}` : ''}`}
            style={style}
            aria-hidden="true"
            dangerouslySetInnerHTML={{ __html: library.glyph }}
        />
    );
}