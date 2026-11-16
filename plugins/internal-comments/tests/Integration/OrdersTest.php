<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Automattic\WooCommerce\Utilities\OrderUtil;
use DeepWebSolutions\InternalComments\Comments;
use DeepWebSolutions\InternalComments\CommentsList;
use DeepWebSolutions\InternalComments\ListColumn;
use DeepWebSolutions\InternalComments\MetaBox;
use DeepWebSolutions\InternalComments\Options;
use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Asset\Assets;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\InternalComments\Tests\Support\InsertsUsersTrait;
use DeepWebSolutions\InternalComments\Tests\Support\OrderStorageTrait;
use DeepWebSolutions\InternalComments\Tests\Support\RegistersHooksTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Orders live in WooCommerce's order tables, in the posts table, or in both, so each row runs on every storage.
#[CoversNothing]
final class OrdersTest extends TestCase {
	use InsertsUsersTrait;
	use OrderStorageTrait;
	use RegistersHooksTrait;

	protected static array $users = array();

	protected array $stored = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
		foreach ( array( 'administrator', 'shop_manager', 'editor' ) as $role ) {
			self::$users[ $role ] = self::insert_user(
				array(
					'user_login' => "ic-orders-$role",
					'user_pass'  => 'password',
					'user_email' => "ic-orders-$role@example.org",
					'role'       => $role,
				)
			);
		}
	}

	#[\Override]
	public static function tearDownAfterClass(): void {
		foreach ( self::$users as $user ) {
			wp_delete_user( $user );
		}
	}

	#[Before]
	protected function select_orders(): void {
		foreach ( array( Options::POST_TYPES, Options::PRIVATE_COMMENTS ) as $option ) {
			$this->stored[ $option ] = get_option( $option );
		}
		update_option( Options::POST_TYPES, array( 'shop_order' ) );
	}

	#[After]
	protected function restore_settings(): void {
		global $wp_meta_boxes;
		$wp_meta_boxes = array();
		foreach ( $this->stored as $option => $value ) {
			update_option( $option, $value );
		}
		wp_set_current_user( 0 );
	}

	#[DataProvider( 'storages' )]
	public function test_an_order_takes_internal_comments_from_the_users_who_may_edit_it( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order = $this->create_order();

		self::assertTrue( dws_ic_is_selected_post_type( $order->get_id() ) );
		self::assertSame( 201, $this->post_comment( 'administrator', $order->get_id(), 'By an administrator' ) );
		self::assertSame( 201, $this->post_comment( 'shop_manager', $order->get_id(), 'By a shop manager' ) );
		self::assertSame( 403, $this->post_comment( 'editor', $order->get_id(), 'By an editor' ) );

		wp_set_current_user( self::$users['shop_manager'] );
		$html = CommentsList::get_html( $order->get_id() );
		self::assertStringContainsString( 'By an administrator', $html );
		self::assertStringContainsString( 'By a shop manager', $html );
		self::assertCount( 2, dws_ic_get_internal_comments( $order->get_id() ) );
	}

	#[DataProvider( 'storages' )]
	public function test_the_order_screen_shows_the_meta_box( bool $hpos, bool $sync ): void {
		global $wp_meta_boxes;
		$this->use_storage( $hpos, $sync );
		$order  = $this->create_order();
		$object = $hpos ? $order : get_post( $order->get_id() );
		$screen = $hpos ? 'woocommerce_page_wc-orders' : 'shop_order';
		dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => $order->get_id(),
				'comment_content' => 'On the order',
			),
			get_user_by( 'id', self::$users['administrator'] ) ?: null
		);
		wp_set_current_user( self::$users['shop_manager'] );

		new MetaBox( self::make_assets() )->add_meta_box( $screen, $object );
		$box = $wp_meta_boxes[ $screen ]['normal']['core'][ MetaBox::ID ] ?? null;
		self::assertIsArray( $box );
		\ob_start();
		\call_user_func( $box['callback'], $object, $box );
		$markup = (string) \ob_get_clean();

		self::assertStringContainsString( 'On the order', $markup );
		self::assertStringContainsString( 'data-post-id="' . $order->get_id() . '"', $markup );
	}

	// Each storage lists orders through hooks of its own: WordPress's posts list, or WooCommerce's order tables list for each order type.
	#[DataProvider( 'storages' )]
	public function test_the_orders_list_shows_the_column_and_loads_its_quick_view( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order = $this->create_order();
		dws_ic_create_internal_comment( array( 'comment_post_ID' => $order->get_id() ), get_user_by( 'id', self::$users['administrator'] ) ?: null );
		wp_set_current_user( self::$users['shop_manager'] );
		$column                    = new ListColumn( self::make_assets() );
		$GLOBALS['current_screen'] = \WP_Screen::get( $hpos ? wc_get_page_screen_id( 'shop_order' ) : 'edit-shop_order' );

		$this->register( $column );

		try {
			$columns = apply_filters( $hpos ? 'woocommerce_shop_order_list_table_columns' : 'manage_shop_order_posts_columns', array( 'order_number' => 'Order' ) );
			\ob_start();
			do_action( $hpos ? 'woocommerce_shop_order_list_table_custom_column' : 'manage_shop_order_posts_custom_column', ListColumn::COLUMN, $hpos ? $order : $order->get_id() );
			$cell = (string) \ob_get_clean();
			$column->enqueue_list_assets( $hpos ? wc_get_page_screen_id( 'shop_order' ) : 'edit.php' );
			$loaded = wp_script_is( 'internal-comments-quick-view' );
		} finally {
			unset( $GLOBALS['current_screen'] );
			// WordPress refuses to register a handle twice, so the next row's enqueue starts afresh.
			wp_dequeue_script( 'internal-comments-quick-view' );
			wp_dequeue_style( 'internal-comments-quick-view' );
			wp_deregister_script( 'internal-comments-quick-view' );
			wp_deregister_style( 'internal-comments-quick-view' );
		}

		self::assertSame( array( 'order_number', ListColumn::COLUMN ), \array_keys( $columns ) );
		self::assertStringContainsString( '1 internal comment', $cell );
		self::assertTrue( $loaded );
	}

	// WooCommerce's v1 to v3 note routes check only that a comment sits on the order, never its type.
	#[DataProvider( 'storages' )]
	public function test_woocommerces_order_note_routes_answer_an_internal_comment_as_missing( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order    = $this->create_order();
		$internal = (int) dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => $order->get_id(),
				'comment_content' => 'Secret',
				'comment_meta'    => array( Comments::META_PRIVATE => 'yes' ),
			),
			get_user_by( 'id', self::$users['administrator'] ) ?: null
		);
		$note     = $order->add_order_note( 'Ordinary note' );
		wp_set_current_user( self::$users['shop_manager'] );

		foreach ( array( 'wc/v1', 'wc/v2', 'wc/v3' ) as $namespace ) {
			$route  = "/$namespace/orders/{$order->get_id()}/notes";
			$read   = rest_do_request( new \WP_REST_Request( 'GET', "$route/$internal" ) );
			$delete = new \WP_REST_Request( 'DELETE', "$route/$internal" );
			$delete->set_query_params( array( 'force' => true ) );
			$deleted = rest_do_request( $delete );

			self::assertSame( 404, $read->get_status(), $namespace );
			self::assertSame( 'woocommerce_rest_invalid_id', $read->as_error()?->get_error_code(), $namespace );
			self::assertStringNotContainsString( 'Secret', (string) wp_json_encode( $read->get_data() ) );
			self::assertSame( 404, $deleted->get_status(), $namespace );
			self::assertSame( 200, rest_do_request( new \WP_REST_Request( 'GET', "$route/$note" ) )->get_status(), $namespace );
		}
		self::assertInstanceOf( \WP_Comment::class, get_comment( $internal ) );
	}

	// WooCommerce's note deletion checks its nonce and edit_shop_orders, then casts the note id and deletes any comment it names for good.
	public function test_woocommerces_note_deletion_refuses_an_internal_comment(): void {
		$order    = $this->create_order();
		$internal = (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $order->get_id() ), get_user_by( 'id', self::$users['administrator'] ) ?: null );
		$note     = $order->add_order_note( 'Ordinary note' );
		$stop     = static function (): callable {
			return static function ( mixed $message, mixed $title = '', mixed $args = array() ): void {
				throw new \RuntimeException( (string) ( \is_array( $args ) ? ( $args['response'] ?? '' ) : '' ) );
			};
		};
		$delete   = static function ( string $note_id ): string {
			$_POST    = array(
				'note_id'  => $note_id,
				'security' => wp_create_nonce( 'delete-order-note' ),
			);
			$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Stands in for the request, whose nonce WooCommerce verifies.
			try {
				do_action( 'wp_ajax_woocommerce_delete_order_note' );
			} catch ( \RuntimeException $stopped ) {
				return $stopped->getMessage();
			} finally {
				$_POST    = array();
				$_REQUEST = array();
			}

			return 'not stopped';
		};
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $stop );
		wp_set_current_user( self::$users['shop_manager'] );

		try {
			$refused   = $delete( (string) $internal );
			$malformed = $delete( "{$internal}abc" );
			$deleted   = $delete( (string) $note );
		} finally {
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $stop );
		}

		self::assertSame( '403', $refused );
		self::assertSame( '403', $malformed );
		self::assertInstanceOf( \WP_Comment::class, get_comment( $internal ) );
		self::assertNotSame( '403', $deleted );
		self::assertNull( get_comment( $note ) );
	}

	#[DataProvider( 'storages' )]
	public function test_an_internal_comments_link_leads_to_its_orders_edit_screen( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order   = $this->create_order();
		$comment = (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $order->get_id() ), get_user_by( 'id', self::$users['administrator'] ) ?: null );
		wp_set_current_user( self::$users['shop_manager'] );

		self::assertSame( OrderUtil::get_order_admin_edit_url( $order->get_id() ) . "#comment-$comment", get_comment_link( $comment ) );
	}

	// The Comments screen names a comment's post by its title, which an order's placeholder in the posts table lacks, and links to its front end, which an order lacks.
	#[DataProvider( 'storages' )]
	public function test_the_comments_screen_names_an_order_by_its_number_and_hides_its_view_link( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order   = $this->create_order();
		$comment = (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $order->get_id() ), get_user_by( 'id', self::$users['administrator'] ) ?: null );
		$post    = (int) wp_insert_post(
			array(
				'post_title'  => 'Plain',
				'post_status' => 'publish',
			)
		);
		wp_set_current_user( self::$users['shop_manager'] );
		$GLOBALS['current_screen'] = \WP_Screen::get( 'edit-comments' );

		try {
			$title   = get_the_title( $order->get_id() );
			$plain   = get_the_title( $post );
			$classes = get_comment_class( '', $comment, $order->get_id() );
		} finally {
			unset( $GLOBALS['current_screen'] );
			wp_delete_post( $post, true );
		}

		self::assertSame( 'Order #' . $order->get_order_number(), $title );
		self::assertSame( 'Plain', $plain );
		self::assertContains( 'dws-ic-order-comment', $classes );
		self::assertNotSame( 'Order #' . $order->get_order_number(), get_the_title( $order->get_id() ), 'Other screens keep the title.' );
	}

	public function test_the_plugin_declares_itself_compatible_with_the_order_tables(): void {
		self::assertContains( plugin_basename( DWS_IC_FILE ), FeaturesUtil::get_compatible_plugins_for_feature( 'custom_order_tables' )['compatible'] ?? array() );
	}

	protected function post_comment( string $role, int $order_id, string $content ): int {
		wp_set_current_user( self::$users[ $role ] );
		$request = new \WP_REST_Request( 'POST', "/internal-comments/v1/posts/$order_id/comments" );
		$request->set_param( 'content', $content );
		return rest_do_request( $request )->get_status();
	}

	protected static function make_assets(): Assets {
		return new Assets( new PluginIdentity( DWS_IC_FILE, Plugin::SLUG, Plugin::PREFIX ) );
	}
}
