<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Unit;

use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class FootprintTest extends TestCase {
	// Prefix families a port keeps from its v1 names, which uninstall must still remove.
	protected const array LEGACY_FAMILIES = array();

	public function test_the_prefix_has_one_segment_after_dws_so_no_prefix_in_its_family_starts_with_another(): void {
		self::assertMatchesRegularExpression( '/^dws_[a-z0-9]+$/', Plugin::PREFIX );
	}

	public function test_every_kind_is_one_the_uninstall_engine_removes(): void {
		$doc = (string) ( new \ReflectionClass( Uninstaller::class ) )->getDocComment();
		self::assertSame( 1, \preg_match( '/@phpstan-type Footprint (.+)$/m', $doc, $type ) );
		\preg_match_all( '/(\w+)\?:/', $type[1], $kinds );

		self::assertSame( array(), \array_diff( \array_keys( dws_lowc_get_footprint() ), $kinds[1] ) );
	}

	public function test_every_entry_is_in_the_plugin_prefix_family(): void {
		$family = array(
			Plugin::PREFIX . '_',
			Plugin::PREFIX . '/', // Hook names put a slash after the prefix.
			'_' . Plugin::PREFIX . '_', // WordPress hides meta keys that start with an underscore.
			'_transient_' . Plugin::PREFIX . '_',
			'_transient_timeout_' . Plugin::PREFIX . '_',
			...self::LEGACY_FAMILIES,
		);

		foreach ( dws_lowc_get_footprint() as $kind => $entries ) {
			$entries = \in_array( $kind, array( 'meta', 'meta_prefixes' ), true ) ? \array_merge( ...\array_values( $entries ) ) : $entries;
			foreach ( $entries as $entry ) {
				$covered = 'as_groups' === $kind ? Plugin::PREFIX === $entry : \array_any( $family, static fn ( string $start ): bool => \str_starts_with( $entry, $start ) );
				self::assertTrue( $covered, "The $kind entry $entry is outside the plugin's prefix family." );
			}
		}
	}

	public function test_it_covers_what_the_framework_roots_store(): void {
		$prefix = Plugin::PREFIX . '_';

		self::assertContains( $prefix, dws_lowc_get_footprint()['option_prefixes'] );
		self::assertContains( '_transient_' . $prefix, dws_lowc_get_footprint()['option_prefixes'] );
		self::assertContains( '_transient_timeout_' . $prefix, dws_lowc_get_footprint()['option_prefixes'] );
		self::assertContains( $prefix, dws_lowc_get_footprint()['user_meta_site_prefixes'] );
		self::assertContains( $prefix, dws_lowc_get_footprint()['user_meta_global_prefixes'] );
		self::assertContains( $prefix . 'network_notices', dws_lowc_get_footprint()['network_options'] );
		self::assertContains( $prefix . 'revision/migration/run', dws_lowc_get_footprint()['cron_hooks'] );
		self::assertContains( Plugin::PREFIX, dws_lowc_get_footprint()['as_groups'] );
	}

	public function test_it_covers_every_option_key(): void {
		$keys = \array_filter( \get_defined_constants( true )['user'], static fn ( mixed $value, string $name ): bool => \str_starts_with( $name, 'DWS_LOWC_OPTION_' ) && \is_string( $value ), \ARRAY_FILTER_USE_BOTH );

		self::assertNotSame( array(), $keys );
		foreach ( $keys as $key ) {
			$covered = \in_array( $key, dws_lowc_get_footprint()['options'] ?? array(), true ) || \array_any( dws_lowc_get_footprint()['option_prefixes'], static fn ( string $prefix ): bool => \str_starts_with( $key, $prefix ) );
			self::assertTrue( $covered, "The option $key is outside the footprint." );
		}
	}
}
