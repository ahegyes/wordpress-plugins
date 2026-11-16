<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Comments;
use DeepWebSolutions\InternalComments\Options;
use DeepWebSolutions\InternalComments\Tests\Support\InsertsUsersTrait;
use DeepWebSolutions\InternalComments\Tests\Support\RecordsDeprecationsTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CommentsTest extends TestCase {
	use InsertsUsersTrait;
	use RecordsDeprecationsTrait;

	protected int $post;

	protected int $admin;

	#[Before]
	protected function create_post(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$this->admin = self::insert_user(
			array(
				'user_login'   => 'ic-comments-admin',
				'user_pass'    => 'password',
				'user_email'   => 'ic-comments-admin@example.org',
				'display_name' => 'Comments Admin',
				'role'         => 'administrator',
			)
		);
		wp_set_current_user( $this->admin );
		$this->post = (int) wp_insert_post(
			array(
				'post_title'  => 'Comments',
				'post_status' => 'publish',
			)
		);
	}

	#[After]
	protected function delete_post(): void {
		wp_delete_post( $this->post, true );
		wp_set_current_user( 0 );
		wp_delete_user( $this->admin );
	}

	public function test_an_internal_comment_keeps_v1s_type_under_its_own_status_by_its_author(): void {
		$id = dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => $this->post,
				'comment_content' => 'A back\\slashed note',
			)
		);

		$comment = get_comment( (int) $id );
		self::assertInstanceOf( \WP_Comment::class, $comment );
		self::assertSame( 'dws_internal', Comments::TYPE );
		self::assertSame( 'dws_internal', Comments::STATUS );
		self::assertSame( Comments::TYPE, $comment->comment_type );
		self::assertSame( Comments::STATUS, $comment->comment_approved );
		self::assertSame( 'A back\\slashed note', $comment->comment_content );
		self::assertSame( (string) $this->admin, $comment->user_id );
		self::assertSame( 'Comments Admin', $comment->comment_author );
		self::assertSame( 'ic-comments-admin@example.org', $comment->comment_author_email );
	}

	public function test_the_creation_data_passes_v1s_filter_then_the_v2_filter(): void {
		$seen = array();
		$v1   = static function ( array $data ) use ( &$seen ): array {
			$seen[] = 'v1';
			return array( 'comment_content' => 'v1' ) + $data;
		};
		$v2   = static function ( array $data ) use ( &$seen ): array {
			$seen[]                  = 'v2:' . $data['comment_content'];
			$data['comment_content'] = 'v2';
			return $data;
		};
		add_filter( 'internal_comments/create_comment_data', $v1 );
		add_filter( 'internal_comments/internal_comment/creation_data', $v2 );

		try {
			$id = dws_ic_create_internal_comment( array( 'comment_post_ID' => $this->post ) );
		} finally {
			remove_filter( 'internal_comments/create_comment_data', $v1 );
			remove_filter( 'internal_comments/internal_comment/creation_data', $v2 );
		}

		self::assertSame( array( 'v1', 'v2:v1' ), $seen );
		self::assertSame( 'v2', get_comment( (int) $id )?->comment_content );
		self::assertSame( array( array( 'internal_comments/create_comment_data', 'internal_comments/internal_comment/creation_data' ) ), $this->deprecated );
	}

	public function test_a_comment_reads_as_internal_by_its_type(): void {
		$internal = (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $this->post ) );
		$public   = (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) );

		self::assertTrue( dws_ic_is_internal_comment( $internal ) );
		self::assertTrue( dws_ic_is_internal_comment( get_comment( $internal ) ) );
		self::assertTrue( dws_ic_is_internal_comment( array( 'comment_ID' => $internal ) ) );
		self::assertFalse( dws_ic_is_internal_comment( $public ) );
		self::assertNull( dws_ic_is_internal_comment( 0 ) );
		self::assertNull( dws_ic_is_internal_comment( \PHP_INT_MAX ) );
		self::assertSame( $internal, (int) dws_ic_get_comment( (string) $internal )?->comment_ID );
		self::assertNull( dws_ic_get_comment( \PHP_INT_MAX ) );
	}

	public function test_a_post_supports_internal_comments_when_its_type_is_selected(): void {
		$stored = get_option( Options::POST_TYPES );
		update_option( Options::POST_TYPES, array( 'page' ) );

		try {
			self::assertFalse( dws_ic_is_selected_post_type( $this->post ) );
			update_option( Options::POST_TYPES, array( 'post' ) );
			self::assertTrue( dws_ic_is_selected_post_type( $this->post ) );
		} finally {
			update_option( Options::POST_TYPES, $stored );
		}
	}

	public function test_the_internal_comments_of_a_post_read_threaded_as_models(): void {
		$parent = (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $this->post ) );
		$reply  = (int) dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => $this->post,
				'comment_parent'  => $parent,
			)
		);
		wp_insert_comment( array( 'comment_post_ID' => $this->post ) );

		$comments = dws_ic_get_internal_comments( $this->post );

		self::assertCount( 1, $comments );
		self::assertInstanceOf( \DWS_Internal_Comment::class, $comments[0] );
		self::assertSame( (string) $parent, $comments[0]->comment_ID );
		self::assertSame( array( (string) $reply ), \array_map( static fn ( \WP_Comment $child ): string => $child->comment_ID, \array_values( $comments[0]->get_children() ) ) );
		self::assertCount( 2, dws_ic_get_internal_comments( $this->post, array( 'hierarchical' => false ) ) );
	}

	public function test_the_model_wraps_only_internal_comments(): void {
		$internal = (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $this->post ) );
		$public   = (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) );

		$model = new \DWS_Internal_Comment( $internal );

		self::assertSame( (string) $internal, $model->comment_ID );
		self::assertNull( $model->not_a_property );
		self::assertTrue( $model->is_author() );
		self::assertTrue( $model->can_see() );
		self::assertTrue( $model->can_edit() );
		self::assertTrue( $model->can_trash() );
		self::assertTrue( $model->can_edit_author() );
		self::assertStringContainsString( "c=$internal", (string) $model->get_edit_comment_link() );
		$this->expectException( \InvalidArgumentException::class );
		new \DWS_Internal_Comment( $public );
	}

	public function test_a_trashed_posts_internal_comments_read_from_its_trash_meta(): void {
		$internal = (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $this->post ) );
		wp_insert_comment( array( 'comment_post_ID' => $this->post ) );

		wp_trash_post( $this->post );

		self::assertSame( array( (string) $internal ), \array_map( static fn ( \DWS_Internal_Comment $comment ): string => $comment->comment_ID, \array_values( dws_ic_get_internal_comments_from_trash_meta( $this->post ) ) ) );

		$statuses              = get_post_meta( $this->post, '_wp_trash_meta_comments_status', true );
		$statuses[ $internal ] = '1'; // v1 stored internal comments approved.
		update_post_meta( $this->post, '_wp_trash_meta_comments_status', $statuses );
		self::assertCount( 1, dws_ic_get_internal_comments_from_trash_meta( $this->post ) );
	}

	public function test_a_comment_is_private_by_its_meta_or_any_ancestors(): void {
		$private = (int) dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => $this->post,
				'comment_meta'    => array( 'dws_ic_is_private' => 'yes' ),
			)
		);
		$reply   = (int) dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => $this->post,
				'comment_parent'  => $private,
			)
		);
		$open    = (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $this->post ) );

		self::assertTrue( dws_ic_is_private_comment( $private ) );
		self::assertTrue( dws_ic_is_private_comment( $reply ) );
		self::assertFalse( dws_ic_is_private_comment( $open ) );
		self::assertNull( dws_ic_is_private_comment( (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) ) ) );
	}

	// A threaded read fills each comment's replies from the query, so a reply the reader cannot see is left out of its parent's.
	public function test_a_threaded_read_leaves_out_the_replies_the_reader_cannot_see(): void {
		$open   = $this->comment();
		$hidden = $this->comment(
			array(
				'comment_parent' => $open,
				'comment_meta'   => array( Comments::META_PRIVATE => 'yes' ),
			)
		);
		$this->comment( array( 'comment_parent' => $hidden ) );
		$shown  = $this->comment( array( 'comment_parent' => $open ) );
		$editor = self::insert_user(
			array(
				'user_login' => 'ic-comments-editor',
				'user_pass'  => 'password',
				'user_email' => 'ic-comments-editor@example.org',
				'role'       => 'editor',
			)
		);
		$ids    = static fn ( array $comments ): array => \array_map( static fn ( object $comment ): string => $comment->comment_ID, \array_values( $comments ) );

		try {
			wp_set_current_user( $editor );
			$read = dws_ic_get_internal_comments( $this->post );
			$all  = dws_ic_get_internal_comments( $this->post, array(), true );
		} finally {
			wp_set_current_user( $this->admin );
			wp_delete_user( $editor );
		}

		self::assertSame( array( (string) $open ), $ids( $read ) );
		self::assertSame( array( (string) $shown ), $ids( $read[0]->get_children() ) );
		self::assertSame( array( (string) $hidden, (string) $shown ), $ids( $all[0]->get_children() ) );
	}

	public function test_trashing_an_internal_comment_trashes_its_replies_and_restoring_it_restores_them(): void {
		$parent = $this->comment();
		$reply  = $this->comment( array( 'comment_parent' => $parent ) );
		$nested = $this->comment( array( 'comment_parent' => $reply ) );
		$public = (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) );
		$answer = (int) wp_insert_comment(
			array(
				'comment_post_ID' => $this->post,
				'comment_parent'  => $public,
			)
		);
		$status = static fn ( int $comment ): ?string => get_comment( $comment )?->comment_approved;

		wp_trash_comment( $parent );
		wp_trash_comment( $public );
		$trashed = array( $status( $reply ), $status( $nested ), $status( $answer ) );
		wp_untrash_comment( $parent );

		self::assertSame( array( 'trash', 'trash', '1' ), $trashed );
		self::assertSame( array( Comments::STATUS, Comments::STATUS, Comments::STATUS ), array( $status( $parent ), $status( $reply ), $status( $nested ) ) );
	}

	// WordPress stores a comment's meta after the comment and reports no failure of it, so a private comment would otherwise be stored for every reader.
	public function test_a_comment_whose_private_flag_is_refused_is_not_stored(): void {
		$refuse = static fn ( mixed $check, int $comment, string $key ): mixed => Comments::META_PRIVATE === $key ? false : $check;
		add_filter( 'add_comment_metadata', $refuse, 10, 3 );

		try {
			$private = dws_ic_create_internal_comment(
				array(
					'comment_post_ID' => $this->post,
					'comment_content' => 'Meant private',
					'comment_meta'    => array( Comments::META_PRIVATE => 'yes' ),
				)
			);
			$public  = dws_ic_create_internal_comment(
				array(
					'comment_post_ID' => $this->post,
					'comment_content' => 'Meant public',
					'comment_meta'    => array( Comments::META_PRIVATE => 'no' ),
				)
			);
		} finally {
			remove_filter( 'add_comment_metadata', $refuse );
		}

		self::assertNull( $private );
		self::assertIsInt( $public );
		$stored = get_comments(
			array(
				'post_id' => $this->post,
				'type'    => Comments::TYPE,
				'status'  => 'any',
			)
		);
		self::assertSame( array( 'Meant public' ), wp_list_pluck( $stored, 'comment_content' ) );
	}

	public function test_the_model_returns_a_comments_earlier_versions_newest_first(): void {
		$comment = $this->comment( array( 'comment_content' => 'First' ) );
		$model   = new \DWS_Internal_Comment( $comment );
		self::assertSame( array(), $model->get_revisions() );

		foreach ( array( 'Second', 'Third' ) as $content ) {
			wp_update_comment(
				array(
					'comment_ID'      => $comment,
					'comment_content' => $content,
				)
			);
		}
		add_comment_meta( $comment, '_dws_ic_change_id', 'malformed' );
		$revisions = new \DWS_Internal_Comment( $comment )->get_revisions();

		self::assertSame( array( 'Second', 'First' ), \array_column( $revisions, 'content' ) );
		self::assertSame( array( $this->admin, $this->admin ), \array_column( $revisions, 'user_id' ) );
		foreach ( $revisions as $revision ) {
			self::assertIsInt( $revision['time'] );
			self::assertEqualsWithDelta( \time(), $revision['time'], 60 );
		}
	}

	// v1 led an internal comment's link to the meta box on its post's edit screen, where the comment shows.
	public function test_an_internal_comments_link_leads_to_its_posts_edit_screen(): void {
		$comment = $this->comment();
		$reply   = $this->comment( array( 'comment_parent' => $comment ) );
		$public  = (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) );
		$edit    = (string) get_edit_post_link( $this->post, 'url' );

		self::assertSame( "$edit#comment-$comment", get_comment_link( $comment ) );
		self::assertSame( "$edit#reply-$reply", get_comment_link( $reply ) );
		self::assertStringStartsWith( (string) get_permalink( $this->post ), get_comment_link( $public ) );
	}

	protected function comment( array $args = array() ): int {
		return (int) dws_ic_create_internal_comment( $args + array( 'comment_post_ID' => $this->post ) );
	}
}
