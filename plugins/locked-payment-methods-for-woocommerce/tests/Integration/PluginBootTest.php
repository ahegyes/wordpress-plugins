<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\Permission\CapabilityGrants;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use DeepWebSolutions\LockedPaymentMethods\Settings;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\GroupsStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\OrderStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\UserProfileStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\UserRoleStrategy;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PluginBootTest extends TestCase {
	public function test_the_plugin_boots_every_component_whose_switch_is_on(): void {
		$plugin = dws_lpmwc_plugin();

		self::assertSame( 'booted', $plugin->boot->status );
		self::assertSame( array( Scheduler::class, AdminNotices::class, CapabilityGrants::class, Settings::class, UserRoleStrategy::class, UserProfileStrategy::class ), $plugin->kernel?->resolved );
		self::assertSame( array( OrderStrategy::class, GroupsStrategy::class ), $plugin->kernel->skipped ); // Orders and Groups are off by default, as v1's were.
	}

	public function test_the_entry_loads_every_include_but_those_whose_names_start_with_an_underscore(): void {
		$includes = \dirname( DWS_LPMWC_FILE ) . '/includes/';

		self::assertContains( $includes . 'index.php', \get_included_files() );
		self::assertNotContains( $includes . '_disabled-example.php', \get_included_files() );
	}

	public function test_the_entry_loads_its_safeguards_before_the_bootstrap_package_and_its_floor_gate(): void {
		$root       = \dirname( DWS_LPMWC_FILE );
		$safeguards = \array_search( $root . '/includes/safeguards.php', \get_included_files(), true );
		$bootstrap  = \array_search( $root . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php', \get_included_files(), true );

		self::assertIsInt( $safeguards );
		self::assertIsInt( $bootstrap );
		self::assertLessThan( $bootstrap, $safeguards );
	}

	public function test_the_stored_data_is_at_the_revision_of_the_code(): void {
		self::assertInstanceOf( MigrationRunner::class, dws_lpmwc_plugin()->migrations );
		self::assertSame( \count( dws_lpmwc_get_migration_steps() ), MigrationRunner::get_revision( Plugin::PREFIX . '_revision' ) );
	}
}
