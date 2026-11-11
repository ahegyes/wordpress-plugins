<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Integration\SequentialOrderNumbersPro;
use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\LinkedOrders\Tests\Support\RunsRequestsTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PluginBootTest extends TestCase {
	use RunsRequestsTrait;

	public function test_the_plugin_boots_every_component_but_the_integration_with_an_absent_plugin(): void {
		$plugin = dws_lowc_plugin();

		self::assertSame( 'booted', $plugin->boot->status );
		self::assertSame( array( SequentialOrderNumbersPro::class ), $plugin->kernel->skipped );
	}

	public function test_the_entry_loads_every_include_but_those_whose_names_start_with_an_underscore(): void {
		$includes = \dirname( DWS_LOWC_FILE ) . '/includes/';

		self::assertContains( $includes . 'index.php', \get_included_files() );
		self::assertIsInt( \file_put_contents( $includes . '_lowc-skipped.php', "<?php\nconst DWS_LOWC_SKIPPED_INCLUDE = true;\n" ) );
		try {
			$request = self::request( 'echo 0 < did_action( "dws_lowc/boot/succeeded" ) ? ( defined( "DWS_LOWC_SKIPPED_INCLUDE" ) ? "loaded" : "skipped" ) : "not booted";' );
		} finally {
			\unlink( $includes . '_lowc-skipped.php' );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( 'skipped', $request['last'], $request['output'] );
	}

	public function test_a_boot_listener_that_throws_is_not_reported_as_an_incomplete_build(): void {
		$include = \dirname( DWS_LOWC_FILE ) . '/includes/zz-boot-listener.php';
		\file_put_contents( $include, "<?php\nadd_action( 'dws_lowc/boot/succeeded', static function (): void { throw new RuntimeException( 'A boot listener failed.' ); } );\n" );
		try {
			$request = self::request_provoking( 'A boot listener failed.', 'echo 1;' );
		} finally {
			\unlink( $include );
		}

		self::assertStringContainsString( 'A boot listener failed.', $request['output'] );
		self::assertStringNotContainsString( 'did not finish loading', $request['output'] );
	}

	public function test_the_entry_loads_its_safeguards_before_the_bootstrap_package_and_its_floor_gate(): void {
		$root       = \dirname( DWS_LOWC_FILE );
		$safeguards = \array_search( $root . '/includes/safeguards.php', \get_included_files(), true );
		$bootstrap  = \array_search( $root . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php', \get_included_files(), true );

		self::assertIsInt( $safeguards );
		self::assertIsInt( $bootstrap );
		self::assertLessThan( $bootstrap, $safeguards );
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
