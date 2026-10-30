<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CapabilitiesTest extends TestCase {
	protected const array EVERY = array(
		'insert_dws_internal_comments',
		'edit_dws_internal_comments',
		'edit_others_dws_internal_comments',
		'trash_dws_internal_comments',
		'trash_others_dws_internal_comments',
		'see_dws_internal_comments',
		'see_dws_internal_comments_table',
		'see_dws_internal_comments_metabox',
		'see_dws_internal_comments_column',
		'manage_dws_internal_comments_options',
		'insert_private_dws_internal_comments',
		'see_private_dws_internal_comments',
	);

	protected const array CONTRIBUTING = array(
		'insert_dws_internal_comments',
		'see_dws_internal_comments',
		'see_dws_internal_comments_metabox',
		'see_dws_internal_comments_column',
		'insert_private_dws_internal_comments',
	);

	public function test_the_capabilities_keep_their_v1_names(): void {
		self::assertSame(
			self::EVERY,
			array(
				DWS_IC_CAP_INSERT,
				DWS_IC_CAP_EDIT,
				DWS_IC_CAP_EDIT_OTHERS,
				DWS_IC_CAP_TRASH,
				DWS_IC_CAP_TRASH_OTHERS,
				DWS_IC_CAP_SEE,
				DWS_IC_CAP_SEE_TABLE,
				DWS_IC_CAP_SEE_METABOX,
				DWS_IC_CAP_SEE_COLUMN,
				DWS_IC_CAP_MANAGE_OPTIONS,
				DWS_IC_CAP_INSERT_PRIVATE,
				DWS_IC_CAP_SEE_PRIVATE,
			)
		);
	}

	public function test_each_role_receives_the_capabilities_v1_granted_it(): void {
		self::assertSame(
			array(
				'administrator' => self::EVERY,
				'editor'        => self::CONTRIBUTING,
				'author'        => self::CONTRIBUTING,
				'contributor'   => self::CONTRIBUTING,
				'shop_manager'  => self::CONTRIBUTING,
			),
			DWS_IC_CAPABILITY_GRANTS
		);
		foreach ( DWS_IC_CAPABILITY_GRANTS as $role => $capabilities ) {
			if ( \is_null( get_role( $role ) ) ) {
				continue; // The shop manager role exists only beside WooCommerce.
			}
			foreach ( $capabilities as $capability ) {
				self::assertTrue( get_role( $role )->has_cap( $capability ), "The $role role lacks $capability." );
			}
		}
		self::assertFalse( get_role( 'subscriber' )?->has_cap( DWS_IC_CAP_SEE ) );
		self::assertFalse( get_role( 'editor' )?->has_cap( DWS_IC_CAP_SEE_PRIVATE ) );
	}
}
