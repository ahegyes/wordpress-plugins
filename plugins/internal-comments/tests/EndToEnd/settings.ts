import { cli } from './cli';

// No REST route exposes the plugin's settings, so the site's WP-CLI stores each in its own row.
export const storeSettings = ( rows: Record< string, unknown > ): void => {
	for ( const [ name, value ] of Object.entries( rows ) ) {
		cli(
			'option',
			'update',
			name,
			JSON.stringify( value ),
			'--format=json'
		);
	}
};
