<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Migration\BatchedMigrationStepInterface;
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

	public function test_the_second_step_converts_v1s_internal_comments_in_batches_and_recounts_their_posts(): void {
		global $wpdb;
		$post   = (int) wp_insert_post(
			array(
				'post_title'  => 'Converted',
				'post_status' => 'publish',
			)
		);
		$insert = static function ( string $type, string $status ) use ( $wpdb, $post ): int {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Stands in for v1, which ran without the plugin's guards.
				$wpdb->comments,
				array(
					'comment_post_ID'  => $post,
					'comment_type'     => $type,
					'comment_approved' => $status,
					'comment_content'  => "$type $status",
					'comment_date'     => \current_time( 'mysql' ),
					'comment_date_gmt' => \current_time( 'mysql', true ),
				)
			);
			return (int) $wpdb->insert_id;
		};
		$approved = $insert( DWS_IC_COMMENT_TYPE, '1' );
		$pending  = $insert( DWS_IC_COMMENT_TYPE, '0' );
		$trashed  = $insert( DWS_IC_COMMENT_TYPE, 'trash' );
		$public   = $insert( 'comment', '1' );
		$note     = $insert( 'order_note', '1' );
		$wpdb->update( $wpdb->posts, array( 'comment_count' => 4 ), array( 'ID' => $post ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Stands in for v1's count.
		clean_post_cache( $post );

		self::assertInstanceOf( BatchedMigrationStepInterface::class, dws_ic_get_migration_steps()[1] ?? null );
		$batches = 0;
		while ( dws_ic_convert_v1_comments() ) {
			self::assertLessThan( 10000, ++$batches );
		}

		self::assertSame( DWS_IC_COMMENT_STATUS, get_comment( $approved )?->comment_approved );
		self::assertSame( DWS_IC_COMMENT_STATUS, get_comment( $pending )?->comment_approved );
		self::assertSame( 'trash', get_comment( $trashed )?->comment_approved );
		self::assertSame( '1', get_comment( $public )?->comment_approved );
		self::assertSame( '1', get_comment( $note )?->comment_approved );
		self::assertSame( '2', get_post( $post )?->comment_count );
		self::assertFalse( get_option( 'dws_ic_v1_comments_cursor' ) );

		wp_delete_post( $post, true );
	}
}
