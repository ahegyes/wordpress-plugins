<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Migration\BatchedMigrationStepInterface;
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

	protected const array OPTIONS = array( DWS_LOWC_OPTION_MAX_DEPTH, DWS_LOWC_OPTION_AUTOCOMPLETE, DWS_LOWC_OPTION_DELETE_DATA );

	#[After]
	protected function restore_the_revision(): void {
		update_option( Plugin::PREFIX . '_revision', \count( dws_lowc_get_migration_steps() ) );
	}

	// The runner's time budget lasts for its request, and this process's bootstrap spends it when it migrates a new site, so the row runs as a request of its own.
	#[RunInSeparateProcess]
	public function test_the_runner_brings_a_new_site_to_revision_one_with_the_untranslated_defaults_autoloaded(): void {
		global $wpdb;
		foreach ( self::OPTIONS as $key ) {
			delete_option( $key );
		}
		update_option( Plugin::PREFIX . '_revision', 0 );

		dws_lowc_plugin()->migrations?->run();

		self::assertSame( 1, MigrationRunner::get_revision( Plugin::PREFIX . '_revision' ) );
		self::assertSame( '1', get_option( DWS_LOWC_OPTION_MAX_DEPTH ) );
		self::assertSame( 'no', get_option( DWS_LOWC_OPTION_AUTOCOMPLETE ) );
		self::assertSame( 'no', get_option( DWS_LOWC_OPTION_DELETE_DATA ) );
		foreach ( self::OPTIONS as $key ) {
			self::assertSame( 'on', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $key ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the stored autoload flag.
		}
	}

	public function test_the_first_step_keeps_the_values_a_v1_site_stored(): void {
		update_option( DWS_LOWC_OPTION_MAX_DEPTH, '3' );
		update_option( DWS_LOWC_OPTION_AUTOCOMPLETE, 'yes' );
		update_option( DWS_LOWC_OPTION_DELETE_DATA, 'yes' );

		dws_lowc_add_settings();

		self::assertSame( '3', get_option( DWS_LOWC_OPTION_MAX_DEPTH ) );
		self::assertSame( 'yes', get_option( DWS_LOWC_OPTION_AUTOCOMPLETE ) );
		self::assertSame( 'yes', get_option( DWS_LOWC_OPTION_DELETE_DATA ) );
	}

	public function test_the_first_step_throws_when_a_default_cannot_be_stored(): void {
		global $wpdb;
		delete_option( DWS_LOWC_OPTION_DELETE_DATA );
		$fail       = static fn ( string $query ): string => \str_contains( $query, "'" . DWS_LOWC_OPTION_DELETE_DATA . "'" ) && \str_starts_with( \ltrim( $query ), 'INSERT' ) ? "SELECT * FROM {$wpdb->prefix}dws_lowc_missing" : $query;
		$suppressed = $wpdb->suppress_errors();
		add_filter( 'query', $fail );

		try {
			$this->expectExceptionObject( new \RuntimeException( 'The option ' . DWS_LOWC_OPTION_DELETE_DATA . ' could not be stored.' ) );
			dws_lowc_add_settings();
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

		self::assertInstanceOf( BatchedMigrationStepInterface::class, dws_lowc_get_migration_steps()[1] ?? null );
		$batches = 0;
		while ( dws_lowc_repair_links() ) {
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
		while ( dws_lowc_repair_links() ) {
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
		while ( dws_lowc_repair_links() ) {
			self::assertLessThan( 10000, ++$batches );
		}

		self::assertSame( array( $lost->get_id() ), wc_get_order( $orphan->get_id() )->get_meta( '_dws_lo_children' ) );
		self::assertSame( array( '0', '1', '1', '2' ), \array_map( static fn ( \WC_Order $order ): string => (string) wc_get_order( $order->get_id() )->get_meta( '_dws_lo_depth' ), array( $orphan, $lost, $stale, $grandchild ) ) );
	}
}
