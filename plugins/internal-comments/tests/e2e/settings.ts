/// <reference types="node" />
import { execFileSync } from 'node:child_process';

// No REST route exposes the plugin's settings, so the site's WP-CLI stores them.
export const storeSettings = ( value: Record< string, unknown > ): void => {
	execFileSync(
		'node_modules/.bin/wp-env',
		[
			'--config',
			'.wp-env.tests.json',
			'run',
			'cli',
			'wp',
			'option',
			'update',
			'dws-internal-comments_general',
			JSON.stringify( value ),
			'--format=json',
		],
		{ stdio: 'ignore' }
	);
};
