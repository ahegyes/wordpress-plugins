/// <reference types="node" />
import { execFileSync } from 'node:child_process';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

// WordPress core's own message for a plugin whose Requires Plugins header lists an inactive plugin.
const DEPENDENCY =
	'Linked Orders for WooCommerce requires 1 plugin to be installed and activated: WooCommerce.';

// No page shows the boot's outcome, so the site's WP-CLI reads it.
const bootStatus = (): string =>
	execFileSync(
		'node_modules/.bin/wp-env',
		[
			'--config',
			'.wp-env.tests.json',
			'run',
			'cli',
			'wp',
			'eval',
			'echo dws_lowc_plugin()->boot->status;',
		],
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] }
	)
		.trim()
		.split( '\n' )
		.pop() ?? '';

test.describe( 'Without WooCommerce', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( 'woocommerce' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'woocommerce' );
	} );

	test( 'the plugin stays blocked, WordPress names the missing plugin and every screen still loads', async ( {
		page,
	} ) => {
		expect( bootStatus() ).toBe( 'blocked' );

		const front = await page.goto( '/' );
		expect( front?.status() ).toBe( 200 );

		for ( const screen of [ '/wp-admin/', '/wp-admin/plugins.php' ] ) {
			const response = await page.goto( screen );
			expect( response?.status() ).toBe( 200 );
			await expect( page.getByText( DEPENDENCY ) ).toBeVisible();
		}
	} );
} );
