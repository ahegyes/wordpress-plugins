<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Options;
use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Tests\Support\RunsRequestsTrait;
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
			\exec( self::command( 'echo (int) defined( "DWS_IC_FILE" );', '--url=' . get_home_url( $site_id, '/' ), '--skip-plugins=' . Plugin::SLUG ) . ' 2>&1', $installed, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- The warm-up's output is WooCommerce's install, which the row does not judge.
			$installed = \array_values( \array_filter( $installed, static fn ( string $line ): bool => '' !== \trim( $line ) ) );
			self::assertSame( 0, $status, \implode( "\n", $installed ) );
			self::assertSame( '0', \end( $installed ), \implode( "\n", $installed ) );

			$request = self::request( 'echo did_action( "internal_comments/boot/succeeded" );', '--url=' . get_home_url( $site_id, '/' ) );

			self::assertSame( '1', $request['last'], $request['output'] );
			self::assertSame( 'no', get_blog_option( $site_id, Options::DELETE_DATA ) );
			self::assertTrue( update_blog_option( $site_id, Options::DELETE_DATA, 'yes' ) );
			self::assertSame( 'no', get_option( Options::DELETE_DATA ) );
		} finally {
			wp_delete_site( $site_id );
		}
	}
}
