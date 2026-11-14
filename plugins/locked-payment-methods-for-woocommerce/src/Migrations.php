<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods;

use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\GroupsStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\OrderStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\UserProfileStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\WooCommerceMembershipsStrategy;

\defined( 'ABSPATH' ) || exit;

/**
 * Holds the plugin's migration steps, which bring its stored data to the revision its code expects.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Migrations {
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
	 * Returns the migration steps, append-only, where index 0 brings the data to revision 1.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<callable(): void>
	 */
	public static function get_steps(): array {
		return array(
			self::add_settings( ... ),
			self::move_user_grants( ... ),
			self::move_order_grants( ... ),
			self::move_group_grants( ... ),
			self::move_plan_grants( ... ),
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Stores each setting the site has not stored yet.
	 *
	 * It takes v1's row where that holds a value of the setting's kind, and the setting's default otherwise, and leaves v1's rows in place, so a site that goes back to v1 still finds its settings.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  \RuntimeException Thrown when a setting could not be stored.
	 *
	 * @return  void
	 */
	protected static function add_settings(): void {
		foreach ( Options::V1_SETTINGS as $group => $fields ) {
			foreach ( $fields as $id => $key ) {
				if ( false !== get_option( $key ) ) {
					continue;
				}

				$default = Options::DEFAULTS[ $key ];
				$v1      = get_option( "dws-wc-lpm_{$group}_$id", null );
				if ( \is_array( $default ) ) {
					$value = \is_array( $v1 ) ? \array_values( \array_filter( $v1, 'is_string' ) ) : $default;
				} else {
					$value = wc_bool_to_string( \is_null( $v1 ) ? $default : ( \filter_var( $v1, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) ?? $default ) ); // filter_var() reads null as false, so an absent row takes the default before it.
				}

				if ( ! add_option( $key, $value, '', true ) ) {
					throw new \RuntimeException( "The option $key could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
				}
			}
		}
	}

	/**
	 * Moves v1's user grants, one global 'yes' row per payment method, into every site's list beside the grants it holds, so they keep v1's reach across a network, then deletes every v1 grant row; a run that stops part way converges on the next, since each list keeps what an earlier run merged.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  \wpdb $wpdb WordPress's database access.
	 *
	 * @throws  \RuntimeException Thrown when v1's grants could not be read, a user's grants could not be stored or a v1 grant could not be deleted.
	 *
	 * @return  void
	 */
	protected static function move_user_grants(): void {
		global $wpdb;

		$prefix = 'dws_wc_lpm_grant_access_';
		$wpdb->flush();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT user_id, meta_key, meta_value FROM %i WHERE meta_key LIKE %s', $wpdb->usermeta, $wpdb->esc_like( $prefix ) . '%' ) ?? '', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- v1's keys carry the payment method's id, so no metadata API lists them.
		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'The v1 grants could not be read.' );
		}

		$grants = array();
		$keys   = array();
		foreach ( (array) $rows as $row ) {
			// The column's collation also matches keys that differ from v1's in letter case.
			if ( ! \is_array( $row ) || ! \is_numeric( $row['user_id'] ?? null ) || ! \is_string( $row['meta_key'] ?? null ) || ! \str_starts_with( $row['meta_key'], $prefix ) ) {
				continue;
			}

			$user_id                              = (int) $row['user_id'];
			$keys[ $user_id ][ $row['meta_key'] ] = true;
			if ( 'yes' === ( $row['meta_value'] ?? null ) ) {
				$grants[ $user_id ][] = \substr( $row['meta_key'], \strlen( $prefix ) );
			}
		}

		$sites = is_multisite() ? get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) : array( get_current_blog_id() );
		foreach ( $grants as $user_id => $ids ) {
			foreach ( $sites as $site_id ) {
				$key     = $wpdb->get_blog_prefix( $site_id ) . UserProfileStrategy::USER_OPTION;
				$current = get_user_meta( $user_id, $key, true );
				$merged  = \array_values( \array_unique( \array_merge( \is_array( $current ) ? \array_filter( $current, 'is_string' ) : array(), $ids ) ) );
				if ( $merged !== $current && false === update_user_meta( $user_id, $key, wp_slash( $merged ) ) ) {
					throw new \RuntimeException( "The payment methods unlocked for user $user_id could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
				}
			}
		}

		foreach ( $keys as $user_id => $user_keys ) {
			foreach ( \array_keys( $user_keys ) as $meta_key ) {
				if ( ! delete_user_meta( $user_id, $meta_key ) ) {
					throw new \RuntimeException( "The v1 grant $meta_key of user $user_id could not be deleted." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
				}
			}
		}
	}

	/**
	 * Moves v1's order grants, one 'yes' row per payment method, into each order's list beside the grants it holds, through WooCommerce's order data, so either order storage keeps them, then deletes every v1 grant row of the order.
	 *
	 * The list is saved before v1's rows are deleted, because one save deletes an order's meta before it adds any, so a failed write would lose the grants a retry needs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  \wpdb $wpdb WordPress's database access.
	 *
	 * @throws  \RuntimeException Thrown when v1's order grants could not be read, or an order's grants could not be stored or its v1 rows deleted.
	 *
	 * @return  void
	 */
	protected static function move_order_grants(): void {
		global $wpdb;

		$prefix = '_dws_wc_lpm_grant_access_';
		$ids    = array();
		$tables = array(
			$wpdb->postmeta                  => 'post_id',
			$wpdb->prefix . 'wc_orders_meta' => 'order_id',
		);
		foreach ( $tables as $table => $column ) {
			$wpdb->flush();
			$listed = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The order tables exist only once WooCommerce created them.
			if ( \is_null( $listed ) && '' !== $wpdb->last_error ) {
				throw new \RuntimeException( 'The v1 order grants could not be read.' );
			}
			if ( $table !== $listed ) {
				continue;
			}

			$found = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT %i FROM %i WHERE meta_key LIKE %s', $column, $table, $wpdb->esc_like( $prefix ) . '%' ) ?? '' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- v1's keys carry the payment method's id, so no order query lists them.
			if ( '' !== $wpdb->last_error ) {
				throw new \RuntimeException( 'The v1 order grants could not be read.' );
			}
			$ids = \array_merge( $ids, wp_parse_id_list( $found ) );
		}

		foreach ( \array_unique( $ids ) as $order_id ) {
			$order = wc_get_order( $order_id );
			$rows  = $order instanceof \WC_Order ? self::get_v1_order_grant_rows( $order ) : array();
			if ( array() === $rows ) {
				continue;
			}

			$granted = OrderStrategy::get_unlocked_ids( $order_id );
			foreach ( $rows as $key => $value ) {
				if ( 'yes' === $value ) {
					$granted[] = \substr( $key, \strlen( $prefix ) );
				}
			}
			$granted = \array_values( \array_unique( $granted ) );
			$order->update_meta_data( OrderStrategy::META_KEY, $granted );
			$order->save();
			if ( OrderStrategy::get_unlocked_ids( $order_id ) !== $granted ) {
				throw new \RuntimeException( "The payment methods unlocked for order $order_id could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}

			foreach ( \array_keys( $rows ) as $key ) {
				$order->delete_meta_data( $key );
			}
			$order->save();
			$order = wc_get_order( $order_id );
			if ( $order instanceof \WC_Order && array() !== self::get_v1_order_grant_rows( $order ) ) {
				throw new \RuntimeException( "The v1 grants of order $order_id could not be deleted." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}

	/**
	 * Returns an order's v1 grant rows, by meta key.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WC_Order $order The order.
	 *
	 * @return  array<string, mixed>
	 */
	protected static function get_v1_order_grant_rows( \WC_Order $order ): array {
		$rows = array();
		foreach ( $order->get_meta_data() as $meta ) {
			$data = $meta instanceof \WC_Meta_Data ? $meta->get_data() : array();
			if ( \is_string( $data['key'] ?? null ) && \str_starts_with( $data['key'], '_dws_wc_lpm_grant_access_' ) ) {
				$rows[ $data['key'] ] = $data['value'] ?? null;
			}
		}

		return $rows;
	}

	/**
	 * Moves v1's Groups grants, one option per group, into the map of every group's grants beside the grants it holds, then deletes v1's options.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  \wpdb $wpdb WordPress's database access.
	 *
	 * @throws  \RuntimeException Thrown when v1's options could not be read, the map could not be stored or a v1 option could not be deleted.
	 *
	 * @return  void
	 */
	protected static function move_group_grants(): void {
		global $wpdb;

		$prefix = 'dws-wc-lpm_groups-settings_';
		$wpdb->flush();
		$names = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( $prefix ) . '%' ) ?? '' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- v1's option names carry the group's id, so no options API lists them.
		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'The v1 Groups grants could not be read.' );
		}

		$groups = GroupsStrategy::get_unlocked_ids();
		$moved  = array();
		foreach ( $names as $name ) {
			if ( ! \is_string( $name ) || ! \str_starts_with( $name, $prefix ) || ! \ctype_digit( \substr( $name, \strlen( $prefix ) ) ) ) {
				continue;
			}

			$id            = (int) \substr( $name, \strlen( $prefix ) );
			$stored        = get_option( $name );
			$groups[ $id ] = \array_values( \array_unique( \array_merge( $groups[ $id ] ?? array(), \is_array( $stored ) ? \array_filter( $stored, 'is_string' ) : array() ) ) );
			$moved[]       = $name;
		}
		if ( array() === $moved ) {
			return;
		}

		$groups = \array_filter( $groups );
		if ( GroupsStrategy::get_unlocked_ids() !== $groups && ! update_option( GroupsStrategy::OPTION, $groups, true ) ) {
			throw new \RuntimeException( 'The payment methods unlocked for Groups groups could not be stored.' );
		}
		foreach ( $moved as $name ) {
			if ( ! delete_option( $name ) ) {
				throw new \RuntimeException( "The v1 option $name could not be deleted." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}

	/**
	 * Moves v1's WooCommerce Memberships grants from each plan's v1 meta to the plugin's key beside the grants it holds, then deletes the v1 meta; it reads the post meta table itself, since Memberships may be inactive and its plan type unregistered.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  \wpdb $wpdb WordPress's database access.
	 *
	 * @throws  \RuntimeException Thrown when v1's plan meta could not be read, a plan's grants could not be stored or v1's meta could not be deleted.
	 *
	 * @return  void
	 */
	protected static function move_plan_grants(): void {
		global $wpdb;

		$v1_key = '_dws_wc_lpm_unlocked_payment_methods';
		$wpdb->flush();
		$plans = wp_parse_id_list( $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT post_id FROM %i WHERE meta_key = %s', $wpdb->postmeta, $v1_key ) ?? '' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Memberships' plan type may be unregistered, so no post query lists the plans.
		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'The v1 plan grants could not be read.' );
		}

		foreach ( $plans as $plan_id ) {
			$v1     = get_post_meta( $plan_id, $v1_key, true );
			$merged = \array_values( \array_unique( \array_merge( WooCommerceMembershipsStrategy::get_unlocked_ids( $plan_id ), \is_array( $v1 ) ? \array_filter( $v1, 'is_string' ) : array() ) ) );
			if ( WooCommerceMembershipsStrategy::get_unlocked_ids( $plan_id ) !== $merged && false === update_post_meta( $plan_id, WooCommerceMembershipsStrategy::META_KEY, wp_slash( $merged ) ) ) {
				throw new \RuntimeException( "The payment methods unlocked for plan $plan_id could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
			if ( ! delete_post_meta( $plan_id, $v1_key ) ) {
				throw new \RuntimeException( "The v1 grants of plan $plan_id could not be deleted." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}

	// endregion
}
