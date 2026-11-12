<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use Automattic\WooCommerce\Utilities\OrderUtil;
use DeepWebSolutions\LinkedOrders\MetaBox;
use DeepWebSolutions\LinkedOrders\Tests\Support\CreatesUsersTrait;
use DeepWebSolutions\LinkedOrders\Tests\Support\LoadsAdminScreensTrait;
use DeepWebSolutions\LinkedOrders\Tests\Support\OrderStorageTrait;
use DeepWebSolutions\LinkedOrders\Tests\Support\RestoresOptionsTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( MetaBox::class )]
final class MetaBoxTest extends TestCase {
	use CreatesUsersTrait;
	use LoadsAdminScreensTrait;
	use OrderStorageTrait;
	use RestoresOptionsTrait;

	protected const array OPTIONS = array( DWS_LOWC_OPTION_MAX_DEPTH );

	#[Before]
	protected function log_in(): void {
		wp_set_current_user( 1 );
	}

	#[After]
	protected function clear_meta_boxes(): void {
		global $wp_meta_boxes;
		$wp_meta_boxes = array();
		wp_set_current_user( 0 );
	}

	#[DataProvider( 'storages' )]
	public function test_the_box_sits_beside_an_orders_editor_under_its_v1_id( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order = $this->create_order();

		$box = $this->register( $order );

		self::assertSame( 'Linked Orders', $box['title'] ?? null );
	}

	#[DataProvider( 'storages' )]
	public function test_the_box_is_not_added_for_a_user_without_its_capabilities_or_beside_other_editors( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$user  = $this->create_user( 'dws-lowc-meta-box-editor', 'editor' );
		$order = $this->create_order();
		$post  = get_post( wp_insert_post( array( 'post_title' => 'Not an order' ) ) );
		self::assertInstanceOf( \WP_Post::class, $post );

		try {
			self::assertNull( $this->register( $post, 'post' ) );

			wp_set_current_user( $user );
			self::assertNull( $this->register( $order ) );
		} finally {
			wp_delete_post( $post->ID, true );
		}
	}

	#[DataProvider( 'storages' )]
	public function test_the_box_shows_the_parent_the_children_and_the_button_to_add_a_child( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		update_option( DWS_LOWC_OPTION_MAX_DEPTH, '2' );
		$root  = $this->create_order();
		$order = $this->create_order();
		$child = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $order->get_id() ) );
		self::assertTrue( dws_lowc_link_orders( $order->get_id(), $child->get_id() ) );

		$box = $this->render( $order->get_id() );

		$parent = $box->querySelector( '.dws-linked-orders__parent a' );
		self::assertSame( wc_get_order( $root->get_id() )->get_edit_order_url(), $parent?->getAttribute( 'href' ) );
		self::assertSame( 'Order #' . $root->get_order_number() . ' - Pending payment (opens in a new tab)', \preg_replace( '/\s+/', ' ', \trim( (string) $parent?->textContent ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		$link = $box->querySelector( '#linked-order-' . $child->get_id() . ' a' );
		self::assertSame( wc_get_order( $child->get_id() )->get_edit_order_url(), $link?->getAttribute( 'href' ) );
		foreach ( array( $parent, $link ) as $opens_tab ) {
			self::assertSame( '_blank', $opens_tab?->getAttribute( 'target' ) );
			self::assertSame( '(opens in a new tab)', \trim( (string) $opens_tab?->querySelector( '.screen-reader-text' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}
		$button = $box->querySelector( 'a.button' );
		self::assertSame( 'Add new child order', \trim( (string) $button?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		\parse_str( (string) wp_parse_url( (string) $button?->getAttribute( 'href' ), \PHP_URL_QUERY ), $query );
		self::assertSame( 'dws_lowc_create_linked_child', $query['action'] ?? null );
		self::assertSame( (string) $order->get_id(), $query['parent_id'] ?? null );
		self::assertSame( 1, wp_verify_nonce( (string) ( $query['_wpnonce'] ?? '' ), 'dws_create_linked_child' ) );
		$tree = $box->querySelector( 'a.dws-linked-orders__tree' );
		self::assertSame( add_query_arg( '_dws_linked_order_id', $order->get_id(), OrderUtil::custom_orders_table_usage_is_enabled() ? admin_url( 'admin.php?page=wc-orders' ) : admin_url( 'edit.php?post_type=shop_order' ) ), $tree?->getAttribute( 'href' ) );
		self::assertSame( '_blank', $tree?->getAttribute( 'target' ) );
		self::assertSame( 'View all linked orders (opens in a new tab)', \preg_replace( '/\s+/', ' ', \trim( (string) $tree?->textContent ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
	}

	#[DataProvider( 'storages' )]
	public function test_a_childless_order_at_the_maximum_depth_says_no_child_can_be_added( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$root  = $this->create_order();
		$child = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $child->get_id() ) );

		$box = $this->render( $child->get_id() );

		self::assertSame( 'There are no attached children. Order #' . $child->get_order_number() . ' is at the maximum linked orders depth, set under WooCommerce > Settings > Advanced > Linked Orders.', \preg_replace( '/\s+/', ' ', \trim( (string) $box->querySelector( '.dws-linked-orders__no-children' )?->textContent ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNull( $box->querySelector( 'a.button' ) );
		self::assertNull( $this->render( $this->create_order()->get_id() )->querySelector( 'a.dws-linked-orders__tree' ) );
	}

	// The posts editor passes its post to the box and the order-tables editor its order, so a row passes what its storage's editor does.
	protected function register( \WC_Order|\WP_Post $post_or_order, ?string $screen = null ): ?array {
		global $wp_meta_boxes;
		$screen ??= wc_get_page_screen_id( 'shop-order' );
		if ( $post_or_order instanceof \WC_Order && ! OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$post_or_order = get_post( $post_or_order->get_id() );
			self::assertInstanceOf( \WP_Post::class, $post_or_order );
		}
		do_action( 'add_meta_boxes', $screen, $post_or_order );
		return $wp_meta_boxes[ $screen ]['side']['default']['dws-linked-orders'] ?? null;
	}

	protected function render( int $order_id ): \Dom\HTMLDocument {
		$order = OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_order( $order_id ) : get_post( $order_id );
		self::assertTrue( $order instanceof \WC_Order || $order instanceof \WP_Post );
		$box = $this->register( $order );
		self::assertIsArray( $box );

		\ob_start();
		\call_user_func( $box['callback'], $order, $box );
		return \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . \ob_get_clean() . '</body></html>', \LIBXML_NOERROR );
	}
}
