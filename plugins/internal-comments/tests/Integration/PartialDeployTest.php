<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PartialDeployTest extends TestCase {
	public function test_a_missing_blocks_manifest_shows_the_build_notice_and_loads_nothing_built(): void {
		$manifest = \dirname( DWS_IC_FILE ) . '/blocks/build/blocks-manifest.php';
		\rename( $manifest, "$manifest.hidden" );
		try {
			$request = self::request( 'echo wp_json_encode( array( function_exists( "dws_ic_boot" ), false !== has_action( "all_admin_notices", "dws_ic_render_build_notice" ) ) );' );
		} finally {
			\rename( "$manifest.hidden", $manifest );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( '[false,true]', $request['last'] );
	}

	public function test_an_include_that_throws_shows_the_build_notice_and_boots_nothing(): void {
		$include = \dirname( DWS_IC_FILE ) . '/includes/zz-throws.php';
		\file_put_contents( $include, "<?php\nthrow new RuntimeException( 'A partial deploy.' );\n" );
		try {
			$request = self::request( 'echo wp_json_encode( array( false !== has_action( "plugins_loaded", "dws_ic_boot" ), false !== has_action( "all_admin_notices", "dws_ic_render_build_notice" ) ) );' );
		} finally {
			\unlink( $include );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( '[false,true]', $request['last'] );
	}

	public function test_the_peer_getters_a_partial_deploy_defines_still_answer(): void {
		$root = \dirname( DWS_IC_FILE ) . '/src/Plugin.php'; // options.php reads it at include time, after metadata.php has loaded.
		\rename( $root, "$root.hidden" );
		try {
			$request = self::request( 'echo wp_json_encode( array( false !== has_action( "plugins_loaded", "dws_ic_boot" ), dws_ic_get_plugin_slug(), dws_ic_get_plugin_name(), dws_ic_get_plugin_version() ) );' );
		} finally {
			\rename( "$root.hidden", $root );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( wp_json_encode( array( false, dws_ic_get_plugin_slug(), dws_ic_get_plugin_name(), dws_ic_get_plugin_version() ) ), $request['last'] );
	}

	public function test_a_deactivation_whose_composition_root_cannot_be_built_is_reported_as_a_build_error(): void {
		$root = \dirname( DWS_IC_FILE ) . '/vendor-prefixed/ahegyes/wp-framework-core/src/PluginBoot.php'; // The composition root constructs it, and no include reads it.
		\rename( $root, "$root.hidden" );
		try {
			$request = self::request( 'remove_action( "all_admin_notices", "dws_ic_render_build_notice" ); do_action( "deactivate_" . plugin_basename( DWS_IC_FILE ), false ); echo false !== has_action( "all_admin_notices", "dws_ic_render_build_notice" ) ? "reported" : "silent";' );
		} finally {
			\rename( "$root.hidden", $root );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( 'reported', $request['last'] );
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_whose_engine_is_missing_keeps_the_data_and_lets_the_web_delete_the_files(): void {
		update_option( DWS_IC_OPTION_DELETE_DATA, 'yes' );
		$log = (string) \tempnam( \sys_get_temp_dir(), 'dws-tpl-uninstall-' );
		\ini_set( 'error_log', $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- The child process's stderr would fail the test.
		\spl_autoload_register(
			static function ( string $class_name ): void {
				if ( Uninstaller::class === $class_name ) {
					throw new \Error( "Failed opening required '$class_name'." ); // Stands in for an engine that cannot load.
				}
			},
			true,
			true
		);

		self::assertTrue( uninstall_plugin( plugin_basename( DWS_IC_FILE ) ) );
		self::assertSame( 'yes', get_option( DWS_IC_OPTION_DELETE_DATA ) );
		self::assertStringContainsString( 'kept its data on uninstall', (string) \file_get_contents( $log ) );

		\unlink( $log );
		update_option( DWS_IC_OPTION_DELETE_DATA, 'no' );
	}

	/**
	 * @return array{status: int, output: string, last: string}
	 */
	protected static function request( string $php ): array {
		\exec( 'wp --path=' . \escapeshellarg( ABSPATH ) . ' eval ' . \escapeshellarg( $php ) . ' 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Each row needs a fresh request that loads the plugin.
		$output = \array_values( \array_filter( $output, static fn ( string $line ): bool => '' !== \trim( $line ) ) );

		return array(
			'status' => $status,
			'output' => \implode( "\n", $output ),
			'last'   => (string) \end( $output ),
		);
	}
}
