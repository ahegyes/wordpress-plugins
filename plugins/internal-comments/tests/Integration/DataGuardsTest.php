<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Internal comments stay hidden by their status, which WordPress's queries, counts, feeds and REST API leave out without the plugin's help.
#[CoversNothing]
final class DataGuardsTest extends TestCase {
	protected int $admin;

	protected int $other;

	protected int $post;

	#[Before]
	protected function create_post(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array( 'admin', 'other' ) as $user ) {
			$this->$user = (int) wp_insert_user(
				array(
					'user_login' => "ic-guards-$user",
					'user_pass'  => 'password',
					'user_email' => "ic-guards-$user@example.org",
					'role'       => 'administrator',
				)
			);
		}
		wp_set_current_user( $this->admin );
		$this->post = (int) wp_insert_post(
			array(
				'post_title'  => 'Guards',
				'post_status' => 'publish',
			)
		);
	}

	#[After]
	protected function delete_post(): void {
		wp_delete_post( $this->post, true );
		wp_set_current_user( 0 );
		wp_delete_user( $this->admin );
		wp_delete_user( $this->other );
	}

	public static function status_changes(): array {
		return array(
			'approve' => array( 'approve' ),
			'hold'    => array( 'hold' ),
			'spam'    => array( 'spam' ),
		);
	}

	#[DataProvider( 'status_changes' )]
	public function test_a_status_change_leaves_an_internal_comment_internal_and_notifies_no_one( string $status ): void {
		$comment = $this->comment();
		$mailed  = array();
		$record  = static function ( mixed $result, array $mail ) use ( &$mailed ): bool {
			$mailed[] = $mail['subject'];

			return true;
		};
		$notify  = get_option( 'comments_notify' );
		update_option( 'comments_notify', '1' );
		add_filter( 'pre_wp_mail', $record, 10, 2 );

		try {
			wp_set_comment_status( $comment, $status );
		} finally {
			remove_filter( 'pre_wp_mail', $record );
			update_option( 'comments_notify', $notify );
		}

		self::assertSame( DWS_IC_COMMENT_STATUS, get_comment( $comment )?->comment_approved );
		self::assertSame( array(), $mailed );
		self::assertSame( '0', get_post( $this->post )?->comment_count );
	}

	public function test_an_update_changes_only_the_content_of_an_internal_comment_and_records_the_previous_one(): void {
		$comment = $this->comment( 'First' );
		$other   = (int) wp_insert_post( array( 'post_title' => 'Other' ) );
		$before  = get_comment( $comment );

		wp_update_comment(
			array(
				'comment_ID'           => $comment,
				'comment_content'      => 'Second',
				'comment_approved'     => '1',
				'comment_date'         => '2001-01-01 00:00:00',
				'comment_author'       => 'Someone else',
				'comment_author_email' => 'else@example.org',
				'user_id'              => $this->other,
				'comment_post_ID'      => $other,
				'comment_type'         => 'comment',
			)
		);
		wp_delete_post( $other, true );

		$after = get_comment( $comment );
		self::assertSame( 'Second', $after?->comment_content );
		foreach ( array( 'comment_approved', 'comment_date', 'comment_date_gmt', 'comment_author', 'comment_author_email', 'user_id', 'comment_post_ID', 'comment_type', 'comment_parent' ) as $field ) {
			self::assertSame( $before?->$field, $after?->$field, "The update changed $field." );
		}
		$changes = get_comment_meta( $comment, '_dws_ic_change_id', false );
		self::assertCount( 1, $changes );
		self::assertSame( 'First', get_comment_meta( $comment, "_dws_ic_previous_content_{$changes[0]}", true ) );
		self::assertSame( (string) $this->admin, (string) get_comment_meta( $comment, "_dws_ic_changed_by_{$changes[0]}", true ) );
		self::assertEqualsWithDelta( \time(), (int) get_comment_meta( $comment, "_dws_ic_changed_time_{$changes[0]}", true ), 60 );
	}

	public function test_an_update_of_another_comment_records_no_revision(): void {
		$public = (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) );

		wp_update_comment(
			array(
				'comment_ID'      => $public,
				'comment_content' => 'Changed',
			)
		);

		self::assertSame( array(), get_comment_meta( $public, '_dws_ic_change_id', false ) );
	}

	public function test_an_internal_comment_stored_with_another_status_is_stored_internal(): void {
		$imported = (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $this->post,
				'comment_type'     => DWS_IC_COMMENT_TYPE,
				'comment_approved' => '1',
			)
		);

		self::assertSame( DWS_IC_COMMENT_STATUS, get_comment( $imported )?->comment_approved );
		self::assertSame( '0', get_post( $this->post )?->comment_count );
	}

	public function test_a_trashed_internal_comment_restores_internal(): void {
		$comment = $this->comment();
		$v1      = $this->comment();

		self::assertTrue( wp_trash_comment( $comment ) );
		self::assertTrue( wp_trash_comment( $v1 ) );
		update_comment_meta( $v1, '_wp_trash_meta_status', '1' ); // v1 stored internal comments approved.

		self::assertTrue( wp_untrash_comment( $comment ) );
		self::assertTrue( wp_untrash_comment( $v1 ) );
		self::assertSame( DWS_IC_COMMENT_STATUS, get_comment( $comment )?->comment_approved );
		self::assertSame( DWS_IC_COMMENT_STATUS, get_comment( $v1 )?->comment_approved );
		self::assertSame( '', get_comment_meta( $comment, '_wp_trash_meta_status', true ) );
	}

	public function test_a_restored_posts_internal_comments_restore_internal(): void {
		$comment = $this->comment();
		$v1      = $this->comment();
		$public  = (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) );

		wp_trash_post( $this->post );
		$statuses        = get_post_meta( $this->post, '_wp_trash_meta_comments_status', true );
		$statuses[ $v1 ] = '1'; // v1 stored internal comments approved.
		update_post_meta( $this->post, '_wp_trash_meta_comments_status', $statuses );
		wp_untrash_post( $this->post );

		self::assertSame( DWS_IC_COMMENT_STATUS, get_comment( $comment )?->comment_approved );
		self::assertSame( DWS_IC_COMMENT_STATUS, get_comment( $v1 )?->comment_approved );
		self::assertSame( '1', get_comment( $public )?->comment_approved );
		self::assertSame( '1', get_post( $this->post )?->comment_count );
	}

	public function test_only_a_comments_author_changes_whether_it_is_private(): void {
		$comment = $this->comment( 'Private', array( DWS_IC_META_PRIVATE => 'yes' ) );

		wp_set_current_user( $this->other );
		self::assertFalse( update_comment_meta( $comment, DWS_IC_META_PRIVATE, 'no' ) );
		self::assertFalse( delete_comment_meta( $comment, DWS_IC_META_PRIVATE ) );
		self::assertTrue( dws_ic_is_private_comment( $comment ) );

		wp_set_current_user( $this->admin );
		self::assertTrue( update_comment_meta( $comment, DWS_IC_META_PRIVATE, 'no' ) );
		self::assertFalse( dws_ic_is_private_comment( $comment ) );

		wp_set_current_user( 0 ); // Code that runs as no one, such as WP-CLI or a scheduled job, keeps its say.
		self::assertTrue( update_comment_meta( $comment, DWS_IC_META_PRIVATE, 'yes' ) );
	}

	public function test_wordpress_reads_leave_internal_comments_out_without_the_plugin(): void {
		$comment = $this->comment();
		$public  = (int) wp_insert_comment( array( 'comment_post_ID' => $this->post ) );
		$php     = \sprintf(
			'echo wp_json_encode( array( get_comments( array( "post_id" => %1$d, "fields" => "ids" ) ), get_comments( array( "post_id" => %1$d, "status" => "all", "fields" => "ids" ) ), (int) wp_count_comments( %1$d )->total_comments, (int) get_comments_number( %1$d ), rest_do_request( new WP_REST_Request( "GET", "/wp/v2/comments/%2$d" ) )->get_status(), wp_list_pluck( rest_do_request( WP_REST_Request::from_url( rest_url( "wp/v2/comments?post=%1$d" ) ) )->get_data(), "id" ) ) );',
			$this->post,
			$comment
		);

		\exec( 'wp --path=' . \escapeshellarg( ABSPATH ) . ' --skip-plugins=internal-comments eval ' . \escapeshellarg( $php ) . ' 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- The row needs a request without the plugin.

		self::assertSame( 0, $status, \implode( "\n", $output ) );
		self::assertSame( wp_json_encode( array( array( $public ), array( $public ), 1, 1, 401, array( $public ) ) ), (string) \end( $output ) );
	}

	protected function comment( string $content = 'Internal', array $meta = array() ): int {
		return (int) dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => $this->post,
				'comment_content' => $content,
				'comment_meta'    => $meta,
			)
		);
	}
}
