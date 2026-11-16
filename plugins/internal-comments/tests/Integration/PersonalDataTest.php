<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

// WordPress's own exporter and eraser query only the public statuses, which leave internal comments out.
#[CoversNothing]
final class PersonalDataTest extends TestCase {
	protected const string EMAIL = 'ic-privacy-staff@example.org';

	protected int $staff;

	protected int $post;

	#[Before]
	protected function create_fixtures(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$this->staff = (int) wp_insert_user(
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
