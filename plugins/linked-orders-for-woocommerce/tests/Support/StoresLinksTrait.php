<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Support;

// Stands in for links v1 stored without the checks a link makes now.
trait StoresLinksTrait {
	protected static function store_links( \WC_Order $order, int $depth, int $parent_id, array $child_ids ): void {
		$order->update_meta_data( '_dws_lo_depth', (string) $depth );
		$order->update_meta_data( '_dws_lo_parent', 0 === $parent_id ? '' : (string) $parent_id );
		$order->update_meta_data( '_dws_lo_children', $child_ids );
		$order->save();
	}
}
