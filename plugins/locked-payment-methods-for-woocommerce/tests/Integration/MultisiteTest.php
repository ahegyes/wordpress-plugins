<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Options;
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
			// WooCommerce installs itself on a new site's first request, which queries its tables before they exist and translates early on WordPress trunk, so that request runs without the plugin and only its exit status and last line count.
			\exec( self::command( 'echo (int) defined( "DWS_LPMWC_FILE" );', '--url=' . get_home_url( $site_id, '/' ), '--skip-plugins=' . Plugin::SLUG ) . ' 2>&1', $installed, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- The warm-up's output is WooCommerce's install, which the row does not judge.
			$installed = \array_values( \array_filter( $installed, static fn ( string $line ): bool => '' !== \trim( $line ) ) );
			self::assertSame( 0, $status, \implode( "\n", $installed ) );
			self::assertSame( '0', \end( $installed ), \implode( "\n", $installed ) );

			$request = self::request( 'echo did_action( "locked_payment_methods_for_woocommerce/boot/succeeded" );', '--url=' . get_home_url( $site_id, '/' ) );

			self::assertSame( '1', $request['last'], $request['output'] );
			self::assertSame( 'no', get_blog_option( $site_id, Options::DELETE_DATA ) );
			self::assertTrue( update_blog_option( $site_id, Options::DELETE_DATA, 'yes' ) );
			self::assertSame( 'no', get_option( Options::DELETE_DATA ) );
		} finally {
			wp_delete_site( $site_id );
		}
	}

	public function test_a_users_grants_live_on_the_site_that_granted_them(): void {
		global $wpdb;

		$site_id = wp_insert_site(
			array(
				'domain' => get_network()?->domain,
				'path'   => get_network()?->path . \str_replace( '_', '-', Plugin::PREFIX ) . '-grants/',
			)
		);
		self::assertIsInt( $site_id );
		$user     = (int) wp_insert_user(
			array(
				'user_login' => 'lpm-multisite-grants',
				'user_pass'  => 'password',
				'user_email' => 'lpm-multisite-grants@example.org',
			)
		);
		$strategy = dws_lpmwc_get_unlock_strategy( 'user_profile' );
		self::assertInstanceOf( \DWS_LPMWC_Grant_Strategy::class, $strategy );

		try {
			switch_to_blog( $site_id );
			self::assertTrue( $strategy->grant( $user, 'cod' ) );
			self::assertSame( array( 'cod' ), $strategy->get_granted_ids( $user ) );
			restore_current_blog();

			self::assertSame( array(), $strategy->get_granted_ids( $user ) );
			self::assertSame( array( 'cod' ), get_user_meta( $user, $wpdb->get_blog_prefix( $site_id ) . 'dws_lpmwc_unlocked_payment_methods', true ) );
			self::assertSame( '', get_user_meta( $user, $wpdb->get_blog_prefix() . 'dws_lpmwc_unlocked_payment_methods', true ) );
		} finally {
			wpmu_delete_user( $user );
			wp_delete_site( $site_id );
		}
	}
}
