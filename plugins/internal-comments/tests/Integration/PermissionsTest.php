<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PermissionsTest extends TestCase {
	protected static array $users = array();

	protected array $posts = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array( 'administrator', 'editor', 'author', 'subscriber' ) as $role ) {
			self::$users[ $role ] = (int) wp_insert_user(
				array(
					'user_login' => "ic-permissions-$role",
					'user_pass'  => 'password',
					'user_email' => "ic-permissions-$role@example.org",
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

	#[After]
	protected function delete_posts(): void {
		foreach ( $this->posts as $post ) {
			wp_delete_post( $post, true );
		}
		wp_set_current_user( 0 );
	}

	public function test_an_author_sees_their_own_comment_and_others_need_the_see_capability(): void {
		$post    = $this->post( 'author' );
		$comment = $this->comment( $post, 'author' );

		self::assertTrue( user_can( self::$users['author'], 'see_dws_internal_comment', $comment ) );
		self::assertTrue( user_can( self::$users['editor'], 'see_dws_internal_comment', $comment ) );
		self::assertTrue( user_can( self::$users['administrator'], 'see_dws_internal_comment', $comment ) );
		self::assertFalse( user_can( self::$users['subscriber'], 'see_dws_internal_comment', $comment ) );
	}

	public function test_reading_a_comment_needs_access_to_edit_its_post(): void {
		$comment = $this->comment( $this->post( 'administrator' ), 'administrator' );

		self::assertFalse( user_can( self::$users['author'], 'see_dws_internal_comment', $comment ) );
		self::assertTrue( user_can( self::$users['editor'], 'see_dws_internal_comment', $comment ) );
	}

	public function test_editing_and_trashing_follow_v1s_own_and_others_capabilities(): void {
		$post   = $this->post( 'author' );
		$own    = $this->comment( $post, 'author' );
		$others = $this->comment( $post, 'administrator' );

		self::assertFalse( user_can( self::$users['author'], 'edit_dws_internal_comment', $own ) );
		self::assertFalse( user_can( self::$users['author'], 'trash_dws_internal_comment', $own ) );
		get_role( 'author' )?->add_cap( DWS_IC_CAP_EDIT );
		get_role( 'author' )?->add_cap( DWS_IC_CAP_TRASH );
		try {
			self::assertTrue( user_can( self::$users['author'], 'edit_dws_internal_comment', $own ) );
			self::assertTrue( user_can( self::$users['author'], 'trash_dws_internal_comment', $own ) );
			self::assertFalse( user_can( self::$users['author'], 'edit_dws_internal_comment', $others ) );
			self::assertFalse( user_can( self::$users['author'], 'trash_dws_internal_comment', $others ) );
		} finally {
			get_role( 'author' )?->remove_cap( DWS_IC_CAP_EDIT );
			get_role( 'author' )?->remove_cap( DWS_IC_CAP_TRASH );
		}
		self::assertTrue( user_can( self::$users['administrator'], 'edit_dws_internal_comment', $own ) );
		self::assertTrue( user_can( self::$users['administrator'], 'trash_dws_internal_comment', $own ) );
	}

	// Core's comment screens and REST API check edit_comment for every change to a comment.
	public function test_editing_an_internal_comment_through_core_needs_the_edit_capability(): void {
		$post     = $this->post( 'author' );
		$internal = $this->comment( $post, 'author' );
		$public   = (int) wp_insert_comment( array( 'comment_post_ID' => $post ) );

		self::assertTrue( user_can( self::$users['editor'], 'edit_comment', $public ) );
		self::assertFalse( user_can( self::$users['editor'], 'edit_comment', $internal ) );
		self::assertTrue( user_can( self::$users['administrator'], 'edit_comment', $internal ) );
	}

	public function test_the_meta_capabilities_refuse_a_comment_that_is_not_internal(): void {
		$public = (int) wp_insert_comment( array( 'comment_post_ID' => $this->post( 'administrator' ) ) );

		foreach ( array( 'see', 'edit', 'trash' ) as $action ) {
			self::assertFalse( user_can( self::$users['administrator'], "{$action}_dws_internal_comment", $public ) );
			self::assertFalse( user_can( self::$users['administrator'], "{$action}_dws_internal_comment", \PHP_INT_MAX ) );
		}
	}

	public function test_a_private_thread_is_seen_by_its_authors_and_holders_of_the_private_capability(): void {
		$post    = $this->post( 'author' );
		$private = $this->comment( $post, 'author', array( 'comment_meta' => array( DWS_IC_META_PRIVATE => 'yes' ) ) );
		$reply   = $this->comment( $post, 'administrator', array( 'comment_parent' => $private ) );

		self::assertTrue( user_can( self::$users['author'], 'see_dws_internal_comment', $private ) );
		self::assertTrue( user_can( self::$users['author'], 'see_dws_internal_comment', $reply ) );
		self::assertTrue( user_can( self::$users['administrator'], 'see_dws_internal_comment', $private ) );
		self::assertFalse( user_can( self::$users['editor'], 'see_dws_internal_comment', $private ) );
		self::assertFalse( user_can( self::$users['editor'], 'see_dws_internal_comment', $reply ) );
	}

	public function test_the_comments_a_user_reads_leave_out_the_ones_they_cannot_see(): void {
		$post    = $this->post( 'author' );
		$open    = $this->comment( $post, 'author' );
		$private = $this->comment( $post, 'author', array( 'comment_meta' => array( DWS_IC_META_PRIVATE => 'yes' ) ) );
		$ids     = static fn ( array $comments ): array => \array_map( static fn ( \DWS_Internal_Comment $comment ): int => (int) $comment->comment_ID, \array_values( $comments ) );

		wp_set_current_user( self::$users['editor'] );
		self::assertSame( array( $open ), $ids( dws_ic_get_internal_comments( $post ) ) );
		self::assertEqualsCanonicalizing( array( $open, $private ), $ids( dws_ic_get_internal_comments( $post, array(), true ) ) );
		wp_set_current_user( self::$users['author'] );
		self::assertEqualsCanonicalizing( array( $open, $private ), $ids( dws_ic_get_internal_comments( $post ) ) );
	}

	public function test_the_rest_api_shows_an_internal_comment_only_to_who_can_edit_it(): void {
		$comment = $this->comment( $this->post( 'administrator' ), 'administrator' );

		self::assertSame( 401, rest_do_request( new \WP_REST_Request( 'GET', "/wp/v2/comments/$comment" ) )->get_status() );
		wp_set_current_user( self::$users['editor'] );
		self::assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', "/wp/v2/comments/$comment" ) )->get_status() );
		wp_set_current_user( self::$users['administrator'] );
		self::assertSame( 200, rest_do_request( new \WP_REST_Request( 'GET', "/wp/v2/comments/$comment" ) )->get_status() );
	}

	protected function post( string $role ): int {
		$post          = (int) wp_insert_post(
			array(
				'post_title'  => 'Permissions',
				'post_status' => 'publish',
				'post_author' => self::$users[ $role ],
			)
		);
		$this->posts[] = $post;

		return $post;
	}

	protected function comment( int $post, string $role, array $args = array() ): int {
		return (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $post ) + $args, get_user_by( 'id', self::$users[ $role ] ) ?: null );
	}
}
