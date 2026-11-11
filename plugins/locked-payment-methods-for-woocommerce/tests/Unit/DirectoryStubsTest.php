<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DirectoryStubsTest extends TestCase {
	// A release ships every directory but these, and wp-scripts writes the build trees, so none of them needs a stub.
	protected const array SKIPPED = array( 'build', 'changelog', 'docs', 'node_modules', 'tests', 'vendor', 'vendor-prefixed' );

	public function test_every_shipped_directory_holds_the_silence_is_golden_stub(): void {
		$root        = \dirname( __DIR__, 2 );
		$directories = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
				static fn ( \SplFileInfo $file ): bool => $file->isDir() && ! \str_starts_with( $file->getFilename(), '.' ) && ! \in_array( $file->getFilename(), self::SKIPPED, true )
			),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		$missing     = array();
		foreach ( array( $root, ...\array_keys( \iterator_to_array( $directories ) ) ) as $directory ) {
			$stub = "$directory/index.php";
			if ( ! \is_file( $stub ) || "<?php declare( strict_types=1 ); // Silence is golden.\n" !== \file_get_contents( $stub ) ) {
				$missing[] = \substr( $directory, \strlen( $root ) ) . '/';
			}
		}

		\sort( $missing );
		self::assertSame( array(), $missing );
	}
}
