<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RunsRequestsTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Group( 'multisite' )]
final class MultisiteTest extends TestCase {
	use RunsRequestsTrait;

	public function test_the_plugin_boots_on_another_site_of_the_network_and_stores_its_settings_there(): void {
		$site_id = wp_insert_site(
			array(
				'domain' => get_network()?->domain,
				'path'   => get_network()?->path . \str_replace( '_', '-', Plugin::PREFIX ) . '-second/',
			)
		);
		self::assertIsInt( $site_id );

		try {
			// WooCommerce queries two of its tables during a new site's first init, before its version check installs them, so a request without the plugin installs WooCommerce first.
			$installed = self::request_provoking( 'WordPress database error', 'echo (int) function_exists( "dws_lpmwc_boot" );', '--url=' . get_home_url( $site_id, '/' ), '--skip-plugins=' . Plugin::SLUG );
			self::assertSame( 0, $installed['status'], $installed['output'] );
			self::assertSame( '0', $installed['last'], $installed['output'] );

			$request = self::request( 'echo did_action( "dws_lpmwc/boot/succeeded" );', '--url=' . get_home_url( $site_id, '/' ) );

			self::assertSame( '1', $request['last'], $request['output'] );
			self::assertSame( 'no', get_blog_option( $site_id, DWS_LPMWC_OPTION_DELETE_DATA ) );
			self::assertTrue( update_blog_option( $site_id, DWS_LPMWC_OPTION_DELETE_DATA, 'yes' ) );
			self::assertSame( 'no', get_option( DWS_LPMWC_OPTION_DELETE_DATA ) );
		} finally {
			wp_delete_site( $site_id );
		}
	}
}
