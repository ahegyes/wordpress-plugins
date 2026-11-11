import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { cli } from './cli';

// A context takes the config's storage state, the administrator's session, unless it names its own.
const LOGGED_OUT = { cookies: [], origins: [] };

test.describe( 'Checkout', () => {
	let product: number;
	let customer: number;
	let comingSoon: string;

	test.beforeAll( async ( { requestUtils } ) => {
		// A new store shows its pages as coming soon to everyone but its managers, so the rows open it while they run.
		comingSoon = cli(
			'eval',
			'echo get_option( "woocommerce_coming_soon", "no" );'
		);
		cli( 'option', 'update', 'woocommerce_coming_soon', 'no' );
		for ( const gateway of [ 'cod', 'bacs' ] ) {
			cli(
				'wc',
				'payment_gateway',
				'update',
				gateway,
				'--enabled=true',
				'--user=1'
			);
		}
		cli(
			'option',
			'update',
			'dws_lpmwc_locked_payment_methods',
			JSON.stringify( [ 'cod' ] ),
			'--format=json'
		);
		product = (
			await requestUtils.rest< { id: number } >( {
				method: 'POST',
				path: '/wc/v3/products',
				data: {
					name: 'Locked checkout',
					type: 'simple',
					regular_price: '10',
					virtual: true,
				},
			} )
		).id;
		customer = Number(
			cli(
				'user',
				'create',
				'lpm-e2e-customer',
				'lpm-e2e-customer@example.org',
				'--role=customer',
				'--user_pass=password',
				'--porcelain'
			)
		);
		cli(
			'eval',
			`update_user_option( ${ customer }, DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS, array( 'cod' ) );`
		);
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wc/v3/products/${ product }`,
			params: { force: true },
		} );
		cli( 'user', 'delete', String( customer ), '--yes' );
		// The block checkout and the Store API's checkout leave draft orders, which the next start's order tables check counts as unsynced.
		cli(
			'eval',
			'foreach ( wc_get_orders( array( "status" => "checkout-draft", "limit" => -1 ) ) as $order ) { $order->delete( true ); }'
		);
		cli(
			'option',
			'update',
			'dws_lpmwc_locked_payment_methods',
			'[]',
			'--format=json'
		);
		for ( const gateway of [ 'cod', 'bacs' ] ) {
			cli(
				'wc',
				'payment_gateway',
				'update',
				gateway,
				'--enabled=false',
				'--user=1'
			);
		}
		cli( 'option', 'update', 'woocommerce_coming_soon', comingSoon );
	} );

	test( 'a customer granted a locked payment method may choose it on the block checkout', async ( {
		browser,
		baseURL,
	} ) => {
		const context = await browser.newContext( {
			baseURL,
			storageState: LOGGED_OUT,
		} );

		try {
			const page = await context.newPage();
			await page.goto( '/wp-login.php' );
			await page
				.getByLabel( 'Username or Email Address' )
				.fill( 'lpm-e2e-customer' );
			await page
				.getByLabel( 'Password', { exact: true } )
				.fill( 'password' );
			await page.getByRole( 'button', { name: 'Log In' } ).click();
			await page.waitForLoadState();
			await page.goto( `/?add-to-cart=${ product }` );

			await page.goto( '/checkout/' );
			await expect(
				page.getByLabel( 'Direct bank transfer' )
			).toBeVisible();
			await expect( page.getByLabel( 'Cash on delivery' ) ).toBeVisible();
		} finally {
			await context.close();
		}
	} );

	test( "the Store API refuses a guest's checkout with a locked payment method", async ( {
		playwright,
		baseURL,
	} ) => {
		const api = await playwright.request.newContext( {
			baseURL,
			storageState: LOGGED_OUT,
		} );

		try {
			const nonce = (
				await api.get( '/wp-json/wc/store/v1/cart' )
			).headers().nonce;
			await api.post( '/wp-json/wc/store/v1/cart/add-item', {
				headers: { Nonce: nonce },
				data: { id: product, quantity: 1 },
			} );
			const checkout = await api.post( '/wp-json/wc/store/v1/checkout', {
				headers: { Nonce: nonce },
				data: {
					billing_address: {
						first_name: 'Guest',
						last_name: 'Buyer',
						address_1: '1 Main Street',
						city: 'Springfield',
						postcode: '12345',
						country: 'US',
						state: 'CA',
						email: 'lpm-e2e-guest@example.org',
					},
					payment_method: 'cod',
				},
			} );

			expect( checkout.status() ).toBe( 400 );
			expect( ( await checkout.json() ).code ).toBe(
				'woocommerce_rest_checkout_payment_method_disabled'
			);
		} finally {
			await api.dispose();
		}
	} );
} );
