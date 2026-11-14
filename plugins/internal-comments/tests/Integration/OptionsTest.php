<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class OptionsTest extends TestCase {
	public function test_each_setting_has_a_row_of_its_own_named_whole(): void {
		self::assertSame( 'dws_ic_post_types', DWS_IC_OPTION_POST_TYPES );
		self::assertSame( 'dws_ic_private_comments_enabled', DWS_IC_OPTION_PRIVATE_COMMENTS );
		self::assertSame( 'dws_ic_delete_data', DWS_IC_OPTION_DELETE_DATA );
	}

	public function test_each_row_starts_with_the_value_v1_stored_as_its_default(): void {
		self::assertSame(
			array(
				DWS_IC_OPTION_POST_TYPES       => array(),
				DWS_IC_OPTION_PRIVATE_COMMENTS => 'no',
				DWS_IC_OPTION_DELETE_DATA      => 'no',
			),
			DWS_IC_OPTION_DEFAULTS
		);
	}

	public function test_v1s_groups_map_each_of_their_fields_to_its_row(): void {
		self::assertSame(
			array(
				'general' => array(
					'post-types'              => DWS_IC_OPTION_POST_TYPES,
					'enable-private-comments' => DWS_IC_OPTION_PRIVATE_COMMENTS,
				),
				'plugin'  => array(
					'remove-data-uninstall' => DWS_IC_OPTION_DELETE_DATA,
				),
			),
			DWS_IC_V1_SETTINGS
		);
	}
}
