<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Support;

trait RunsRequestsTrait {
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
