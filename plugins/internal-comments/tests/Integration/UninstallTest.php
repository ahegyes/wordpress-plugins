<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Plugin;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class UninstallTest extends TestCase {
	protected const int USER = 1;

	#[AfterClass]
	public static function forget_the_rows_the_uninstall_deleted(): void {
		wp_cache_flush(); // The uninstall ran in a child process, so this process still caches the rows it deleted, such as a setting the tab saved.
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_checks_the_floors_of_its_own_main_file(): void {
		\define( 'WP_UNINSTALL_PLUGIN', 'another-plugin/another-plugin.php' ); // A bulk uninstall keeps the first plugin's value for every later one.
		$source = (string) \file_get_contents( DWS_IC_FILE );
		\file_put_contents( DWS_IC_FILE, (string) \preg_replace( '/^(\s*\*\s*Requires PHP:\s*).*$/m', '${1}99.0', $source ) );
		$log = (string) \tempnam( \sys_get_temp_dir(), 'dws-tpl-uninstall-' );
		\ini_set( 'error_log', $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- The child process's stderr would fail the test.

		try {
			include \dirname( DWS_IC_FILE ) . '/uninstall.php';
		} finally {
			\file_put_contents( DWS_IC_FILE, $source );
		}

		self::assertStringContainsString( 'below its PHP or WordPress floor', (string) \file_get_contents( $log ) );
		\unlink( $log );
	}

	#[RunInSeparateProcess]
	public function test_a_consenting_uninstall_removes_a_row_of_every_kind_the_framework_roots_store(): void {
		$prefix = Plugin::PREFIX . '_';
		update_option( DWS_IC_OPTION_DELETE_DATA, 'yes' );
		update_option( "{$prefix}notices", array( 'seeded' ) );
		update_network_option( null, "{$prefix}network_notices", array( 'seeded' ) );
		set_transient( "{$prefix}boot_failure_seeded", 1, HOUR_IN_SECONDS );
		update_user_option( self::USER, "{$prefix}hidden_notices", array( 'seeded' ) );
		update_user_meta( self::USER, "{$prefix}network_hidden_notices", array( 'seeded' ) );
		wp_schedule_single_event( \time() + HOUR_IN_SECONDS, "{$prefix}revision/migration/run" );
		as_schedule_single_action( \time() + HOUR_IN_SECONDS, "{$prefix}seeded", array(), Plugin::PREFIX );

		self::assertTrue( uninstall_plugin( plugin_basename( DWS_IC_FILE ) ) );
		wp_cache_flush();

		self::assertFalse( get_option( DWS_IC_OPTION_DELETE_DATA ) );
		self::assertFalse( get_option( "{$prefix}notices" ) );
		self::assertFalse( get_network_option( null, "{$prefix}network_notices" ) );
		self::assertFalse( get_transient( "{$prefix}boot_failure_seeded" ) );
		self::assertFalse( get_user_option( "{$prefix}hidden_notices", self::USER ) );
		self::assertSame( '', get_user_meta( self::USER, "{$prefix}network_hidden_notices", true ) );
		self::assertFalse( wp_next_scheduled( "{$prefix}revision/migration/run" ) );
		self::assertSame(
			array(),
			as_get_scheduled_actions(
				array(
					'group'  => Plugin::PREFIX,
					'status' => 'pending',
				),
				'ids'
			)
		);

		// The other tests share the database, so the runner stores again what a new site starts with.
		dws_ic_plugin()->migrations?->run();
		self::assertSame( 'no', get_option( DWS_IC_OPTION_DELETE_DATA ) );
	}
}
