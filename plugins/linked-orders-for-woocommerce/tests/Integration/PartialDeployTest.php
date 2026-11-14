<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Options;
use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;
use DeepWebSolutions\LinkedOrders\Tests\Support\RunsRequestsTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PartialDeployTest extends TestCase {
	use RunsRequestsTrait;

	protected const string NOTICE = 'Linked Orders for WooCommerce is missing some of its files, so its features stay off until the plugin is reinstalled.';

	// Finds the notice the entry hooks for an incomplete build, and prints whether the plugin booted and what an administrator and a visitor see of the notice.
	protected const string PROBE = '$notice = null; foreach ( isset( $GLOBALS["wp_filter"]["all_admin_notices"] ) ? $GLOBALS["wp_filter"]["all_admin_notices"]->callbacks : array() as $callbacks ) { foreach ( $callbacks as $callback ) { if ( $callback["function"] instanceof Closure && DWS_LOWC_FILE === new ReflectionFunction( $callback["function"] )->getFileName() ) { $notice = $callback["function"]; } } } $seen = array(); foreach ( array( 1, 0 ) as $user ) { wp_set_current_user( $user ); ob_start(); if ( $notice instanceof Closure ) { $notice(); } $seen[] = trim( wp_strip_all_tags( (string) ob_get_clean() ) ); } echo wp_json_encode( array( did_action( "linked_orders_for_woocommerce/boot/succeeded" ), $seen ) );';

	public static function missing_build_files(): array {
		return array(
			'bootstrap-package' => array( 'vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php', '' ),
			'scripts-build'     => array( 'assets/build/orders-list-table.asset.php', '' ),
			'autoloader'        => array( 'vendor/autoload.php', 'Failed to open stream' ),
		);
	}

	public function test_a_complete_build_boots_and_shows_no_build_notice(): void {
		$request = self::request( self::PROBE );

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( 1, array( '', '' ) ) ), $request['last'] );
	}

	#[DataProvider( 'missing_build_files' )]
	public function test_a_missing_build_file_shows_the_build_notice_to_plugin_managers_and_boots_nothing( string $file, string $diagnostic ): void {
		$path = \dirname( DWS_LOWC_FILE ) . "/$file";
		\rename( $path, "$path.hidden" );
		try {
			$request = self::request_provoking( $diagnostic, self::PROBE );
		} finally {
			\rename( "$path.hidden", $path );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( 0, array( self::NOTICE, '' ) ) ), $request['last'] );
	}

	public function test_an_include_that_throws_shows_the_build_notice_and_boots_nothing_without_a_log_line(): void {
		$include = \dirname( DWS_LOWC_FILE ) . '/includes/zz-throws.php';
		\file_put_contents( $include, "<?php\nthrow new RuntimeException( 'A partial deploy.' );\n" );
		try {
			$request = self::request( self::PROBE );
		} finally {
			\unlink( $include );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( 0, array( self::NOTICE, '' ) ) ), $request['last'] );
	}

	public function test_the_peer_getters_a_partial_deploy_defines_still_answer(): void {
		$include = \dirname( DWS_LOWC_FILE ) . '/includes/metadata_zz-throws.php'; // Sorts right after metadata.php, so no later include loads.
		\file_put_contents( $include, "<?php\nthrow new RuntimeException( 'A partial deploy.' );\n" );
		try {
			$request = self::request( 'echo wp_json_encode( array( did_action( "linked_orders_for_woocommerce/boot/succeeded" ), dws_lowc_get_plugin_slug(), dws_lowc_get_plugin_name(), dws_lowc_get_plugin_version() ) );' );
		} finally {
			\unlink( $include );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( 0, dws_lowc_get_plugin_slug(), dws_lowc_get_plugin_name(), dws_lowc_get_plugin_version() ) ), $request['last'] );
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_whose_engine_is_missing_keeps_the_data_and_lets_the_web_delete_the_files(): void {
		$stored = get_option( Options::DELETE_DATA );
		update_option( Options::DELETE_DATA, 'yes' );
		$log = (string) \tempnam( \sys_get_temp_dir(), Plugin::PREFIX . '-uninstall-' );
		\ini_set( 'error_log', $log );
		\spl_autoload_register(
			static function ( string $class_name ): void {
				if ( Uninstaller::class === $class_name ) {
					throw new \Error( "Failed opening required '$class_name'." ); // Stands in for an engine that cannot load.
				}
			},
			true,
			true
		);

		try {
			self::assertTrue( uninstall_plugin( plugin_basename( DWS_LOWC_FILE ) ) );
			self::assertSame( 'yes', get_option( Options::DELETE_DATA ) );
			self::assertStringContainsString( 'kept its data on uninstall', (string) \file_get_contents( $log ) );
		} finally {
			\unlink( $log );
			false === $stored ? delete_option( Options::DELETE_DATA ) : update_option( Options::DELETE_DATA, $stored );
		}
	}
}
