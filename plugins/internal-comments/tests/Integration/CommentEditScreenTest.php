<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\CommentEditScreen;
use DeepWebSolutions\InternalComments\Comments;
use DeepWebSolutions\InternalComments\Options;
use DeepWebSolutions\InternalComments\Tests\Support\InsertsUsersTrait;
use DeepWebSolutions\InternalComments\Tests\Support\RecordsDeprecationsTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( CommentEditScreen::class )]
final class CommentEditScreenTest extends TestCase {
	use InsertsUsersTrait;
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
			self::$users[ $role ] = self::insert_user(
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

	public function test_the_edit_screen_of_an_internal_comment_shows_its_revisions_and_its_author_its_privacy(): void {
		global $wp_meta_boxes;
		update_option( Options::POST_TYPES, array( 'post' ) );
		update_option( Options::PRIVATE_COMMENTS, 'yes' );
		$comment = $this->comment( 'Before' );
		wp_update_comment(
			array(
				'comment_ID'      => $comment,
				'comment_content' => 'After',
			)
		);
		$screens = new CommentEditScreen();

		$wp_meta_boxes = array();
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
		$private = $this->comment( 'Private', array( Comments::META_PRIVATE => 'yes' ) );
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
		$screens = new CommentEditScreen();
		$styles  = static function ( int $comment ) use ( $screens ): string {
			$GLOBALS['comment'] = get_comment( $comment );
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
		// WordPress 7.1's parent control offers only parents that saving an internal comment refuses.
		foreach ( array( '#namediv', '#comment-status', '.misc-pub-curtime', '.misc-pub-reply-to' ) as $selector ) {
			self::assertStringContainsString( $selector, $internal );
		}
		self::assertSame( '', $styles( (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) ) ) );
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
