<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DeprecatedApiTest extends TestCase {
	protected array $deprecated = array();

	public static function hook_tags(): array {
		return array(
			'plugin'                => array( 'dws_ic_get_hook_tag', array( 'create_comment_data' ), 'internal_comments/create_comment_data' ),
			'plugin-extra'          => array( 'dws_ic_get_hook_tag', array( 'comments', array( 'before_get_query' ) ), 'internal_comments/comments/before_get_query' ),
			'plugin-unsafe'         => array( 'dws_ic_get_hook_tag', array( 'Some Name', array( 'A\\B', '' ) ), 'internal_comments/some_name/a_b' ),
			'component'             => array( 'dws_ic_get_component_hook_tag', array( 'metabox-output', 'inline_actions', 'insert' ), 'internal_comments/metabox_output/inline_actions/insert' ),
			'component-nested'      => array( 'dws_ic_get_component_hook_tag', array( 'metabox-output', 'single_comment', array( 'actions', 'start' ) ), 'internal_comments/metabox_output/single_comment/actions/start' ),
			'component-group'       => array( 'dws_ic_get_component_hook_tag', array( 'general-settings', 'get_group_fields' ), 'internal_comments/general_settings/get_group_fields' ),
			'component-table'       => array( 'dws_ic_get_component_hook_tag', array( 'wp-table-output', 'insert_after' ), 'internal_comments/wp_table_output/insert_after' ),
			'component-list-table'  => array( 'dws_ic_get_component_hook_tag', array( 'comments-list-table', 'name' ), 'internal_comments/internal_comments_list_table/name' ),
			'component-permissions' => array( 'dws_ic_get_component_hook_tag', array( 'comment-permissions', 'name' ), 'internal_comments/comments_permissions/name' ),
			'component-ajax'        => array( 'dws_ic_get_component_hook_tag', array( 'ajax', 'before_insert_comment' ), 'internal_comments/ajax/before_insert_comment' ),
			'component-absent'      => array( 'dws_ic_get_component_hook_tag', array( 'freemius', 'name' ), null ),
		);
	}

	#[Before]
	protected function record_deprecations(): void {
		add_filter( 'deprecated_function_trigger_error', '__return_false' );
		add_filter( 'deprecated_hook_trigger_error', '__return_false' );
		add_action( 'deprecated_function_run', array( $this, 'record' ), 10, 2 );
		add_action( 'deprecated_hook_run', array( $this, 'record' ), 10, 2 );
	}

	#[After]
	protected function stop_recording(): void {
		remove_filter( 'deprecated_function_trigger_error', '__return_false' );
		remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		remove_action( 'deprecated_function_run', array( $this, 'record' ) );
		remove_action( 'deprecated_hook_run', array( $this, 'record' ) );
	}

	public function record( string $name, string $replacement ): void {
		$this->deprecated[] = array( $name, $replacement );
	}

	public function test_the_v1_getters_answer_from_the_plugin_header(): void {
		$header = get_file_data(
			DWS_IC_FILE,
			array(
				'Version'     => 'Version',
				'RequiresWP'  => 'Requires at least',
				'RequiresPHP' => 'Requires PHP',
			)
		);

		self::assertSame( dws_ic_get_plugin_name(), dws_ic_name() );
		self::assertSame( $header['Version'], dws_ic_version() );
		self::assertSame( DWS_IC_FILE, dws_ic_path() );
		self::assertSame( $header['RequiresPHP'], dws_ic_min_php() );
		self::assertSame( $header['RequiresWP'], dws_ic_min_wp() );
		self::assertSame(
			array(
				array( 'dws_ic_name', 'dws_ic_get_plugin_name()' ),
				array( 'dws_ic_version', 'dws_ic_get_plugin_version()' ),
				array( 'dws_ic_path', '' ),
				array( 'dws_ic_min_php', '' ),
				array( 'dws_ic_min_wp', '' ),
			),
			$this->deprecated
		);
	}

	public function test_the_v1_constants_hold_the_plugin_header(): void {
		$header = get_file_data(
			DWS_IC_FILE,
			array(
				'Version'     => 'Version',
				'RequiresWP'  => 'Requires at least',
				'RequiresPHP' => 'Requires PHP',
			)
		);

		self::assertSame( 'Internal Comments', DWS_IC_NAME );
		self::assertSame( $header['Version'], DWS_IC_VERSION );
		self::assertSame( DWS_IC_FILE, DWS_IC_PATH );
		self::assertSame( $header['RequiresPHP'], DWS_IC_MIN_PHP );
		self::assertSame( $header['RequiresWP'], DWS_IC_MIN_WP );
	}

	#[DataProvider( 'hook_tags' )]
	public function test_a_v1_hook_tag_builds_as_v1_built_it( string $shim, array $arguments, ?string $expected ): void {
		self::assertSame( $expected, $shim( ...$arguments ) );
		self::assertSame( array( array( $shim, '' ) ), $this->deprecated );
	}

	public function test_the_v1_boot_hooks_fire_after_the_v2_ones_with_the_failure(): void {
		$fired = array();
		$spy   = static function ( mixed $error = null ) use ( &$fired ): void {
			$fired[] = array( current_action(), $error );
		};
		add_action( 'dws_ic_initialized', $spy );
		add_action( 'dws_ic_initialization_failure', $spy );
		$error = new \RuntimeException( 'Failed.' );

		try {
			do_action( 'dws_ic/boot/succeeded' );
			do_action( 'dws_ic/boot/failed', $error );
		} finally {
			remove_action( 'dws_ic_initialized', $spy );
			remove_action( 'dws_ic_initialization_failure', $spy );
		}

		self::assertSame(
			array(
				array( 'dws_ic_initialized', null ),
				array( 'dws_ic_initialization_failure', $error ),
			),
			$fired
		);
		self::assertSame(
			array(
				array( 'dws_ic_initialized', 'dws_ic/boot/succeeded' ),
				array( 'dws_ic_initialization_failure', 'dws_ic/boot/failed' ),
			),
			$this->deprecated
		);
	}
}
