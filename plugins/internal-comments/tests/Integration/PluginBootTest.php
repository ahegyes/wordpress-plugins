<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\InternalComments\Tests\Support\RunsRequestsTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PluginBootTest extends TestCase {
	use RunsRequestsTrait;

	public function test_the_plugin_boots_every_component(): void {
		$plugin = dws_ic_plugin();

		self::assertSame( 'booted', $plugin->boot->status );
		self::assertSame( array(), $plugin->kernel->skipped );
	}

	public function test_the_entry_loads_every_include_but_those_whose_names_start_with_an_underscore(): void {
		$includes = \dirname( DWS_IC_FILE ) . '/includes/';

		self::assertContains( $includes . 'index.php', \get_included_files() );
		self::assertIsInt( \file_put_contents( $includes . '_ic-skipped.php', "<?php\nconst DWS_IC_SKIPPED_INCLUDE = true;\n" ) );
		try {
			$request = self::request( 'echo 0 < did_action( "dws_ic/boot/succeeded" ) ? ( defined( "DWS_IC_SKIPPED_INCLUDE" ) ? "loaded" : "skipped" ) : "not booted";' );
		} finally {
			\unlink( $includes . '_ic-skipped.php' );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( 'skipped', $request['last'], $request['output'] );
	}

	public function test_a_boot_listener_that_throws_is_not_reported_as_an_incomplete_build(): void {
		$include = \dirname( DWS_IC_FILE ) . '/includes/zz-boot-listener.php';
		\file_put_contents( $include, "<?php\nadd_action( 'dws_ic/boot/succeeded', static function (): void { throw new RuntimeException( 'A boot listener failed.' ); } );\n" );
		try {
			$request = self::request( 'echo 1;' );
		} finally {
			\unlink( $include );
		}

		self::assertStringContainsString( 'A boot listener failed.', $request['output'] );
		self::assertStringNotContainsString( 'did not finish loading', $request['output'] );
	}

	public function test_the_entry_loads_its_safeguards_before_the_bootstrap_package_and_its_floor_gate(): void {
		$root       = \dirname( DWS_IC_FILE );
		$safeguards = \array_search( $root . '/includes/safeguards.php', \get_included_files(), true );
		$bootstrap  = \array_search( $root . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php', \get_included_files(), true );

		self::assertIsInt( $safeguards );
		self::assertIsInt( $bootstrap );
		self::assertLessThan( $bootstrap, $safeguards );
	}

	public function test_the_stored_data_is_at_the_revision_of_the_code(): void {
		self::assertInstanceOf( MigrationRunner::class, dws_ic_plugin()->migrations );
		self::assertSame( \count( dws_ic_get_migration_steps() ), MigrationRunner::get_revision( Plugin::PREFIX . '_revision' ) );
	}
}
