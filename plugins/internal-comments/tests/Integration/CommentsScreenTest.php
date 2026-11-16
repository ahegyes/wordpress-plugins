<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Capabilities;
use DeepWebSolutions\InternalComments\Comments;
use DeepWebSolutions\InternalComments\CommentsScreen;
use DeepWebSolutions\InternalComments\Options;
use DeepWebSolutions\InternalComments\Tests\Support\RecordsDeprecationsTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( CommentsScreen::class )]
final class CommentsScreenTest extends TestCase {
	use RecordsDeprecationsTrait;

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

	// WordPress counts its views for every comment, so on the internal view they count internal comments, under the status they never leave.
	public function test_the_internal_views_status_links_count_internal_comments(): void {
		$this->comment( 'Mine' );
		$this->comment( 'Also mine' );
		wp_trash_comment( $this->comment( 'Trashed' ) );
		wp_insert_comment( array( 'comment_post_ID' => $this->post ) );
		$link   = static fn ( string $status, string $counter, int $count ): string => "<a href=\"edit-comments.php?comment_status=$status\">" . \ucfirst( $status ) . " <span class=\"count\">(<span class=\"$counter-count\">$count</span>)</span></a>";
		$links  = array(
			'all'       => $link( 'all', 'all', 9 ),
			'mine'      => $link( 'mine', 'mine', 9 ),
			'moderated' => $link( 'moderated', 'pending', 9 ),
			'approved'  => $link( 'approved', 'approved', 9 ),
			'spam'      => $link( 'spam', 'spam', 9 ),
			'trash'     => $link( 'trash', 'trash', 9 ),
		);
		$counts = static fn ( array $filtered ): array => \array_map( static fn ( string $html ): string => (string) \preg_replace( '/^.*-count">([^<]*)<.*$/s', '$1', $html ), $filtered );

		$general              = $counts( apply_filters( 'comment_status_links', $links ) );
		$_GET['comment_type'] = Comments::TYPE;
		$internal             = $counts( apply_filters( 'comment_status_links', $links ) );
		wp_set_current_user( self::$users['editor'] );
		$unlisted = $counts( apply_filters( 'comment_status_links', $links ) );

		self::assertSame( \array_fill_keys( \array_keys( $links ), '9' ), $general );
		self::assertSame(
			array(
				'all'   => '2',
				'mine'  => '2',
				'trash' => '1',
			),
			$internal
		);
		self::assertSame( \array_fill_keys( array( 'all', 'mine', 'trash' ), '0' ), $unlisted );
	}

	// The type dropdown offers a type only where a count query that names it finds comments, which an empty status would not.
	public function test_the_type_dropdown_finds_internal_comments_on_the_comments_screen(): void {
		$this->comment( 'Counted' );
		$GLOBALS['current_screen'] = \WP_Screen::get( 'edit-comments' );

		try {
			$count = get_comments(
				array(
					'count'   => true,
					'orderby' => 'none',
					'type'    => Comments::TYPE,
				)
			);
		} finally {
			unset( $GLOBALS['current_screen'] );
		}

		self::assertGreaterThan( 0, $count );
	}

	public function test_the_menu_marks_the_internal_comments_view_current_on_it(): void {
		$general              = apply_filters( 'submenu_file', null, 'edit-comments.php' );
		$_GET['comment_type'] = Comments::TYPE;

		self::assertNull( $general );
		self::assertSame( 'edit-comments.php?comment_type=' . Comments::TYPE, apply_filters( 'submenu_file', null, 'edit-comments.php' ) );
		self::assertNull( apply_filters( 'submenu_file', null, 'edit.php' ) );
	}

	public static function move_states(): array {
		return array(
			'move-pending' => array( true ),
			'move-done'    => array( false ),
		);
	}

	public function test_the_comments_screen_lists_internal_comments_only_under_their_type_for_users_who_may(): void {
		$screens = new CommentsScreen();

		$_GET['comment_type'] = Comments::TYPE;
		$args                 = $screens->filter_list_table_args(
			array(
				'status' => '',
				'type'   => Comments::TYPE,
			)
		);
		self::assertSame( Comments::STATUS, $args['status'] );
		self::assertSame( 'trash', $screens->filter_list_table_args( array( 'status' => 'trash', 'type' => Comments::TYPE ) )['status'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- The list table's two arguments.

		wp_set_current_user( self::$users['editor'] );
		$named = $screens->filter_list_table_args(
			array(
				'status' => 'all',
				'type'   => Comments::TYPE,
			)
		);
		self::assertContains( Comments::TYPE, $named['type__not_in'] ?? array() );
		// WooCommerce stops leaving product reviews out of a query that names types to leave out.
		foreach ( array( 'all', 'trash' ) as $status ) {
			self::assertArrayNotHasKey( 'type__not_in', $screens->filter_list_table_args( array( 'status' => $status ) ) );
		}
		self::assertArrayNotHasKey( Comments::TYPE, $screens->add_comment_type( array() ) );
		wp_set_current_user( self::$users['administrator'] );
		self::assertArrayHasKey( Comments::TYPE, $screens->add_comment_type( array() ) );
	}

	public function test_the_comments_screen_leaves_out_the_rows_its_reader_cannot_see(): void {
		$open = $this->comment( 'Open' );
		$this->comment( 'Private', array( Comments::META_PRIVATE => 'yes' ) );
		get_role( 'editor' )?->add_cap( Capabilities::SEE_TABLE );
		wp_set_current_user( self::$users['editor'] );

		try {
			$args = new CommentsScreen()->filter_list_table_args(
				array(
					'post_id' => $this->post,
					'type'    => Comments::TYPE,
					'status'  => 'all',
				)
			);
			$rows = new \WP_Comment_Query( $args )->comments;
			$all  = get_comments( array( 'post_id' => $this->post, 'type' => Comments::TYPE, 'status' => Comments::STATUS ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- A query's three arguments.
		} finally {
			get_role( 'editor' )?->remove_cap( Capabilities::SEE_TABLE );
		}

		self::assertSame( array( (string) $open ), wp_list_pluck( $rows, 'comment_ID' ) );
		self::assertCount( 2, $all );
	}

	public function test_the_comments_screen_offers_only_the_actions_an_internal_comment_supports(): void {
		$comment = get_comment( $this->comment( 'Row' ) );
		$actions = \array_fill_keys( array( 'approve', 'unapprove', 'reply', 'quickedit', 'edit', 'spam', 'trash', 'untrash', 'delete' ), '' );

		self::assertInstanceOf( \WP_Comment::class, $comment );
		self::assertSame( array( 'edit', 'trash', 'untrash', 'delete' ), \array_keys( new CommentsScreen()->filter_row_actions( $actions, $comment ) ) );
	}

	// WordPress counts the Comments screen's views apart from its list, and it and WooCommerce cache the counts for every user.
	#[DataProvider( 'move_states' )]
	public function test_the_comments_screens_views_leave_trashed_internal_comments_out_of_their_lists_and_counts( bool $pending ): void {
		$internal = $this->comment( 'Trashed' );
		$public   = (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) );
		wp_trash_comment( $internal );
		wp_trash_comment( $public );
		$revision = get_option( 'dws_ic_revision' );
		if ( $pending ) {
			update_option( 'dws_ic_revision', 1 );
		}
		$GLOBALS['current_screen'] = \WP_Screen::get( 'edit-comments' );

		$counts      = static function (): array {
			wp_set_current_user( self::$users['editor'] );
			wp_get_current_user()->get_role_caps(); // An earlier row can leave the editor current with capabilities its role has since lost.
			$editor = (int) wp_count_comments()->trash;
			wp_set_current_user( self::$users['administrator'] );
			return array( $editor, (int) wp_count_comments()->trash );
		};
		$listed      = fn (): array => wp_list_pluck(
			new \WP_Comment_Query(
				new CommentsScreen()->filter_list_table_args(
					array(
						'status'  => 'trash',
						'post_id' => $this->post,
					)
				)
			)->comments,
			'comment_ID'
		);
		$woocommerce = has_filter( 'wp_count_comments', array( 'WC_Comments', 'wp_count_comments' ) );
		\WC_Comments::delete_comments_count_cache(); // Earlier tests leave WooCommerce's incremental counts behind.

		try {
			list( $editor, $administrator ) = $counts();
			$rows                           = $listed();
			remove_filter( 'wp_count_comments', array( 'WC_Comments', 'wp_count_comments' ) );
			wp_cache_delete( 'comments-0', 'counts' );
			list( $core_editor, $core_administrator ) = $counts();
			$cached                                   = wp_cache_get( 'comments-0', 'counts' );
		} finally {
			if ( \is_int( $woocommerce ) ) {
				add_filter( 'wp_count_comments', array( 'WC_Comments', 'wp_count_comments' ), $woocommerce, 2 );
			}
			update_option( 'dws_ic_revision', $revision );
			unset( $GLOBALS['current_screen'] );
			\WC_Comments::delete_comments_count_cache();
			wp_cache_delete( 'comments-0', 'counts' );
		}

		self::assertIsInt( $woocommerce );
		self::assertSame( array( (string) $public ), $rows );
		self::assertSame( $administrator, $editor );
		self::assertSame( $core_administrator, $core_editor );
		self::assertSame( $core_administrator, $administrator );
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
		$GLOBALS['current_screen'] = \WP_Screen::get( 'edit-comments' );
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
