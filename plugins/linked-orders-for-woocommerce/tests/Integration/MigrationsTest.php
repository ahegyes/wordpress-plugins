<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Linking;
use DeepWebSolutions\LinkedOrders\LinkRepair;
use DeepWebSolutions\LinkedOrders\Migrations;
use DeepWebSolutions\LinkedOrders\Options;
use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\LinkedOrders\Tests\Support\OrderStorageTrait;
use DeepWebSolutions\LinkedOrders\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LinkedOrders\Tests\Support\StoresLinksTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class MigrationsTest extends TestCase {
	use OrderStorageTrait;
	use RestoresOptionsTrait;
	use StoresLinksTrait;

	protected const array OPTIONS = array( Options::MAX_DEPTH, Options::AUTOCOMPLETE, Options::DELETE_DATA );

	#[After]
	protected function restore_the_migrations(): void {
		update_option( Plugin::PREFIX . '_revision', \count( Migrations::get_steps() ) );
		delete_option( 'dws_lowc_link_repair_page' );
	}

	// The runner's time budget lasts for its request, and this process's bootstrap spends it when it migrates a new site, so the row runs as a request of its own.
	#[RunInSeparateProcess]
	public function test_the_runner_brings_a_new_site_to_revision_one_with_every_default_autoloaded(): void {
		global $wpdb;
		self::assertEqualsCanonicalizing( \array_keys( Options::DEFAULTS ), self::OPTIONS, 'OPTIONS lists every key in Options::DEFAULTS.' );
		foreach ( self::OPTIONS as $key ) {
			delete_option( $key );
		}
		update_option( Plugin::PREFIX . '_revision', 0 );

		dws_lowc_plugin()->migrations?->run();

		self::assertSame( 1, MigrationRunner::get_revision( Plugin::PREFIX . '_revision' ) );
		foreach ( Options::DEFAULTS as $key => $default ) {
			self::assertSame( $default, get_option( $key ) );
			self::assertSame( 'on', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $key ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the stored autoload flag.
		}
	}

	public function test_the_first_step_keeps_the_values_a_v1_site_stored(): void {
		update_option( Options::MAX_DEPTH, '3' );
		update_option( Options::AUTOCOMPLETE, 'yes' );
		update_option( Options::DELETE_DATA, 'yes' );

		Migrations::get_steps()[0]();

		self::assertSame( '3', get_option( Options::MAX_DEPTH ) );
		self::assertSame( 'yes', get_option( Options::AUTOCOMPLETE ) );
		self::assertSame( 'yes', get_option( Options::DELETE_DATA ) );
	}

	public function test_the_first_step_throws_when_a_default_cannot_be_stored(): void {
		global $wpdb;
		delete_option( Options::DELETE_DATA );
		$fail       = static fn ( string $query ): string => \str_contains( $query, "'" . Options::DELETE_DATA . "'" ) && \str_starts_with( \ltrim( $query ), 'INSERT' ) ? "SELECT * FROM {$wpdb->prefix}dws_lowc_missing" : $query;
		$suppressed = $wpdb->suppress_errors();
		add_filter( 'query', $fail );

		try {
			$this->expectExceptionObject( new \RuntimeException( 'The option ' . Options::DELETE_DATA . ' could not be stored.' ) );
			Migrations::get_steps()[0]();
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $suppressed );
		}
	}

	// v1 deleted an order without touching its children, and could save a parent's children without the child it had just linked.
	#[DataProvider( 'storages' )]
	public function test_the_second_step_roots_orphans_and_lists_each_child_among_its_parents_children( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$deleted    = $this->create_order();
		$orphan     = $this->create_order();
		$descendant = $this->create_order();
		$parent     = $this->create_order();
		$listed     = $this->create_order();
		$unlisted   = $this->create_order();
		self::store_links( $orphan, 1, $deleted->get_id(), array( $descendant->get_id() ) );
		self::store_links( $descendant, 2, $orphan->get_id(), array() );
		self::store_links( $parent, 0, 0, array( $listed->get_id() ) );
		self::store_links( $listed, 1, $parent->get_id(), array() );
		self::store_links( $unlisted, 1, $parent->get_id(), array() );
		$deleted->delete( true );

		self::assertInstanceOf( LinkRepair::class, Migrations::get_steps()[1] ?? null );
		$batches = 0;
		while ( new LinkRepair()->process_batch() ) {
			self::assertLessThan( 10000, ++$batches );
		}

		self::assertSame( '0', wc_get_order( $orphan->get_id() )->get_meta( '_dws_lo_depth' ) );
		self::assertSame( '', wc_get_order( $orphan->get_id() )->get_meta( '_dws_lo_parent' ) );
		self::assertSame( '1', wc_get_order( $descendant->get_id() )->get_meta( '_dws_lo_depth' ) );
		self::assertSame( array( $listed->get_id(), $unlisted->get_id() ), wc_get_order( $parent->get_id() )->get_meta( '_dws_lo_children' ) );
		self::assertSame( '1', wc_get_order( $unlisted->get_id() )->get_meta( '_dws_lo_depth' ) );
		self::assertSame( (string) $parent->get_id(), wc_get_order( $unlisted->get_id() )->get_meta( '_dws_lo_parent' ) );
		self::assertFalse( get_option( 'dws_lowc_link_repair_page' ) );
	}

	// The runner's hook stays live when the kernel's boot throws, so a batch in such a request fails, leaving the walk's position and the linking lock as they were, and the walk resumes from that position once the plugin has booted; a batch holds 50 orders, so the orphan after 50 roots sits on the page the position names.
	#[DataProvider( 'storages' )]
	public function test_the_second_step_waits_for_the_plugin_to_boot( bool $hpos, bool $sync ): void {
		global $wp_actions;

		$this->use_storage( $hpos, $sync );
		for ( $i = 0; $i < 50; $i++ ) {
			self::store_links( $this->create_order(), 0, 0, array() );
		}

		$deleted = $this->create_order();
		$orphan  = $this->create_order();
		self::store_links( $orphan, 1, $deleted->get_id(), array() );
		$deleted->delete( true );
		update_option( 'dws_lowc_link_repair_page', '2', false );
		self::assertGreaterThan( 0, did_action( 'linked_orders_for_woocommerce/boot/succeeded' ) );
		$fired = $wp_actions['linked_orders_for_woocommerce/boot/succeeded'];
		unset( $wp_actions['linked_orders_for_woocommerce/boot/succeeded'] );

		$refusal = null;
		try {
			new LinkRepair()->process_batch();
		} catch ( \RuntimeException $exception ) {
			$refusal = $exception->getMessage();
		} finally {
			$wp_actions['linked_orders_for_woocommerce/boot/succeeded'] = $fired;
		}

		self::assertSame( 'The repair of the links waits for the plugin to boot.', $refusal );
		self::assertSame( '2', get_option( 'dws_lowc_link_repair_page' ) );
		$owner = wp_generate_uuid4();
		$lock  = Linking::acquire_lock( $owner );
		self::assertNotNull( $lock );
		$lock->release( $owner );

		$batches = 0;
		while ( new LinkRepair()->process_batch() ) {
			self::assertLessThan( 5, ++$batches );
		}

		self::assertSame( 0, $batches );
		self::assertSame( '0', wc_get_order( $orphan->get_id() )->get_meta( '_dws_lo_depth' ) );
		self::assertFalse( get_option( 'dws_lowc_link_repair_page' ) );
	}

	// A batch holds 50 orders, so the orphan after 50 roots waits on the second page.
	#[DataProvider( 'storages' )]
	public function test_the_second_step_walks_past_its_first_page( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		for ( $i = 0; $i < 50; $i++ ) {
			self::store_links( $this->create_order(), 0, 0, array() );
		}

		$deleted = $this->create_order();
		$orphan  = $this->create_order();
		self::store_links( $orphan, 1, $deleted->get_id(), array() );
		$deleted->delete( true );

		$batches = 0;
		while ( new LinkRepair()->process_batch() ) {
			self::assertLessThan( 5, ++$batches );
		}

		self::assertSame( '0', wc_get_order( $orphan->get_id() )->get_meta( '_dws_lo_depth' ) );
	}

	// v1 could lose a child from the children of an order it later orphaned, and a request killed while it rewrites a tree's depths leaves some unwritten.
	#[DataProvider( 'storages' )]
	public function test_the_second_step_stores_each_child_one_level_below_its_parent( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$deleted    = $this->create_order();
		$orphan     = $this->create_order();
		$lost       = $this->create_order();
		$root       = $this->create_order();
		$stale      = $this->create_order();
		$grandchild = $this->create_order();
		self::store_links( $orphan, 1, $deleted->get_id(), array() );
		self::store_links( $lost, 2, $orphan->get_id(), array() );
		self::store_links( $root, 0, 0, array( $stale->get_id() ) );
		self::store_links( $stale, 3, $root->get_id(), array( $grandchild->get_id() ) );
		self::store_links( $grandchild, 4, $stale->get_id(), array() );
		$deleted->delete( true );

		$batches = 0;
		while ( new LinkRepair()->process_batch() ) {
			self::assertLessThan( 10000, ++$batches );
		}

		self::assertSame( array( $lost->get_id() ), wc_get_order( $orphan->get_id() )->get_meta( '_dws_lo_children' ) );
		self::assertSame( array( '0', '1', '1', '2' ), \array_map( static fn ( \WC_Order $order ): string => (string) wc_get_order( $order->get_id() )->get_meta( '_dws_lo_depth' ), array( $orphan, $lost, $stale, $grandchild ) ) );
	}
}
