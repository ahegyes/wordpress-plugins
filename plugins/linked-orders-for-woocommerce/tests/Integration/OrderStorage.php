<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use PHPUnit\Framework\Attributes\After;

// WooCommerce reads these options on every order load, so pre_option filters move this process to another storage, with or without the order tables' data caching, without changing the tests site.
trait OrderStorage {
	protected array $orders = array();

	protected array $storage_filters = array();

	public static function storages(): array {
		return array(
			'posts'           => array( false, false ),
			'posts-with-sync' => array( false, true ),
			'hpos'            => array( true, false ),
			'hpos-with-sync'  => array( true, true ),
		);
	}

	protected function use_storage( bool $hpos, bool $sync, bool $caching = false ): void {
		$this->storage_filters = array(
			'pre_option_woocommerce_custom_orders_table_enabled'           => static fn (): string => $hpos ? 'yes' : 'no',
			'pre_option_woocommerce_custom_orders_table_data_sync_enabled' => static fn (): string => $sync ? 'yes' : 'no',
			'pre_option_woocommerce_hpos_datastore_caching_enabled'        => static fn (): string => $caching ? 'yes' : 'no',
		);
		foreach ( $this->storage_filters as $hook => $filter ) {
			add_filter( $hook, $filter );
		}
	}

	protected function create_order( array $args = array() ): \WC_Order {
		$order = wc_create_order( $args + array( 'status' => 'pending' ) );
		self::assertInstanceOf( \WC_Order::class, $order );
		$this->orders[] = $order->get_id();

		return $order;
	}

	// The orders go before the filters, because an order deletes from the storage it was loaded from.
	#[After]
	protected function delete_orders_and_restore_storage(): void {
		foreach ( $this->orders as $id ) {
			$order = wc_get_order( $id );
			if ( $order instanceof \WC_Order ) {
				$order->delete( true );
			}
		}
		$this->orders = array();

		foreach ( $this->storage_filters as $hook => $filter ) {
			remove_filter( $hook, $filter );
		}
		$this->storage_filters = array();
	}
}
