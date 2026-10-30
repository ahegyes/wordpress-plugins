<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ReadmeTest extends TestCase {
	public function test_the_readme_repeats_the_entry_files_version_and_floors(): void {
		$readme = self::read_fields( 'readme.txt' );
		$entry  = self::read_fields( 'linked-orders-for-woocommerce.php' );

		self::assertSame( $entry['Version'], $readme['Stable tag'] );
		self::assertSame( $entry['Requires at least'], $readme['Requires at least'] );
		self::assertSame( $entry['Requires PHP'], $readme['Requires PHP'] );
	}

	public function test_the_readme_holds_the_changelog_markers_the_sync_fills(): void {
		$readme = (string) \file_get_contents( \dirname( __DIR__, 2 ) . '/readme.txt' );

		self::assertMatchesRegularExpression( '/== Changelog ==\s+<!-- Start changelog -->.+<!-- End changelog -->/s', $readme );
	}

	protected static function read_fields( string $file ): array {
		\preg_match_all( '/^[\s*]*([A-Za-z ]+):\s*(\S.*?)\s*$/m', (string) \file_get_contents( \dirname( __DIR__, 2 ) . "/$file" ), $matches );

		return \array_combine( $matches[1], $matches[2] );
	}
}
