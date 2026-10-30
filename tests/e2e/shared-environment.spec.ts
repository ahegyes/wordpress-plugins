import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

// The plugins the environment maps, so a plugin joins this check with its mapping line.
const slugs = Object.keys(
	JSON.parse(
		readFileSync( join( __dirname, '../../.wp-env.tests.json' ), 'utf8' )
	).mappings
).map( ( path ) => path.replace( 'wp-content/plugins/', '' ) );

test( 'every mapped plugin is active beside the others and the dashboard loads', async ( {
	admin,
	page,
	requestUtils,
} ) => {
	const plugins = ( await requestUtils.rest( {
		path: '/wp/v2/plugins',
	} ) ) as Array< { plugin: string; status: string } >;
	for ( const slug of slugs ) {
		expect(
			plugins.find( ( { plugin } ) => plugin.startsWith( `${ slug }/` ) )
				?.status,
			slug
		).toBe( 'active' );
	}

	await admin.visitAdminPage( 'index.php' );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Dashboard' } )
	).toBeVisible();
} );
