import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import { cli } from './cli';
import { openMetaBoxes } from './editor';
import { storeSettings } from './settings';

// Every feature but the order integration works without WooCommerce, so its screens run here with WooCommerce deactivated.
test.describe( 'Without WooCommerce', () => {
	let post = 0;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( 'woocommerce' );
		storeSettings( { dws_ic_post_types: [ 'post' ] } );
		post = (
			await requestUtils.rest< { id: number } >( {
				method: 'POST',
				path: '/wp/v2/posts',
				data: { title: 'Without WooCommerce', status: 'publish' },
			} )
		).id;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		storeSettings( { dws_ic_post_types: [] } );
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

	test( 'a comment posted in the meta box shows in the quick view and the Comments view', async ( {
		admin,
		page,
	} ) => {
		await admin.editPost( post );
		await openMetaBoxes( page );
		const box = page.locator( '#dws-internal-comments' );
		await box.getByLabel( 'New internal comment' ).fill( 'No store here' );
		await box.getByRole( 'button', { name: 'Post comment' } ).click();
		await expect(
			box.locator( '.dws-internal-comments__status' )
		).toHaveText( 'Internal comment posted.' );

		await admin.visitAdminPage( 'edit.php' );
		const row = page.locator( `#post-${ post }` );
		await row.getByRole( 'button', { name: 'Quick view' } ).click();
		await expect(
			page.getByRole( 'dialog', { name: '1 internal comment' } )
		).toContainText( 'No store here' );

		await admin.visitAdminPage(
			'edit-comments.php',
			'comment_type=dws_internal'
		);
		await expect( page.locator( '#the-comment-list' ) ).toContainText(
			'No store here'
		);
	} );
} );
