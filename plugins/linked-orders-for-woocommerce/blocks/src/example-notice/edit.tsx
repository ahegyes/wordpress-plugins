import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import './editor.scss';

export default function Edit() {
	return (
		<p { ...useBlockProps() }>
			{ __(
				'An example notice from Linked Orders for WooCommerce.',
				'linked-orders-for-woocommerce'
			) }
		</p>
	);
}
