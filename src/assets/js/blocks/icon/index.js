import { registerBlockType } from '@wordpress/blocks';
import LucideSvg, { PLUGIN_MARK } from '../../picker/icons';
import Edit from './edit';
import '../../formats/inline-icon';

registerBlockType('wpicons/icon', {
    icon: <LucideSvg name={PLUGIN_MARK} size={24} />,
    edit: Edit,
    save: () => null,
});
