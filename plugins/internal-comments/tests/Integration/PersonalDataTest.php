<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Comments;
use DeepWebSolutions\InternalComments\Tests\Support\InsertsUsersTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

// WordPress's own exporter and eraser query only the public statuses, which leave internal comments out.
#[CoversNothing]
final class PersonalDataTest extends TestCase {
	use InsertsUsersTrait;

	protected const string EMAIL = 'ic-privacy-staff@example.org';

	protected int $staff;

	protected int $post;

	#[Before]
	protected function create_fixtures(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$this->staff = self::insert_user(
			array(
				'user_login'   => 'ic-privacy-staff',
				'user_pass'    => 'password',
				'user_email'   => self::EMAIL,
				'user_url'     => 'https://staff.example.org',
				'display_name' => 'Staff Member',
				'role'         => 'editor',
			)
		);
		$this->post  = (int) wp_insert_post(
			array(
				'post_title'  => 'Privacy',
				'post_status' => 'publish',
			)
		);
	}

	#[After]
	protected function delete_fixtures(): void {
		wp_delete_post( $this->post, true );
		wp_delete_user( $this->staff );
	}

	public function test_the_exporter_lists_each_internal_comment_its_author_wrote_the_trash_included(): void {
		$kept    = $this->comment( 'Kept' );
		$trashed = $this->comment( 'Trashed' );
		wp_trash_comment( $trashed );
		$this->comment( 'Someone else', 'someone@example.org' );
		$this->public_comment();

		$export = \call_user_func( $this->registered( 'wp_privacy_personal_data_exporters' ), self::EMAIL, 1 );

		self::assertTrue( $export['done'] );
		self::assertSame( array( "internal-comment-$kept", "internal-comment-$trashed" ), \array_column( $export['data'], 'item_id' ) );
		self::assertSame( array( 'Staff Member', self::EMAIL, 'https://staff.example.org', 'Kept' ), \array_values( \array_diff( \array_column( $export['data'][0]['data'], 'value' ), array( get_comment( $kept )?->comment_date ) ) ) );
	}

	// As WordPress's own eraser does, it anonymizes the author and keeps the text, which the comment's thread still needs.
	public function test_the_eraser_anonymizes_the_author_of_each_internal_comment_and_keeps_its_text(): void {
		$kept    = $this->comment( 'Kept' );
		$trashed = $this->comment( 'Trashed' );
		wp_trash_comment( $trashed );
		$public = $this->public_comment();
		$erase  = $this->registered( 'wp_privacy_personal_data_erasers' );

		$erased = \call_user_func( $erase, self::EMAIL, 1 );
		$again  = \call_user_func( $erase, self::EMAIL, 2 );

		self::assertSame( array( true, false, true ), array( $erased['items_removed'], $erased['items_retained'], $erased['done'] ) );
		self::assertSame( array( false, false, true ), array( $again['items_removed'], $again['items_retained'], $again['done'] ) );
		foreach ( array( $kept, $trashed ) as $comment ) {
			$stored = get_comment( $comment );
			self::assertInstanceOf( \WP_Comment::class, $stored );
			self::assertSame( array( 'Anonymous', '', '', '0' ), array( $stored->comment_author, $stored->comment_author_email, $stored->comment_author_url, $stored->user_id ) );
		}
		self::assertSame( 'Kept', get_comment( $kept )?->comment_content );
		self::assertSame( 'trash', get_comment( $trashed )?->comment_approved );
		self::assertSame( self::EMAIL, get_comment( $public )?->comment_author_email, "WordPress's own eraser handles public comments." );
	}

	// A revision names the user who made it, on any author's comment, and 1.2.4 began its change id with that user's id, so erasing a user anonymizes every revision they made and leaves no key that holds their id.
	public function test_the_eraser_anonymizes_the_revisions_its_user_made_on_any_comment(): void {
		$own    = $this->comment( 'Own' );
		$other  = (int) dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => $this->post,
				'comment_content' => 'Other',
			),
			get_user_by( 'id', 1 ) ?: null
		);
		$erase  = $this->registered( 'wp_privacy_personal_data_erasers' );
		$pluck  = static fn ( int $comment, string $field ): array => wp_list_pluck( new \DWS_Internal_Comment( $comment )->get_revisions(), $field );
		$keys   = static fn ( int $comment ): array => \array_keys( (array) get_comment_meta( $comment ) );
		$legacy = \uniqid( (string) $this->staff, true );
		add_comment_meta( $own, Comments::META_CHANGE_ID, $legacy );
		add_comment_meta( $own, Comments::META_PREVIOUS_CONTENT . $legacy, 'Legacy' );
		add_comment_meta( $own, Comments::META_CHANGED_BY . $legacy, $this->staff );
		add_comment_meta( $own, Comments::META_CHANGED_TIME . $legacy, \time() );
		wp_set_current_user( $this->staff );
		wp_update_comment(
			array(
				'comment_ID'      => $own,
				'comment_content' => 'Own, changed',
			)
		);
		wp_update_comment(
			array(
				'comment_ID'      => $other,
				'comment_content' => 'Other, changed',
			)
		);
		wp_set_current_user( 1 );
		wp_update_comment(
			array(
				'comment_ID'      => $other,
				'comment_content' => 'Other, changed again',
			)
		);

		$erased = \call_user_func( $erase, self::EMAIL, 1 );

		self::assertTrue( $erased['items_removed'] );
		self::assertSame( array( 0, 0 ), $pluck( $own, 'user_id' ) );
		self::assertSame( array( 1, 0 ), $pluck( $other, 'user_id' ) );
		self::assertSame( array( 'Own', 'Legacy' ), $pluck( $own, 'content' ) );
		self::assertSame( array( 'Other, changed', 'Other' ), $pluck( $other, 'content' ) );
		foreach ( array( $own, $other ) as $comment ) {
			foreach ( (array) get_comment_meta( $comment, Comments::META_CHANGE_ID, false ) as $change_id ) {
				self::assertTrue( wp_is_uuid( (string) $change_id, 4 ), (string) $change_id );
			}
			foreach ( $keys( $comment ) as $key ) {
				self::assertStringNotContainsString( $legacy, $key );
			}
		}
	}

	// The eraser anonymizes a page of revisions at a time, as it does comments, so a user who made many edits is erased over several calls.
	public function test_the_eraser_anonymizes_revisions_a_page_at_a_time(): void {
		global $wpdb;
		$comment = (int) dws_ic_create_internal_comment( array( 'comment_post_ID' => $this->post ), get_user_by( 'id', 1 ) ?: null );
		$rows    = array();
		for ( $revision = 0; $revision <= 500; $revision++ ) {
			$rows[] = $wpdb->prepare( '( %d, %s, %s )', $comment, Comments::META_CHANGED_BY . wp_generate_uuid4(), (string) $this->staff );
		}
		$wpdb->query( "INSERT INTO $wpdb->commentmeta ( comment_id, meta_key, meta_value ) VALUES " . \implode( ', ', $rows ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Each row is prepared above, and 501 revisions through the meta API would take thousands of queries.
		wp_cache_delete( $comment, 'comment_meta' );
		$erase  = $this->registered( 'wp_privacy_personal_data_erasers' );
		$staff  = array( (string) $this->staff );
		$staffs = static fn (): int => \count( \array_filter( (array) get_comment_meta( $comment ), static fn ( array $values ): bool => $staff === $values ) );

		$first = \call_user_func( $erase, self::EMAIL, 1 );
		$left  = $staffs();
		$last  = \call_user_func( $erase, self::EMAIL, 2 );

		self::assertSame( array( true, false ), array( $first['items_removed'], $first['done'] ) );
		self::assertSame( 1, $left );
		self::assertSame( array( true, true ), array( $last['items_removed'], $last['done'] ) );
		self::assertSame( 0, $staffs() );
	}

	protected function registered( string $hook ): callable {
		$registered = apply_filters( $hook, array() );
		self::assertIsArray( $registered );
		self::assertIsString( $registered['internal-comments']['exporter_friendly_name'] ?? $registered['internal-comments']['eraser_friendly_name'] ?? null );
		self::assertIsCallable( $registered['internal-comments']['callback'] ?? null );
		return $registered['internal-comments']['callback'];
	}

	protected function comment( string $content, string $email = self::EMAIL ): int {
		return (int) dws_ic_create_internal_comment(
			array(
				'comment_post_ID'      => $this->post,
				'comment_content'      => $content,
				'comment_author_email' => $email,
			),
			get_user_by( 'id', $this->staff ) ?: null
		);
	}

	protected function public_comment(): int {
		return (int) wp_insert_comment(
			array(
				'comment_post_ID'      => $this->post,
				'comment_author'       => 'Staff Member',
				'comment_author_email' => self::EMAIL,
			)
		);
	}
}
