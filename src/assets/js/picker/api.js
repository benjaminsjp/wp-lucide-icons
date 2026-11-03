import apiFetch from '@wordpress/api-fetch';

export async function fetchLibraries() {
    return apiFetch({ path: '/wpicons/v1/libraries' });
}

export async function fetchIcons({ library, search = '', page = 1, perPage = 200, variant = '' }) {
    const params = new URLSearchParams({
        library,
        search,
        page: String(page),
        per_page: String(perPage),
    });
    if (variant) {
        params.set('variant', variant);
    }

    return apiFetch({ path: `/wpicons/v1/icons?${params.toString()}` });
}

/**
 * Searches every enabled library at once; results come back grouped per library.
 */
export async function searchIcons({ search, perLibrary = 24 }) {
    const params = new URLSearchParams({
        search,
        per_library: String(perLibrary),
    });

    return apiFetch({ path: `/wpicons/v1/search?${params.toString()}` });
}

export async function fetchIcon({ library, name, size = 24, color = 'currentColor', stroke = 2, variant = '' }) {
    const params = new URLSearchParams({
        library,
        name,
        size: String(size),
        color,
        stroke: String(stroke),
    });
    if (variant) {
        params.set('variant', variant);
    }

    return apiFetch({ path: `/wpicons/v1/icon?${params.toString()}` });
}