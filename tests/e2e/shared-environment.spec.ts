import { execFileSync } from 'node:child_process';
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

// No page shows a boot's outcome or the Internal Comments settings, so the site's WP-CLI reads and stores them.
const cli = ( ...args: string[] ): string =>
	execFileSync(
		'node_modules/.bin/wp-env',
		[ '--config', '.wp-env.tests.json', 'run', 'cli', 'wp', ...args ],
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] }
	)
		.trim()
		.split( '\n' )
		.pop() ?? '';

test( 'Linked Orders and Internal Comments each boot and share an order screen', async ( {
	page,
	requestUtils,
} ) => {
	expect(
		cli(
			'eval',
			'echo wp_json_encode( array( did_action( "dws_lowc/boot/succeeded" ), did_action( "dws_ic/boot/succeeded" ) ) );'
		)
	).toBe( '[1,1]' );

	cli(
		'option',
		'update',
		'dws-internal-comments_general',
		JSON.stringify( { 'post-types': [ 'shop_order' ] } ),
		'--format=json'
	);
	const order = await requestUtils.rest< { id: number } >( {
		method: 'POST',
		path: '/wc/v3/orders',
		data: { status: 'pending' },
	} );

	try {
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ order.id }`
		);
		await expect( page.locator( '#dws-linked-orders' ) ).toBeVisible();
		await expect( page.locator( '#dws-internal-comments' ) ).toBeVisible();
	} finally {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wc/v3/orders/${ order.id }`,
			params: { force: true },
		} );
		cli(
			'option',
			'update',
			'dws-internal-comments_general',
			JSON.stringify( { 'post-types': [] } ),
			'--format=json'
		);
	}
} );
