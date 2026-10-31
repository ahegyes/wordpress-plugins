<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CapabilitiesTest extends TestCase {
	protected const array EVERY = array(
		'unlock_dws_lpm_via_user_profiles',
		'unlock_dws_lpm_via_orders',
		'unlock_dws_lpm_via_groups',
		'unlock_dws_lpm_via_wc_memberships',
	);

	public function test_the_capabilities_keep_their_v1_names(): void {
		self::assertSame( self::EVERY, array( DWS_LPMWC_CAP_UNLOCK_VIA_USER_PROFILES, DWS_LPMWC_CAP_UNLOCK_VIA_ORDERS, DWS_LPMWC_CAP_UNLOCK_VIA_GROUPS, DWS_LPMWC_CAP_UNLOCK_VIA_WC_MEMBERSHIPS ) );
	}

	public function test_each_role_receives_the_capabilities_v1_granted_it(): void {
		self::assertSame(
			array(
				'administrator' => self::EVERY,
				'shop_manager'  => array( DWS_LPMWC_CAP_UNLOCK_VIA_USER_PROFILES, DWS_LPMWC_CAP_UNLOCK_VIA_ORDERS ),
			),
			DWS_LPMWC_CAPABILITY_GRANTS
		);
		foreach ( DWS_LPMWC_CAPABILITY_GRANTS as $role => $capabilities ) {
			foreach ( $capabilities as $capability ) {
				self::assertTrue( get_role( $role )?->has_cap( $capability ), "The $role role lacks $capability." );
			}
		}
		self::assertFalse( get_role( 'shop_manager' )?->has_cap( DWS_LPMWC_CAP_UNLOCK_VIA_GROUPS ) );
		self::assertFalse( get_role( 'customer' )?->has_cap( DWS_LPMWC_CAP_UNLOCK_VIA_USER_PROFILES ) );
	}
}
