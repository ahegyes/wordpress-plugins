<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class MigrationsTest extends TestCase {
	protected const array ROWS = array( DWS_IC_OPTION_GENERAL, DWS_IC_OPTION_PLUGIN );

	protected array $stored = array();

	#[\Override]
	protected function setUp(): void {
		foreach ( self::ROWS as $row ) {
			$this->stored[ $row ] = get_option( $row );
		}
	}

	#[After]
	protected function restore_rows(): void {
		foreach ( $this->stored as $row => $value ) {
			false === $value ? delete_option( $row ) : update_option( $row, $value );
		}

		update_option( Plugin::PREFIX . '_revision', \count( dws_ic_get_migration_steps() ) );
	}

	// The runner's time budget lasts for its request, and this process's bootstrap spends it when it migrates a new site, so the row runs as a request of its own.
	#[RunInSeparateProcess]
	public function test_the_runner_brings_a_new_site_to_the_latest_revision_with_v1s_defaults_autoloaded(): void {
		global $wpdb;
		foreach ( self::ROWS as $row ) {
			delete_option( $row );
		}
		update_option( Plugin::PREFIX . '_revision', 0 );

		dws_ic_plugin()->migrations?->run();

		self::assertSame( \count( dws_ic_get_migration_steps() ), MigrationRunner::get_revision( Plugin::PREFIX . '_revision' ) );
		foreach ( self::ROWS as $row ) {
			self::assertSame( DWS_IC_SETTING_DEFAULTS[ $row ], get_option( $row ) );
			self::assertSame( 'on', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $row ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the stored autoload flag.
		}
	}

	public function test_the_first_step_keeps_the_rows_a_v1_site_stored(): void {
		update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_POST_TYPES => array( 'page' ) ) );
		update_option( DWS_IC_OPTION_PLUGIN, array( DWS_IC_SETTING_DELETE_DATA => 'yes' ) );

		dws_ic_add_settings();

		self::assertSame( array( DWS_IC_SETTING_POST_TYPES => array( 'page' ) ), get_option( DWS_IC_OPTION_GENERAL ) );
		self::assertSame( array( DWS_IC_SETTING_DELETE_DATA => 'yes' ), get_option( DWS_IC_OPTION_PLUGIN ) );
	}

	public function test_the_first_step_throws_when_a_default_row_cannot_be_stored(): void {
		global $wpdb;
		delete_option( DWS_IC_OPTION_PLUGIN );
		$fail       = static fn ( string $query ): string => \str_contains( $query, "'" . DWS_IC_OPTION_PLUGIN . "'" ) && \str_starts_with( \ltrim( $query ), 'INSERT' ) ? "SELECT * FROM {$wpdb->prefix}dws_ic_missing" : $query;
		$suppressed = $wpdb->suppress_errors();
		add_filter( 'query', $fail );

		try {
			$this->expectExceptionObject( new \RuntimeException( 'The option ' . DWS_IC_OPTION_PLUGIN . ' could not be stored.' ) );
			dws_ic_add_settings();
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $suppressed );
		}
	}
}
