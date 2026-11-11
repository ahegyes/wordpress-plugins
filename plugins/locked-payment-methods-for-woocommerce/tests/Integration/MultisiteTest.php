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
