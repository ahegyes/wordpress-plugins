<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\AdminScreens;
use DeepWebSolutions\InternalComments\Comments;
use DeepWebSolutions\InternalComments\MetaBox;
use DeepWebSolutions\InternalComments\Permissions;
use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\RestRoutes;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Permission\CapabilityGrants;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use DeepWebSolutions\InternalComments\Settings;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PluginBootTest extends TestCase {
	public function test_the_plugin_boots_every_component(): void {
		$plugin = dws_ic_plugin();

		self::assertSame( 'booted', $plugin->boot->status );
		self::assertSame( array( Scheduler::class, AdminNotices::class, CapabilityGrants::class, Settings::class, Permissions::class, Comments::class, MetaBox::class, RestRoutes::class, AdminScreens::class ), $plugin->kernel?->resolved );
		self::assertSame( array(), $plugin->kernel->skipped );
	}

	public function test_the_entry_loads_every_include_but_those_whose_names_start_with_an_underscore(): void {
		$includes = \dirname( DWS_IC_FILE ) . '/includes/';

		self::assertContains( $includes . 'safeguards.php', \get_included_files() );
		self::assertContains( $includes . 'index.php', \get_included_files() );
		self::assertNotContains( $includes . '_disabled-example.php', \get_included_files() );
	}

	public function test_the_stored_data_is_at_the_revision_of_the_code(): void {
		self::assertInstanceOf( MigrationRunner::class, dws_ic_plugin()->migrations );
		self::assertSame( \count( dws_ic_get_migration_steps() ), MigrationRunner::get_revision( Plugin::PREFIX . '_revision' ) );
	}
}
