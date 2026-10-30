<?php declare( strict_types=1 );
/**
 * Holds the plugin's capabilities, under the names v1 granted them, and the roles that receive them.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

defined( 'ABSPATH' ) || exit;

/**
 * The capability a user needs, beside edit_shop_orders on the parent order, to create a linked child order.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_CAP_CREATE_LINKED_CHILDREN = 'create_dws_linked_children';

/**
 * The capability a user needs to see any of the plugin's order screens.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_CAP_SEE_ORDER_LINKS = 'see_dws_order_links';

/**
 * The capability a user needs, beside the one to see order links, to see the linked orders meta box.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_CAP_SEE_METABOX = 'see_dws_linked_orders_metabox';

/**
 * The capability a user needs, beside the one to see order links, to see the depth column, filter and actions of the orders list.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_CAP_SEE_TABLE_COLUMN = 'see_dws_linked_orders_column';

/**
 * The capabilities each role receives, as v1 granted them.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, list<string>>
 */
const DWS_LOWC_CAPABILITY_GRANTS = array(
	'administrator' => array( DWS_LOWC_CAP_CREATE_LINKED_CHILDREN, DWS_LOWC_CAP_SEE_ORDER_LINKS, DWS_LOWC_CAP_SEE_METABOX, DWS_LOWC_CAP_SEE_TABLE_COLUMN ),
	'shop_manager'  => array( DWS_LOWC_CAP_CREATE_LINKED_CHILDREN, DWS_LOWC_CAP_SEE_ORDER_LINKS, DWS_LOWC_CAP_SEE_METABOX, DWS_LOWC_CAP_SEE_TABLE_COLUMN ),
);
