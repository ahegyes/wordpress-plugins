<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use DeepWebSolutions\InternalComments\AdminScreens;
use DeepWebSolutions\InternalComments\MetaBox;
use DeepWebSolutions\InternalComments\Options;
use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Asset\Assets;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\InternalComments\Tests\Support\OrderStorageTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Orders live in WooCommerce's order tables, in the posts table, or in both, so each row runs on every storage.
#[CoversNothing]
final class OrdersTest extends TestCase {
	use OrderStorageTrait;

	protected static array $users = array();

	protected array $stored = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		foreach ( array( 'administrator', 'shop_manager', 'editor' ) as $role ) {
			self::$users[ $role ] = (int) wp_insert_user(
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
		$html = dws_ic_get_comments_list_html( $order->get_id() );
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
		dws_ic_create_internal_comment( array( 'comment_post_ID' => $order->get_id(), 'comment_content' => 'On the order' ), get_user_by( 'id', self::$users['administrator'] ) ?: null ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- A comment's two fields.
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

	#[DataProvider( 'storages' )]
	public function test_the_orders_list_shows_the_column( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order = $this->create_order();
		dws_ic_create_internal_comment( array( 'comment_post_ID' => $order->get_id() ), get_user_by( 'id', self::$users['administrator'] ) ?: null );
		wp_set_current_user( self::$users['shop_manager'] );
		$screens = new AdminScreens( self::make_assets() );

		self::assertArrayHasKey( AdminScreens::COLUMN, $screens->add_column( array( 'order_number' => 'Order' ), 'shop_order' ) );
		\ob_start();
		$screens->render_order_cell( AdminScreens::COLUMN, $order );
		self::assertStringContainsString( '1 internal comment', (string) \ob_get_clean() );
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
