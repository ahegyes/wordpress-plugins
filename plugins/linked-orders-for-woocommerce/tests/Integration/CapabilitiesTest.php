<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CapabilitiesTest extends TestCase {
	public function test_the_capabilities_keep_their_v1_names(): void {
		self::assertSame( 'create_dws_linked_children', DWS_LOWC_CAP_CREATE_LINKED_CHILDREN );
		self::assertSame( 'see_dws_order_links', DWS_LOWC_CAP_SEE_ORDER_LINKS );
		self::assertSame( 'see_dws_linked_orders_metabox', DWS_LOWC_CAP_SEE_METABOX );
		self::assertSame( 'see_dws_linked_orders_column', DWS_LOWC_CAP_SEE_TABLE_COLUMN );
	}

	public function test_administrators_and_shop_managers_hold_every_capability_as_in_v1(): void {
		$all = array( DWS_LOWC_CAP_CREATE_LINKED_CHILDREN, DWS_LOWC_CAP_SEE_ORDER_LINKS, DWS_LOWC_CAP_SEE_METABOX, DWS_LOWC_CAP_SEE_TABLE_COLUMN );

		self::assertSame(
			array(
				'administrator' => $all,
				'shop_manager'  => $all,
			),
			DWS_LOWC_CAPABILITY_GRANTS
		);
		foreach ( DWS_LOWC_CAPABILITY_GRANTS as $role => $capabilities ) {
			foreach ( $capabilities as $capability ) {
				self::assertTrue( get_role( $role )?->has_cap( $capability ), "The $role role lacks $capability." );
			}
		}
		self::assertFalse( get_role( 'customer' )?->has_cap( DWS_LOWC_CAP_SEE_ORDER_LINKS ) );
	}
}
