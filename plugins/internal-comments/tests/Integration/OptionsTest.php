<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class OptionsTest extends TestCase {
	public function test_the_settings_keep_the_rows_and_fields_v1_stored_them_under(): void {
		self::assertSame( 'dws-internal-comments_general', DWS_IC_OPTION_GENERAL );
		self::assertSame( 'dws-internal-comments_plugin', DWS_IC_OPTION_PLUGIN );
		self::assertSame( 'post-types', DWS_IC_SETTING_POST_TYPES );
		self::assertSame( 'enable-private-comments', DWS_IC_SETTING_PRIVATE_COMMENTS );
		self::assertSame( 'remove-data-uninstall', DWS_IC_SETTING_DELETE_DATA );
	}

	public function test_each_row_starts_with_the_values_v1_stored_as_its_defaults(): void {
		self::assertSame(
			array(
				DWS_IC_OPTION_GENERAL => array(
					DWS_IC_SETTING_POST_TYPES       => array(),
					DWS_IC_SETTING_PRIVATE_COMMENTS => 'no',
				),
				DWS_IC_OPTION_PLUGIN  => array(
					DWS_IC_SETTING_DELETE_DATA => 'no',
				),
			),
			DWS_IC_SETTING_DEFAULTS
		);
	}
}
