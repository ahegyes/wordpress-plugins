<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class MetaBoxTest extends TestCase {
	use OrderStorage;

	protected int $user = 0;

	// WooCommerce loads its screen ids only in admin requests.
	#[BeforeClass]
	public static function load_screen_ids(): void {
		require_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
	}

	#[Before]
	protected function log_in(): void {
		wp_set_current_user( 1 );
	}

	#[After]
	protected function clear_meta_boxes(): void {
		global $wp_meta_boxes;
		$wp_meta_boxes = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resets what the rows registered.
		wp_set_current_user( 0 );
		if ( 0 !== $this->user ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $this->user );
			$this->user = 0;
		}
	}

	#[DataProvider( 'storages' )]
	public function test_the_box_sits_beside_an_orders_editor_under_its_v1_id( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order = $this->create_order();

		$box = $this->register( $order );

		self::assertSame( 'Linked Orders', $box['title'] ?? null );
	}

	public function test_the_box_is_not_added_for_a_user_without_its_capabilities_or_beside_other_editors(): void {
		$this->user = wp_insert_user(
			array(
				'user_login' => 'dws-lowc-meta-box-editor',
				'user_pass'  => wp_generate_password(),
				'role'       => 'editor',
			)
		);
		self::assertIsInt( $this->user );
		$order = $this->create_order();
		$post  = get_post( wp_insert_post( array( 'post_title' => 'Not an order' ) ) );
		self::assertInstanceOf( \WP_Post::class, $post );

		try {
			self::assertNull( $this->register( $post, 'post' ) );

			wp_set_current_user( $this->user );
			self::assertNull( $this->register( $order ) );
		} finally {
			wp_delete_post( $post->ID, true );
		}
	}

	#[DataProvider( 'storages' )]
	public function test_the_box_shows_the_parent_the_children_and_the_button_to_add_a_child( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$stored = get_option( DWS_LOWC_OPTION_MAX_DEPTH );
		update_option( DWS_LOWC_OPTION_MAX_DEPTH, '2' );
		$root  = $this->create_order();
		$order = $this->create_order();
		$child = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $order->get_id() ) );
		self::assertTrue( dws_lowc_link_orders( $order->get_id(), $child->get_id() ) );

		try {
			$box = $this->render( $order->get_id() );
		} finally {
			update_option( DWS_LOWC_OPTION_MAX_DEPTH, $stored );
		}

		$parent = $box->querySelector( '.dws-linked-orders__parent a' );
		self::assertSame( wc_get_order( $root->get_id() )->get_edit_order_url(), $parent?->getAttribute( 'href' ) );
		self::assertSame( 'Order #' . $root->get_order_number() . ' - Pending payment', \trim( (string) $parent?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		$link = $box->querySelector( '#linked-order-' . $child->get_id() . ' a' );
		self::assertSame( wc_get_order( $child->get_id() )->get_edit_order_url(), $link?->getAttribute( 'href' ) );
		$button = $box->querySelector( 'a.button' );
		self::assertSame( 'Add new child Order', \trim( (string) $button?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		\parse_str( (string) \wp_parse_url( (string) $button?->getAttribute( 'href' ), \PHP_URL_QUERY ), $query );
		self::assertSame( 'dws_lowc_create_linked_child', $query['action'] ?? null );
		self::assertSame( (string) $order->get_id(), $query['parent_id'] ?? null );
		self::assertSame( 1, wp_verify_nonce( (string) ( $query['_wpnonce'] ?? '' ), 'dws_create_linked_child' ) );
	}

	public function test_a_childless_order_at_the_maximum_depth_says_no_child_can_be_added(): void {
		$root  = $this->create_order();
		$child = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $child->get_id() ) );

		$box = $this->render( $child->get_id() );

		self::assertSame( 'There are no attached children. New children cannot be added to this Order.', \preg_replace( '/\s+/', ' ', \trim( (string) $box->querySelector( '.dws-linked-orders__no-children' )?->textContent ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNull( $box->querySelector( 'a.button' ) );
	}

	protected function register( \WC_Order|\WP_Post $post_or_order, ?string $screen = null ): ?array {
		global $wp_meta_boxes;
		$screen ??= wc_get_page_screen_id( 'shop-order' );
		do_action( 'add_meta_boxes', $screen, $post_or_order );

		return $wp_meta_boxes[ $screen ]['side']['default']['dws-linked-orders'] ?? null;
	}

	protected function render( int $order_id ): \Dom\HTMLDocument {
		$order = wc_get_order( $order_id );
		$box   = $this->register( $order );
		self::assertIsArray( $box );

		\ob_start();
		\call_user_func( $box['callback'], $order, $box );

		return \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . \ob_get_clean() . '</body></html>', \LIBXML_NOERROR );
	}
}
