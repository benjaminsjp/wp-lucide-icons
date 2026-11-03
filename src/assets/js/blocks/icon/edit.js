import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    InspectorControls,
    useBlockProps,
} from '@wordpress/block-editor';
import { Button, PanelBody } from '@wordpress/components';
import IconPickerModal from '../../inline/IconPickerModal';
import { fetchIcon } from '../../picker/api';

const defaults = window.WPIconsAdmin?.defaults || {
    library: 'lucide',
    size: 24,
    color: 'currentColor',
    stroke: 2,
};

export default function Edit({ attributes, setAttributes }) {
    const { library, name, size, color, strokeWidth, title } = attributes;
    const [pickerOpen, setPickerOpen] = useState(false);
    const [preview, setPreview] = useState('');

    const blockProps = useBlockProps({
        className: 'wpicons wp-block-wpicons-icon__wrap',
    });

    useEffect(() => {
        if (!name) {
            setPreview('');
            return undefined;
        }

        let cancelled = false;
        fetchIcon({
            library,
            name,
            size,
            color: color || 'currentColor',
            stroke: strokeWidth,
        })
            .then((data) => {
                if (!cancelled) {
                    setPreview(data.svg || '');
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setPreview('');
                }
            });

        return () => {
            cancelled = true;
        };
    }, [library, name, size, color, strokeWidth]);

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Icon', 'wpicons')} initialOpen>
                    <div className="wpicons wpicons_" theme="light">
                    <p>
                        <strong>{library}</strong> / {name}
                    </p>
                    <Button variant="secondary" onClick={() => setPickerOpen(true)}>
                        {__('Choose icon', 'wpicons')}
                    </Button>
                    </div>
                </PanelBody>
            </InspectorControls>
            <div
                {...blockProps}
                onClick={(event) => {
                    blockProps.onClick?.(event);
                    setPickerOpen(true);
                }}
            >
                {preview ? (
                    <span dangerouslySetInnerHTML={{ __html: preview }} />
                ) : (
                    <Button variant="primary" onClick={() => setPickerOpen(true)}>
                        {__('Choose icon', 'wpicons')}
                    </Button>
                )}
            </div>
            {pickerOpen ? (
                <IconPickerModal
                    library={library || defaults.library}
                    name={name}
                    size={size || defaults.size}
                    color={color ?? defaults.color}
                    stroke={strokeWidth ?? defaults.stroke}
                    title={title || ''}
                    allowMatchText={false}
                    matchText={false}
                    onLibraryChange={(nextLibrary) => setAttributes({ library: nextLibrary })}
                    onSelect={(icon) => {
                        setAttributes({
                            library: icon.library,
                            name: icon.name,
                            size: icon.size || defaults.size,
                            color: icon.color || defaults.color,
                            strokeWidth: icon.stroke ?? defaults.stroke,
                            title: icon.title || '',
                        });
                        setPickerOpen(false);
                    }}
                    onClose={() => setPickerOpen(false)}
                />
            ) : null}
        </>
    );
}
