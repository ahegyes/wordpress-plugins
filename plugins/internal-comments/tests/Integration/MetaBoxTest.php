<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\MetaBox;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class MetaBoxTest extends TestCase {
	protected static array $users = array();

	protected int $post;

	protected int $page;

	protected mixed $stored;

	protected array $deprecated = array();

	// WordPress reports a deprecated hook only when something listens on it.
	protected const array V1_HOOKS = array(
		'internal_comments/metabox_output/comment_classes',
		'internal_comments/metabox_output/single_comment/actions/start',
		'internal_comments/metabox_output/single_comment/actions/end',
		'internal_comments/metabox_output/inline_actions',
		'internal_comments/metabox_output/inline_actions/insert',
		'internal_comments/metabox_output/inline_actions/edit',
		'internal_comments/metabox_output/reply',
	);

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		foreach ( array( 'administrator', 'editor', 'subscriber' ) as $role ) {
			self::$users[ $role ] = (int) wp_insert_user(
				array(
					'user_login' => "ic-meta-box-$role",
					'user_pass'  => 'password',
					'user_email' => "ic-meta-box-$role@example.org",
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
					'post_title'  => 'Meta box',
					'post_type'   => $type,
					'post_status' => 'publish',
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
		global $wp_meta_boxes;
		$wp_meta_boxes = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Each row registers the meta boxes anew.
		wp_delete_post( $this->post, true );
		wp_delete_post( $this->page, true );
		update_option( DWS_IC_OPTION_GENERAL, $this->stored );
		wp_set_current_user( 0 );
		wp_dequeue_script( 'internal-comments-meta-box' );
		wp_dequeue_style( 'internal-comments-meta-box' );
		remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		remove_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ) );
		foreach ( self::V1_HOOKS as $hook ) {
			remove_filter( $hook, array( $this, 'listen' ) );
		}
	}

	public function listen( mixed $value = null ): mixed {
		return $value;
	}

	public function record_deprecated_hook( string $hook, string $replacement ): void {
		$this->deprecated[] = array( $hook, $replacement );
	}

	public function test_the_meta_box_shows_on_a_selected_post_type_to_users_with_the_capability(): void {
		self::assertTrue( $this->registers( 'administrator', $this->post ) );
		self::assertFalse( $this->registers( 'subscriber', $this->post ) );
		self::assertFalse( $this->registers( 'administrator', $this->page ) );
	}

	public function test_the_meta_box_lists_the_comments_and_the_forms_the_user_may_use(): void {
		$comment = $this->comment( 'First' );

		$admin  = $this->render( 'administrator' );
		$editor = $this->render( 'editor' );

		self::assertSame( 'First', \trim( (string) $admin->querySelector( "#comment-$comment .dws-internal-comment__content" )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNotNull( $admin->querySelector( 'textarea#dws-ic-new-comment' ) );
		self::assertNotNull( $admin->querySelector( 'input[type="checkbox"]#dws-ic-new-comment-private' ) );
		self::assertNotNull( $admin->querySelector( 'template#dws-ic-edit-form' ) );
		self::assertNotNull( $admin->querySelector( 'template#dws-ic-reply-form' ) );
		self::assertNotNull( $admin->querySelector( '[role="status"]' ) );
		self::assertSame( array( 'edit', 'reply', 'trash' ), $this->actions( $admin, $comment ) );
		self::assertSame( array( 'reply' ), $this->actions( $editor, $comment ) );
		update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_POST_TYPES => array( 'post' ) ) );
		self::assertNull( $this->render( 'administrator' )->querySelector( '#dws-ic-new-comment-private' ) );
	}

	public function test_a_reply_the_user_cannot_see_is_left_out_of_its_thread(): void {
		$parent  = $this->comment( 'Open' );
		$private = $this->comment( 'Private reply', array( DWS_IC_META_PRIVATE => 'yes' ), $parent );

		self::assertNotNull( $this->render( 'administrator' )->querySelector( "#reply-$private" ) );
		self::assertNull( $this->render( 'editor' )->querySelector( "#reply-$private" ) );
	}

	public function test_the_markup_hooks_fire_v1s_names_first_then_v2s(): void {
		$comment = $this->comment( 'Hooked' );
		$classes = static fn ( array $classes ): array => \array_merge( $classes, array( 'from-v2' ) );
		$action  = static function ( \DWS_Internal_Comment $comment ): void {
			echo '<span class="from-action">' . \esc_html( (string) $comment->comment_ID ) . '</span>';
		};
		add_filter( 'dws_ic/meta_box/comment_classes', $classes );
		add_action( 'dws_ic/meta_box/before_comment_actions', $action );

		try {
			$document = $this->render( 'administrator' );
		} finally {
			remove_filter( 'dws_ic/meta_box/comment_classes', $classes );
			remove_action( 'dws_ic/meta_box/before_comment_actions', $action );
		}

		self::assertTrue( (bool) $document->querySelector( "#comment-$comment" )?->classList->contains( 'from-v2' ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNotNull( $document->querySelector( "#comment-$comment .from-action" ) );
		self::assertEqualsCanonicalizing(
			array(
				array( 'internal_comments/metabox_output/comment_classes', 'dws_ic/meta_box/comment_classes' ),
				array( 'internal_comments/metabox_output/single_comment/actions/start', 'dws_ic/meta_box/before_comment_actions' ),
				array( 'internal_comments/metabox_output/single_comment/actions/end', 'dws_ic/meta_box/after_comment_actions' ),
				array( 'internal_comments/metabox_output/inline_actions', 'dws_ic/meta_box/before_form_buttons' ),
				array( 'internal_comments/metabox_output/inline_actions/insert', 'dws_ic/meta_box/before_new_comment_buttons' ),
				array( 'internal_comments/metabox_output/inline_actions', 'dws_ic/meta_box/before_form_buttons' ),
				array( 'internal_comments/metabox_output/inline_actions/edit', 'dws_ic/meta_box/before_edit_comment_buttons' ),
				array( 'internal_comments/metabox_output/inline_actions', 'dws_ic/meta_box/before_form_buttons' ),
				array( 'internal_comments/metabox_output/reply', 'dws_ic/meta_box/before_reply_buttons' ),
			),
			$this->deprecated
		);
	}

	public function test_the_edit_screen_of_a_selected_post_loads_the_meta_boxs_assets(): void {
		$GLOBALS['post'] = get_post( $this->post ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The edit screen sets its post.
		wp_set_current_user( self::$users['administrator'] );

		try {
			( new MetaBox() )->enqueue_assets( 'post.php' );
		} finally {
			unset( $GLOBALS['post'] );
		}

		self::assertTrue( wp_script_is( 'internal-comments-meta-box' ) );
		self::assertTrue( wp_style_is( 'internal-comments-meta-box' ) );
	}

	protected function registers( string $role, int $post ): bool {
		global $wp_meta_boxes;
		$wp_meta_boxes = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Each check registers the meta boxes anew.
		wp_set_current_user( self::$users[ $role ] );
		$type = (string) get_post_type( $post );
		do_action( 'add_meta_boxes', $type, get_post( $post ) );

		return isset( $wp_meta_boxes[ $type ]['normal']['core']['dws-internal-comments'] );
	}

	protected function render( string $role ): \Dom\HTMLDocument {
		global $wp_meta_boxes;
		$this->registers( 'administrator', $this->post );
		wp_set_current_user( self::$users[ $role ] );
		$box = $wp_meta_boxes['post']['normal']['core']['dws-internal-comments'];
		\ob_start();
		\call_user_func( $box['callback'], get_post( $this->post ), $box );

		return \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . \ob_get_clean() . '</body></html>', \LIBXML_NOERROR );
	}

	protected function actions( \Dom\HTMLDocument $document, int $comment ): array {
		return \array_map( static fn ( \Dom\Element $button ): string => $button->getAttribute( 'data-action' ), \iterator_to_array( $document->querySelectorAll( "#comment-$comment button[data-action]" ) ) );
	}

	protected function comment( string $content, array $meta = array(), int $parent_id = 0 ): int {
		return (int) dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => $this->post,
				'comment_content' => $content,
				'comment_parent'  => $parent_id,
				'comment_meta'    => $meta,
			),
			get_user_by( 'id', self::$users['administrator'] ) ?: null
		);
	}
}
