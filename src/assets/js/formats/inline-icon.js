import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { dispatch, select, subscribe } from '@wordpress/data';
import { insertObject, registerFormatType } from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import IconPickerModal from '../inline/IconPickerModal';
import {
    migrateInlineIconHtml,
    placeholderAttributes,
    pluginDefaults,
    resolveMaskSvg,
} from '../inline/placeholder';

export const FORMAT_NAME = 'wpicons/inline-icon';

function draftFromAttributes(attrs) {
    const defaults = pluginDefaults();
    return {
        size: attrs?.size ? Number(attrs.size) : (defaults.size || 24),
        matchText: !attrs?.size,
        color: attrs?.color || defaults.color || 'currentColor',
        stroke: Number(attrs?.stroke ?? defaults.stroke ?? 2),
        title: attrs?.title || '',
        variant: attrs?.variant || '',
    };
}

function updateObjectAt(value, index, attributes) {
    if (!value || index === null || index === undefined) {
        return null;
    }
    const source = value.replacements || [];
    const replacements = source.slice();
    const current = replacements[index];
    if (!current || current.type !== FORMAT_NAME) {
        return null;
    }
    replacements[index] = {
        type: FORMAT_NAME,
        attributes,
    };
    return {
        ...value,
        replacements,
    };
}

function Edit({
    value,
    onChange,
    onFocus,
    isObjectActive,
    activeObjectAttributes,
    contentRef,
}) {
    const defaults = pluginDefaults();
    const valueRef = useRef(value);
    const editIndexRef = useRef(null);
    const openedForRef = useRef('');
    const awaitingSavedRef = useRef(false);
    const savedIdentityRef = useRef('');
    valueRef.current = value;

    const [pickerOpen, setPickerOpen] = useState(false);
    const [applyEpoch, setApplyEpoch] = useState(0);
    const [library, setLibrary] = useState(defaults.library);
    const [pickerName, setPickerName] = useState('');
    const [pickerOptions, setPickerOptions] = useState(() => draftFromAttributes(null));

    const selectedKey = isObjectActive
        ? `${value.start}:${activeObjectAttributes?.library || ''}:${activeObjectAttributes?.iconName || ''}`
        : '';

    const openPicker = () => {
        const attrs = isObjectActive ? (activeObjectAttributes || {}) : {};
        editIndexRef.current = isObjectActive ? value.start : null;
        const options = isObjectActive ? draftFromAttributes(attrs) : draftFromAttributes(null);
        setLibrary(attrs.library || defaults.library);
        setPickerName(attrs.iconName || '');
        setPickerOptions(options);
        setPickerOpen(true);
    };

    useEffect(() => {
        const identity = isObjectActive
            ? `${activeObjectAttributes?.library || ''}:${activeObjectAttributes?.iconName || ''}`
            : '';

        if (awaitingSavedRef.current) {
            if (selectedKey && identity === savedIdentityRef.current) {
                awaitingSavedRef.current = false;
                openedForRef.current = selectedKey;
            }
            return;
        }

        if (!selectedKey) {
            openedForRef.current = '';
            return;
        }

        editIndexRef.current = value.start;
        if (openedForRef.current === selectedKey) {
            return;
        }

        openedForRef.current = selectedKey;
        const attrs = activeObjectAttributes || {};
        setLibrary(attrs.library || defaults.library);
        setPickerName(attrs.iconName || '');
        setPickerOptions(draftFromAttributes(attrs));
        setPickerOpen(true);
    }, [selectedKey, applyEpoch]);

    useEffect(() => {
        const root = contentRef?.current;
        if (!root || typeof root.addEventListener !== 'function') {
            return undefined;
        }

        const onClick = (event) => {
            const target = event.target;
            if (!target || typeof target.closest !== 'function' || !target.closest('img.wpicons-inline')) {
                return;
            }
            awaitingSavedRef.current = false;
            if (isObjectActive) {
                openPicker();
            }
        };
        root.addEventListener('click', onClick, true);
        return () => root.removeEventListener('click', onClick, true);
    }, [contentRef, isObjectActive, value.start, activeObjectAttributes]);

    const applyIcon = async (icon) => {
        const svg = await resolveMaskSvg({
            library: icon.library,
            name: icon.name,
            svg: icon.svg,
            stroke: icon.stroke,
            variant: icon.variant,
        });
        if (!svg) {
            awaitingSavedRef.current = false;
            setPickerOpen(false);
            return;
        }

        const attributes = placeholderAttributes({
            library: icon.library,
            name: icon.name,
            svg,
            color: icon.color,
            stroke: icon.stroke,
            size: icon.size,
            title: icon.title,
            variant: icon.variant,
            hex: icon.hex,
        });
        const index = editIndexRef.current;
        const current = valueRef.current;
        const replaced = updateObjectAt(current, index, attributes);
        savedIdentityRef.current = `${icon.library}:${icon.name}`;
        awaitingSavedRef.current = true;
        onChange(replaced || insertObject(current, {
            type: FORMAT_NAME,
            attributes,
        }));
        setApplyEpoch((epoch) => epoch + 1);
        onFocus?.();
        setPickerOpen(false);
    };

    return (
        <>
            <RichTextToolbarButton
                icon="star-filled"
                title={__('Insert icon', 'wpicons')}
                onClick={openPicker}
                isActive={isObjectActive}
            />
            {pickerOpen ? (
                <IconPickerModal
                    library={library}
                    name={pickerName}
                    variant={pickerOptions.variant}
                    size={pickerOptions.size}
                    color={pickerOptions.color}
                    stroke={pickerOptions.stroke}
                    title={pickerOptions.title}
                    matchText={pickerOptions.matchText}
                    allowMatchText
                    onLibraryChange={setLibrary}
                    onSelect={applyIcon}
                    onClose={() => setPickerOpen(false)}
                />
            ) : null}
        </>
    );
}

registerFormatType(FORMAT_NAME, {
    title: __('Icon', 'wpicons'),
    tagName: 'img',
    className: 'wpicons-inline',
    object: true,
    attributes: {
        url: 'data-src',
        src: 'src',
        alt: 'alt',
        iconStyle: 'style',
        library: 'data-library',
        iconName: 'data-name',
        color: 'data-color',
        hex: 'data-hex',
        stroke: 'data-stroke',
        size: 'data-size',
        title: 'data-title',
        variant: 'data-variant',
    },
    edit: Edit,
});

function migrateBlockTree(blocks) {
    blocks.forEach((block) => {
        const updates = {};
        Object.entries(block.attributes || {}).forEach(([key, value]) => {
            if (typeof value !== 'string' || !value.includes('wpicons-inline') || !value.includes('<span')) {
                return;
            }
            const next = migrateInlineIconHtml(value);
            if (next !== value) {
                updates[key] = next;
            }
        });
        if (Object.keys(updates).length > 0) {
            dispatch('core/block-editor').updateBlockAttributes(block.clientId, updates);
        }
        if (block.innerBlocks && block.innerBlocks.length) {
            migrateBlockTree(block.innerBlocks);
        }
    });
}

function watchForLegacyIcons() {
    if (typeof subscribe !== 'function' || !select('core/block-editor')) {
        return;
    }

    let started = false;
    const unsubscribe = subscribe(() => {
        if (started) {
            return;
        }
        const editorSelect = select('core/editor');
        if (editorSelect && typeof editorSelect.__unstableIsEditorReady === 'function') {
            if (!editorSelect.__unstableIsEditorReady()) {
                return;
            }
        } else if (!select('core/block-editor').getBlocks().length) {
            return;
        }

        started = true;
        unsubscribe();
        window.setTimeout(() => {
            const blocks = select('core/block-editor').getBlocks();
            if (blocks.length) {
                migrateBlockTree(blocks);
            }
        }, 0);
    });
}

watchForLegacyIcons();
