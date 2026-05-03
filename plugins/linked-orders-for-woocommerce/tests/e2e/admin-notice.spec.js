// E2E — confirms AdminNotice renders in wp-admin when the plugin is active.

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'AdminNotice', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'dws-plugin-template' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( 'dws-plugin-template' );
	} );

	test( 'renders on the WordPress admin dashboard', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );

		const notice = page.locator( '.notice.notice-info', {
			hasText: 'DWS Plugin Template is active.',
		} );
		await expect( notice ).toBeVisible();
	} );
} );
