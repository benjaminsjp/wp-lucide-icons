const STYLE_MARK = 'data-wpicons-picker-style';

export function getMountDocument() {
    try {
        if (window.top?.document?.body) {
            return window.top.document;
        }
    } catch (error) {
        // Cross-origin iframe.
    }

    return document;
}

function styleKey(node) {
    if (node.tagName === 'LINK') {
        return node.href || node.id || '';
    }

    return node.id || node.textContent || '';
}

function isPickerStyle(node) {
    const href = node.href || '';
    const id = node.id || '';
    const text = node.tagName === 'STYLE' ? (node.textContent || '') : '';

    return href.includes('wpicons')
        || href.includes('components')
        || id.includes('wpicons')
        || id.includes('wp-components')
        || text.includes('--wpicons')
        || text.includes('wpicons');
}

export function ensurePickerStyles(targetDoc) {
    if (!targetDoc?.head || targetDoc === document) {
        return;
    }

    const existing = new Set(
        [...targetDoc.querySelectorAll('link[rel="stylesheet"], style')].map(styleKey),
    );

    document.querySelectorAll('link[rel="stylesheet"], style').forEach((node) => {
        if (!isPickerStyle(node)) {
            return;
        }

        const key = styleKey(node);
        if (!key || existing.has(key)) {
            return;
        }

        const clone = targetDoc.importNode(node, true);
        clone.setAttribute(STYLE_MARK, 'true');
        targetDoc.head.appendChild(clone);
        existing.add(key);
    });
}
