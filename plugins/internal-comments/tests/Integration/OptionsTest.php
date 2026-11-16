<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Options;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class OptionsTest extends TestCase {
	public function test_each_setting_has_a_row_of_its_own_named_whole(): void {
		self::assertSame( 'dws_ic_post_types', Options::POST_TYPES );
		self::assertSame( 'dws_ic_private_comments_enabled', Options::PRIVATE_COMMENTS );
		self::assertSame( 'dws_ic_delete_data', Options::DELETE_DATA );
	}

	public function test_each_row_starts_with_the_value_v1_stored_as_its_default(): void {
		self::assertSame(
			array(
				Options::POST_TYPES       => array(),
				Options::PRIVATE_COMMENTS => 'no',
				Options::DELETE_DATA      => 'no',
			),
			Options::DEFAULTS
		);
	}

	public function test_v1s_groups_map_each_of_their_fields_to_its_row(): void {
		self::assertSame( array( 'general', 'post-types', Options::POST_TYPES ), Options::find_v1_setting( 'post-types', 'general' ) );
		self::assertSame( array( 'general', 'enable-private-comments', Options::PRIVATE_COMMENTS ), Options::find_v1_setting( 'enable-private-comments', 'general' ) );
		self::assertSame( array( 'plugin', 'remove-data-uninstall', Options::DELETE_DATA ), Options::find_v1_setting( 'remove-data-uninstall', 'plugin' ) );
	}
}
