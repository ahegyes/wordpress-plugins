import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import { storeSettings } from './settings';

// The block editor folds the classic meta boxes into a pane below the content, whose resize handle covers the middle of its toggle.
const openMetaBoxes = async ( page: Page ): Promise< void > => {
	const toggle = page.getByRole( 'button', { name: 'Meta Boxes' } );
	if ( ( await toggle.getAttribute( 'aria-expanded' ) ) === 'false' ) {
		await toggle.press( 'Enter' );
	}
};

test.describe( 'Meta box', () => {
	let post = 0;

	test.beforeAll( async ( { requestUtils } ) => {
		storeSettings( {
			'post-types': [ 'post', 'shop_order' ],
			'enable-private-comments': 'yes',
		} );
		post = (
			await requestUtils.rest< { id: number } >( {
				method: 'POST',
				path: '/wp/v2/posts',
				data: { title: 'Internal', status: 'publish' },
			} )
		).id;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		storeSettings( {
			'post-types': [],
			'enable-private-comments': 'no',
		} );
	} );

	test( 'a comment is posted, replied to, edited and trashed in place', async ( {
		admin,
		page,
	} ) => {
		await admin.editPost( post );
		await openMetaBoxes( page );
		const box = page.locator( '#dws-internal-comments' );
		const status = box.getByRole( 'status' );

		await box.getByLabel( 'New internal comment' ).fill( 'First note' );
		await box.getByRole( 'button', { name: 'Post comment' } ).click();
		await expect( status ).toHaveText( 'Internal comment posted.' );
		const first = box
			.locator( '.dws-internal-comments__list > li' )
			.first();
		await expect( first ).toContainText( 'First note' );

		await first.getByRole( 'button', { name: 'Reply' } ).click();
		await expect( first.getByRole( 'textbox' ) ).toBeFocused();
		await first.getByRole( 'textbox' ).fill( 'A reply' );
		await first.getByRole( 'button', { name: 'Post reply' } ).click();
		await expect( status ).toHaveText( 'Reply posted.' );
		const reply = box.locator( '.internal-comment-reply' );
		await expect( reply ).toContainText( 'A reply' );

		await box
			.locator( '.dws-internal-comments__list > li' )
			.first()
			.getByRole( 'button', { name: 'Edit' } )
			.click();
		const editor = box
			.locator( '.dws-internal-comments__list > li' )
			.first()
			.getByRole( 'textbox' );
		await expect( editor ).toHaveValue( 'First note' );
		await editor.fill( 'Edited note' );
		await editor.press( 'ControlOrMeta+Enter' );
		await expect( status ).toHaveText( 'Internal comment saved.' );
		await expect(
			box.locator( '.dws-internal-comments__list' )
		).toContainText( 'Edited note' );

		await reply.getByRole( 'button', { name: 'Move to Trash' } ).click();
		await expect( status ).toHaveText(
			'Internal comment moved to the Trash.'
		);
		await expect( box ).not.toContainText( 'A reply' );

		const results = await new AxeBuilder( { page } )
			.include( '#dws-internal-comments' )
			.analyze();
		expect( results.violations ).toEqual( [] );
	} );

	test( 'an empty comment is refused with a message', async ( {
		admin,
		page,
	} ) => {
		await admin.editPost( post );
		await openMetaBoxes( page );
		const box = page.locator( '#dws-internal-comments' );

		await box.getByRole( 'button', { name: 'Post comment' } ).click();

		await expect( box.getByRole( 'status' ) ).toHaveText(
			'You cannot post an empty internal comment.'
		);
		await expect( box.getByLabel( 'New internal comment' ) ).toBeFocused();
	} );

	// The tests environment stores orders in WooCommerce's order tables, whose edit screen is not a post's.
	test( 'a comment is posted on an order', async ( {
		page,
		requestUtils,
	} ) => {
		const order = await requestUtils.rest< { id: number } >( {
			method: 'POST',
			path: '/wc/v3/orders',
			data: { status: 'pending' },
		} );

		try {
			await page.goto(
				`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ order.id }`
			);
			const box = page.locator( '#dws-internal-comments' );
			await box
				.getByRole( 'textbox', { name: 'New internal comment' } )
				.fill( 'About the order' );
			await box.getByRole( 'button', { name: 'Post comment' } ).click();

			await expect( box.getByRole( 'status' ) ).toHaveText(
				'Internal comment posted.'
			);
			await expect( box ).toContainText( 'About the order' );
		} finally {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wc/v3/orders/${ order.id }`,
				params: { force: true },
			} );
		}
	} );
} );
