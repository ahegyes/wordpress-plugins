import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import { cli } from './cli';
import { openMetaBoxes } from './editor';
import { storeSettings } from './settings';

test.describe( 'Meta box', () => {
	let post = 0;

	test.beforeAll( async ( { requestUtils } ) => {
		storeSettings( {
			dws_ic_post_types: [ 'post', 'shop_order' ],
			dws_ic_private_comments_enabled: 'yes',
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
			dws_ic_post_types: [],
			dws_ic_private_comments_enabled: 'no',
		} );
	} );

	test( 'a comment is posted, replied to, edited and trashed in place', async ( {
		admin,
		page,
	} ) => {
		await admin.editPost( post );
		await openMetaBoxes( page );
		const box = page.locator( '#dws-internal-comments' );
		const status = box.locator( '.dws-internal-comments__status' );

		await box.getByLabel( 'New internal comment' ).fill( 'First note' );
		await box.getByRole( 'button', { name: 'Post comment' } ).click();
		await expect( status ).toHaveText( 'Internal comment posted.' );
		await expect( page.locator( '#a11y-speak-polite' ) ).toContainText(
			'Internal comment posted.'
		);
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

		// The reply sits inside its parent's item, so the parent's own actions and form are its direct children's.
		await first
			.locator( ':scope > .dws-internal-comment__other' )
			.getByRole( 'button', { name: 'Edit' } )
			.click();
		const editor = first
			.locator( ':scope > [data-form="edit"]' )
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

		await expect(
			box.locator( '.dws-internal-comments__status' )
		).toHaveText( 'An internal comment cannot be empty.' );
		await expect( page.locator( '#a11y-speak-assertive' ) ).toContainText(
			'An internal comment cannot be empty.'
		);
		await expect( box.getByLabel( 'New internal comment' ) ).toBeFocused();
	} );

	test( 'a reply’s error shows in its form and Cancel clears it', async ( {
		admin,
		page,
	} ) => {
		await admin.editPost( post );
		await openMetaBoxes( page );
		const box = page.locator( '#dws-internal-comments' );
		await box.getByLabel( 'New internal comment' ).fill( 'Replied to' );
		await box.getByRole( 'button', { name: 'Post comment' } ).click();
		const item = box.locator( '.dws-internal-comments__list > li', {
			hasText: 'Replied to',
		} );

		await item.getByRole( 'button', { name: 'Reply' } ).click();
		const form = item.locator( ':scope > [data-form="reply"]' );
		await form.getByRole( 'button', { name: 'Post reply' } ).click();

		const field = form.getByRole( 'textbox' );
		await expect( field ).toHaveAttribute( 'aria-invalid', 'true' );
		const error = form.locator(
			`#${ ( await field.getAttribute( 'aria-describedby' ) ) ?? '' }`
		);
		await expect( error ).toHaveText(
			'An internal comment cannot be empty.'
		);
		await expect(
			box.locator( '.dws-internal-comments__status' )
		).toBeEmpty();

		await form.getByRole( 'button', { name: 'Cancel' } ).click();
		await expect( form ).toHaveCount( 0 );
		await expect(
			box.locator( '.dws-internal-comments__status' )
		).toBeEmpty();
	} );

	test( 'a request runs alone, so a repeated shortcut or Escape waits for it', async ( {
		admin,
		page,
	} ) => {
		await admin.editPost( post );
		await openMetaBoxes( page );
		const box = page.locator( '#dws-internal-comments' );
		const field = box.getByLabel( 'New internal comment' );
		let posts = 0;
		let answer = (): void => {};
		const answered = new Promise< void >( ( resolve ) => {
			answer = resolve;
		} );
		await page.route(
			( url ) => url.href.includes( 'internal-comments' ),
			async ( route ) => {
				if ( route.request().method() === 'POST' ) {
					posts++;
					await answered;
				}
				await route.continue();
			}
		);

		await field.fill( 'Sent once' );
		await field.press( 'ControlOrMeta+Enter' );
		await field.press( 'ControlOrMeta+Enter' );
		await expect(
			box.locator( '.dws-internal-comments__status' )
		).toHaveText(
			'Wait for the previous change to finish, then try again.'
		);
		answer();
		await expect(
			box.locator( '.dws-internal-comments__status' )
		).toHaveText( 'Internal comment posted.' );
		await expect(
			box.locator( '.dws-internal-comment__content', {
				hasText: 'Sent once',
			} )
		).toHaveCount( 1 );
		expect( posts ).toBe( 1 );

		let release = (): void => {};
		const released = new Promise< void >( ( resolve ) => {
			release = resolve;
		} );
		await page.unrouteAll();
		await page.route(
			( url ) => url.href.includes( 'internal-comments' ),
			async ( route ) => {
				if ( route.request().method() === 'POST' ) {
					await released;
				}
				await route.continue();
			}
		);
		const item = box.locator( '.dws-internal-comments__list > li', {
			hasText: 'Sent once',
		} );
		await item.getByRole( 'button', { name: 'Reply' } ).click();
		const reply = item.locator( ':scope > [data-form="reply"]' );
		await reply.getByRole( 'textbox' ).fill( 'Pending reply' );
		await reply.getByRole( 'textbox' ).press( 'ControlOrMeta+Enter' );
		await reply.getByRole( 'textbox' ).press( 'Escape' );
		await expect( reply ).toHaveCount( 1 );
		release();

		await expect(
			box.locator( '.dws-internal-comments__status' )
		).toHaveText( 'Reply posted.' );
		await expect( box.locator( '.internal-comment-reply' ) ).toContainText(
			'Pending reply'
		);
	} );

	test( 'a comment marked private is stored private', async ( {
		admin,
		page,
	} ) => {
		await admin.editPost( post );
		await openMetaBoxes( page );
		const box = page.locator( '#dws-internal-comments' );

		await box.getByLabel( 'New internal comment' ).fill( 'Kept private' );
		await box.getByLabel( 'Mark as private' ).check();
		await box.getByRole( 'button', { name: 'Post comment' } ).click();

		const item = box.locator( '.dws-internal-comment', {
			hasText: 'Kept private',
		} );
		await expect( item ).toHaveClass( /is-private/ );
		const id = ( await item.getAttribute( 'data-comment-id' ) ) ?? '';
		expect(
			cli(
				'eval',
				`echo wp_json_encode( dws_ic_is_private_comment( ${ id } ) );`
			)
		).toBe( 'true' );
	} );

	test( 'a failed send keeps the comment and its privacy for a retry', async ( {
		admin,
		page,
	} ) => {
		await admin.editPost( post );
		await openMetaBoxes( page );
		const box = page.locator( '#dws-internal-comments' );
		const field = box.getByLabel( 'New internal comment' );
		const privacy = box.getByLabel( 'Mark as private' );
		const button = box.getByRole( 'button', { name: 'Post comment' } );
		await page.route(
			( url ) =>
				url.href.includes( 'internal-comments' ) &&
				url.href.includes( 'comments' ),
			async ( route ) => {
				if ( route.request().method() !== 'POST' ) {
					return route.continue();
				}
				return route.fulfill( {
					status: 500,
					contentType: 'application/json',
					body: JSON.stringify( {
						code: 'dws_ic_not_stored',
						message: 'The internal comment could not be stored.',
					} ),
				} );
			}
		);

		await field.fill( 'Retried note' );
		await privacy.check();
		await button.click();

		await expect(
			box.locator( '.dws-internal-comments__status' )
		).toHaveText( 'The internal comment could not be stored.' );
		await expect( field ).toHaveValue( 'Retried note' );
		await expect( field ).toBeEditable();
		await expect( privacy ).toBeChecked();
		await expect( button ).toBeEnabled();

		await page.unrouteAll();
		await button.click();
		await expect(
			box.locator( '.dws-internal-comments__status' )
		).toHaveText( 'Internal comment posted.' );
		await expect(
			box.locator( '.dws-internal-comment.is-private', {
				hasText: 'Retried note',
			} )
		).toHaveCount( 1 );
	} );

	test( 'a comment being sent stays as sent until the server answers', async ( {
		admin,
		page,
	} ) => {
		await admin.editPost( post );
		await openMetaBoxes( page );
		const box = page.locator( '#dws-internal-comments' );
		const field = box.getByLabel( 'New internal comment' );
		let answer = (): void => {};
		const answered = new Promise< void >( ( resolve ) => {
			answer = resolve;
		} );
		await page.route(
			( url ) => url.href.includes( 'dws_ic' ),
			async ( route ) => {
				await answered;
				await route.continue();
			}
		);

		await field.fill( 'Slow note' );
		await box.getByRole( 'button', { name: 'Post comment' } ).click();
		await expect( field ).not.toBeEditable();
		answer();

		await expect(
			box.locator( '.dws-internal-comments__status' )
		).toHaveText( 'Internal comment posted.' );
		await expect( field ).toBeEditable();
	} );

	test( 'an open reply keeps its text when another comment changes', async ( {
		admin,
		page,
	} ) => {
		await admin.editPost( post );
		await openMetaBoxes( page );
		const box = page.locator( '#dws-internal-comments' );
		const status = box.locator( '.dws-internal-comments__status' );
		const item = ( text: string ) =>
			box.locator( '.dws-internal-comments__list > li', {
				hasText: text,
			} );
		for ( const content of [ 'Kept note', 'Dropped note' ] ) {
			await box.getByLabel( 'New internal comment' ).fill( content );
			await box.getByRole( 'button', { name: 'Post comment' } ).click();
			await expect( item( content ) ).toBeVisible();
		}

		await item( 'Kept note' )
			.getByRole( 'button', { name: 'Reply' } )
			.click();
		await item( 'Kept note' ).getByRole( 'textbox' ).fill( 'Unsent reply' );
		await item( 'Dropped note' )
			.getByRole( 'button', { name: 'Move to Trash' } )
			.click();

		await expect( status ).toHaveText(
			'Internal comment moved to the Trash.'
		);
		await expect( item( 'Kept note' ).getByRole( 'textbox' ) ).toHaveValue(
			'Unsent reply'
		);
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

			await expect(
				box.locator( '.dws-internal-comments__status' )
			).toHaveText( 'Internal comment posted.' );
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
