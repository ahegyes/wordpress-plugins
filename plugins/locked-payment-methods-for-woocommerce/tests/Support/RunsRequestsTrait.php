<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Support;

trait RunsRequestsTrait {
	protected static function request( string $php, string ...$flags ): array {
		return self::request_provoking( '', $php, ...$flags );
	}

	// A row whose fixture provokes a diagnostic names it, so that diagnostic and what follows it, such as a fatal error's trace, pass, while any output before it fails the row.
	protected static function request_provoking( string $diagnostic, string $php, string ...$flags ): array {
		$arguments = \implode( '', \array_map( static fn ( string $flag ): string => ' ' . \escapeshellarg( $flag ), $flags ) );
		\exec( 'wp --path=' . \escapeshellarg( ABSPATH ) . "$arguments eval " . \escapeshellarg( $php ) . ' 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Each row needs a fresh request that loads the plugin.
		$output   = \array_values( \array_filter( $output, static fn ( string $line ): bool => '' !== \trim( $line ) ) );
		$provoked = '' === $diagnostic ? null : \array_find_key( $output, static fn ( string $line ): bool => \str_contains( $line, $diagnostic ) );

		self::assertSame( array(), \array_slice( $output, 0, \is_int( $provoked ) ? $provoked : \max( 0, \count( $output ) - 1 ) ), \implode( "\n", $output ) );

		return array(
			'status' => $status,
			'output' => \implode( "\n", $output ),
			'last'   => (string) \end( $output ),
		);
	}
}
