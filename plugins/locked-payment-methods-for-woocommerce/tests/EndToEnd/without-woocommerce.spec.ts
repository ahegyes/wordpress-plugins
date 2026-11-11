import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { cli } from './cli';

// WordPress core's own message for a plugin whose Requires Plugins header lists an inactive plugin.
const DEPENDENCY =
	'Locked Payment Methods for WooCommerce requires 1 plugin to be installed and activated: WooCommerce.';

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
		expect(
			cli( 'eval', 'echo dws_lpmwc_plugin()->boundary->status;' )
		).toBe( 'blocked' );

		const front = await page.goto( '/' );
		expect( front?.status() ).toBe( 200 );

		for ( const screen of [ '/wp-admin/', '/wp-admin/plugins.php' ] ) {
			const response = await page.goto( screen );
			expect( response?.status() ).toBe( 200 );
			await expect( page.getByText( DEPENDENCY ) ).toBeVisible();
		}
	} );
} );
