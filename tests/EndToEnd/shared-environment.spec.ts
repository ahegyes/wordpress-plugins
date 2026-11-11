/// <reference types="node" />
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

// No page shows a boot's outcome or the plugins' settings, so the site's WP-CLI reads and stores them.
const cli = ( ...args: string[] ): string =>
	execFileSync(
		'node_modules/.bin/wp-env',
		[ '--config', '.wp-env.tests.json', 'run', 'cli', 'wp', ...args ],
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] }
	)
		.trim()
		.split( '\n' )
		.pop() ?? '';

test( 'every plugin boots from its own scoped framework', () => {
	expect(
		cli(
			'eval',
			'echo wp_json_encode( array( did_action( "dws_lowc/boot/succeeded" ), did_action( "dws_ic/boot/succeeded" ), did_action( "dws_lpmwc/boot/succeeded" ), class_exists( "DeepWebSolutions\\LinkedOrders\\Scoped\\DeepWebSolutions\\Framework\\Core\\PluginKernel", false ), class_exists( "DeepWebSolutions\\InternalComments\\Scoped\\DeepWebSolutions\\Framework\\Core\\PluginKernel", false ), class_exists( "DeepWebSolutions\\LockedPaymentMethods\\Scoped\\DeepWebSolutions\\Framework\\Core\\PluginKernel", false ) ) );'
		)
	).toBe( '[1,1,1,true,true,true]' );
} );

test( 'Linked Orders and Internal Comments share an order screen', async ( {
	page,
	requestUtils,
} ) => {
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

test( 'a locked payment method stays hidden from a guest on the classic and the block checkout', async ( {
	browser,
	baseURL,
	requestUtils,
} ) => {
	// A new store shows its pages to visitors as coming soon, so the row opens it while it runs.
	const comingSoon = cli(
		'eval',
		'echo get_option( "woocommerce_coming_soon", "no" );'
	);
	cli( 'option', 'update', 'woocommerce_coming_soon', 'no' );
	for ( const gateway of [ 'cod', 'bacs' ] ) {
		cli(
			'wc',
			'payment_gateway',
			'update',
			gateway,
			'--enabled=true',
			'--user=1'
		);
	}
	cli(
		'option',
		'update',
		'dws_lpmwc_locked_payment_methods',
		JSON.stringify( [ 'cod' ] ),
		'--format=json'
	);
	const product = await requestUtils.rest< { id: number } >( {
		method: 'POST',
		path: '/wc/v3/products',
		data: {
			name: 'Locked checkout',
			type: 'simple',
			regular_price: '10',
			virtual: true,
		},
	} );
	const classic = await requestUtils.rest< { id: number; link: string } >( {
		method: 'POST',
		path: '/wp/v2/pages',
		data: {
			title: 'Classic checkout',
			status: 'publish',
			content:
				'<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
		},
	} );
	// A context takes the config's storage state, the administrator's session, unless it names its own.
	const guest = await browser.newContext( {
		baseURL,
		storageState: { cookies: [], origins: [] },
	} );

	try {
		const page = await guest.newPage();
		await page.goto( `/?add-to-cart=${ product.id }` );

		await page.goto( classic.link );
		await expect( page.locator( '#payment_method_bacs' ) ).toBeAttached();
		await expect( page.locator( '#payment_method_cod' ) ).toHaveCount( 0 );

		await page.goto( '/checkout/' );
		await expect( page.getByLabel( 'Direct bank transfer' ) ).toBeVisible();
		await expect( page.getByLabel( 'Cash on delivery' ) ).toHaveCount( 0 );
	} finally {
		await guest.close();
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/pages/${ classic.id }`,
			params: { force: true },
		} );
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wc/v3/products/${ product.id }`,
			params: { force: true },
		} );
		cli(
			'option',
			'update',
			'dws_lpmwc_locked_payment_methods',
			'[]',
			'--format=json'
		);
		for ( const gateway of [ 'cod', 'bacs' ] ) {
			cli(
				'wc',
				'payment_gateway',
				'update',
				gateway,
				'--enabled=false',
				'--user=1'
			);
		}
		cli( 'option', 'update', 'woocommerce_coming_soon', comingSoon );
	}
} );
