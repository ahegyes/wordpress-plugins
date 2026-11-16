<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use Automattic\WooCommerce\Utilities\OrderUtil;

\defined( 'ABSPATH' ) || exit;

/**
 * Reads the orders and statuses of the order types that can be linked, and words those types: their singular names, the phrase that adds a child and their admin lists.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class OrderTypes {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	protected function __construct() {}

	// endregion

	// region METHODS

	/**
	 * Returns an order, or null when there is none or the plugin has not booted.
	 *
	 * The plugin's global functions and model stay defined where WooCommerce is inactive, so they read orders only through here, once the boot has checked WooCommerce.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $order The order, its post or its id.
	 *
	 * @return  \WC_Order|null
	 */
	public static function get_order( mixed $order ): ?\WC_Order {
		if ( 0 === did_action( 'linked_orders_for_woocommerce/boot/succeeded' ) ) {
			return null;
		}

		$order = wc_get_order( $order );
		return $order instanceof \WC_Order ? $order : null;
	}

	/**
	 * Returns WooCommerce's order statuses without their 'wc-' prefix, or none until the plugin has booted.
	 *
	 * The plugin's global functions stay defined where WooCommerce is inactive, so they read the statuses only through here, once the boot has checked WooCommerce.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string[]
	 */
	public static function get_statuses(): array {
		if ( 0 === did_action( 'linked_orders_for_woocommerce/boot/succeeded' ) ) {
			return array();
		}

		return \array_map( array( OrderUtil::class, 'remove_status_prefix' ), \array_keys( wc_get_order_statuses() ) );
	}

	/**
	 * Returns an order type's singular name, or its plural name where the type registered no singular one.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Post_Type $post_type The post type object of the order type.
	 *
	 * @return  string
	 */
	public static function get_singular_name( \WP_Post_Type $post_type ): string {
		$name = $post_type->labels->singular_name ?? null;
		return \is_string( $name ) ? $name : $post_type->label;
	}

	/**
	 * Returns the phrase that adds a linked child to an order of the given type, which the type registers as its `linked_orders_for_woocommerce_add_new_child` label.
	 *
	 * A child takes the type its creator makes, which only the type's registrant knows, so the registrant words the whole phrase, for its own translation.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Post_Type $post_type The post type object of the parent's order type.
	 *
	 * @return  string
	 */
	public static function get_add_child_phrase( \WP_Post_Type $post_type ): string {
		$phrase = $post_type->labels->linked_orders_for_woocommerce_add_new_child ?? null;
		return \is_string( $phrase ) && '' !== $phrase ? $phrase : __( 'Add new child order', 'linked-orders-for-woocommerce' );
	}

	/**
	 * Returns the URL of an order type's admin list, on the storage WooCommerce uses for orders.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Post_Type $post_type The post type object of the order type.
	 *
	 * @return  string
	 */
	public static function get_list_url( \WP_Post_Type $post_type ): string {
		return OrderUtil::custom_orders_table_usage_is_enabled() ? admin_url( 'admin.php?page=wc-orders' . ( 'shop_order' === $post_type->name ? '' : '--' . $post_type->name ) ) : admin_url( 'edit.php?post_type=' . $post_type->name );
	}

	// endregion
}
