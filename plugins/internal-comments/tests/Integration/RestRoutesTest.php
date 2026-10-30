<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class RestRoutesTest extends TestCase {
	protected static array $users = array();

	protected int $post;

	protected int $page;

	protected mixed $stored;

	protected array $seen = array();

	// WordPress reports a deprecated hook only when something listens on it.
	protected const array V1_HOOKS = array(
		'internal_comments/ajax/before_insert_comment',
		'internal_comments/ajax/update_internal_comment_data',
		'internal_comments/ajax/before_update_comment',
		'internal_comments/ajax/before_trash_comment',
	);

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array( 'administrator', 'editor', 'author', 'subscriber' ) as $role ) {
			self::$users[ $role ] = (int) wp_insert_user(
				array(
					'user_login' => "ic-rest-$role",
					'user_pass'  => 'password',
					'user_email' => "ic-rest-$role@example.org",
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
	protected function create_posts(): void {
		$this->stored = get_option( DWS_IC_OPTION_GENERAL );
		update_option(
			DWS_IC_OPTION_GENERAL,
			array(
				DWS_IC_SETTING_POST_TYPES       => array( 'post' ),
				DWS_IC_SETTING_PRIVATE_COMMENTS => 'yes',
			)
		);
		foreach ( array( 'post', 'page' ) as $type ) {
			$this->$type = (int) wp_insert_post(
				array(
					'post_title'  => 'Rest',
					'post_type'   => $type,
					'post_status' => 'publish',
					'post_author' => self::$users['administrator'],
				)
			);
		}
		add_filter( 'deprecated_hook_trigger_error', '__return_false' );
		add_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ), 10, 2 );
		foreach ( self::V1_HOOKS as $hook ) {
			add_filter( $hook, array( $this, 'listen' ) );
		}
	}

	#[After]
	protected function delete_posts(): void {
		wp_delete_post( $this->post, true );
		wp_delete_post( $this->page, true );
		update_option( DWS_IC_OPTION_GENERAL, $this->stored );
		wp_set_current_user( 0 );
		remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		remove_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ) );
		foreach ( self::V1_HOOKS as $hook ) {
			remove_filter( $hook, array( $this, 'listen' ) );
		}
	}

	public function listen( mixed $value = null ): mixed {
		return $value;
	}

	public function record_deprecated_hook( string $hook ): void {
		$this->seen[] = "deprecated:$hook";
	}

	public function test_posting_a_comment_stores_its_text_and_answers_with_the_list(): void {
		$recorder = function ( int $post_id ): void {
			$this->seen[] = "before:$post_id";
		};
		add_action( 'dws_ic/internal_comment/before_insert', $recorder );

		try {
			$response = $this->create( 'administrator', $this->post, array( 'content' => "<b>Bold</b> line\nnext" ) );
		} finally {
			remove_action( 'dws_ic/internal_comment/before_insert', $recorder );
		}

		$comments = $this->stored_comments();
		self::assertSame( 201, $response->get_status() );
		self::assertSame( array( "Bold line\nnext" ), \array_column( $comments, 'comment_content' ) );
		self::assertStringContainsString( 'Bold line', $response->get_data()['html'] ?? '' );
		self::assertSame( array( 'deprecated:internal_comments/ajax/before_insert_comment', "before:{$this->post}" ), $this->seen );
	}

	public function test_a_reply_names_an_internal_comment_of_the_same_post(): void {
		$parent    = $this->comment( 'Parent' );
		$public    = (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) );
		$elsewhere = $this->comment( 'Elsewhere', array(), $this->page );

		self::assertSame( 201, $this->reply( $parent ) );
		self::assertSame( array( (string) $parent ), \array_column( \array_slice( $this->stored_comments(), 1 ), 'comment_parent' ) );
		foreach ( array( $public, $elsewhere, \PHP_INT_MAX ) as $invalid ) {
			self::assertSame( 400, $this->reply( $invalid ) );
		}
	}

	public function test_a_comment_is_private_only_where_the_site_allows_it(): void {
		$this->create( 'administrator', $this->post, array( 'content' => 'Private', 'private' => true ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- A request's two parameters.
		update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_POST_TYPES => array( 'post' ) ) );
		$this->create( 'administrator', $this->post, array( 'content' => 'Disabled', 'private' => true ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- A request's two parameters.

		$private = array();
		foreach ( $this->stored_comments() as $comment ) {
			$private[ $comment->comment_content ] = dws_ic_is_private_comment( $comment->comment_ID );
		}
		self::assertSame( array( 'Private' => true, 'Disabled' => false ), $private ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Two expected values.
	}

	public function test_posting_needs_the_capability_access_to_the_post_and_a_selected_post_type(): void {
		$status = fn ( string $role, int $post, string $content = 'Denied' ): int => $this->create( $role, $post, array( 'content' => $content ) )->get_status();

		self::assertSame( 401, $status( '', $this->post ) );
		self::assertSame( 403, $status( 'subscriber', $this->post ) );
		self::assertSame( 403, $status( 'author', $this->post ) );
		self::assertSame( 403, $status( 'administrator', $this->page ) );
		self::assertSame( 403, $status( 'administrator', \PHP_INT_MAX ) );
		self::assertSame( 400, $status( 'administrator', $this->post, " \n " ) );
		self::assertSame( array(), $this->stored_comments() );
	}

	public function test_the_list_shows_the_comments_the_user_can_see(): void {
		$open    = $this->comment( 'Open' );
		$private = $this->comment( 'Hidden', array( DWS_IC_META_PRIVATE => 'yes' ) );

		$response = $this->request( 'editor', 'GET', "/dws_ic/v1/posts/{$this->post}/comments" );

		self::assertSame( 200, $response->get_status() );
		self::assertStringContainsString( "comment-$open", $response->get_data()['html'] ?? '' );
		self::assertStringNotContainsString( 'Hidden', $response->get_data()['html'] ?? '' );
		self::assertStringNotContainsString( "comment-$private", $response->get_data()['html'] ?? '' );
		self::assertSame( 403, $this->request( 'subscriber', 'GET', "/dws_ic/v1/posts/{$this->post}/comments" )->get_status() );
	}

	public function test_editing_reads_and_replaces_the_text_of_a_comment_the_user_can_edit(): void {
		$comment = $this->comment( "Raw\ntext" );
		$extra   = static fn ( array $extra ): array => $extra + array(
			'comment_karma'   => 5,
			'comment_content' => 'Ignored',
		);
		$before  = function ( array $comment, array $data ): void {
			$this->seen[] = "before:{$data['comment_content']}";
		};
		add_filter( 'dws_ic/internal_comment/update_data', $extra );
		add_action( 'dws_ic/internal_comment/before_update', $before, 10, 2 );

		try {
			$read   = $this->request( 'administrator', 'GET', "/dws_ic/v1/comments/$comment" );
			$update = $this->request( 'administrator', 'PUT', "/dws_ic/v1/comments/$comment", array( 'content' => 'Changed' ) );
		} finally {
			remove_filter( 'dws_ic/internal_comment/update_data', $extra );
			remove_action( 'dws_ic/internal_comment/before_update', $before );
		}

		self::assertSame( array( 'id' => $comment, 'content' => "Raw\ntext" ), $read->get_data() ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- The response's two fields.
		self::assertSame( 200, $update->get_status() );
		self::assertSame( 'Changed', get_comment( $comment )?->comment_content );
		self::assertSame( array( 'deprecated:internal_comments/ajax/update_internal_comment_data', 'deprecated:internal_comments/ajax/before_update_comment', 'before:Changed' ), $this->seen );
		self::assertSame( 403, $this->request( 'editor', 'GET', "/dws_ic/v1/comments/$comment" )->get_status() );
		self::assertSame( 403, $this->request( 'editor', 'PUT', "/dws_ic/v1/comments/$comment", array( 'content' => 'Denied' ) )->get_status() );
		self::assertSame( 400, $this->request( 'administrator', 'PUT', "/dws_ic/v1/comments/$comment", array( 'content' => '' ) )->get_status() );
	}

	public function test_trashing_moves_a_comment_the_user_can_trash_to_the_trash(): void {
		$comment = $this->comment( 'Trashed' );
		$public  = (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) );

		self::assertSame( 403, $this->request( 'editor', 'DELETE', "/dws_ic/v1/comments/$comment" )->get_status() );
		self::assertSame( 403, $this->request( 'administrator', 'DELETE', "/dws_ic/v1/comments/$public" )->get_status() );
		$response = $this->request( 'administrator', 'DELETE', "/dws_ic/v1/comments/$comment" );

		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'trash', get_comment( $comment )?->comment_approved );
		self::assertStringNotContainsString( 'Trashed', $response->get_data()['html'] ?? '' );
		self::assertSame( array( 'deprecated:internal_comments/ajax/before_trash_comment' ), $this->seen );
	}

	protected function comment( string $content, array $meta = array(), int $post = 0 ): int {
		return (int) dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => 0 === $post ? $this->post : $post,
				'comment_content' => $content,
				'comment_meta'    => $meta,
			),
			get_user_by( 'id', self::$users['administrator'] ) ?: null
		);
	}

	protected function create( string $role, int $post, array $params ): \WP_REST_Response {
		return $this->request( $role, 'POST', "/dws_ic/v1/posts/$post/comments", $params );
	}

	protected function reply( int $parent_id ): int {
		return $this->create(
			'administrator',
			$this->post,
			array(
				'content' => 'Reply',
				'parent'  => $parent_id,
			)
		)->get_status();
	}

	protected function stored_comments(): array {
		return get_comments(
			array(
				'post_id' => $this->post,
				'type'    => DWS_IC_COMMENT_TYPE,
				'status'  => DWS_IC_COMMENT_STATUS,
				'orderby' => 'comment_ID',
				'order'   => 'ASC',
			)
		);
	}

	protected function request( string $role, string $method, string $route, array $params = array() ): \WP_REST_Response {
		wp_set_current_user( '' === $role ? 0 : self::$users[ $role ] );
		$request = new \WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}
}
