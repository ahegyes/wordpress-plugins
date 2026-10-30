<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class MigrationsTest extends TestCase {
	protected const array KEYS = array( DWS_LOWC_OPTION_MAX_DEPTH, DWS_LOWC_OPTION_AUTOCOMPLETE, DWS_LOWC_OPTION_DELETE_DATA );

	protected array $stored = array();

	#[\Override]
	protected function setUp(): void {
		foreach ( self::KEYS as $key ) {
			$this->stored[ $key ] = get_option( $key );
		}
	}

	#[After]
	protected function restore_rows(): void {
		foreach ( $this->stored as $key => $value ) {
			false === $value ? delete_option( $key ) : update_option( $key, $value );
		}

		update_option( Plugin::PREFIX . '_revision', \count( dws_lowc_get_migration_steps() ) );
	}

	public function test_the_runner_brings_a_new_site_to_revision_one_with_the_untranslated_defaults_autoloaded(): void {
		global $wpdb;
		foreach ( self::KEYS as $key ) {
			delete_option( $key );
		}
		update_option( Plugin::PREFIX . '_revision', 0 );

		dws_lowc_plugin()->migrations?->run();

		self::assertSame( 1, MigrationRunner::get_revision( Plugin::PREFIX . '_revision' ) );
		self::assertSame( '1', get_option( DWS_LOWC_OPTION_MAX_DEPTH ) );
		self::assertSame( 'no', get_option( DWS_LOWC_OPTION_AUTOCOMPLETE ) );
		self::assertSame( 'no', get_option( DWS_LOWC_OPTION_DELETE_DATA ) );
		foreach ( self::KEYS as $key ) {
			self::assertSame( 'on', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $key ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the stored autoload flag.
		}
	}

	public function test_the_first_step_keeps_the_values_a_v1_site_stored(): void {
		update_option( DWS_LOWC_OPTION_MAX_DEPTH, '3' );
		update_option( DWS_LOWC_OPTION_AUTOCOMPLETE, 'yes' );
		update_option( DWS_LOWC_OPTION_DELETE_DATA, 'yes' );

		dws_lowc_add_settings();

		self::assertSame( '3', get_option( DWS_LOWC_OPTION_MAX_DEPTH ) );
		self::assertSame( 'yes', get_option( DWS_LOWC_OPTION_AUTOCOMPLETE ) );
		self::assertSame( 'yes', get_option( DWS_LOWC_OPTION_DELETE_DATA ) );
	}

	public function test_the_first_step_throws_when_a_default_cannot_be_stored(): void {
		global $wpdb;
		delete_option( DWS_LOWC_OPTION_DELETE_DATA );
		$fail       = static fn ( string $query ): string => \str_contains( $query, "'" . DWS_LOWC_OPTION_DELETE_DATA . "'" ) && \str_starts_with( \ltrim( $query ), 'INSERT' ) ? "SELECT * FROM {$wpdb->prefix}dws_lowc_missing" : $query;
		$suppressed = $wpdb->suppress_errors();
		add_filter( 'query', $fail );

		try {
			$this->expectExceptionObject( new \RuntimeException( 'The option ' . DWS_LOWC_OPTION_DELETE_DATA . ' could not be stored.' ) );
			dws_lowc_add_settings();
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $suppressed );
		}
	}
}
