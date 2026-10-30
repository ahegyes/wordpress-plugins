<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Autocompletion;
use DeepWebSolutions\LinkedOrders\ChildCreation;
use DeepWebSolutions\LinkedOrders\Integration\SequentialOrderNumbersPro;
use DeepWebSolutions\LinkedOrders\ListTable;
use DeepWebSolutions\LinkedOrders\MetaBox;
use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Permission\CapabilityGrants;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use DeepWebSolutions\LinkedOrders\Settings;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PluginBootTest extends TestCase {
	public function test_the_plugin_boots_every_component_but_the_integration_with_an_absent_plugin(): void {
		$plugin = dws_lowc_plugin();

		self::assertSame( 'booted', $plugin->boot->status );
		self::assertSame( array( Scheduler::class, AdminNotices::class, CapabilityGrants::class, Settings::class, ChildCreation::class, MetaBox::class, ListTable::class, Autocompletion::class ), $plugin->kernel?->resolved );
		self::assertSame( array( SequentialOrderNumbersPro::class ), $plugin->kernel->skipped );
	}

	public function test_the_entry_loads_every_include_but_those_whose_names_start_with_an_underscore(): void {
		$includes = \dirname( DWS_LOWC_FILE ) . '/includes/';

		self::assertContains( $includes . 'safeguards.php', \get_included_files() );
		self::assertContains( $includes . 'index.php', \get_included_files() );
		\file_put_contents( $includes . '_lowc-skipped.php', "<?php\nconst DWS_LOWC_SKIPPED_INCLUDE = true;\n" );
		try {
			\exec( 'wp --path=' . \escapeshellarg( ABSPATH ) . ' eval ' . \escapeshellarg( 'echo defined( "DWS_LOWC_SKIPPED_INCLUDE" ) ? "loaded" : "skipped";' ) . ' 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- The loader runs only in a fresh request.
		} finally {
			\unlink( $includes . '_lowc-skipped.php' );
		}

		self::assertSame( 0, $status, \implode( "\n", $output ) );
		self::assertSame( 'skipped', (string) \end( $output ) );
	}

	public function test_the_stored_data_is_at_the_revision_of_the_code(): void {
		self::assertInstanceOf( MigrationRunner::class, dws_lowc_plugin()->migrations );
		self::assertSame( \count( dws_lowc_get_migration_steps() ), MigrationRunner::get_revision( Plugin::PREFIX . '_revision' ) );
	}

	public function test_the_plugin_declares_itself_compatible_with_woocommerces_order_tables(): void {
		$plugins = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_plugins_for_feature( 'custom_order_tables' );

		self::assertContains( plugin_basename( DWS_LOWC_FILE ), $plugins['compatible'] );
	}
}
