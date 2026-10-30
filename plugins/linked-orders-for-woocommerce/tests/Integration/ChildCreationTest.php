<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\Notice;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\NoticeScope;
use DeepWebSolutions\LinkedOrders\Scoped\Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ChildCreationTest extends TestCase {
	use OrderStorage;

	protected const string ACTION = 'admin_post_dws_lowc_create_linked_child';

	protected AdminNotices $notices;

	protected int $user = 0;

	#[Before]
	protected function prepare_request(): void {
		wp_set_current_user( 1 );
		$this->notices = new AdminNotices( Plugin::PREFIX, new NullLogger() );
		add_filter( 'wp_redirect', array( $this, 'stop_at_redirect' ) );
		add_filter( 'wp_die_handler', array( $this, 'get_die_handler' ) );
	}

	#[After]
	protected function end_request(): void {
		foreach ( $this->notices->get_notices( NoticeScope::User, 1 ) as $notice ) {
			$this->notices->remove( $notice->id, NoticeScope::User, 1 );
		}
		remove_filter( 'wp_redirect', array( $this, 'stop_at_redirect' ) );
		remove_filter( 'wp_die_handler', array( $this, 'get_die_handler' ) );
		unset( $_GET['parent_id'], $_REQUEST['parent_id'], $_REQUEST['_wpnonce'], $_SERVER['HTTP_REFERER'] );
		wp_set_current_user( 0 );
		if ( 0 !== $this->user ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $this->user );
			$this->user = 0;
		}
	}

	// The redirect ends the request with exit, which the test replaces with a throw.
	public function stop_at_redirect( string $location ): never {
		throw new \UnexpectedValueException( $location );
	}

	public function get_die_handler(): callable {
		return static function ( mixed $message ): never {
			throw new \RuntimeException( \is_string( $message ) ? $message : 'wp_die' );
		};
	}

	#[DataProvider( 'storages' )]
	public function test_a_created_child_opens_in_its_editor( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$parent = $this->create_order();

		$location = $this->request( (string) $parent->get_id() );

		$children = dws_lowc_get_order_node( $parent->get_id() )?->get_children() ?? array();
		self::assertCount( 1, $children );
		$this->orders[] = $children[0]->get_order()->get_id();
		self::assertSame( $children[0]->get_order()->get_edit_order_url(), $location );
		self::assertSame( array(), $this->notices->get_notices( NoticeScope::User, 1 ) );
	}

	public function test_a_request_without_a_valid_nonce_dies(): void {
		$parent_id             = (string) $this->create_order()->get_id();
		$_GET['parent_id']     = $parent_id;
		$_REQUEST['parent_id'] = $parent_id;
		$_REQUEST['_wpnonce']  = 'invalid';

		$this->expectException( \RuntimeException::class );

		do_action( self::ACTION );
	}

	public function test_a_missing_parent_returns_to_the_referring_screen_with_a_notice(): void {
		$_SERVER['HTTP_REFERER'] = admin_url( 'edit.php?post_type=shop_order' );

		$location = $this->request( (string) \PHP_INT_MAX );

		self::assertSame( admin_url( 'edit.php?post_type=shop_order' ), $location );
		self::assertSame( array( 'There is no order ' . \PHP_INT_MAX . ' to create a linked child for.' ), $this->get_messages() );
	}

	public function test_a_user_who_may_not_create_a_child_returns_to_the_parent_with_a_notice(): void {
		$parent     = $this->create_order();
		$this->user = wp_insert_user(
			array(
				'user_login' => 'dws-lowc-child-creation-editor',
				'user_pass'  => wp_generate_password(),
				'role'       => 'editor',
			)
		);
		self::assertIsInt( $this->user );
		wp_set_current_user( $this->user );

		$location = $this->request( (string) $parent->get_id() );

		self::assertSame( $parent->get_edit_order_url(), $location );
		self::assertSame( array( 'You may not create a linked child for order ' . $parent->get_id() . '.' ), \array_map( static fn ( Notice $notice ): string => $notice->message, $this->notices->get_notices( NoticeScope::User, $this->user ) ) );
		self::assertSame( array(), dws_lowc_get_order_node( $parent->get_id() )?->get_children() );
	}

	public function test_a_failed_creation_returns_to_the_parent_with_the_error(): void {
		$parent  = $this->create_order();
		$creator = static fn ( array $args ): array => array( 'create_function' => static fn (): \WP_Error => new \WP_Error( 'failed', 'The <b>store</b> is closed.' ) ) + $args;
		add_filter( 'dws_lowc/linked_order/creation_args', $creator );

		try {
			$location = $this->request( (string) $parent->get_id() );
		} finally {
			remove_filter( 'dws_lowc/linked_order/creation_args', $creator );
		}

		self::assertSame( $parent->get_edit_order_url(), $location );
		self::assertSame( array( 'The linked child of order ' . $parent->get_id() . ' could not be created: The &lt;b&gt;store&lt;/b&gt; is closed.' ), $this->get_messages() );
	}

	protected function request( string $parent_id ): string {
		$_GET['parent_id']     = $parent_id;
		$_REQUEST['parent_id'] = $parent_id;
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'dws_create_linked_child' );

		try {
			do_action( self::ACTION );
		} catch ( \UnexpectedValueException $redirect ) {
			return $redirect->getMessage();
		}

		self::fail( 'The request did not redirect.' );
	}

	protected function get_messages(): array {
		return \array_map( static fn ( Notice $notice ): string => $notice->message, $this->notices->get_notices( NoticeScope::User, 1 ) );
	}
}
