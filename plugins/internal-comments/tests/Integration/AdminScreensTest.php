<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\AdminScreens;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class AdminScreensTest extends TestCase {
	// WordPress reports a deprecated hook only when something listens on it.
	protected const string V1_PRECEDING_COLUMN = 'internal_comments/wp_table_output/insert_after';

	protected static array $users = array();

	protected int $post;

	protected mixed $stored;

	protected array $deprecated = array();

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
		$this->stored = get_option( DWS_IC_OPTION_GENERAL );
		update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_POST_TYPES => array( 'post' ) ) );
		$this->post = (int) wp_insert_post(
			array(
				'post_title'  => 'Screens',
				'post_status' => 'publish',
			)
		);
		wp_set_current_user( self::$users['administrator'] );
		add_filter( 'deprecated_hook_trigger_error', '__return_false' );
		add_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ), 10, 2 );
	}

	#[After]
	protected function delete_post(): void {
		wp_delete_post( $this->post, true );
		update_option( DWS_IC_OPTION_GENERAL, $this->stored );
		wp_set_current_user( 0 );
		unset( $_GET['comment_type'] );
		remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		remove_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ) );
	}

	public function record_deprecated_hook( string $hook, string $replacement ): void {
		$this->deprecated[] = array( $hook, $replacement );
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
		add_filter( 'dws_ic/list_table/preceding_column', $v2, 10, 2 );

		try {
			$columns = ( new AdminScreens() )->add_column(
				array(
					'cb'       => '',
					'title'    => 'Title',
					'comments' => 'Comments',
					'date'     => 'Date',
				),
				'post'
			);
		} finally {
			remove_filter( self::V1_PRECEDING_COLUMN, $v1 );
			remove_filter( 'dws_ic/list_table/preceding_column', $v2 );
		}

		self::assertSame( array( 'cb', 'title', 'dws-internal-comments', 'comments', 'date' ), \array_keys( $columns ) );
		self::assertSame( array( array( 'v1', 'comments', 'post' ), array( 'v2', 'comments', 'post' ) ), $seen );
		self::assertSame( array( array( self::V1_PRECEDING_COLUMN, 'dws_ic/list_table/preceding_column' ) ), $this->deprecated );
		wp_set_current_user( self::$users['editor'] );
		self::assertArrayHasKey( 'dws-internal-comments', ( new AdminScreens() )->add_column( array( 'title' => 'Title' ), 'post' ) );
		self::assertSame( array( 'title' => 'Title' ), ( new AdminScreens() )->add_column( array( 'title' => 'Title' ), 'page' ) );
	}

	// WooCommerce rebuilds the columns of its orders and coupons lists on that hook at the default priority.
	public function test_the_column_joins_a_list_after_woocommerce_rebuilds_it(): void {
		update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_POST_TYPES => array( 'shop_order' ) ) );
		$screens = new AdminScreens();
		$screens->register_hooks();

		try {
			$priority = has_filter( 'manage_shop_order_posts_columns', array( $screens, 'add_posts_column' ) );
		} finally {
			foreach ( $GLOBALS['wp_filter'] as $hook => $wp_hook ) {
				foreach ( $wp_hook->callbacks as $level => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						if ( \is_array( $callback['function'] ) && $screens === $callback['function'][0] ) {
							remove_filter( $hook, $callback['function'], $level );
						}
					}
				}
			}
		}

		self::assertIsInt( $priority );
		self::assertGreaterThan( 10, $priority );
	}

	public function test_the_cell_counts_the_comments_the_user_can_see_and_opens_their_quick_view(): void {
		$this->comment( 'Open' );
		$this->comment( 'Private', array( DWS_IC_META_PRIVATE => 'yes' ) );

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
		self::assertSame( '1', \trim( (string) $editor->querySelector( '.comment-count' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNull( $editor->querySelector( 'a.post-com-count' ) );
	}

	public function test_a_post_without_comments_shows_a_dash(): void {
		self::assertStringContainsString( 'No internal comments', (string) $this->cell()->body?->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
	}

	public function test_the_comments_screen_lists_internal_comments_only_under_their_type_for_users_who_may(): void {
		$screens = new AdminScreens();

		$_GET['comment_type'] = DWS_IC_COMMENT_TYPE;
		$args                 = $screens->filter_list_table_args(
			array(
				'status' => '',
				'type'   => DWS_IC_COMMENT_TYPE,
			)
		);
		self::assertSame( DWS_IC_COMMENT_STATUS, $args['status'] );
		self::assertSame( 'trash', $screens->filter_list_table_args( array( 'status' => 'trash', 'type' => DWS_IC_COMMENT_TYPE ) )['status'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- The list table's two arguments.

		wp_set_current_user( self::$users['editor'] );
		foreach ( array( 'all', 'trash' ) as $status ) {
			self::assertContains( DWS_IC_COMMENT_TYPE, $screens->filter_list_table_args( array( 'status' => $status ) )['type__not_in'] ?? array() );
		}
		self::assertArrayNotHasKey( DWS_IC_COMMENT_TYPE, $screens->add_comment_type( array() ) );
		wp_set_current_user( self::$users['administrator'] );
		self::assertArrayHasKey( DWS_IC_COMMENT_TYPE, $screens->add_comment_type( array() ) );
	}

	public function test_the_comments_screen_leaves_out_the_rows_its_reader_cannot_see(): void {
		$open = $this->comment( 'Open' );
		$this->comment( 'Private', array( DWS_IC_META_PRIVATE => 'yes' ) );
		get_role( 'editor' )?->add_cap( DWS_IC_CAP_SEE_TABLE );
		wp_set_current_user( self::$users['editor'] );

		try {
			$args = ( new AdminScreens() )->filter_list_table_args(
				array(
					'post_id' => $this->post,
					'type'    => DWS_IC_COMMENT_TYPE,
					'status'  => 'all',
				)
			);
			$rows = ( new \WP_Comment_Query( $args ) )->comments;
			$all  = get_comments( array( 'post_id' => $this->post, 'type' => DWS_IC_COMMENT_TYPE, 'status' => DWS_IC_COMMENT_STATUS ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- A query's three arguments.
		} finally {
			get_role( 'editor' )?->remove_cap( DWS_IC_CAP_SEE_TABLE );
		}

		self::assertSame( array( (string) $open ), wp_list_pluck( $rows, 'comment_ID' ) );
		self::assertCount( 2, $all );
	}

	public function test_the_comments_screen_offers_only_the_actions_an_internal_comment_supports(): void {
		$comment = get_comment( $this->comment( 'Row' ) );
		$actions = array_fill_keys( array( 'approve', 'unapprove', 'reply', 'quickedit', 'edit', 'spam', 'trash', 'untrash', 'delete' ), '' );

		self::assertInstanceOf( \WP_Comment::class, $comment );
		self::assertSame( array( 'edit', 'trash', 'untrash', 'delete' ), \array_keys( ( new AdminScreens() )->filter_row_actions( $actions, $comment ) ) );
	}

	public function test_the_edit_screen_of_an_internal_comment_shows_its_revisions_and_its_author_its_privacy(): void {
		global $wp_meta_boxes;
		update_option(
			DWS_IC_OPTION_GENERAL,
			array(
				DWS_IC_SETTING_POST_TYPES       => array( 'post' ),
				DWS_IC_SETTING_PRIVATE_COMMENTS => 'yes',
			)
		);
		$comment = $this->comment( 'Before' );
		wp_update_comment(
			array(
				'comment_ID'      => $comment,
				'comment_content' => 'After',
			)
		);
		$screens = new AdminScreens();

		$wp_meta_boxes = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The row registers the meta boxes anew.
		$screens->add_revisions_meta_box( get_comment( $comment ) );
		$box = $wp_meta_boxes['comment']['normal']['default']['dws-internal-comments-revisions'] ?? null;
		self::assertIsArray( $box );
		\ob_start();
		\call_user_func( $box['callback'], get_comment( $comment ), $box );
		self::assertStringContainsString( 'Before', (string) \ob_get_clean() );

		$author = $screens->add_privacy_field( '', get_comment( $comment ) );
		wp_set_current_user( self::$users['editor'] );
		$other = $screens->add_privacy_field( '', get_comment( $comment ) );
		self::assertStringContainsString( 'name="comment_meta[dws_ic_is_private]"', $author );
		self::assertStringNotContainsString( 'name="comment_meta[dws_ic_is_private]"', $other );
		self::assertSame( '', $screens->add_privacy_field( '', get_comment( (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) ) ) ) );

		// Once someone has replied, the author may still make a private comment public, but not the other way round.
		wp_set_current_user( self::$users['administrator'] );
		$private = $this->comment( 'Private', array( DWS_IC_META_PRIVATE => 'yes' ) );
		foreach ( array( $comment, $private ) as $replied_to ) {
			dws_ic_create_internal_comment(
				array(
					'comment_post_ID' => $this->post,
					'comment_parent'  => $replied_to,
				)
			);
		}
		self::assertStringNotContainsString( 'name="comment_meta[dws_ic_is_private]"', $screens->add_privacy_field( '', get_comment( $comment ) ) );
		self::assertStringContainsString( 'name="comment_meta[dws_ic_is_private]"', $screens->add_privacy_field( '', get_comment( $private ) ) );
	}

	// Saving keeps an internal comment's author, status and date as stored, so the edit screen does not offer them.
	public function test_the_comment_edit_screen_hides_the_fields_an_internal_comment_keeps(): void {
		$screens = new AdminScreens();
		$styles  = static function ( int $comment ) use ( $screens ): string {
			$GLOBALS['comment'] = get_comment( $comment ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Stands in for the edit screen's comment.
			try {
				$screens->enqueue_comment_screen_style( 'comment.php' );

				return \implode( '', (array) wp_styles()->get_data( 'internal-comments-comment-screen', 'after' ) );
			} finally {
				unset( $GLOBALS['comment'] );
				wp_dequeue_style( 'internal-comments-comment-screen' );
				wp_deregister_style( 'internal-comments-comment-screen' );
			}
		};

		$internal = $styles( $this->comment( 'Internal' ) );
		foreach ( array( '#namediv', '#comment-status', '.misc-pub-curtime' ) as $selector ) {
			self::assertStringContainsString( $selector, $internal );
		}
		self::assertSame( '', $styles( (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) ) ) );
	}

	// WordPress counts the Comments screen's views apart from its list, and it and WooCommerce cache the counts for every user.
	public function test_the_comments_screen_counts_only_the_trashed_comments_the_user_may_list(): void {
		$internal = $this->comment( 'Trashed' );
		$public   = (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) );
		wp_trash_comment( $internal );
		wp_trash_comment( $public );
		$GLOBALS['current_screen'] = \WP_Screen::get( 'edit-comments' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Sets the screen without firing current_screen, which other plugins hook with admin-only code.

		$counts      = static function (): array {
			wp_set_current_user( self::$users['editor'] );
			wp_get_current_user()->get_role_caps(); // An earlier row can leave the editor current with capabilities its role has since lost.
			$editor = (int) wp_count_comments()->trash;
			wp_set_current_user( self::$users['administrator'] );

			return array( $editor, (int) wp_count_comments()->trash );
		};
		$woocommerce = has_filter( 'wp_count_comments', array( 'WC_Comments', 'wp_count_comments' ) );
		\WC_Comments::delete_comments_count_cache(); // Earlier tests leave WooCommerce's incremental counts behind.

		try {
			list( $editor, $administrator ) = $counts();
			remove_filter( 'wp_count_comments', array( 'WC_Comments', 'wp_count_comments' ) );
			wp_cache_delete( 'comments-0', 'counts' );
			list( $core_editor, $core_administrator ) = $counts();
			$cached                                   = wp_cache_get( 'comments-0', 'counts' );
		} finally {
			if ( \is_int( $woocommerce ) ) {
				add_filter( 'wp_count_comments', array( 'WC_Comments', 'wp_count_comments' ), $woocommerce, 2 );
			}
			unset( $GLOBALS['current_screen'] );
		}

		self::assertIsInt( $woocommerce );
		self::assertSame( $administrator - 1, $editor );
		self::assertSame( $core_administrator - 1, $core_editor );
		self::assertIsObject( $cached );
		self::assertSame( $core_administrator, (int) $cached->trash );
	}

	// WooCommerce leaves comments on products out of every comment query that names no type, the counts included.
	public function test_the_comments_screen_counts_trashed_comments_as_woocommerce_lists_them(): void {
		$product = (int) wp_insert_post(
			array(
				'post_type'   => 'product',
				'post_title'  => 'Product',
				'post_status' => 'publish',
			)
		);
		wp_trash_comment( (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $product ), get_user_by( 'id', self::$users['administrator'] ) ?: null ) );
		wp_trash_comment( (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) ) );
		$GLOBALS['current_screen'] = \WP_Screen::get( 'edit-comments' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Sets the screen without firing current_screen, which other plugins hook with admin-only code.
		\WC_Comments::delete_comments_count_cache();
		wp_cache_delete( 'comments-0', 'counts' );

		try {
			wp_set_current_user( self::$users['administrator'] );
			$administrator = (int) wp_count_comments()->trash;
			wp_set_current_user( self::$users['editor'] );
			wp_get_current_user()->get_role_caps(); // An earlier row can leave the editor current with capabilities its role has since lost.
			$editor = (int) wp_count_comments()->trash;
		} finally {
			unset( $GLOBALS['current_screen'] );
			wp_delete_post( $product, true );
			\WC_Comments::delete_comments_count_cache();
			wp_cache_delete( 'comments-0', 'counts' );
		}

		self::assertGreaterThan( 0, $administrator );
		self::assertSame( $administrator, $editor );
	}

	// While v1's comments are being moved, the type exclusion already leaves internal comments out of every count.
	public function test_the_comments_screen_counts_trashed_comments_once_while_v1s_are_moved(): void {
		wp_trash_comment( $this->comment( 'Trashed' ) );
		wp_trash_comment( (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) ) );
		$revision = get_option( 'dws_ic_revision' );
		update_option( 'dws_ic_revision', 1 );
		$GLOBALS['current_screen'] = \WP_Screen::get( 'edit-comments' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Sets the screen without firing current_screen, which other plugins hook with admin-only code.
		\WC_Comments::delete_comments_count_cache();
		wp_cache_delete( 'comments-0', 'counts' );

		try {
			wp_set_current_user( self::$users['administrator'] );
			$administrator = (int) wp_count_comments()->trash;
			wp_set_current_user( self::$users['editor'] );
			wp_get_current_user()->get_role_caps(); // An earlier row can leave the editor current with capabilities its role has since lost.
			$editor = (int) wp_count_comments()->trash;
		} finally {
			update_option( 'dws_ic_revision', $revision );
			unset( $GLOBALS['current_screen'] );
			\WC_Comments::delete_comments_count_cache();
			wp_cache_delete( 'comments-0', 'counts' );
		}

		self::assertGreaterThan( 0, $administrator );
		self::assertSame( $administrator, $editor );
	}

	public function test_the_posts_list_loads_the_quick_views_assets(): void {
		( new AdminScreens() )->enqueue_list_assets( 'edit.php' );

		self::assertTrue( wp_script_is( 'internal-comments-quick-view' ) );
		self::assertTrue( wp_style_is( 'internal-comments-quick-view' ) );
		wp_dequeue_script( 'internal-comments-quick-view' );
		wp_dequeue_style( 'internal-comments-quick-view' );
	}

	protected function cell(): \Dom\HTMLDocument {
		\ob_start();
		( new AdminScreens() )->render_cell( 'dws-internal-comments', $this->post );

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
}
