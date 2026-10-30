<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Plugin;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class MetadataTest extends TestCase {
	protected const string TRANSLATION = 'Translated & renamed plugin';

	public function test_the_slug_is_the_text_domain_wordpress_lists(): void {
		self::assertSame( self::get_listed_header( 'TextDomain' ), dws_lowc_get_plugin_slug() );
	}

	public function test_the_version_is_the_one_wordpress_lists(): void {
		self::assertSame( self::get_listed_header( 'Version' ), dws_lowc_get_plugin_version() );
	}

	public function test_the_name_is_translated_once_init_has_fired(): void {
		self::assertGreaterThan( 0, did_action( 'init' ) );
		$translate = self::translate_name();
		add_filter( 'gettext_' . Plugin::SLUG, $translate, 10, 2 );

		try {
			self::assertSame( self::TRANSLATION, dws_lowc_get_plugin_name() );
		} finally {
			remove_filter( 'gettext_' . Plugin::SLUG, $translate );
		}
	}

	public function test_the_name_stays_as_wordpress_lists_it_before_init(): void {
		global $wp_actions;
		$fired = $wp_actions['init'];
		unset( $wp_actions['init'] );
		$translate = self::translate_name();
		add_filter( 'gettext_' . Plugin::SLUG, $translate, 10, 2 );

		try {
			self::assertSame( self::get_listed_header( 'Name' ), dws_lowc_get_plugin_name() );
		} finally {
			remove_filter( 'gettext_' . Plugin::SLUG, $translate );
			$wp_actions['init'] = $fired;
		}
	}

	protected static function get_listed_header( string $header ): string {
		return get_plugins()[ plugin_basename( DWS_LOWC_FILE ) ][ $header ];
	}

	protected static function translate_name(): \Closure {
		$name = self::get_listed_header( 'Name' );

		return static fn ( string $translation, string $text ): string => $name === $text ? self::TRANSLATION : $translation;
	}
}
