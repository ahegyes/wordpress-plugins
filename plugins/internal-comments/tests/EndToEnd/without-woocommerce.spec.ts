import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { cli } from './cli';

test.describe( 'Without WooCommerce', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( 'woocommerce' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'woocommerce' );
	} );

	test( 'the plugin boots and every screen still loads', async ( {
		page,
	} ) => {
		expect( cli( 'eval', 'echo dws_ic_plugin()->boundary->status;' ) ).toBe(
			'booted'
		);

		const front = await page.goto( '/' );
		expect( front?.status() ).toBe( 200 );

		for ( const screen of [ '/wp-admin/', '/wp-admin/plugins.php' ] ) {
			const response = await page.goto( screen );
			expect( response?.status() ).toBe( 200 );
		}
	} );
} );
