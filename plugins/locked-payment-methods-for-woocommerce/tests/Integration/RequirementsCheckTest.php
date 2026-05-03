<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Bootstrap\check_requirements;
use function DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Bootstrap\get_plugin_metadata;

final class RequirementsCheckTest extends TestCase {
	private string $basename = 'locked-payment-methods-for-woocommerce/locked-payment-methods-for-woocommerce.php';

	public function test_plugin_header_declares_expected_min_versions(): void {
		$meta = get_plugin_metadata( $this->basename );

		self::assertSame( '8.5', $meta['RequiresPHP'] );
		self::assertSame( '7.0', $meta['RequiresWP'] );
	}

	public function test_check_requirements_matches_runtime(): void {
		$result = check_requirements( $this->basename );

		$env_compatible = \is_php_version_compatible( '8.5' )
			&& \is_wp_version_compatible( '7.0' );

		if ( $env_compatible ) {
			self::assertTrue( $result );
			return;
		}

		self::assertInstanceOf( \WP_Error::class, $result );
		$codes = $result->get_error_codes();
		self::assertTrue(
			\in_array( 'plugin_php_incompatible', $codes, true )
				|| \in_array( 'plugin_wp_incompatible', $codes, true ),
			'Expected at least one incompat code on an incompatible runtime.'
		);
	}
}
