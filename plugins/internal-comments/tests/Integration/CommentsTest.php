<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CommentsTest extends TestCase {
	protected int $post;

	protected int $admin;

	protected array $deprecated = array();

	#[Before]
	protected function create_post(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$this->admin = (int) wp_insert_user(
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
		add_filter( 'deprecated_hook_trigger_error', '__return_false' );
		add_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ), 10, 2 );
	}

	#[After]
	protected function delete_post(): void {
		wp_delete_post( $this->post, true );
		wp_set_current_user( 0 );
		wp_delete_user( $this->admin );
		remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		remove_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ) );
	}

	public function record_deprecated_hook( string $hook, string $replacement ): void {
		$this->deprecated[] = array( $hook, $replacement );
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
		self::assertSame( 'dws_internal', DWS_IC_COMMENT_TYPE );
		self::assertSame( 'dws_internal', DWS_IC_COMMENT_STATUS );
		self::assertSame( DWS_IC_COMMENT_TYPE, $comment->comment_type );
		self::assertSame( DWS_IC_COMMENT_STATUS, $comment->comment_approved );
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
		add_filter( 'dws_ic/internal_comment/creation_data', $v2 );

		try {
			$id = dws_ic_create_internal_comment( array( 'comment_post_ID' => $this->post ) );
		} finally {
			remove_filter( 'internal_comments/create_comment_data', $v1 );
			remove_filter( 'dws_ic/internal_comment/creation_data', $v2 );
		}

		self::assertSame( array( 'v1', 'v2:v1' ), $seen );
		self::assertSame( 'v2', get_comment( (int) $id )?->comment_content );
		self::assertSame( array( array( 'internal_comments/create_comment_data', 'dws_ic/internal_comment/creation_data' ) ), $this->deprecated );
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
		$stored = get_option( DWS_IC_OPTION_GENERAL );
		update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_POST_TYPES => array( 'page' ) ) );

		try {
			self::assertFalse( dws_ic_is_selected_post_type( $this->post ) );
			update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_POST_TYPES => array( 'post' ) ) );
			self::assertTrue( dws_ic_is_selected_post_type( $this->post ) );
		} finally {
			update_option( DWS_IC_OPTION_GENERAL, $stored );
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
}
