import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import { storeSettings } from './settings';

test.describe( 'Posts list and Comments screen', () => {
	let post = 0;

	test.beforeAll( async ( { requestUtils } ) => {
		storeSettings( { 'post-types': [ 'post' ] } );
		post = (
			await requestUtils.rest< { id: number } >( {
				method: 'POST',
				path: '/wp/v2/posts',
				data: { title: 'Listed', status: 'publish' },
			} )
		).id;
		await requestUtils.rest( {
			method: 'POST',
			path: `/dws_ic/v1/posts/${ post }/comments`,
			data: { content: 'Seen in the list' },
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		storeSettings( { 'post-types': [] } );
	} );

	test( 'the quick view shows a post’s internal comments', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'edit.php' );
		const row = page.locator( `#post-${ post }` );
		await expect(
			row.locator( '.column-dws-internal-comments' )
		).toContainText( '1' );

		await row.getByRole( 'button', { name: 'Quick view' } ).click();
		const popover = page.getByRole( 'dialog', {
			name: '1 internal comment',
		} );
		await expect( popover ).toContainText( 'Seen in the list' );
		const results = await new AxeBuilder( { page } )
			.include( `#dws-ic-quick-view-${ post }` )
			.analyze();
		expect( results.violations ).toEqual( [] );

		await page.keyboard.press( 'Escape' );
		await expect( popover ).toBeHidden();
	} );

	test( 'the Comments screen lists internal comments under their own view', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'edit-comments.php',
			'comment_type=dws_internal'
		);

		await expect( page.locator( '#the-comment-list' ) ).toContainText(
			'Seen in the list'
		);

		await admin.visitAdminPage( 'edit-comments.php' );
		await expect( page.locator( '#the-comment-list' ) ).not.toContainText(
			'Seen in the list'
		);
	} );

	test( 'an internal comment’s edit screen shows only what saving changes', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'edit-comments.php',
			'comment_type=dws_internal'
		);
		const href = await page
			.locator( '#the-comment-list' )
			.getByRole( 'link', { name: 'Edit this comment' } )
			.first()
			.getAttribute( 'href' );
		const id = new URL( href ?? '', page.url() ).searchParams.get( 'c' );

		await admin.visitAdminPage(
			'comment.php',
			`action=editcomment&c=${ id ?? '' }`
		);

		await expect( page.locator( '#content' ) ).toHaveValue(
			'Seen in the list'
		);
		for ( const selector of [
			'#namediv',
			'#comment-status',
			'.misc-pub-curtime',
		] ) {
			await expect( page.locator( selector ) ).toBeHidden();
		}
	} );
} );
