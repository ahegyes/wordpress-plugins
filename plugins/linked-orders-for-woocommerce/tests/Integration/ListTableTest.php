<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use Automattic\WooCommerce\Utilities\OrderUtil;
use DeepWebSolutions\LinkedOrders\Plugin;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ListTableTest extends TestCase {
	use OrderStorage;

	protected const array COLUMNS = array(
		'cb'           => '',
		'order_number' => 'Order',
		'order_total'  => 'Total',
		'wc_actions'   => 'Actions',
	);

	protected int $user = 0;

	// WordPress and WooCommerce load these only in admin requests.
	#[BeforeClass]
	public static function load_screen_api(): void {
		require_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
	}

	#[Before]
	protected function log_in(): void {
		wp_set_current_user( 1 );
	}

	// A screen's column hooks stay for the rest of the process, as they stay for the rest of a request; WooCommerce adds its own only in admin requests, so clearing them removes none of its.
	#[After]
	protected function leave_screen(): void {
		foreach ( array( 'edit-shop_order', 'woocommerce_page_wc-orders', 'admin_page_wc-orders' ) as $screen ) {
			remove_all_filters( "manage_{$screen}_columns" );
			remove_all_actions( "manage_{$screen}_custom_column" );
		}
		remove_all_actions( 'manage_shop_order_posts_custom_column' );
		unset( $GLOBALS['current_screen'], $GLOBALS['typenow'], $_GET['_dws_lo_depth'], $_GET['_guest_customer_user'], $_GET['_dws_linked_order_id'], $_GET['_customer_user'] );
		wp_dequeue_script( Plugin::SLUG . '-orders-list-table' );
		wp_dequeue_style( Plugin::SLUG . '-orders-list-table-icons' );
		wp_set_current_user( 0 );
		if ( 0 !== $this->user ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $this->user );
			$this->user = 0;
		}
	}

	#[DataProvider( 'storages' )]
	public function test_the_depth_column_follows_the_total_and_names_each_orders_level( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$root  = $this->create_order();
		$child = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $child->get_id() ) );
		$screen = $this->open_list();

		self::assertSame( array( 'cb', 'order_number', 'order_total', 'dws_lo_depth', 'wc_actions' ), \array_keys( apply_filters( "manage_{$screen}_columns", self::COLUMNS ) ) );
		self::assertSame( 'Depth', apply_filters( "manage_{$screen}_columns", self::COLUMNS )['dws_lo_depth'] );
		self::assertSame( 'Root Order', $this->render_cell( $root->get_id() ) );
		self::assertSame( 'Child Order (level 2)', $this->render_cell( $child->get_id() ) );
		self::assertSame( '', $this->render_cell( $child->get_id(), 'order_total' ) );
	}

	#[DataProvider( 'storages' )]
	public function test_a_user_without_the_columns_capability_sees_the_list_unchanged( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$this->user = wp_insert_user(
			array(
				'user_login' => 'dws-lowc-list-table-editor',
				'user_pass'  => wp_generate_password(),
				'role'       => 'editor',
			)
		);
		self::assertIsInt( $this->user );
		wp_set_current_user( $this->user );
		$screen = $this->open_list();
		$order  = $this->create_order();

		self::assertSame( self::COLUMNS, apply_filters( "manage_{$screen}_columns", self::COLUMNS ) );
		self::assertSame( array(), apply_filters( 'woocommerce_admin_order_actions', array(), $order ) );
		self::assertSame( '', $this->render_filters() );
	}

	#[DataProvider( 'storages' )]
	public function test_the_depth_filter_lists_every_level_up_to_the_deepest_stored_or_allowed( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		// The shallower order is newer, so only an order by depth finds the deep one.
		foreach ( array( 3, 1 ) as $depth ) {
			$order = $this->create_order();
			$order->update_meta_data( '_dws_lo_depth', (string) $depth );
			$order->set_date_created( \time() + DAY_IN_SECONDS / $depth );
			$order->save();
		}
		$this->open_list();
		$_GET['_dws_lo_depth'] = '2';

		$filters = \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . $this->render_filters() . '</body></html>', \LIBXML_NOERROR );

		$options = \iterator_to_array( $filters->querySelectorAll( 'select[name="_dws_lo_depth"] option' ) );
		self::assertSame( array( '', '0', '1', '2', '3' ), \array_map( static fn ( \Dom\Element $option ): string => $option->getAttribute( 'value' ) ?? '', $options ) );
		self::assertSame( array( 'All Order depths', 'Root Orders', 'Child Orders (level 2)', 'Child Orders (level 3)', 'Child Orders (level 4)' ), \array_map( static fn ( \Dom\Element $option ): string => \trim( $option->textContent ), $options ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertTrue( $options[3]->hasAttribute( 'selected' ) );
		self::assertNotNull( $filters->querySelector( 'input[type="hidden"][name="_guest_customer_user"]' ) );
		self::assertNotNull( $filters->querySelector( 'input[type="hidden"][name="_dws_linked_order_id"]' ) );
	}

	// v1 read the deepest depth from every order of the type, whatever its status, such as the Trash, which the list's Trash view shows, or a draft.
	#[DataProvider( 'storages' )]
	public function test_the_depth_filter_counts_an_order_in_any_status_at_the_deepest_level( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$deep = $this->create_order();
		$deep->update_meta_data( '_dws_lo_depth', '5' );
		$this->open_list();

		foreach ( array( 'trash', 'draft' ) as $status ) {
			$deep->set_status( $status );
			$deep->save();
			self::assertSame( $status, wc_get_order( $deep->get_id() ) ? wc_get_order( $deep->get_id() )->get_status() : null );

			$filters = \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . $this->render_filters() . '</body></html>', \LIBXML_NOERROR );

			self::assertSame( '5', \array_reverse( \array_map( static fn ( \Dom\Element $option ): string => $option->getAttribute( 'value' ) ?? '', \iterator_to_array( $filters->querySelectorAll( 'select[name="_dws_lo_depth"] option' ) ) ) )[0], $status );
		}
	}

	// WooCommerce counts the order tables' list from its cache only when the query holds no argument beyond its own.
	#[DataProvider( 'storages' )]
	public function test_an_unfiltered_list_keeps_its_query_as_it_came( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$this->open_list();

		$args = array(
			'type'   => 'shop_order',
			'status' => array( 'wc-pending' ),
			'limit'  => 20,
			'page'   => 1,
		);
		$hook = $hpos ? 'woocommerce_order_list_table_prepare_items_query_args' : 'request';

		self::assertSame( $args, apply_filters( $hook, $args ) );
	}

	#[DataProvider( 'storages' )]
	public function test_the_list_filters_by_depth_by_guest_email_and_by_tree( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$root   = $this->create_order( array( 'customer_id' => 0 ) );
		$child  = $this->create_order();
		$other  = $this->create_order();
		$sister = $this->create_order( array( 'customer_id' => 0 ) );
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $child->get_id() ) );
		foreach ( array( $root, $sister ) as $guest ) {
			$guest = wc_get_order( $guest->get_id() );
			$guest->set_billing_email( 'guest-' . $root->get_id() . '@example.com' );
			$guest->save();
		}
		$this->open_list();

		$_GET['_dws_lo_depth'] = '1';
		self::assertSame( array( $child->get_id() ), \array_values( \array_intersect( $this->query(), array( $root->get_id(), $child->get_id(), $other->get_id() ) ) ) );
		$_GET['_dws_lo_depth'] = '0';
		self::assertSame( array( $root->get_id(), $other->get_id() ), \array_values( \array_intersect( $this->query(), array( $root->get_id(), $child->get_id(), $other->get_id() ) ) ) );
		unset( $_GET['_dws_lo_depth'] );

		$_GET['_guest_customer_user'] = 'guest-' . $root->get_id() . '@example.com';
		self::assertSame( array( $root->get_id(), $sister->get_id() ), $this->query() );
		unset( $_GET['_guest_customer_user'] );

		$_GET['_dws_linked_order_id'] = (string) $child->get_id();
		self::assertSame( array( $root->get_id(), $child->get_id() ), $this->query() );
	}

	#[DataProvider( 'storages' )]
	public function test_the_depth_filter_narrows_another_extensions_alternatives_rather_than_joining_them( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$root  = $this->create_order();
		$child = $this->create_order();
		$other = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $child->get_id() ) );
		foreach ( array( $root, $child ) as $flagged ) {
			$flagged = wc_get_order( $flagged->get_id() );
			$flagged->update_meta_data( '_dws_lowc_test_flag', 'yes' );
			$flagged->save();
		}
		$this->open_list();
		$_GET['_dws_lo_depth'] = '0';

		$either = array(
			'relation' => 'OR',
			array(
				'key'   => '_dws_lowc_test_flag',
				'value' => 'yes',
			),
			array(
				'key'   => '_dws_lowc_test_flag',
				'value' => 'maybe',
			),
		);

		self::assertSame( array( $root->get_id() ), \array_values( \array_intersect( $this->query( null, $either ), array( $root->get_id(), $child->get_id(), $other->get_id() ) ) ) );
	}

	#[DataProvider( 'storages' )]
	public function test_the_tree_filter_narrows_the_orders_a_search_found_rather_than_replacing_them( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$root  = $this->create_order();
		$child = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $child->get_id() ) );
		$this->open_list();
		$_GET['_dws_linked_order_id'] = (string) $child->get_id();

		self::assertSame( array( $child->get_id() ), $this->query( array( $child->get_id(), $this->create_order()->get_id() ) ) );
	}

	#[DataProvider( 'storages' )]
	public function test_an_order_row_offers_its_customers_orders_its_tree_and_a_new_child( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$root  = $this->create_order( array( 'customer_id' => 1 ) );
		$child = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $child->get_id() ) );
		$this->open_list();
		$list = OrderUtil::custom_orders_table_usage_is_enabled() ? admin_url( 'admin.php?page=wc-orders' ) : admin_url( 'edit.php?post_type=shop_order' );

		$actions = apply_filters( 'woocommerce_admin_order_actions', array( 'complete' => array( 'action' => 'complete' ) ), wc_get_order( $root->get_id() ) );

		self::assertSame( array( 'view_all_customer_orders', 'view_all_linked_orders', 'create_empty_linked_child', 'complete' ), \array_keys( $actions ) );
		self::assertSame( add_query_arg( '_customer_user', 1, $list ), $actions['view_all_customer_orders']['url'] );
		self::assertSame( 'View all customer Orders', $actions['view_all_customer_orders']['name'] );
		self::assertSame( add_query_arg( '_dws_linked_order_id', $root->get_id(), $list ), $actions['view_all_linked_orders']['url'] );
		self::assertSame( 'View all linked Orders', $actions['view_all_linked_orders']['name'] );
		self::assertSame( 'Create empty linked Order', $actions['create_empty_linked_child']['name'] );
		self::assertSame( 'create-empty-linked-child', $actions['create_empty_linked_child']['action'] );
	}

	#[DataProvider( 'storages' )]
	public function test_a_guest_row_offers_the_orders_of_its_billing_email_unless_the_list_shows_them( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$guest = $this->create_order( array( 'customer_id' => 0 ) );
		$guest->set_billing_email( 'guest+row@example.com' );
		$guest->save();
		$this->open_list();
		$list = OrderUtil::custom_orders_table_usage_is_enabled() ? admin_url( 'admin.php?page=wc-orders' ) : admin_url( 'edit.php?post_type=shop_order' );

		$actions = apply_filters( 'woocommerce_admin_order_actions', array(), $guest );
		self::assertSame( add_query_arg( '_guest_customer_user', \rawurlencode( 'guest+row@example.com' ), $list ), $actions['view_all_customer_orders']['url'] );
		self::assertArrayNotHasKey( 'view_all_linked_orders', $actions );

		$_GET['_guest_customer_user'] = 'guest+row@example.com';
		self::assertArrayNotHasKey( 'view_all_customer_orders', apply_filters( 'woocommerce_admin_order_actions', array(), $guest ) );
	}

	public function test_the_list_loads_the_script_and_the_icons_only_on_order_lists(): void {
		$this->open_list();
		do_action( 'admin_enqueue_scripts', 'edit.php' );

		self::assertTrue( wp_script_is( Plugin::SLUG . '-orders-list-table' ) );
		self::assertTrue( wp_style_is( Plugin::SLUG . '-orders-list-table-icons' ) );

		wp_dequeue_script( Plugin::SLUG . '-orders-list-table' );
		wp_dequeue_style( Plugin::SLUG . '-orders-list-table-icons' );
		set_current_screen( 'edit-post' );
		do_action( 'admin_enqueue_scripts', 'edit.php' );

		self::assertFalse( wp_script_is( Plugin::SLUG . '-orders-list-table' ) );
	}

	protected function open_list(): string {
		$screen = OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'edit-shop_order';
		set_current_screen( $screen );
		$GLOBALS['typenow'] = 'shop_order'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The posts list sets it from the request.

		return $screen;
	}

	protected function render_cell( int $order_id, string $column = 'dws_lo_depth' ): string {
		\ob_start();
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			do_action( 'manage_' . wc_get_page_screen_id( 'shop-order' ) . '_custom_column', $column, wc_get_order( $order_id ) );
		} else {
			do_action( 'manage_shop_order_posts_custom_column', $column, $order_id );
		}

		return \trim( (string) \ob_get_clean() );
	}

	protected function render_filters(): string {
		\ob_start();
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			do_action( 'woocommerce_order_list_table_restrict_manage_orders', 'shop_order', 'top' );
		} else {
			do_action( 'restrict_manage_posts', 'shop_order', 'top' );
		}

		return \trim( (string) \ob_get_clean() );
	}

	// The found ids stand in for the orders a search narrowed the list to, under the key each storage's search sets.
	protected function query( ?array $found = null, array $meta_query = array() ): array {
		$others = array() === $meta_query ? array() : array( 'meta_query' => $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Another extension's filter.
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$args = apply_filters(
				'woocommerce_order_list_table_prepare_items_query_args',
				$others + ( null === $found ? array() : array( 'id' => $found ) ) + array(
					'type'    => 'shop_order',
					'status'  => 'any',
					'limit'   => -1,
					'return'  => 'ids',
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);

			return \array_map( 'intval', wc_get_orders( $args ) );
		}

		$vars = apply_filters(
			'request',
			$others + ( null === $found ? array() : array( 'post__in' => $found ) ) + array(
				'post_type'      => 'shop_order',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		return \array_map( 'intval', ( new \WP_Query( $vars ) )->posts );
	}
}
