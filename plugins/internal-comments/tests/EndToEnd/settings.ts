import { cli } from './cli';

// No REST route exposes the plugin's settings, so the site's WP-CLI stores them.
export const storeSettings = ( value: Record< string, unknown > ): void => {
	cli(
		'option',
		'update',
		'dws-internal-comments_general',
		JSON.stringify( value ),
		'--format=json'
	);
};
