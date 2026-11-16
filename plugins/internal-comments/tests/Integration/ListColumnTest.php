<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Capabilities;
use DeepWebSolutions\InternalComments\Comments;
use DeepWebSolutions\InternalComments\ListColumn;
use DeepWebSolutions\InternalComments\Options;
use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Asset\Assets;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\InternalComments\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\InternalComments\Tests\Support\RegistersHooksTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( ListColumn::class )]
final class ListColumnTest extends TestCase {
	use RecordsDeprecationsTrait;
	use RegistersHooksTrait;

	// WordPress reports a deprecated hook only when something listens on it.
	protected const string V1_PRECEDING_COLUMN = 'internal_comments/wp_table_output/insert_after';

	protected static array $users = array();

	protected int $post;

	protected array $stored = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		foreach ( array( 'administrator', 'editor' ) as $role ) {
			self::$users[ $role ] = (int) wp_insert_user(
				array(
					'user_login' => "ic-screens-$role",
					'user_pass'  => 'password',
					'user_email' => "ic-screens-$role@example.org",
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
	protected function create_post(): void {
		foreach ( array( Options::POST_TYPES, Options::PRIVATE_COMMENTS ) as $option ) {
			$this->stored[ $option ] = get_option( $option );
		}
		update_option( Options::POST_TYPES, array( 'post' ) );
		$this->register( new ListColumn( self::make_assets() ) );
		$this->post = (int) wp_insert_post(
			array(
				'post_title'  => 'Screens',
				'post_status' => 'publish',
			)
		);
		wp_set_current_user( self::$users['administrator'] );
	}

	#[After]
	protected function delete_post(): void {
		wp_delete_post( $this->post, true );
		foreach ( $this->stored as $option => $value ) {
			update_option( $option, $value );
		}
		wp_set_current_user( 0 );
		unset( $_GET['comment_type'] );
	}

	public function test_the_posts_list_column_follows_the_comments_column_and_passes_v1s_filter_first(): void {
		$seen = array();
		$v1   = static function ( string $column, ?string $post_type ) use ( &$seen ): string {
			$seen[] = array( 'v1', $column, $post_type );
			return $column;
		};
		$v2   = static function ( string $column, ?string $post_type ) use ( &$seen ): string {
			$seen[] = array( 'v2', $column, $post_type );
			return 'title';
		};
		add_filter( self::V1_PRECEDING_COLUMN, $v1, 10, 2 );
		add_filter( 'internal_comments/list_table/preceding_column', $v2, 10, 2 );

		try {
			$columns = apply_filters(
				'manage_post_posts_columns',
				array(
					'cb'       => '',
					'title'    => 'Title',
					'comments' => 'Comments',
					'date'     => 'Date',
				)
			);
		} finally {
			remove_filter( self::V1_PRECEDING_COLUMN, $v1 );
			remove_filter( 'internal_comments/list_table/preceding_column', $v2 );
		}

		self::assertSame( array( 'cb', 'title', 'dws-internal-comments', 'comments', 'date' ), \array_keys( $columns ) );
		self::assertSame( array( array( 'v1', 'comments', 'post' ), array( 'v2', 'comments', 'post' ) ), $seen );
		self::assertSame( array( array( self::V1_PRECEDING_COLUMN, 'internal_comments/list_table/preceding_column' ) ), $this->deprecated );
		wp_set_current_user( self::$users['editor'] );
		self::assertArrayHasKey( 'dws-internal-comments', apply_filters( 'manage_post_posts_columns', array( 'title' => 'Title' ) ) );
		self::assertSame( array( 'title' => 'Title' ), apply_filters( 'manage_page_posts_columns', array( 'title' => 'Title' ) ) );
	}

	// WooCommerce rebuilds the columns of its orders and coupons lists on that hook at the default priority.
	public function test_the_column_joins_a_list_after_woocommerce_rebuilds_it(): void {
		update_option( Options::POST_TYPES, array( 'shop_order' ) );
		$column = $this->register( new ListColumn( self::make_assets() ) );

		$priority = has_filter( 'manage_shop_order_posts_columns', array( $column, 'add_posts_column' ) );

		self::assertIsInt( $priority );
		self::assertGreaterThan( 10, $priority );
	}

	public function test_the_cell_counts_the_comments_the_user_can_see_and_opens_their_quick_view(): void {
		$this->comment( 'Open' );
		$this->comment( 'Private', array( Comments::META_PRIVATE => 'yes' ) );

		$admin = $this->cell();
		wp_set_current_user( self::$users['editor'] );
		$editor = $this->cell();

		self::assertSame( '2', \trim( (string) $admin->querySelector( '.comment-count' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertStringContainsString( 'comment_type=dws_internal', (string) $admin->querySelector( 'a.post-com-count' )?->getAttribute( 'href' ) );
		$button  = $admin->querySelector( 'button[popovertarget]' );
		$popover = $admin->querySelector( '[popover]' );
		self::assertNotNull( $button );
		self::assertSame( $popover?->getAttribute( 'id' ), $button?->getAttribute( 'popovertarget' ) );
		self::assertSame( (string) $this->post, $popover?->getAttribute( 'data-post-id' ) );
		self::assertStringContainsString( 'no-link', (string) $popover?->getAttribute( 'class' ) ); // WooCommerce opens the order on a click anywhere in its row but inside this class.
		self::assertSame( '1', \trim( (string) $editor->querySelector( '.comment-count' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNull( $editor->querySelector( 'a.post-com-count' ) );
	}

	public function test_a_post_without_comments_shows_a_dash(): void {
		self::assertStringContainsString( 'No internal comments', (string) $this->cell()->body?->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
	}

	// v1 showed the column and loaded its quick view only for users who may see internal comments.
	public function test_the_column_and_its_assets_need_the_capability_to_see_internal_comments(): void {
		$screens = new ListColumn( self::make_assets() );
		get_role( 'editor' )?->remove_cap( Capabilities::SEE );
		wp_set_current_user( self::$users['editor'] );
		wp_get_current_user()->get_role_caps();

		try {
			$column = apply_filters( 'manage_post_posts_columns', array( 'title' => 'Title' ) );
			$screens->enqueue_list_assets( 'edit.php' );
			$loaded = wp_script_is( 'internal-comments-quick-view' );
		} finally {
			get_role( 'editor' )?->add_cap( Capabilities::SEE );
			wp_get_current_user()->get_role_caps();
			wp_dequeue_script( 'internal-comments-quick-view' );
			wp_dequeue_style( 'internal-comments-quick-view' );
		}

		self::assertArrayNotHasKey( 'dws-internal-comments', $column );
		self::assertFalse( $loaded );
		self::assertArrayHasKey( 'dws-internal-comments', apply_filters( 'manage_post_posts_columns', array( 'title' => 'Title' ) ) );
	}

	public function test_the_posts_list_loads_the_quick_views_assets(): void {
		new ListColumn( self::make_assets() )->enqueue_list_assets( 'edit.php' );

		self::assertTrue( wp_script_is( 'internal-comments-quick-view' ) );
		self::assertTrue( wp_style_is( 'internal-comments-quick-view' ) );
		wp_dequeue_script( 'internal-comments-quick-view' );
		wp_dequeue_style( 'internal-comments-quick-view' );
		wp_deregister_script( 'internal-comments-quick-view' );
		wp_deregister_style( 'internal-comments-quick-view' );
	}

	protected function cell(): \Dom\HTMLDocument {
		\ob_start();
		do_action( 'manage_post_posts_custom_column', 'dws-internal-comments', $this->post );
		return \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . \ob_get_clean() . '</body></html>', \LIBXML_NOERROR );
	}

	protected function comment( string $content, array $meta = array() ): int {
		return (int) dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => $this->post,
				'comment_content' => $content,
				'comment_meta'    => $meta,
			),
			get_user_by( 'id', self::$users['administrator'] ) ?: null
		);
	}

	protected static function make_assets(): Assets {
		return new Assets( new PluginIdentity( DWS_IC_FILE, Plugin::SLUG, Plugin::PREFIX ) );
	}
}
