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
		wp_cache_flush(); // The uninstall ran in a child process, so this process still caches the rows it deleted, such as a setting the page saved.
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_checks_the_floors_of_its_own_main_file(): void {
		\define( 'WP_UNINSTALL_PLUGIN', 'another-plugin/another-plugin.php' ); // A bulk uninstall keeps the first plugin's value for every later one.
		$source = (string) \file_get_contents( DWS_IC_FILE );
		\file_put_contents( DWS_IC_FILE, (string) \preg_replace( '/^(\s*\*\s*Requires PHP:\s*).*$/m', '${1}99.0', $source ) );
		$log = (string) \tempnam( \sys_get_temp_dir(), 'dws-ic-uninstall-' );
		\ini_set( 'error_log', $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- The child process's stderr would fail the test.

		try {
			include \dirname( DWS_IC_FILE ) . '/uninstall.php';
		} finally {
			\file_put_contents( DWS_IC_FILE, $source );
		}

		self::assertStringContainsString( 'below its PHP or WordPress floor', (string) \file_get_contents( $log ) );
		\unlink( $log );
	}

	// WordPress uninstalls an inactive plugin, so the request loads uninstall.php alone, never the entry file or functions.php.
	public function test_an_uninstall_in_a_request_that_skips_the_plugin_removes_its_data(): void {
		update_option( DWS_IC_OPTION_PLUGIN, array( DWS_IC_SETTING_DELETE_DATA => 'yes' ) );
		$php = 'uninstall_plugin( "internal-comments/internal-comments.php" ); echo wp_json_encode( array( defined( "DWS_IC_FILE" ), get_option( "' . DWS_IC_OPTION_PLUGIN . '" ) ) );';

		\exec( 'wp --path=' . \escapeshellarg( ABSPATH ) . ' --skip-plugins=internal-comments eval ' . \escapeshellarg( $php ) . ' 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- The uninstall needs a request without the plugin.

		// The other tests share the database, so a request with the plugin stores again what a new site starts with, as the site's next request would.
		\exec( 'wp --path=' . \escapeshellarg( ABSPATH ) . ' eval ' . \escapeshellarg( 'do_action( "' . Plugin::PREFIX . '_revision/migration/run" );' ) . ' 2>&1', $restored, $restore_status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- The restore needs a request with the plugin.
		wp_cache_flush();
		wp_roles()->for_site();

		self::assertSame( 0, $status, \implode( "\n", $output ) );
		self::assertSame( '[false,false]', (string) \end( $output ) );
		self::assertSame( 0, $restore_status, \implode( "\n", $restored ) );
		self::assertSame( DWS_IC_SETTING_DEFAULTS[ DWS_IC_OPTION_PLUGIN ], get_option( DWS_IC_OPTION_PLUGIN ) );
		self::assertTrue( get_role( 'editor' )?->has_cap( DWS_IC_CAP_INSERT ) );
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_after_v1_consent_removes_the_internal_comments_v1s_rows_and_the_capabilities(): void {
		update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_POST_TYPES => array( 'post' ) ) );
		update_option( DWS_IC_OPTION_PLUGIN, array( DWS_IC_SETTING_DELETE_DATA => 'yes' ) );
		update_option( 'internal_comments_version', array( 'seeded' ) );
		update_option( 'internal_comments_original_version', array( 'seeded' ) );
		update_option( '_internal_comments_admin_notices', array( 'seeded' ) );
		update_user_meta( self::USER, '_internal_comments_admin_notices', array( 'seeded' ) );
		$post     = wp_insert_post(
			array(
				'post_title'  => 'Uninstall',
				'post_status' => 'publish',
			)
		);
		$public   = wp_insert_comment(
			array(
				'comment_post_ID' => $post,
				'comment_content' => 'Public',
			)
		);
		$internal = array();
		foreach ( array( '1', 'trash', 'post-trashed' ) as $status ) {
			$internal[] = wp_insert_comment(
				array(
					'comment_post_ID'  => $post,
					'comment_content'  => "Internal, $status",
					'comment_type'     => 'dws_internal',
					'comment_approved' => $status,
				)
			);
		}
		add_comment_meta( $internal[0], 'dws_ic_is_private', 'yes' );

		self::assertTrue( uninstall_plugin( plugin_basename( DWS_IC_FILE ) ) );
		wp_cache_flush();
		wp_roles()->for_site();

		foreach ( $internal as $comment ) {
			self::assertNull( get_comment( $comment ), "The internal comment $comment is still stored." );
		}
		self::assertSame( '', get_comment_meta( $internal[0], 'dws_ic_is_private', true ) );
		self::assertInstanceOf( \WP_Comment::class, get_comment( $public ) );
		foreach ( array( DWS_IC_OPTION_GENERAL, DWS_IC_OPTION_PLUGIN, 'internal_comments_version', 'internal_comments_original_version', '_internal_comments_admin_notices' ) as $option ) {
			self::assertFalse( get_option( $option ), "The option $option is still stored." );
		}
		self::assertSame( '', get_user_meta( self::USER, '_internal_comments_admin_notices', true ) );
		foreach ( DWS_IC_CAPABILITY_GRANTS as $role => $capabilities ) {
			foreach ( $capabilities as $capability ) {
				self::assertFalse( (bool) get_role( $role )?->has_cap( $capability ), "The $role role still holds $capability." );
			}
		}

		// The other tests share the database, so the roles get their capabilities back and the runner stores again what a new site starts with.
		foreach ( DWS_IC_CAPABILITY_GRANTS as $role => $capabilities ) {
			foreach ( $capabilities as $capability ) {
				get_role( $role )?->add_cap( $capability );
			}
		}
		do_action( Plugin::PREFIX . '_revision/migration/run' );
		wp_delete_post( $post, true );
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_without_consent_keeps_the_internal_comments(): void {
		update_option( DWS_IC_OPTION_PLUGIN, array( DWS_IC_SETTING_DELETE_DATA => 'no' ) );
		$post     = wp_insert_post( array( 'post_title' => 'Kept' ) );
		$internal = wp_insert_comment(
			array(
				'comment_post_ID' => $post,
				'comment_content' => 'Kept',
				'comment_type'    => 'dws_internal',
			)
		);

		self::assertTrue( uninstall_plugin( plugin_basename( DWS_IC_FILE ) ) );
		wp_cache_flush();

		self::assertInstanceOf( \WP_Comment::class, get_comment( $internal ) );
		self::assertSame( array( DWS_IC_SETTING_DELETE_DATA => 'no' ), get_option( DWS_IC_OPTION_PLUGIN ) );

		wp_delete_post( $post, true );
	}

	#[RunInSeparateProcess]
	public function test_a_consenting_uninstall_removes_a_row_of_every_kind_the_framework_roots_store(): void {
		$prefix = Plugin::PREFIX . '_';
		update_option( DWS_IC_OPTION_PLUGIN, array( DWS_IC_SETTING_DELETE_DATA => 'yes' ) );
		update_option( "{$prefix}notices", array( 'seeded' ) );
		update_network_option( null, "{$prefix}network_notices", array( 'seeded' ) );
		set_transient( "{$prefix}boot_failure_seeded", 1, HOUR_IN_SECONDS );
		update_user_option( self::USER, "{$prefix}hidden_notices", array( 'seeded' ) );
		update_user_meta( self::USER, "{$prefix}network_hidden_notices", array( 'seeded' ) );
		wp_schedule_single_event( \time() + HOUR_IN_SECONDS, "{$prefix}revision/migration/run" );
		as_schedule_single_action( \time() + HOUR_IN_SECONDS, "{$prefix}seeded", array(), Plugin::PREFIX );

		self::assertTrue( uninstall_plugin( plugin_basename( DWS_IC_FILE ) ) );
		wp_cache_flush();

		self::assertFalse( get_option( DWS_IC_OPTION_PLUGIN ) );
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
		foreach ( DWS_IC_CAPABILITY_GRANTS as $role => $capabilities ) {
			foreach ( $capabilities as $capability ) {
				get_role( $role )?->add_cap( $capability );
			}
		}
		do_action( Plugin::PREFIX . '_revision/migration/run' );
		self::assertSame( DWS_IC_SETTING_DEFAULTS[ DWS_IC_OPTION_PLUGIN ], get_option( DWS_IC_OPTION_PLUGIN ) );
	}
}
