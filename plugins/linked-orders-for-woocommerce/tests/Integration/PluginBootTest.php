<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use DeepWebSolutions\LinkedOrders\Settings;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PluginBootTest extends TestCase {
	public function test_the_plugin_boots_every_component(): void {
		$plugin = dws_lowc_plugin();

		self::assertSame( 'booted', $plugin->boot->status );
		self::assertSame( array( Scheduler::class, AdminNotices::class, Settings::class ), $plugin->kernel?->resolved );
		self::assertSame( array(), $plugin->kernel->skipped );
	}

	public function test_the_entry_loads_every_include_but_those_whose_names_start_with_an_underscore(): void {
		$includes = \dirname( DWS_LOWC_FILE ) . '/includes/';

		self::assertContains( $includes . 'safeguards.php', \get_included_files() );
		self::assertContains( $includes . 'index.php', \get_included_files() );
		self::assertNotContains( $includes . '_disabled-example.php', \get_included_files() );
	}

	public function test_the_stored_data_is_at_the_revision_of_the_code(): void {
		self::assertInstanceOf( MigrationRunner::class, dws_lowc_plugin()->migrations );
		self::assertSame( \count( dws_lowc_get_migration_steps() ), MigrationRunner::get_revision( Plugin::PREFIX . '_revision' ) );
	}
}
