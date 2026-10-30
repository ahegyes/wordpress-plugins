<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Storage\Lock;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Storage\LockClaim;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class LinkingTest extends TestCase {
	use OrderStorage;

	protected int $user = 0;

	#[Before]
	protected function log_in(): void {
		wp_set_current_user( 1 );
	}

	#[After]
	protected function log_out(): void {
		wp_set_current_user( 0 );
		if ( 0 !== $this->user ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $this->user );
			$this->user = 0;
		}
	}

	#[DataProvider( 'storages' )]
	public function test_a_link_stores_the_childs_parent_and_depth_and_the_parents_children( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$parent = $this->create_order();
		$child  = $this->create_order();

		self::assertTrue( dws_lowc_link_orders( $parent->get_id(), $child->get_id() ) );

		$stored_parent = wc_get_order( $parent->get_id() );
		$stored_child  = wc_get_order( $child->get_id() );
		self::assertSame( '0', (string) $stored_parent->get_meta( '_dws_lo_depth' ) );
		self::assertSame( array( $child->get_id() ), $stored_parent->get_meta( '_dws_lo_children' ) );
		self::assertSame( '1', (string) $stored_child->get_meta( '_dws_lo_depth' ) );
		self::assertSame( (string) $parent->get_id(), (string) $stored_child->get_meta( '_dws_lo_parent' ) );
		self::assertTrue( dws_lowc_is_root_order( $parent->get_id() ) );
		self::assertFalse( dws_lowc_is_root_order( $child ) );
		self::assertSame( $parent->get_id(), dws_lowc_get_order_node( $child->get_id() )?->get_parent()?->get_id() );
		self::assertSame( $parent->get_id(), dws_lowc_get_root_order( $child->get_id() )?->get_id() );
		self::assertSame( array( $parent->get_id(), $child->get_id() ), dws_lowc_get_orders_tree( $parent->get_id() ) );
	}

	// The parent's children are one meta value that every link reads and rewrites, so a link must not write back a list another request changed after this one loaded the parent.
	#[DataProvider( 'storages' )]
	public function test_a_link_keeps_a_child_another_request_linked_after_the_parent_was_loaded( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$parent = $this->create_order();
		$first  = $this->create_order();
		$second = $this->create_order();
		self::assertSame( array(), dws_lowc_get_order_node( $parent->get_id() )?->get_children() );

		$this->link_in_another_request( $hpos, $sync, $parent->get_id(), $first->get_id() );
		self::assertTrue( dws_lowc_link_orders( $parent->get_id(), $second->get_id() ) );

		wp_cache_flush();
		self::assertSame( array( $first->get_id(), $second->get_id() ), \array_map( static fn ( \DWS_Order_Node $child ): int => $child->get_order()->get_id(), dws_lowc_get_order_node( $parent->get_id() )?->get_children() ?? array() ) );
	}

	public function test_a_link_is_refused_while_another_request_links_orders(): void {
		$parent = $this->create_order();
		$child  = $this->create_order();
		$lock   = new Lock( 'dws_lowc_linking', 30 );
		self::assertSame( LockClaim::Acquired, $lock->acquire( 'another-request' ) );

		try {
			self::assertFalse( dws_lowc_link_orders( $parent->get_id(), $child->get_id() ) );
		} finally {
			$lock->release( 'another-request' );
		}

		self::assertFalse( dws_lowc_get_order_node( $child->get_id() )?->has_parent() );
		self::assertFalse( dws_lowc_link_orders( $child->get_id(), $child->get_id() ) );
		self::assertTrue( dws_lowc_link_orders( $parent->get_id(), $child->get_id() ) );
	}

	#[DataProvider( 'storages' )]
	public function test_a_tree_lists_its_root_then_each_child_before_the_next( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$root       = $this->create_order();
		$first      = $this->create_order();
		$grandchild = $this->create_order();
		$second     = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $first->get_id() ) );
		self::assertTrue( dws_lowc_link_orders( $first->get_id(), $grandchild->get_id() ) );
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $second->get_id() ) );

		self::assertSame( array( $root->get_id(), $first->get_id(), $grandchild->get_id(), $second->get_id() ), dws_lowc_get_orders_tree( $root ) );
		self::assertSame( array( $first->get_id(), $grandchild->get_id() ), dws_lowc_get_orders_tree( $first->get_id() ) );
		self::assertSame( 2, dws_lowc_get_order_node( $grandchild->get_id() )?->get_depth() );
		self::assertSame( $root->get_id(), dws_lowc_get_root_order( $grandchild->get_id() )?->get_id() );
	}

	// A cycle would send every tree walk into endless recursion, and a linked subtree would keep its old depths, so a child is always a lone root.
	#[DataProvider( 'storages' )]
	public function test_a_link_is_refused_for_a_child_with_a_parent_or_children_and_for_the_order_itself( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$root  = $this->create_order();
		$child = $this->create_order();
		$other = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $child->get_id() ) );

		self::assertFalse( dws_lowc_link_orders( $other->get_id(), $child->get_id() ) );
		self::assertFalse( dws_lowc_link_orders( $other->get_id(), $other->get_id() ) );
		self::assertFalse( dws_lowc_link_orders( $child->get_id(), $root->get_id() ) );
		self::assertFalse( dws_lowc_link_orders( $other->get_id(), $root->get_id() ) );
		self::assertSame( array( $root->get_id(), $child->get_id() ), dws_lowc_get_orders_tree( $root->get_id() ) );
		self::assertSame( array( $other->get_id() ), dws_lowc_get_orders_tree( $other->get_id() ) );
	}

	#[DataProvider( 'storages' )]
	public function test_an_order_that_is_missing_or_not_supported_has_no_node( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order  = $this->create_order();
		$refund = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 0,
			)
		);
		self::assertInstanceOf( \WC_Order_Refund::class, $refund );

		self::assertNull( dws_lowc_get_order_node( $refund->get_id() ) );
		self::assertNull( dws_lowc_get_order_node( \PHP_INT_MAX ) );
		self::assertNull( dws_lowc_is_root_order( \PHP_INT_MAX ) );
		self::assertNull( dws_lowc_get_root_order( \PHP_INT_MAX ) );
		self::assertNull( dws_lowc_get_orders_tree( \PHP_INT_MAX ) );
		self::assertNull( dws_lowc_link_orders( $order->get_id(), \PHP_INT_MAX ) );
		self::assertNull( dws_lowc_is_supported_order( 0 ) );
		self::assertNull( dws_lowc_is_supported_order( $refund ) );
		self::assertTrue( dws_lowc_is_supported_order( $order->get_id() ) );
	}

	#[DataProvider( 'storages' )]
	public function test_a_child_whose_parent_was_deleted_is_its_own_root( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$parent = $this->create_order();
		$child  = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $parent->get_id(), $child->get_id() ) );

		$parent->delete( true );

		self::assertSame( $child->get_id(), dws_lowc_get_root_order( $child->get_id() )?->get_id() );
		self::assertFalse( dws_lowc_get_order_node( $child->get_id() )?->has_parent() );
	}

	#[DataProvider( 'storages' )]
	public function test_a_node_proxies_its_order_and_names_it_with_its_type_number_and_status( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order = $this->create_order();
		$node  = dws_lowc_get_order_node( $order );

		self::assertInstanceOf( \DWS_Order_Node::class, $node );
		self::assertSame( $order->get_id(), $node->get_id() );
		self::assertSame( $order->get_id(), $node->get_order()->get_id() );
		self::assertSame( 'shop_order', $node->get_post_type()->name );
		self::assertNull( $node->no_such_method() );
		self::assertSame( 'Order #' . $order->get_order_number() . ' - Pending payment', $node->get_formatted_name() );
	}

	public function test_a_node_of_an_unsupported_order_cannot_be_built(): void {
		$this->expectException( \InvalidArgumentException::class );

		new \DWS_Order_Node( \PHP_INT_MAX );
	}

	#[DataProvider( 'storages' )]
	public function test_a_child_can_be_created_below_the_maximum_depth_by_a_user_who_may_edit_the_order( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$root  = $this->create_order();
		$child = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $child->get_id() ) );
		$stored = get_option( DWS_LOWC_OPTION_MAX_DEPTH );

		try {
			update_option( DWS_LOWC_OPTION_MAX_DEPTH, '1' );
			self::assertTrue( dws_lowc_get_order_node( $root->get_id() )?->can_create_child() );
			self::assertFalse( dws_lowc_get_order_node( $child->get_id() )?->can_create_child() );

			update_option( DWS_LOWC_OPTION_MAX_DEPTH, '2' );
			self::assertTrue( dws_lowc_get_order_node( $child->get_id() )?->can_create_child() );
		} finally {
			update_option( DWS_LOWC_OPTION_MAX_DEPTH, $stored );
		}

		$this->user = wp_insert_user(
			array(
				'user_login' => 'dws-lowc-linking-customer',
				'user_pass'  => wp_generate_password(),
				'role'       => 'customer',
			)
		);
		self::assertIsInt( $this->user );
		self::assertFalse( dws_lowc_get_order_node( $root->get_id() )?->can_create_child( $this->user ) );
	}

	#[DataProvider( 'storages' )]
	public function test_a_child_cannot_be_created_from_an_order_whose_status_is_not_valid_for_one( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order    = $this->create_order();
		$statuses = static fn ( array $statuses ): array => \array_values( \array_diff( $statuses, array( 'pending' ) ) );
		add_filter( 'dws_lowc/post_type/valid_statuses_for_new_child', $statuses );

		try {
			self::assertFalse( dws_lowc_get_order_node( $order->get_id() )?->can_create_child() );
		} finally {
			remove_filter( 'dws_lowc/post_type/valid_statuses_for_new_child', $statuses );
		}
	}

	public function test_the_supported_types_are_shop_orders_and_every_status_is_valid_for_a_new_child(): void {
		self::assertSame( array( 'shop_order' ), dws_lowc_get_supported_order_types() );
		self::assertSame( \array_map( static fn ( string $status ): string => \substr( $status, 3 ), \array_keys( wc_get_order_statuses() ) ), dws_lowc_get_valid_statuses_for_new_child() );
	}

	#[DataProvider( 'storages' )]
	public function test_a_linked_child_is_created_with_the_parents_customer_and_addresses_and_linked( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$parent = $this->create_order(
			array(
				'customer_id' => 1,
				'status'      => 'processing',
			)
		);
		$parent->set_address(
			array(
				'first_name' => 'Ada',
				'city'       => 'London',
			),
			'billing'
		);
		$parent->set_address( array( 'city' => 'Paris' ), 'shipping' );
		$parent->save();

		$id = dws_lowc_create_linked_child( $parent->get_id() );

		self::assertIsInt( $id );
		$this->orders[] = $id;
		$child          = wc_get_order( $id );
		self::assertInstanceOf( \WC_Order::class, $child );
		self::assertSame( 'pending', $child->get_status() );
		self::assertSame( 1, $child->get_customer_id() );
		self::assertSame( 'dws-linking', $child->get_created_via() );
		self::assertSame( 'Ada', $child->get_billing_first_name() );
		self::assertSame( 'London', $child->get_billing_city() );
		self::assertSame( 'Paris', $child->get_shipping_city() );
		self::assertSame( '1', (string) $child->get_meta( '_dws_lo_created_by' ) );
		self::assertSame( $parent->get_id(), dws_lowc_get_order_node( $id )?->get_parent()?->get_id() );
	}

	#[DataProvider( 'storages' )]
	public function test_a_linked_child_is_not_created_for_an_unsupported_parent_or_an_uncallable_creator( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$wrong = array();
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		$record = static function ( string $function_name ) use ( &$wrong ): void {
			$wrong[] = $function_name;
		};
		add_action( 'doing_it_wrong_run', $record );

		try {
			self::assertNull( dws_lowc_create_linked_child( \PHP_INT_MAX ) );
			self::assertNull( dws_lowc_create_linked_child( $this->create_order()->get_id(), array( 'create_function' => 'dws_lowc_no_such_function' ) ) );
			self::assertNull( dws_lowc_create_linked_child( $this->create_order()->get_id(), array( 'create_function' => static fn (): string => 'not an order' ) ) );
		} finally {
			remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
			remove_action( 'doing_it_wrong_run', $record );
		}

		self::assertSame( array( 'dws_lowc_create_linked_child', 'dws_lowc_create_linked_child' ), $wrong );
	}

	#[DataProvider( 'storages' )]
	public function test_a_linked_child_whose_link_is_refused_is_deleted_and_an_error_returned( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$parent  = $this->create_order();
		$created = 0;
		$record  = function ( \WC_Order $child ) use ( &$created ): void {
			$created = $child->get_id();
		};
		add_action( 'dws_lowc/linked_order/created', $record );
		$lock = new Lock( 'dws_lowc_linking', 30 );
		self::assertSame( LockClaim::Acquired, $lock->acquire( 'another-request' ) );

		try {
			$result = dws_lowc_create_linked_child( $parent->get_id() );
		} finally {
			$lock->release( 'another-request' );
			remove_action( 'dws_lowc/linked_order/created', $record );
		}

		$this->orders[] = \is_int( $result ) ? $result : $created;
		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'dws_lowc_link_refused', $result->get_error_code() );
		self::assertFalse( wc_get_order( $created ) );
		self::assertSame( array(), dws_lowc_get_order_node( $parent->get_id() )?->get_children() );
	}

	#[DataProvider( 'storages' )]
	public function test_a_creator_error_is_returned( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$error = new \WP_Error( 'failed', 'Failed.' );

		self::assertSame( $error, dws_lowc_create_linked_child( $this->create_order()->get_id(), array( 'create_function' => static fn (): \WP_Error => $error ) ) );
	}

	// The other request runs on the row's storage, as the shared tests site stores orders on the order tables.
	protected function link_in_another_request( bool $hpos, bool $sync, int $parent_id, int $child_id ): void {
		$php = \sprintf( 'add_filter( "pre_option_woocommerce_custom_orders_table_enabled", fn () => "%s" ); add_filter( "pre_option_woocommerce_custom_orders_table_data_sync_enabled", fn () => "%s" ); echo var_export( dws_lowc_link_orders( %d, %d ), true );', $hpos ? 'yes' : 'no', $sync ? 'yes' : 'no', $parent_id, $child_id );
		\exec( 'wp --path=' . \escapeshellarg( ABSPATH ) . ' eval ' . \escapeshellarg( $php ) . ' 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- The race needs a second request.

		self::assertSame( 0, $status, \implode( "\n", $output ) );
		self::assertSame( 'true', (string) \end( $output ) );
	}
}
