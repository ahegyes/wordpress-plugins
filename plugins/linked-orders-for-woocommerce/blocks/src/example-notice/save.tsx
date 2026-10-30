import { useBlockProps } from '@wordpress/block-editor';

// The editor validates saved markup against the post's content, so a translated string would break the block in another locale.
export default function save() {
	return (
		<p { ...useBlockProps.save() }>
			An example notice from Linked Orders for WooCommerce.
		</p>
	);
}
