<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Support;

trait RunsRequestsTrait {
	protected static function request( string $php, string ...$flags ): array {
		$arguments = \implode( '', \array_map( static fn ( string $flag ): string => ' ' . \escapeshellarg( $flag ), $flags ) );
		\exec( 'wp --path=' . \escapeshellarg( ABSPATH ) . "$arguments eval " . \escapeshellarg( $php ) . ' 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Each row needs a fresh request that loads the plugin.
		$output = \array_values( \array_filter( $output, static fn ( string $line ): bool => '' !== \trim( $line ) ) );
		return array(
			'status' => $status,
			'output' => \implode( "\n", $output ),
			'last'   => (string) \end( $output ),
		);
	}
}
