<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Capabilities;
use DeepWebSolutions\InternalComments\Comments;
use DeepWebSolutions\InternalComments\MetaBox;
use DeepWebSolutions\InternalComments\Options;
use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Asset\Assets;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\InternalComments\Tests\Support\InsertsUsersTrait;
use DeepWebSolutions\InternalComments\Tests\Support\RecordsDeprecationsTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( MetaBox::class )]
final class MetaBoxTest extends TestCase {
	use InsertsUsersTrait;
	use RecordsDeprecationsTrait;

	protected static array $users = array();

	protected int $post;

	protected int $page;

	protected array $stored = array();

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
			self::$users[ $role ] = self::insert_user(
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
		foreach ( array( Options::POST_TYPES, Options::PRIVATE_COMMENTS ) as $option ) {
			$this->stored[ $option ] = get_option( $option );
		}
		update_option( Options::POST_TYPES, array( 'post' ) );
		update_option( Options::PRIVATE_COMMENTS, 'yes' );
		foreach ( array( 'post', 'page' ) as $type ) {
			$this->$type = (int) wp_insert_post(
				array(
					'post_title'  => 'Meta box',
					'post_type'   => $type,
					'post_status' => 'publish',
				)
			);
		}
		$this->listen_on( ...self::V1_HOOKS );
	}

	#[After]
	protected function delete_posts(): void {
		global $wp_meta_boxes;
		$wp_meta_boxes = array();
		wp_delete_post( $this->post, true );
		wp_delete_post( $this->page, true );
		foreach ( $this->stored as $option => $value ) {
			update_option( $option, $value );
		}
		wp_set_current_user( 0 );
		wp_dequeue_script( 'internal-comments-meta-box' );
		wp_dequeue_style( 'internal-comments-meta-box' );
	}

	public function test_the_meta_box_shows_on_a_selected_post_type_to_users_with_the_capability(): void {
		self::assertTrue( $this->registers( 'administrator', $this->post ) );
		self::assertFalse( $this->registers( 'subscriber', $this->post ) );
		self::assertFalse( $this->registers( 'administrator', $this->page ) );
	}

	// v1 showed the meta box only to users who may see internal comments.
	public function test_the_meta_box_needs_the_capability_to_see_internal_comments(): void {
		get_role( 'editor' )?->remove_cap( Capabilities::SEE );
		wp_set_current_user( self::$users['editor'] );
		wp_get_current_user()->get_role_caps();

		try {
			$without = $this->registers( 'editor', $this->post );
		} finally {
			get_role( 'editor' )?->add_cap( Capabilities::SEE );
			wp_get_current_user()->get_role_caps();
		}

		self::assertFalse( $without );
		self::assertTrue( $this->registers( 'editor', $this->post ) );
	}

	// The meta box's own capability and the capability to post each refuse on their own, for a role that keeps the rest.
	public function test_the_meta_box_and_its_form_each_need_their_own_capability(): void {
		$role = get_role( 'editor' );
		$role?->remove_cap( Capabilities::SEE_METABOX );
		wp_set_current_user( self::$users['editor'] );
		wp_get_current_user()->get_role_caps();
		try {
			$boxed = $this->registers( 'editor', $this->post );
		} finally {
			$role?->add_cap( Capabilities::SEE_METABOX );
			wp_get_current_user()->get_role_caps();
		}

		$role?->remove_cap( Capabilities::INSERT );
		wp_get_current_user()->get_role_caps();
		try {
			$form = $this->render( 'editor' )->querySelector( 'textarea#dws-ic-new-comment' );
		} finally {
			$role?->add_cap( Capabilities::INSERT );
			wp_get_current_user()->get_role_caps();
		}

		self::assertFalse( $boxed );
		self::assertNull( $form );
		self::assertNotNull( $this->render( 'editor' )->querySelector( 'textarea#dws-ic-new-comment' ) );
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
		self::assertNotNull( $admin->querySelector( '.dws-internal-comments__status' ) );
		// The edit screen submits every named field inside the post's form, and WordPress saves `content` as the post's body.
		self::assertNull( $admin->querySelector( '.dws-internal-comments [name]' ) );
		self::assertSame( '-1', $admin->querySelector( '.dws-internal-comments' )?->getAttribute( 'tabindex' ) );
		self::assertSame( array( 'edit', 'reply', 'trash' ), $this->actions( $admin, $comment ) );
		self::assertSame( array( 'reply' ), $this->actions( $editor, $comment ) );
		update_option( Options::PRIVATE_COMMENTS, 'no' );
		self::assertNull( $this->render( 'administrator' )->querySelector( '#dws-ic-new-comment-private' ) );
	}

	// The byline reads as one sentence in every language, and each action names its comment, since a list holds many of each.
	public function test_a_comment_reads_as_one_byline_and_its_actions_name_it(): void {
		$comment = $this->comment( 'Named' );
		$author  = get_comment_author( $comment );
		$when    = get_comment_date( '', $comment ) . ' at ' . get_comment_time( '', false, true, $comment );

		$document = $this->render( 'administrator' );
		$labels   = array();
		foreach ( $document->querySelectorAll( "#comment-$comment button[data-action]" ) as $button ) {
			$labels[ $button->getAttribute( 'data-action' ) ] = $button->getAttribute( 'aria-label' );
		}

		self::assertSame( "Added on $when by $author", \trim( (string) \preg_replace( '/\s+/', ' ', (string) $document->querySelector( "#comment-$comment .dws-internal-comment__meta" )?->textContent ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNotNull( $document->querySelector( "#comment-$comment .dws-internal-comment__meta a[href]" ) );
		self::assertSame(
			array(
				'edit'  => "Edit internal comment by $author from $when",
				'reply' => "Reply to internal comment by $author from $when",
				'trash' => "Move to Trash: internal comment by $author from $when",
			),
			$labels
		);
	}

	// A reply belongs to its parent's list item, so assistive technology reads the thread as nested.
	public function test_replies_nest_inside_their_parents_item(): void {
		$parent = $this->comment( 'Parent' );
		$reply  = $this->comment( 'Reply', array(), $parent );
		$nested = $this->comment( 'Nested', array(), $reply );

		$document = $this->render( 'administrator' );

		self::assertNotNull( $document->querySelector( "#comment-$parent > ul#replies-comment-$parent > #reply-$reply > ul#replies-comment-$reply > #reply-$nested" ) );
		self::assertCount( 1, $document->querySelectorAll( '.dws-internal-comments__list > li' ) );
	}

	public function test_a_post_without_comments_says_so_in_one_sentence(): void {
		self::assertSame( 'No internal comments yet.', \trim( (string) $this->render( 'administrator' )->querySelector( '.dws-internal-comments__empty' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
	}

	public function test_a_reply_the_user_cannot_see_is_left_out_of_its_thread(): void {
		$parent  = $this->comment( 'Open' );
		$private = $this->comment( 'Private reply', array( Comments::META_PRIVATE => 'yes' ), $parent );

		self::assertNotNull( $this->render( 'administrator' )->querySelector( "#reply-$private" ) );
		self::assertNull( $this->render( 'editor' )->querySelector( "#reply-$private" ) );
	}

	public function test_the_markup_hooks_fire_v1s_names_first_then_v2s(): void {
		$comment = $this->comment( 'Hooked' );
		$classes = static fn ( array $classes ): array => \array_merge( $classes, array( 'from-v2' ) );
		$action  = static function ( \DWS_Internal_Comment $comment ): void {
			echo '<span class="from-action">' . esc_html( (string) $comment->comment_ID ) . '</span>';
		};
		add_filter( 'internal_comments/meta_box/comment_classes', $classes );
		add_action( 'internal_comments/meta_box/before_comment_actions', $action );

		try {
			$document = $this->render( 'administrator' );
		} finally {
			remove_filter( 'internal_comments/meta_box/comment_classes', $classes );
			remove_action( 'internal_comments/meta_box/before_comment_actions', $action );
		}

		self::assertTrue( (bool) $document->querySelector( "#comment-$comment" )?->classList->contains( 'from-v2' ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNotNull( $document->querySelector( "#comment-$comment .from-action" ) );
		self::assertEqualsCanonicalizing(
			array(
				array( 'internal_comments/metabox_output/comment_classes', 'internal_comments/meta_box/comment_classes' ),
				array( 'internal_comments/metabox_output/single_comment/actions/start', 'internal_comments/meta_box/before_comment_actions' ),
				array( 'internal_comments/metabox_output/single_comment/actions/end', 'internal_comments/meta_box/after_comment_actions' ),
				array( 'internal_comments/metabox_output/inline_actions', 'internal_comments/meta_box/before_form_buttons' ),
				array( 'internal_comments/metabox_output/inline_actions/insert', 'internal_comments/meta_box/before_new_comment_buttons' ),
				array( 'internal_comments/metabox_output/inline_actions', 'internal_comments/meta_box/before_form_buttons' ),
				array( 'internal_comments/metabox_output/inline_actions/edit', 'internal_comments/meta_box/before_edit_comment_buttons' ),
				array( 'internal_comments/metabox_output/inline_actions', 'internal_comments/meta_box/before_form_buttons' ),
				array( 'internal_comments/metabox_output/reply', 'internal_comments/meta_box/before_reply_buttons' ),
			),
			$this->deprecated
		);
	}

	// Each v1 name runs before its v2 name, and the v2 filter receives what the v1 filter returned.
	public function test_each_markup_hook_fires_its_v1_name_first_and_passes_its_value_on(): void {
		$this->comment( 'Ordered' );
		$events = array();
		$record = static function ( string $event ) use ( &$events ): \Closure {
			return static function () use ( &$events, $event ): void {
				$events[] = $event;
			};
		};
		$v1     = static function ( array $extra ) use ( &$events ): array {
			$events[] = 'v1 classes';
			return array( ...$extra, 'from-v1' );
		};
		$v2     = static function ( array $classes ) use ( &$events ): array {
			$events[] = \in_array( 'from-v1', $classes, true ) ? 'v2 classes after v1' : 'v2 classes alone';
			return $classes;
		};
		$hooks  = array(
			'internal_comments/metabox_output/comment_classes' => $v1,
			'internal_comments/meta_box/comment_classes' => $v2,
			'internal_comments/metabox_output/single_comment/actions/start' => $record( 'v1 start' ),
			'internal_comments/meta_box/before_comment_actions' => $record( 'v2 start' ),
			'internal_comments/metabox_output/single_comment/actions/end' => $record( 'v1 end' ),
			'internal_comments/meta_box/after_comment_actions' => $record( 'v2 end' ),
		);
		foreach ( $hooks as $hook => $callback ) {
			add_filter( $hook, $callback );
		}

		try {
			$this->render( 'administrator' );
		} finally {
			foreach ( $hooks as $hook => $callback ) {
				remove_filter( $hook, $callback );
			}
		}

		self::assertSame( array( 'v1 classes', 'v2 classes after v1', 'v1 start', 'v2 start', 'v1 end', 'v2 end' ), $events );
	}

	public function test_the_edit_screen_of_a_selected_post_loads_the_meta_boxs_assets(): void {
		$GLOBALS['post'] = get_post( $this->post );
		wp_set_current_user( self::$users['administrator'] );

		try {
			new MetaBox( self::make_assets() )->enqueue_assets( 'post.php' );
		} finally {
			unset( $GLOBALS['post'] );
		}

		self::assertTrue( wp_script_is( 'internal-comments-meta-box' ) );
		self::assertTrue( wp_style_is( 'internal-comments-meta-box' ) );
	}

	protected function registers( string $role, int $post ): bool {
		global $wp_meta_boxes;
		$wp_meta_boxes = array();
		wp_set_current_user( self::$users[ $role ] );
		$type = (string) get_post_type( $post );
		do_action( 'add_meta_boxes', $type, get_post( $post ) );
		return isset( $wp_meta_boxes[ $type ]['normal']['core'][ MetaBox::ID ] );
	}

	protected function render( string $role ): \Dom\HTMLDocument {
		global $wp_meta_boxes;
		$this->registers( 'administrator', $this->post );
		wp_set_current_user( self::$users[ $role ] );
		$box = $wp_meta_boxes['post']['normal']['core'][ MetaBox::ID ];
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

	protected static function make_assets(): Assets {
		return new Assets( new PluginIdentity( DWS_IC_FILE, Plugin::SLUG, Plugin::PREFIX ) );
	}
}
