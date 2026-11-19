import type { Browser, BrowserContext, Page } from '@playwright/test';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { cli, keepOptions } from './cli';

// A context takes the config's storage state, the administrator's session, unless it names its own.
const LOGGED_OUT = { cookies: [], origins: [] };

const GATEWAYS = [ 'cod', 'bacs' ];

const logIn = async (
	browser: Browser,
	baseURL: string | undefined,
	login: string
): Promise< { context: BrowserContext; page: Page } > => {
	const context = await browser.newContext( {
		baseURL,
		storageState: LOGGED_OUT,
	} );
	const page = await context.newPage();
	await page.goto( '/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( login );
	await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await page.waitForLoadState();
	return { context, page };
};

test.describe( 'Checkout', () => {
	let product: number;
	let customer: number;
	let payer: number;
	const orders: number[] = [];
	let enabled: Record< string, string >;
	let restoreOptions: () => void;

	test.beforeAll( async ( { requestUtils } ) => {
		// A new store shows its pages as coming soon to everyone but its managers, so the rows open it while they run.
		restoreOptions = keepOptions( {
			woocommerce_coming_soon: 'no',
			dws_lpmwc_locked_payment_methods: [ 'cod' ],
			dws_lpmwc_unlock_via_orders: 'yes',
		} );
		enabled = JSON.parse(
			cli(
				'eval',
				`echo wp_json_encode( array_map( static fn ( WC_Payment_Gateway $gateway ): string => $gateway->enabled, array_intersect_key( WC()->payment_gateways()->payment_gateways(), array_flip( ${ JSON.stringify(
					GATEWAYS
				) } ) ) ) );`
			)
		);
		for ( const gateway of GATEWAYS ) {
			cli(
				'wc',
				'payment_gateway',
				'update',
				gateway,
				'--enabled=true',
				'--user=1'
			);
		}
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
			`update_user_option( ${ customer }, 'dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );`
		);
		payer = Number(
			cli(
				'user',
				'create',
				'lpm-e2e-payer',
				'lpm-e2e-payer@example.org',
				'--role=customer',
				'--user_pass=password',
				'--porcelain'
			)
		);
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// The block checkout and the Store API's checkout leave draft orders, which the next start's order tables check counts as unsynced, so the drafts holding the rows' product go with the rows' orders.
		cli(
			'eval',
			`foreach ( wc_get_orders( array( "status" => "checkout-draft", "limit" => -1 ) ) as $order ) { foreach ( $order->get_items() as $item ) { if ( ${ product } === $item->get_product_id() ) { $order->delete( true ); break; } } } foreach ( ${ JSON.stringify(
				orders
			) } as $id ) { wc_get_order( $id )?->delete( true ); }`
		);
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wc/v3/products/${ product }`,
			params: { force: true },
		} );
		cli( 'user', 'delete', String( customer ), String( payer ), '--yes' );
		for ( const gateway of GATEWAYS ) {
			cli(
				'wc',
				'payment_gateway',
				'update',
				gateway,
				`--enabled=${ 'yes' === enabled[ gateway ] }`,
				'--user=1'
			);
		}
		restoreOptions();
	} );

	test( 'a customer granted a locked payment method may choose it on the block checkout', async ( {
		browser,
		baseURL,
	} ) => {
		const { context, page } = await logIn(
			browser,
			baseURL,
			'lpm-e2e-customer'
		);

		try {
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

	test( 'a guest is not offered a locked payment method on the block checkout', async ( {
		browser,
		baseURL,
	} ) => {
		const context = await browser.newContext( {
			baseURL,
			storageState: LOGGED_OUT,
		} );

		try {
			const page = await context.newPage();
			await page.goto( `/?add-to-cart=${ product }` );

			await page.goto( '/checkout/' );
			await expect(
				page.getByLabel( 'Direct bank transfer' )
			).toBeVisible();
			await expect( page.getByLabel( 'Cash on delivery' ) ).toHaveCount(
				0
			);
		} finally {
			await context.close();
		}
	} );

	test( 'a customer pays an order granted a locked payment method with it on its payment page', async ( {
		browser,
		baseURL,
		requestUtils,
	} ) => {
		const order = await requestUtils.rest< {
			id: number;
			payment_url: string;
		} >( {
			method: 'POST',
			path: '/wc/v3/orders',
			data: {
				customer_id: payer,
				status: 'pending',
				line_items: [ { product_id: product, quantity: 1 } ],
				meta_data: [
					{
						key: '_dws_lpmwc_unlocked_payment_methods',
						value: [ 'cod' ],
					},
				],
			},
		} );
		orders.push( order.id );
		const { context, page } = await logIn(
			browser,
			baseURL,
			'lpm-e2e-payer'
		);

		try {
			await page.goto( order.payment_url );
			await page.getByLabel( 'Cash on delivery' ).check();
			await page.getByRole( 'button', { name: 'Pay for order' } ).click();

			await expect( page ).toHaveURL( /order-received/ );
			expect(
				cli(
					'eval',
					`echo wc_get_order( ${ order.id } )->get_payment_method();`
				)
			).toBe( 'cod' );
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
