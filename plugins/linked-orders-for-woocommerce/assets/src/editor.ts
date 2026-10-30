import { registerBlockStyle } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

registerBlockStyle( 'linked-orders-for-woocommerce/example-notice', {
	name: 'outlined',
	label: __( 'Outlined', 'linked-orders-for-woocommerce' ),
} );
