<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Tests\Support\OrderStorageTrait;
use DeepWebSolutions\LinkedOrders\Tests\Support\RecordsDeprecationsTrait;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DeprecatedApiTest extends TestCase {
	use OrderStorageTrait;
	use RecordsDeprecationsTrait;

	protected static array $header = array();


	public static function hook_tags(): array {
		return array(
			'plugin'           => array( 'dws_lowc_get_hook_tag', array( 'post_type', 'supported_order_types' ), 'linked_orders_for_woocommerce/post_type/supported_order_types' ),
			'plugin-no-extra'  => array( 'dws_lowc_get_hook_tag', array( 'created_linked_order' ), 'linked_orders_for_woocommerce/created_linked_order' ),
			'plugin-unsafe'    => array( 'dws_lowc_get_hook_tag', array( 'Some Name', array( 'A\\B', '' ) ), 'linked_orders_for_woocommerce/some_name/a_b' ),
			'plugin-non-ascii' => array( 'dws_lowc_get_hook_tag', array( "Caf\u{e9}" ), 'linked_orders_for_woocommerce/caf' ),
			'plugin-bytes'     => array( 'dws_lowc_get_hook_tag', array( 'name', array( "ex\x01tra\u{e9}" ) ), 'linked_orders_for_woocommerce/name/extra' ),
			'component'        => array( 'dws_lowc_get_component_hook_tag', array( 'list-table-output', 'column', 'insert_after' ), 'linked_orders_for_woocommerce/list_table_output/column/insert_after' ),
			'component-meta'   => array( 'dws_lowc_get_component_hook_tag', array( 'metabox-output', 'after_add_new_child_button' ), 'linked_orders_for_woocommerce/metabox_output/after_add_new_child_button' ),
			'component-group'  => array( 'dws_lowc_get_component_hook_tag', array( 'general-settings', 'get_group_fields' ), 'linked_orders_for_woocommerce/general_settings/get_group_fields' ),
			'component-bytes'  => array( 'dws_lowc_get_component_hook_tag', array( 'general-settings', "get_group\x01_fields\u{e9}" ), 'linked_orders_for_woocommerce/general_settings/get_group_fields' ),
			'component-sos'    => array( 'dws_lowc_get_component_hook_tag', array( 'wc-sequential-order-numbers-pro-integration', 'name' ), 'linked_orders_for_woocommerce/wc_sequential_order_numbers_pro_integration/name' ),
			'component-other'  => array( 'dws_lowc_get_component_hook_tag', array( 'freemius', 'name' ), 'linked_orders_for_woocommerce/freemius/name' ),
		);
	}

	#[BeforeClass]
	public static function read_the_header(): void {
		self::$header = get_file_data(
			DWS_LOWC_FILE,
			array(
				'Version'     => 'Version',
				'RequiresWP'  => 'Requires at least',
				'RequiresPHP' => 'Requires PHP',
			)
		);
	}

	public function test_the_v1_getters_answer_from_the_plugin_header(): void {
		self::assertSame( dws_lowc_get_plugin_name(), dws_lowc_name() );
		self::assertSame( self::$header['Version'], dws_lowc_version() );
		self::assertSame( DWS_LOWC_FILE, dws_lowc_path() );
		self::assertSame( self::$header['RequiresPHP'], dws_lowc_min_php() );
		self::assertSame( self::$header['RequiresWP'], dws_lowc_min_wp() );
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
		self::assertSame( 'Linked Orders for WooCommerce', DWS_LOWC_NAME );
		self::assertSame( self::$header['Version'], DWS_LOWC_VERSION );
		self::assertSame( DWS_LOWC_FILE, DWS_LOWC_PATH );
		self::assertSame( self::$header['RequiresPHP'], DWS_LOWC_MIN_PHP );
		self::assertSame( self::$header['RequiresWP'], DWS_LOWC_MIN_WP );
	}

	// The shims build each name as v1 built it, so a name v1 normalized still matches the hook the plugin fires.
	#[DataProvider( 'hook_tags' )]
	public function test_a_v1_hook_tag_builds_as_v1_built_it( string $shim, array $arguments, string $expected ): void {
		self::assertSame( $expected, $shim( ...$arguments ) );
		self::assertSame( array( array( $shim, '' ) ), $this->deprecated );
	}

	// v1's setters write the links outside the linking lock, so each names the functions that write them under it, and keeps working as v1's did.
	#[DataProvider( 'storages' )]
	public function test_the_node_setters_store_links_as_v1_did_and_name_their_replacements( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$parent      = $this->create_order();
		$child       = $this->create_order();
		$other       = $this->create_order();
		$parent_node = dws_lowc_get_order_node( $parent->get_id() );
		$child_node  = dws_lowc_get_order_node( $child->get_id() );
		$other_node  = dws_lowc_get_order_node( $other->get_id() );
		self::assertInstanceOf( \DWS_Order_Node::class, $parent_node );
		self::assertInstanceOf( \DWS_Order_Node::class, $child_node );
		self::assertInstanceOf( \DWS_Order_Node::class, $other_node );

		self::assertTrue( $parent_node->add_child( $child_node ) );
		$child_node->save();
		$parent_node->save();
		self::assertSame( $parent->get_id(), dws_lowc_get_order_node( $child->get_id() )?->get_parent()?->get_order()->get_id() );
		self::assertSame( array( $parent->get_id(), $child->get_id() ), dws_lowc_get_orders_tree( $parent->get_id() ) );

		$other_node->set_parent( $parent_node );
		self::assertSame( 1, $other_node->get_depth() );
		$parent_node->set_children( array( $other_node, 'not a node' ) );
		$other_node->set_depth( 3 );
		$other_node->save();
		$parent_node->save();

		$stored = wc_get_order( $other->get_id() );
		self::assertSame( '3', (string) $stored->get_meta( '_dws_lo_depth' ) );
		self::assertSame( (string) $parent->get_id(), (string) $stored->get_meta( '_dws_lo_parent' ) );
		self::assertSame( array( $other->get_id() ), wc_get_order( $parent->get_id() )->get_meta( '_dws_lo_children' ) );

		$either = 'dws_lowc_link_orders() or dws_lowc_unlink_order()';
		self::assertSame(
			array(
				array( 'DWS_Order_Node::add_child', 'dws_lowc_link_orders()' ),
				array( 'DWS_Order_Node::save', $either ),
				array( 'DWS_Order_Node::save', $either ),
				array( 'DWS_Order_Node::set_parent', 'dws_lowc_link_orders()' ),
				array( 'DWS_Order_Node::set_children', $either ),
				array( 'DWS_Order_Node::set_depth', $either ),
				array( 'DWS_Order_Node::save', $either ),
				array( 'DWS_Order_Node::save', $either ),
			),
			$this->deprecated
		);
	}

	// The v1 hooks fire at the earliest priority, so every v1 listener runs before a v2 listener of any later one.
	public function test_the_v1_boot_hooks_fire_at_the_earliest_priority_with_the_failure(): void {
		$fired = array();
		$spy   = static function ( mixed $error = null ) use ( &$fired ): void {
			$fired[] = array( current_action(), $error );
		};
		add_action( 'dws_lowc_initialized', $spy );
		add_action( 'dws_lowc_initialization_failure', $spy );
		add_action( 'linked_orders_for_woocommerce/boot/succeeded', $spy, PHP_INT_MIN + 1 );
		add_action( 'linked_orders_for_woocommerce/boot/failed', $spy, PHP_INT_MIN + 1 );
		$error = new \RuntimeException( 'Failed.' );

		try {
			do_action( 'linked_orders_for_woocommerce/boot/succeeded' );
			do_action( 'linked_orders_for_woocommerce/boot/failed', $error );
		} finally {
			remove_action( 'dws_lowc_initialized', $spy );
			remove_action( 'dws_lowc_initialization_failure', $spy );
			remove_action( 'linked_orders_for_woocommerce/boot/succeeded', $spy, PHP_INT_MIN + 1 );
			remove_action( 'linked_orders_for_woocommerce/boot/failed', $spy, PHP_INT_MIN + 1 );
		}

		self::assertSame(
			array(
				array( 'dws_lowc_initialized', null ),
				array( 'linked_orders_for_woocommerce/boot/succeeded', '' ),
				array( 'dws_lowc_initialization_failure', $error ),
				array( 'linked_orders_for_woocommerce/boot/failed', $error ),
			),
			$fired
		);
		self::assertSame(
			array(
				array( 'dws_lowc_initialized', 'linked_orders_for_woocommerce/boot/succeeded' ),
				array( 'dws_lowc_initialization_failure', 'linked_orders_for_woocommerce/boot/failed' ),
			),
			$this->deprecated
		);
		foreach ( array( 'linked_orders_for_woocommerce/boot/succeeded', 'linked_orders_for_woocommerce/boot/failed' ) as $hook ) {
			$relays = \array_filter( $GLOBALS['wp_filter'][ $hook ]->callbacks[ PHP_INT_MIN ] ?? array(), static fn ( array $callback ): bool => $callback['function'] instanceof \Closure && \dirname( DWS_LOWC_FILE ) . '/functions.php' === new \ReflectionFunction( $callback['function'] )->getFileName() );
			self::assertCount( 1, $relays, $hook );
		}
	}
}
