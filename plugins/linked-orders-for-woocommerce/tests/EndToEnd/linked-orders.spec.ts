import { expect, test } from '@wordpress/e2e-test-utils-playwright';

// The tests environment stores orders in WooCommerce's order tables; the Integration suite covers the posts storage.
test.describe( 'Linked orders', () => {
	const orders: number[] = [];

	test.afterAll( async ( { requestUtils } ) => {
		for ( const id of orders ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wc/v3/orders/${ id }`,
				params: { force: true },
			} );
		}
	} );

	test( 'a child created from the order screen links back to its parent and shows its depth in the orders list', async ( {
		page,
		requestUtils,
	} ) => {
		const parent = await requestUtils.rest< { id: number } >( {
			method: 'POST',
			path: '/wc/v3/orders',
			data: { status: 'pending' },
		} );
		orders.push( parent.id );

		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ parent.id }`
		);
		const box = page.locator( '#dws-linked-orders' );
		await expect( box ).toContainText( 'There are no attached children.' );
		await box.getByRole( 'link', { name: 'Add new child Order' } ).click();

		await expect( page ).toHaveURL( /[?&]action=edit&id=\d+/ );
		const child = Number(
			new URL( page.url() ).searchParams.get( 'id' ) ?? 0
		);
		orders.push( child );
		expect( child ).not.toBe( parent.id );
		await expect(
			page.locator( '#dws-linked-orders .dws-linked-orders__parent' )
		).toContainText( `Order #${ parent.id }` );

		await page.goto( '/wp-admin/admin.php?page=wc-orders' );
		await expect( page.locator( 'thead #dws_lo_depth' ) ).toHaveText(
			'Depth'
		);
		await expect(
			page.locator( `#order-${ child } td.column-dws_lo_depth` )
		).toHaveText( 'Child Order (level 2)' );
		await expect(
			page.locator( `#order-${ parent.id } td.column-dws_lo_depth` )
		).toHaveText( 'Root Order' );
	} );

	test( 'the plugin row opens the section on the Advanced tab', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/plugins.php' );
		await page
			.locator( 'tr[data-slug="linked-orders-for-woocommerce"]' )
			.getByRole( 'link', { name: 'Settings' } )
			.click();

		await expect( page ).toHaveURL(
			/tab=advanced&section=dws-linked-orders/
		);
		await expect(
			page.getByLabel( 'Maximum linked orders depth' )
		).toHaveValue( '1' );
	} );
} );
