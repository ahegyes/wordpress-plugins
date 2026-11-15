<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Comments;
use DeepWebSolutions\InternalComments\CommentStatusMove;
use DeepWebSolutions\InternalComments\Migrations;
use DeepWebSolutions\InternalComments\Options;
use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\InternalComments\Tests\Support\RestoresOptionsTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class MigrationsTest extends TestCase {
	use RestoresOptionsTrait;

	protected const array OPTIONS = array( Options::POST_TYPES, Options::PRIVATE_COMMENTS, Options::DELETE_DATA, 'dws-internal-comments_general', 'dws-internal-comments_plugin', 'dws_ic_general_extra' );

	#[After]
	protected function restore_the_revision(): void {
		update_option( Plugin::PREFIX . '_revision', \count( Migrations::get_steps() ) );
	}

	// The runner's time budget lasts for its request, and this process's bootstrap spends it when it migrates a new site, so the row runs as a request of its own.
	#[RunInSeparateProcess]
	public function test_the_runner_brings_a_new_site_to_the_latest_revision_with_each_default_in_an_autoloaded_row(): void {
		global $wpdb;
		foreach ( self::OPTIONS as $row ) {
			delete_option( $row );
		}
		update_option( Plugin::PREFIX . '_revision', 0 );

		do_action( 'internal_comments/migration/run' ); // A batched step runs only from the runner's scheduled hook.

		self::assertSame( \count( Migrations::get_steps() ), MigrationRunner::get_revision( Plugin::PREFIX . '_revision' ) );
		foreach ( Options::DEFAULTS as $row => $default ) {
			self::assertSame( $default, get_option( $row ) );
			self::assertSame( 'on', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $row ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the stored autoload flag.
		}
		self::assertFalse( get_option( 'dws-internal-comments_general' ) );
	}

	// v1 kept each tab's fields in one row, which stays for a rollback while each field moves to a row of its own.
	public function test_the_first_step_copies_each_field_of_v1s_rows_and_keeps_those_rows(): void {
		$general = array(
			'post-types'              => array( 'page' ),
			'enable-private-comments' => 'yes',
			'extra'                   => 'kept',
			'Not an id'               => 'left',
		);
		foreach ( array( Options::POST_TYPES, Options::PRIVATE_COMMENTS, Options::DELETE_DATA, 'dws_ic_general_extra' ) as $row ) {
			delete_option( $row );
		}
		update_option( 'dws-internal-comments_general', $general );
		update_option( 'dws-internal-comments_plugin', array( 'remove-data-uninstall' => 'yes' ) );

		Migrations::get_steps()[0]();

		self::assertSame( array( 'page' ), get_option( Options::POST_TYPES ) );
		self::assertSame( 'yes', get_option( Options::PRIVATE_COMMENTS ) );
		self::assertSame( 'yes', get_option( Options::DELETE_DATA ) );
		self::assertSame( 'kept', get_option( 'dws_ic_general_extra' ) );
		self::assertFalse( get_option( 'dws_ic_general_Not an id' ) );
		self::assertSame( $general, get_option( 'dws-internal-comments_general' ) );
		self::assertSame( array( 'remove-data-uninstall' => 'yes' ), get_option( 'dws-internal-comments_plugin' ) );
	}

	public function test_the_first_step_leaves_each_absent_setting_of_a_site_without_v1s_rows_to_the_seeding(): void {
		delete_option( 'dws-internal-comments_general' );
		delete_option( 'dws-internal-comments_plugin' );
		delete_option( Options::POST_TYPES );

		Migrations::get_steps()[0]();

		self::assertFalse( get_option( Options::POST_TYPES ) );
	}

	// Neither settings step overwrites a row, so a value stored before a step runs again outlasts both v1's row and the default.
	public function test_the_settings_steps_keep_the_settings_a_site_stored(): void {
		update_option( Options::POST_TYPES, array( 'page' ) );
		update_option( Options::DELETE_DATA, 'yes' );
		update_option( 'dws-internal-comments_general', array( 'post-types' => array( 'post' ) ) );
		update_option( 'dws-internal-comments_plugin', array( 'remove-data-uninstall' => 'no' ) );

		Migrations::get_steps()[0]();
		Migrations::get_steps()[1]();

		self::assertSame( array( 'page' ), get_option( Options::POST_TYPES ) );
		self::assertSame( 'yes', get_option( Options::DELETE_DATA ) );
	}

	public function test_the_first_step_throws_when_a_field_cannot_be_stored(): void {
		global $wpdb;
		update_option( 'dws-internal-comments_general', array( 'post-types' => array( 'page' ) ) );
		delete_option( Options::POST_TYPES );
		$fail       = static fn ( string $query ): string => \str_contains( $query, "'" . Options::POST_TYPES . "'" ) && \str_starts_with( \ltrim( $query ), 'INSERT' ) ? "SELECT * FROM {$wpdb->prefix}dws_ic_missing" : $query;
		$suppressed = $wpdb->suppress_errors();
		add_filter( 'query', $fail );

		try {
			$this->expectExceptionObject( new \RuntimeException( 'The option ' . Options::POST_TYPES . ' could not be stored.' ) );
			Migrations::get_steps()[0]();
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $suppressed );
		}
	}

	public function test_the_second_step_stores_each_absent_default_and_keeps_the_settings_a_site_stored(): void {
		update_option( Options::POST_TYPES, array( 'page' ) );
		delete_option( Options::DELETE_DATA );

		Migrations::get_steps()[1]();

		self::assertSame( array( 'page' ), get_option( Options::POST_TYPES ) );
		self::assertSame( 'no', get_option( Options::DELETE_DATA ) );
	}

	public function test_the_second_step_throws_when_a_default_cannot_be_stored(): void {
		global $wpdb;
		delete_option( Options::DELETE_DATA );
		$fail       = static fn ( string $query ): string => \str_contains( $query, "'" . Options::DELETE_DATA . "'" ) && \str_starts_with( \ltrim( $query ), 'INSERT' ) ? "SELECT * FROM {$wpdb->prefix}dws_ic_missing" : $query;
		$suppressed = $wpdb->suppress_errors();
		add_filter( 'query', $fail );

		try {
			$this->expectExceptionObject( new \RuntimeException( 'The option ' . Options::DELETE_DATA . ' could not be stored.' ) );
			Migrations::get_steps()[1]();
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $suppressed );
		}
	}

	// The type exclusion leaves internal comments out of the counts cached while the move is pending, so its end has them counted afresh.
	public function test_the_end_of_the_move_has_the_comment_counts_counted_afresh(): void {
		$post    = (int) wp_insert_post(
			array(
				'post_title'  => 'Counted',
				'post_status' => 'publish',
			)
		);
		$trashed = (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $post ) );
		wp_trash_comment( $trashed );
		$revision = get_option( 'dws_ic_revision' );
		update_option( 'dws_ic_revision', 1 );
		delete_option( 'dws_ic_v1_comments_cursor' );
		\WC_Comments::delete_comments_count_cache();
		wp_cache_delete( 'comments-0', 'counts' );

		try {
			wp_count_comments();
			while ( new CommentStatusMove()->process_batch() ) {
				continue;
			}
		} finally {
			update_option( 'dws_ic_revision', $revision );
		}
		$cached = (array) wp_count_comments();
		\WC_Comments::delete_comments_count_cache();
		wp_cache_delete( 'comments-0', 'counts' );
		$fresh = (array) wp_count_comments();
		wp_delete_post( $post, true );

		self::assertSame( $fresh, $cached );
	}

	public function test_the_third_step_converts_v1s_internal_comments_in_batches_and_recounts_their_posts(): void {
		global $wpdb;
		$post = (int) wp_insert_post(
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
					'comment_date'     => current_time( 'mysql' ),
					'comment_date_gmt' => current_time( 'mysql', true ),
				)
			);
			return (int) $wpdb->insert_id;
		};

		$approved = $insert( Comments::TYPE, '1' );
		$pending  = $insert( Comments::TYPE, '0' );
		$trashed  = $insert( Comments::TYPE, 'trash' );
		$public   = $insert( 'comment', '1' );
		$note     = $insert( 'order_note', '1' );
		$wpdb->update( $wpdb->posts, array( 'comment_count' => 4 ), array( 'ID' => $post ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Stands in for v1's count.
		clean_post_cache( $post );

		self::assertInstanceOf( CommentStatusMove::class, Migrations::get_steps()[2] ?? null );
		\WC_Comments::delete_comments_count_cache();
		wp_count_comments(); // WooCommerce caches the counts before the move, as on a store whose admin screens were open.
		$batches = 0;
		while ( new CommentStatusMove()->process_batch() ) {
			self::assertLessThan( 10000, ++$batches );
		}

		self::assertSame( Comments::STATUS, get_comment( $approved )?->comment_approved );
		self::assertSame( Comments::STATUS, get_comment( $pending )?->comment_approved );
		self::assertSame( 'trash', get_comment( $trashed )?->comment_approved );
		self::assertSame( '1', get_comment( $public )?->comment_approved );
		self::assertSame( '1', get_comment( $note )?->comment_approved );
		self::assertSame( '2', get_post( $post )?->comment_count );
		self::assertFalse( get_option( 'dws_ic_v1_comments_cursor' ) );
		$cached = (array) wp_count_comments();
		\WC_Comments::delete_comments_count_cache();
		self::assertSame( (array) wp_count_comments(), $cached );

		wp_delete_post( $post, true );
	}
}
