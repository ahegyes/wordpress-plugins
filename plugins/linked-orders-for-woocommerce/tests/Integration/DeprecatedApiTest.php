<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

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
			'plugin'           => array( 'dws_lowc_get_hook_tag', array( 'post_type', 'supported_order_types' ), 'linked_orders_for_woocommerce/post_type/supported_order_types' ),
			'plugin-no-extra'  => array( 'dws_lowc_get_hook_tag', array( 'created_linked_order' ), 'linked_orders_for_woocommerce/created_linked_order' ),
			'plugin-unsafe'    => array( 'dws_lowc_get_hook_tag', array( 'Some Name', array( 'A\\B', '' ) ), 'linked_orders_for_woocommerce/some_name/a_b' ),
			'component'        => array( 'dws_lowc_get_component_hook_tag', array( 'list-table-output', 'column', 'insert_after' ), 'linked_orders_for_woocommerce/list_table_output/column/insert_after' ),
			'component-meta'   => array( 'dws_lowc_get_component_hook_tag', array( 'metabox-output', 'after_add_new_child_button' ), 'linked_orders_for_woocommerce/metabox_output/after_add_new_child_button' ),
			'component-group'  => array( 'dws_lowc_get_component_hook_tag', array( 'general-settings', 'get_group_fields' ), 'linked_orders_for_woocommerce/general_settings/get_group_fields' ),
			'component-sos'    => array( 'dws_lowc_get_component_hook_tag', array( 'wc-sequential-order-numbers-pro-integration', 'name' ), 'linked_orders_for_woocommerce/wc_sequential_order_numbers_pro_integration/name' ),
			'component-absent' => array( 'dws_lowc_get_component_hook_tag', array( 'freemius', 'name' ), null ),
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
			DWS_LOWC_FILE,
			array(
				'Version'     => 'Version',
				'RequiresWP'  => 'Requires at least',
				'RequiresPHP' => 'Requires PHP',
			)
		);

		self::assertSame( dws_lowc_get_plugin_name(), dws_lowc_name() );
		self::assertSame( $header['Version'], dws_lowc_version() );
		self::assertSame( DWS_LOWC_FILE, dws_lowc_path() );
		self::assertSame( $header['RequiresPHP'], dws_lowc_min_php() );
		self::assertSame( $header['RequiresWP'], dws_lowc_min_wp() );
		self::assertSame(
			array(
				array( 'dws_lowc_name', 'dws_lowc_get_plugin_name()' ),
				array( 'dws_lowc_version', 'dws_lowc_get_plugin_version()' ),
				array( 'dws_lowc_path', '' ),
				array( 'dws_lowc_min_php', '' ),
				array( 'dws_lowc_min_wp', '' ),
			),
			$this->deprecated
		);
	}

	public function test_the_v1_constants_hold_the_plugin_header(): void {
		$header = get_file_data(
			DWS_LOWC_FILE,
			array(
				'Version'     => 'Version',
				'RequiresWP'  => 'Requires at least',
				'RequiresPHP' => 'Requires PHP',
			)
		);

		self::assertSame( 'Linked Orders for WooCommerce', DWS_LOWC_NAME );
		self::assertSame( $header['Version'], DWS_LOWC_VERSION );
		self::assertSame( DWS_LOWC_FILE, DWS_LOWC_PATH );
		self::assertSame( $header['RequiresPHP'], DWS_LOWC_MIN_PHP );
		self::assertSame( $header['RequiresWP'], DWS_LOWC_MIN_WP );
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
		add_action( 'dws_lowc_initialized', $spy );
		add_action( 'dws_lowc_initialization_failure', $spy );
		$error = new \RuntimeException( 'Failed.' );

		try {
			do_action( 'dws_lowc/boot/succeeded' );
			do_action( 'dws_lowc/boot/failed', $error );
		} finally {
			remove_action( 'dws_lowc_initialized', $spy );
			remove_action( 'dws_lowc_initialization_failure', $spy );
		}

		self::assertSame(
			array(
				array( 'dws_lowc_initialized', null ),
				array( 'dws_lowc_initialization_failure', $error ),
			),
			$fired
		);
		self::assertSame(
			array(
				array( 'dws_lowc_initialized', 'dws_lowc/boot/succeeded' ),
				array( 'dws_lowc_initialization_failure', 'dws_lowc/boot/failed' ),
			),
			$this->deprecated
		);
	}
}
