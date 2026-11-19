<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RunsRequestsTrait;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PartialDeployTest extends TestCase {
	use RestoresOptionsTrait;
	use RunsRequestsTrait;

	protected const array OPTIONS = array( Options::LOCKED_METHODS );

	protected const string NOTICE = 'Locked Payment Methods for WooCommerce is missing some of its files, so every locked payment method stays hidden from every customer until the plugin is reinstalled.';

	protected const string HIDDEN = 'While Locked Payment Methods for WooCommerce is not running, every locked payment method stays hidden from every customer.';

	// Prints the texts of the admin notices an administrator sees.
	protected const string NOTICES = 'require_once ABSPATH . "wp-admin/includes/plugin.php"; wp_set_current_user( 1 ); ob_start(); do_action( "all_admin_notices" ); $html = Dom\\HTMLDocument::createFromString( "<!DOCTYPE html><html><body>" . ob_get_clean() . "</body></html>", LIBXML_NOERROR ); echo wp_json_encode( array_map( static fn ( Dom\\Element $notice ): string => trim( $notice->textContent ), iterator_to_array( $html->querySelectorAll( "div.notice" ) ) ) );';

	// Finds the notice the entry hooks for an incomplete build, and prints whether the plugin booted, what an administrator and a visitor see of the notice, and whether a visitor is offered the locked cash on delivery.
	protected const string PROBE = '$notice = null; foreach ( isset( $GLOBALS["wp_filter"]["all_admin_notices"] ) ? $GLOBALS["wp_filter"]["all_admin_notices"]->callbacks : array() as $callbacks ) { foreach ( $callbacks as $callback ) { if ( $callback["function"] instanceof Closure && DWS_LPMWC_FILE === new ReflectionFunction( $callback["function"] )->getFileName() ) { $notice = $callback["function"]; } } } $seen = array(); foreach ( array( 1, 0 ) as $user ) { wp_set_current_user( $user ); ob_start(); if ( $notice instanceof Closure ) { $notice(); } $seen[] = trim( wp_strip_all_tags( (string) ob_get_clean() ) ); } echo wp_json_encode( array( did_action( "locked_payment_methods_for_woocommerce/boot/succeeded" ), $seen, array_key_exists( "cod", apply_filters( "woocommerce_available_payment_gateways", WC()->payment_gateways()->payment_gateways() ) ) ) );';

	#[Before]
	protected function lock_cash_on_delivery(): void {
		update_option( Options::LOCKED_METHODS, array( 'cod' ) );
	}

	public static function missing_build_files(): array {
		return array(
			'bootstrap-package' => array( 'vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php', '' ),
			'autoloader'        => array( 'vendor/autoload.php', 'Failed to open stream' ),
		);
	}

	public function test_a_complete_build_boots_and_shows_no_build_notice(): void {
		$request = self::request( self::PROBE );

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( 1, array( '', '' ), false ) ), $request['last'] );
	}

	#[DataProvider( 'missing_build_files' )]
	public function test_a_missing_build_file_shows_the_build_notice_to_plugin_managers_and_boots_nothing( string $file, string $diagnostic ): void {
		$path = \dirname( DWS_LPMWC_FILE ) . "/$file";
		\rename( $path, "$path.hidden" );
		try {
			$request = self::request_provoking( $diagnostic, self::PROBE );
		} finally {
			\rename( "$path.hidden", $path );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( 0, array( self::NOTICE, '' ), false ) ), $request['last'] );
	}

	public function test_an_include_that_throws_shows_the_build_notice_and_boots_nothing_without_a_log_line(): void {
		$include = \dirname( DWS_LPMWC_FILE ) . '/includes/zz-throws.php';
		\file_put_contents( $include, "<?php\nthrow new RuntimeException( 'A partial deploy.' );\n" );
		try {
			$request = self::request( self::PROBE );
		} finally {
			\unlink( $include );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( 0, array( self::NOTICE, '' ), false ) ), $request['last'] );
	}

	public function test_a_blocked_boot_adds_that_every_locked_method_stays_hidden(): void {
		$request = self::request( self::NOTICES, '--skip-plugins=woocommerce' );

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertContains( self::HIDDEN, (array) \json_decode( $request['last'], true ), $request['output'] );
	}

	public function test_a_failed_boot_adds_below_the_frameworks_notice_that_every_locked_method_stays_hidden(): void {
		$request = self::request( self::NOTICES, '--require=' . \dirname( __DIR__ ) . '/Support/fails-the-boot.php' );

		self::assertSame( 0, $request['status'], $request['output'] );
		$notices = (array) \json_decode( $request['last'], true );
		$failed  = \array_key_first( \array_filter( $notices, static fn ( mixed $notice ): bool => \str_contains( (string) $notice, 'could not finish starting' ) ) );
		self::assertIsInt( $failed, $request['output'] );
		self::assertSame( self::HIDDEN, $notices[ $failed + 1 ] ?? null, $request['output'] );
	}

	public function test_the_peer_getters_a_partial_deploy_defines_still_answer(): void {
		$include = \dirname( DWS_LPMWC_FILE ) . '/includes/metadata_zz-throws.php'; // Sorts right after metadata.php, so no later include loads.
		\file_put_contents( $include, "<?php\nthrow new RuntimeException( 'A partial deploy.' );\n" );
		try {
			$request = self::request( 'echo wp_json_encode( array( did_action( "locked_payment_methods_for_woocommerce/boot/succeeded" ), dws_lpmwc_get_plugin_slug(), dws_lpmwc_get_plugin_name(), dws_lpmwc_get_plugin_version() ) );' );
		} finally {
			\unlink( $include );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( 0, dws_lpmwc_get_plugin_slug(), dws_lpmwc_get_plugin_name(), dws_lpmwc_get_plugin_version() ) ), $request['last'] );
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_whose_engine_is_missing_keeps_the_data_and_lets_the_web_delete_the_files(): void {
		$consent = get_option( Options::DELETE_DATA );
		update_option( Options::DELETE_DATA, 'yes' );
		$log = (string) \tempnam( \sys_get_temp_dir(), 'dws-lpmwc-uninstall-' );
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
			self::assertTrue( uninstall_plugin( plugin_basename( DWS_LPMWC_FILE ) ) );
			self::assertSame( 'yes', get_option( Options::DELETE_DATA ) );
			self::assertStringContainsString( 'kept its data on uninstall', (string) \file_get_contents( $log ) );
		} finally {
			\unlink( $log );
			update_option( Options::DELETE_DATA, $consent );
		}
	}
}
