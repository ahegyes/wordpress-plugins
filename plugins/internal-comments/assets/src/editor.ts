import { registerBlockStyle } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

registerBlockStyle( 'internal-comments/example-notice', {
	name: 'outlined',
	label: __( 'Outlined', 'internal-comments' ),
} );
