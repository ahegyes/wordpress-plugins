import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import { cli } from './cli';
import { storeSettings } from './settings';

test.describe( 'Settings page', () => {
	test.afterAll( () => {
		storeSettings( { dws_ic_post_types: [] } );
	} );

	test( 'the registered page saves and clears the post types', async ( {
		admin,
		page,
	} ) => {
		const posts = page.getByRole( 'checkbox', { name: 'Posts' } );
		const save = page.getByRole( 'button', { name: 'Save Changes' } );

		await admin.visitAdminPage(
			'options-general.php',
			'page=dws-internal-comments'
		);
		await posts.check();
		await save.click();
		await expect( posts ).toBeChecked();
		expect(
			cli( 'option', 'get', 'dws_ic_post_types', '--format=json' )
		).toBe( '["post"]' );

		await posts.uncheck();
		await save.click();
		await expect( posts ).not.toBeChecked();
		expect(
			cli( 'option', 'get', 'dws_ic_post_types', '--format=json' )
		).toBe( '[]' );
	} );
} );
