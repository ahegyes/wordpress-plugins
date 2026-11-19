import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { cli, keepOptions } from './cli';

// WooCommerce's enhanced select and Groups' profile field fail the scan upstream, so each scan covers only the plugin's own controls.
test.describe( 'Admin screens', () => {
	let customer = 0;
	let order = 0;
	let restoreOptions: () => void;

	test.beforeAll( async ( { requestUtils } ) => {
		restoreOptions = keepOptions( {
			dws_lpmwc_locked_payment_methods: [ 'cod' ],
			dws_lpmwc_unlock_via_orders: 'yes',
		} );
		customer = Number(
			cli(
				'user',
				'create',
				'lpm-e2e-admin-customer',
				'lpm-e2e-admin-customer@example.org',
				'--role=customer',
				'--porcelain'
			)
		);
		order = (
			await requestUtils.rest< { id: number } >( {
				method: 'POST',
				path: '/wc/v3/orders',
				data: { customer_id: customer, status: 'pending' },
			} )
		).id;
	} );

	test.afterAll( () => {
		cli( 'eval', `wc_get_order( ${ order } )?->delete( true );` );
		cli( 'user', 'delete', String( customer ), '--yes' );
		restoreOptions();
	} );

	test( 'the settings section passes an accessibility scan', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'admin.php',
			'page=wc-settings&tab=advanced&section=dws-locked-payment-methods'
		);
		await expect(
			page.locator( '#dws_lpmwc_unlock_via_user_roles' )
		).toBeVisible();

		const results = await new AxeBuilder( { page } )
			.include( '[id^="dws_lpmwc_"]' )
			.analyze();
		expect( results.violations ).toEqual( [] );
	} );

	test( 'a customer’s profile form passes an accessibility scan', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'user-edit.php', `user_id=${ customer }` );
		await expect(
			page.getByRole( 'checkbox', { name: 'Cash on delivery' } )
		).toBeVisible();

		const results = await new AxeBuilder( { page } )
			.include( '[name^="dws_lpmwc_user_profile"]' )
			.analyze();
		expect( results.violations ).toEqual( [] );
	} );

	test( 'an order’s box passes an accessibility scan', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'admin.php',
			`page=wc-orders&action=edit&id=${ order }`
		);
		const box = page.locator( '#dws_lpmwc_order' );
		await expect(
			box.getByRole( 'checkbox', { name: 'Cash on delivery' } )
		).toBeVisible();

		const results = await new AxeBuilder( { page } )
			.include( '#dws_lpmwc_order' )
			.analyze();
		expect( results.violations ).toEqual( [] );
	} );
} );
