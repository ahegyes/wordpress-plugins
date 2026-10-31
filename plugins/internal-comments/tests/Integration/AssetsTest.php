<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Plugin;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class AssetsTest extends TestCase {
	protected const string HANDLE = Plugin::SLUG . '-assets-test';

	#[After]
	protected function deregister(): void {
		wp_deregister_script( self::HANDLE );
		wp_deregister_style( self::HANDLE );
	}

	public function test_the_payload_keeps_script_module_imports_as_generated(): void {
		self::assertSame( require \dirname( __DIR__ ) . '/Fixtures/built.asset.php', dws_ic_get_asset( 'tests/Fixtures/built.js' ) );
	}

	public function test_a_script_registers_with_its_generated_dependencies_and_version_and_the_plugins_translations(): void {
		self::assertTrue( dws_ic_register_script( self::HANDLE, 'tests/Fixtures/built.js', array( 'strategy' => 'defer' ) ) );

		$script = wp_scripts()->registered[ self::HANDLE ];
		self::assertSame( plugins_url( 'tests/Fixtures/built.js', DWS_IC_FILE ), $script->src );
		self::assertSame( array( 'wp-i18n' ), $script->deps );
		self::assertSame( 'fixture', $script->ver );
		self::assertSame( 'defer', $script->extra['strategy'] ?? null );
		self::assertSame( Plugin::SLUG, $script->textdomain );
		self::assertSame( plugin_dir_path( DWS_IC_FILE ) . 'languages', $script->translations_path );
	}

	public function test_a_stylesheet_registers_with_its_generated_version_and_its_right_to_left_variant(): void {
		self::assertTrue( dws_ic_register_style( self::HANDLE, 'tests/Fixtures/built.css', 'print' ) );

		$style = wp_styles()->registered[ self::HANDLE ];
		self::assertSame( plugins_url( 'tests/Fixtures/built.css', DWS_IC_FILE ), $style->src );
		self::assertSame( 'fixture', $style->ver );
		self::assertSame( 'print', $style->args );
		self::assertSame( 'replace', $style->extra['rtl'] ?? null );
	}
}
