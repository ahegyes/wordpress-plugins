<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Comments;
use DeepWebSolutions\InternalComments\Options;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;
use DeepWebSolutions\InternalComments\Tests\Support\RunsRequestsTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PartialDeployTest extends TestCase {
	use RunsRequestsTrait;

	protected const string NOTICE = 'Internal Comments is missing some of its files, so its features stay off until the plugin is reinstalled.';

	// Finds the notice the entry hooks for an incomplete build, and prints whether the plugin booted and what an administrator and a visitor see of the notice.
	protected const string PROBE = '$notice = null; foreach ( isset( $GLOBALS["wp_filter"]["all_admin_notices"] ) ? $GLOBALS["wp_filter"]["all_admin_notices"]->callbacks : array() as $callbacks ) { foreach ( $callbacks as $callback ) { if ( $callback["function"] instanceof Closure && DWS_IC_FILE === new ReflectionFunction( $callback["function"] )->getFileName() ) { $notice = $callback["function"]; } } } $seen = array(); foreach ( array( 1, 0 ) as $user ) { wp_set_current_user( $user ); ob_start(); if ( $notice instanceof Closure ) { $notice(); } $seen[] = trim( wp_strip_all_tags( (string) ob_get_clean() ) ); } echo wp_json_encode( array( did_action( "internal_comments/boot/succeeded" ), $seen ) );';

	// As an administrator, who moderates comments and edits orders: core's edit check by id and by an empty id, which names the loop's comment, a REST update that approves and a deletion, WooCommerce's note route and its note deletion, then what is left of both comments.
	protected const string CORE_ACTIONS = 'wp_set_current_user( 1 ); $update = new WP_REST_Request( "PUT", "/wp/v2/comments/%1$d" ); $update->set_body_params( array( "status" => "approve" ) ); $delete = new WP_REST_Request( "DELETE", "/wp/v2/comments/%1$d" ); $delete->set_query_params( array( "force" => true ) ); $note = rest_do_request( new WP_REST_Request( "GET", "/wc/v3/orders/%3$d/notes/%4$d" ) )->get_status(); $_POST = array( "note_id" => "%4$dabc", "security" => wp_create_nonce( "delete-order-note" ) ); $_REQUEST = $_POST; add_filter( "wp_doing_ajax", "__return_true" ); add_filter( "wp_die_ajax_handler", static fn () => static function ( $message, $title = "", $args = array() ) { throw new RuntimeException( (string) ( $args["response"] ?? "" ) ); } ); try { do_action( "wp_ajax_woocommerce_delete_order_note" ); $deletion = "not stopped"; } catch ( RuntimeException $stopped ) { $deletion = $stopped->getMessage(); } $GLOBALS["comment"] = get_comment( %1$d ); $looped = current_user_can( "edit_comment", 0 ); unset( $GLOBALS["comment"] ); echo wp_json_encode( array( did_action( "internal_comments/boot/succeeded" ), current_user_can( "edit_comment", %1$d ), $looped, current_user_can( "edit_comment", %2$d ), rest_do_request( $update )->get_status(), rest_do_request( $delete )->get_status(), $note, $deletion, get_comment( %1$d )?->comment_approved, get_comment( %4$d ) instanceof WP_Comment ) );';

	public static function missing_build_files(): array {
		return array(
			'bootstrap-package' => array( 'vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php', '' ),
			'scripts-build'     => array( 'assets/build/meta-box.asset.php', '' ),
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
		$path = \dirname( DWS_IC_FILE ) . "/$file";
		\rename( $path, "$path.hidden" );
		try {
			$request = self::request_provoking( $diagnostic, self::PROBE );
		} finally {
			\rename( "$path.hidden", $path );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( 0, array( self::NOTICE, '' ) ) ), $request['last'] );
	}

	// The safeguard loads before the autoloader, so core and WooCommerce still ask the plugin before they let anyone change or read an internal comment, whatever capability lets them past their own checks.
	public function test_a_build_that_cannot_boot_leaves_internal_comments_unchangeable_through_core(): void {
		$post     = (int) wp_insert_post(
			array(
				'post_title'  => 'Safeguarded',
				'post_status' => 'publish',
			)
		);
		$order    = wc_create_order();
		$author   = get_user_by( 'id', 1 ) ?: null;
		$internal = (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $post ), $author );
		$noted    = (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $order->get_id() ), $author );
		$public   = (int) wp_insert_comment( array( 'comment_post_ID' => $post ) );
		$path     = \dirname( DWS_IC_FILE ) . '/vendor/autoload.php';
		\rename( $path, "$path.hidden" );
		try {
			$request = self::request_provoking( 'Failed to open stream', \sprintf( self::CORE_ACTIONS, $internal, $public, $order->get_id(), $noted ) );
		} finally {
			\rename( "$path.hidden", $path );
			wp_delete_post( $post, true );
			wp_delete_comment( $noted, true );
			$order->delete( true );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( 0, false, false, true, 403, 403, 404, '403', Comments::STATUS, true ) ), $request['last'] );
	}

	public function test_an_include_that_throws_shows_the_build_notice_and_boots_nothing_without_a_log_line(): void {
		$request = self::request_with_throwing_include( 'zz-throws.php', self::PROBE );

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( 0, array( self::NOTICE, '' ) ) ), $request['last'] );
	}

	public function test_the_peer_getters_a_partial_deploy_defines_still_answer(): void {
		// The include sorts right after metadata.php, so no later include loads.
		$request = self::request_with_throwing_include( 'metadata_zz-throws.php', 'echo wp_json_encode( array( did_action( "internal_comments/boot/succeeded" ), dws_ic_get_plugin_slug(), dws_ic_get_plugin_name(), dws_ic_get_plugin_version() ) );' );

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( 0, dws_ic_get_plugin_slug(), dws_ic_get_plugin_name(), dws_ic_get_plugin_version() ) ), $request['last'] );
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_whose_engine_is_missing_keeps_the_data_and_lets_the_web_delete_the_files(): void {
		$stored = get_option( Options::DELETE_DATA );
		update_option( Options::DELETE_DATA, 'yes' );
		$log = (string) \tempnam( \sys_get_temp_dir(), 'dws-ic-uninstall-' );
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
			self::assertTrue( uninstall_plugin( plugin_basename( DWS_IC_FILE ) ) );
			self::assertSame( 'yes', get_option( Options::DELETE_DATA ) );
			self::assertStringContainsString( 'kept its data on uninstall', (string) \file_get_contents( $log ) );
		} finally {
			\unlink( $log );
			update_option( Options::DELETE_DATA, $stored );
		}
	}

	protected static function request_with_throwing_include( string $name, string $php ): array {
		$include = \dirname( DWS_IC_FILE ) . "/includes/$name";
		\file_put_contents( $include, "<?php\nthrow new RuntimeException( 'A partial deploy.' );\n" );
		try {
			return self::request( $php );
		} finally {
			\unlink( $include );
		}
	}
}
