<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\ChildCreation;
use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\Notice;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\NoticeScope;
use DeepWebSolutions\LinkedOrders\Scoped\Psr\Log\NullLogger;
use DeepWebSolutions\LinkedOrders\Tests\Support\CreatesUsersTrait;
use DeepWebSolutions\LinkedOrders\Tests\Support\OrderStorageTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ChildCreation::class )]
final class ChildCreationTest extends TestCase {
	use CreatesUsersTrait;
	use OrderStorageTrait;

	protected const string ACTION = 'admin_post_dws_lowc_create_linked_child';

	protected AdminNotices $notices;

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
	}

	// The redirect ends the request with exit, which the test replaces with a throw.
	public function stop_at_redirect( string $location ): never {
		throw new \UnexpectedValueException( $location );
	}

	// A death throws no RuntimeException, so a request that redirected instead, through the redirect's exception, fails the row.
	public function get_die_handler(): callable {
		return static function ( mixed $message ): never {
			throw new class( \is_string( $message ) ? $message : 'wp_die' ) extends \LogicException {};
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

	public function test_a_request_without_a_valid_nonce_dies_without_a_child(): void {
		$parent                = $this->create_order();
		$_GET['parent_id']     = (string) $parent->get_id();
		$_REQUEST['parent_id'] = (string) $parent->get_id();
		$_REQUEST['_wpnonce']  = 'invalid';

		try {
			do_action( self::ACTION );
			self::fail( 'The request did not die.' );
		} catch ( \LogicException ) {
			self::assertSame( array(), dws_lowc_get_order_node( $parent->get_id() )?->get_children() );
		}
	}

	public function test_a_missing_parent_returns_to_the_referring_screen_with_a_notice(): void {
		$_SERVER['HTTP_REFERER'] = admin_url( 'edit.php?post_type=shop_order' );

		$location = $this->request( (string) \PHP_INT_MAX );

		self::assertSame( admin_url( 'edit.php?post_type=shop_order' ), $location );
		self::assertSame( array( 'There is no order ' . \PHP_INT_MAX . ' to create a linked child for.' ), $this->get_messages() );
	}

	#[DataProvider( 'storages' )]
	public function test_a_user_who_may_not_create_a_child_returns_to_the_parent_with_a_notice( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$parent = $this->create_order();
		$user   = $this->create_user( 'dws-lowc-child-creation-editor', 'editor' );
		wp_set_current_user( $user );

		$location = $this->request( (string) $parent->get_id() );

		self::assertSame( $parent->get_edit_order_url(), $location );
		self::assertSame( array( 'No linked child can be created for order #' . $parent->get_order_number() . '.' ), \array_map( static fn ( Notice $notice ): string => $notice->message, $this->notices->get_notices( NoticeScope::User, $user ) ) );
		self::assertSame( array(), dws_lowc_get_order_node( $parent->get_id() )?->get_children() );
	}

	#[DataProvider( 'storages' )]
	public function test_a_failed_creation_returns_to_the_parent_with_the_error( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$parent  = $this->create_order();
		$creator = static fn ( array $args ): array => array( 'create_function' => static fn (): \WP_Error => new \WP_Error( 'failed', 'The <b>store</b> is closed.' ) ) + $args;
		add_filter( 'dws_lowc/linked_order/creation_args', $creator );

		try {
			$location = $this->request( (string) $parent->get_id() );
		} finally {
			remove_filter( 'dws_lowc/linked_order/creation_args', $creator );
		}

		self::assertSame( $parent->get_edit_order_url(), $location );
		self::assertSame( array( 'The linked child of order #' . $parent->get_order_number() . ' could not be created: The &lt;b&gt;store&lt;/b&gt; is closed.' ), $this->get_messages() );
	}

	#[DataProvider( 'storages' )]
	public function test_an_order_at_the_maximum_depth_returns_with_the_setting_that_limits_it( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$parent = $this->create_order();
		$child  = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $parent->get_id(), $child->get_id() ) );

		$this->request( (string) $child->get_id() );

		self::assertSame( array( 'Order #' . $child->get_order_number() . ' is at the maximum linked orders depth, set under WooCommerce &gt; Settings &gt; Advanced &gt; Linked Orders.' ), $this->get_messages() );
	}

	#[DataProvider( 'storages' )]
	public function test_a_creator_that_returns_no_order_is_named_in_a_sentence( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$parent  = $this->create_order();
		$creator = static fn ( array $args ): array => array( 'create_function' => '__return_zero' ) + $args;
		add_filter( 'dws_lowc/linked_order/creation_args', $creator );
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		try {
			$this->request( (string) $parent->get_id() );
		} finally {
			remove_filter( 'dws_lowc/linked_order/creation_args', $creator );
			remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		}

		self::assertSame( array( 'The linked child of order #' . $parent->get_order_number() . ' could not be created: Its creator returned no order.' ), $this->get_messages() );
	}

	public function test_a_parent_id_that_is_not_a_whole_number_names_no_order(): void {
		$parent = $this->create_order();

		foreach ( array( '-' . $parent->get_id(), $parent->get_id() . 'abc' ) as $parent_id ) {
			$this->request( $parent_id );
		}

		$children = dws_lowc_get_order_node( $parent->get_id() )?->get_children() ?? array();
		foreach ( $children as $child ) {
			$this->orders[] = $child->get_order()->get_id();
		}
		self::assertSame( array(), $children );
		self::assertSame( array( 'There is no order 0 to create a linked child for.' ), $this->get_messages() );
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
