<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Unit;

use DeepWebSolutions\LockedPaymentMethods\Plugin;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class EntryHeadersTest extends TestCase {
	public function test_wc_tested_up_to_names_the_locked_woocommerce(): void {
		$lock        = \json_decode( (string) \file_get_contents( \dirname( __DIR__, 2 ) . '/composer.lock' ), true, 512, \JSON_THROW_ON_ERROR );
		$woocommerce = \array_find( $lock['packages-dev'], static fn ( array $package ): bool => 'wp-plugin/woocommerce' === $package['name'] );
		$entry       = (string) \file_get_contents( \dirname( __DIR__, 2 ) . '/' . Plugin::SLUG . '.php' );

		self::assertIsArray( $woocommerce );
		self::assertSame( 1, \preg_match( '/^\s*\*\s*WC tested up to:\s*(\S+)\s*$/m', $entry, $header ) );
		self::assertSame( \implode( '.', \array_slice( \explode( '.', $woocommerce['version'] ), 0, 2 ) ), $header[1] );
	}
}
