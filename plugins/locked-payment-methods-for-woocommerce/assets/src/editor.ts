import { registerBlockStyle } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

registerBlockStyle( 'locked-payment-methods-for-woocommerce/example-notice', {
	name: 'outlined',
	label: __( 'Outlined', 'locked-payment-methods-for-woocommerce' ),
} );
