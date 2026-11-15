<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Capabilities;
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
				Capabilities::INSERT,
				Capabilities::EDIT,
				Capabilities::EDIT_OTHERS,
				Capabilities::TRASH,
				Capabilities::TRASH_OTHERS,
				Capabilities::SEE,
				Capabilities::SEE_TABLE,
				Capabilities::SEE_METABOX,
				Capabilities::SEE_COLUMN,
				Capabilities::MANAGE_OPTIONS,
				Capabilities::INSERT_PRIVATE,
				Capabilities::SEE_PRIVATE,
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
			Capabilities::GRANTS
		);
		foreach ( Capabilities::GRANTS as $role => $capabilities ) {
			if ( \is_null( get_role( $role ) ) ) {
				continue; // The shop manager role exists only beside WooCommerce.
			}
			foreach ( $capabilities as $capability ) {
				self::assertTrue( get_role( $role )->has_cap( $capability ), "The $role role lacks $capability." );
			}
		}
		self::assertFalse( get_role( 'subscriber' )?->has_cap( Capabilities::SEE ) );
		self::assertFalse( get_role( 'editor' )?->has_cap( Capabilities::SEE_PRIVATE ) );
	}
}
