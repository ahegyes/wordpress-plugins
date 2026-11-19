<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods;

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
			self::copy_v1_settings( ... ),
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
	 * Copies each of v1's settings the site has not stored under its own name yet, where v1's row holds a value of the setting's kind, and leaves v1's rows in place, so a site that goes back to v1 still finds its settings.
	 *
	 * The seeding step that follows stores the default of each setting this step leaves unstored.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  \RuntimeException Thrown when a setting could not be stored.
	 *
	 * @return  void
	 */
	protected static function copy_v1_settings(): void {
		$settings = array(
			'dws-wc-lpm_general_locked-payment-methods' => array( 'dws_lpmwc_locked_payment_methods', 'list' ),
			'dws-wc-lpm_general_unlock-via-user-role'   => array( 'dws_lpmwc_unlock_via_user_roles', 'bool' ),
			'dws-wc-lpm_general_full-access-user-roles' => array( 'dws_lpmwc_full_access_user_roles', 'list' ),
			'dws-wc-lpm_general_unlock-via-user-meta'   => array( 'dws_lpmwc_unlock_via_user_profiles', 'bool' ),
			'dws-wc-lpm_general_unlock-via-order-meta'  => array( 'dws_lpmwc_unlock_via_orders', 'bool' ),
			'dws-wc-lpm_integrations_unlock-via-groups' => array( 'dws_lpmwc_unlock_via_groups', 'bool' ),
			'dws-wc-lpm_integrations_unlock-via-wc-memberships' => array( 'dws_lpmwc_unlock_via_wc_memberships', 'bool' ),
			'dws-wc-lpm_plugin_remove-data-uninstall'   => array( 'dws_lpmwc_delete_data', 'bool' ),
		);
		foreach ( $settings as $v1_key => list( $key, $kind ) ) {
			$v1 = get_option( $v1_key, null );
			if ( false !== get_option( $key ) || \is_null( $v1 ) ) {
				continue;
			}

			if ( 'list' === $kind ) {
				$v1    = \is_string( $v1 ) && '' !== $v1 ? array( $v1 ) : $v1; // v1 read a list stored as one string as that one id.
				$value = \is_array( $v1 ) ? \array_values( \array_filter( $v1, 'is_string' ) ) : null;
			} else {
				$enabled = \filter_var( $v1, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE );
				$value   = \is_null( $enabled ) ? null : ( $enabled ? 'yes' : 'no' );
			}

			if ( ! \is_null( $value ) && ! add_option( $key, $value, '', true ) ) {
				throw new \RuntimeException( "The option $key could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}

	/**
	 * Stores each setting's default in an autoloaded row of its own and leaves a stored setting as it is.
	 *
	 * Each release that adds settings lists this step again, so that existing sites store the new defaults.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  \RuntimeException Thrown when a default could not be stored.
	 *
	 * @return  void
	 */
	protected static function add_settings(): void {
		wp_prime_option_caches( \array_keys( Options::DEFAULTS ) );
		foreach ( Options::DEFAULTS as $key => $value ) {
			if ( false === get_option( $key ) && ! add_option( $key, $value, '', true ) ) {
				throw new \RuntimeException( "The option $key could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}

	/**
	 * Moves v1's user grants, one global 'yes' row per payment method, into every site's list beside the grants it holds, then deletes every v1 grant row.
	 *
	 * Every site receives them, so they keep v1's reach across a network. A run that stops part way converges on the next, since each list keeps what an earlier run merged.
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
				$key     = $wpdb->get_blog_prefix( $site_id ) . 'dws_lpmwc_unlocked_payment_methods';
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

			$granted = self::get_strings( $order->get_meta( '_dws_lpmwc_unlocked_payment_methods' ) );
			foreach ( $rows as $key => $value ) {
				if ( 'yes' === $value ) {
					$granted[] = \substr( $key, \strlen( $prefix ) );
				}
			}
			$granted = \array_values( \array_unique( $granted ) );
			$order->update_meta_data( '_dws_lpmwc_unlocked_payment_methods', $granted );
			$order->save();
			$stored = wc_get_order( $order_id );
			if ( ! $stored instanceof \WC_Order || self::get_strings( $stored->get_meta( '_dws_lpmwc_unlocked_payment_methods' ) ) !== $granted ) {
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
	 * Moves each v1 Groups option, one per group, to the group's own option beside the grants it holds, then deletes v1's option.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  \wpdb $wpdb WordPress's database access.
	 *
	 * @throws  \RuntimeException Thrown when v1's options could not be read, a group's option could not be stored or a v1 option could not be deleted.
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

		foreach ( $names as $name ) {
			if ( ! \is_string( $name ) || ! \str_starts_with( $name, $prefix ) || ! \ctype_digit( \substr( $name, \strlen( $prefix ) ) ) ) {
				continue;
			}

			$option  = 'dws_lpmwc_group_' . (int) \substr( $name, \strlen( $prefix ) ) . '_unlocked_payment_methods';
			$granted = self::get_strings( get_option( $option, array() ) );
			$merged  = \array_values( \array_unique( \array_merge( $granted, self::get_strings( get_option( $name ) ) ) ) );
			if ( $granted !== $merged && ! update_option( $option, $merged, true ) ) {
				throw new \RuntimeException( "The option $option could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
			if ( ! delete_option( $name ) ) {
				throw new \RuntimeException( "The v1 option $name could not be deleted." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}

	/**
	 * Moves v1's WooCommerce Memberships grants from each plan's v1 meta to the plugin's key beside the grants it holds, then deletes the v1 meta.
	 *
	 * It reads the post meta table itself, since Memberships may be inactive and its plan type unregistered.
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
			$v1      = get_post_meta( $plan_id, $v1_key, true );
			$granted = self::get_strings( get_post_meta( $plan_id, '_dws_lpmwc_unlocked_payment_methods', true ) );
			$merged  = \array_values( \array_unique( \array_merge( $granted, self::get_strings( $v1 ) ) ) );
			if ( $granted !== $merged && false === update_post_meta( $plan_id, '_dws_lpmwc_unlocked_payment_methods', wp_slash( $merged ) ) ) {
				throw new \RuntimeException( "The payment methods unlocked for plan $plan_id could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
			if ( ! delete_post_meta( $plan_id, $v1_key ) ) {
				throw new \RuntimeException( "The v1 grants of plan $plan_id could not be deleted." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}

	/**
	 * Returns the strings a stored list holds, in order, or none for a value that is not a list.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $stored The stored value.
	 *
	 * @return  list<string>
	 */
	protected static function get_strings( mixed $stored ): array {
		return \is_array( $stored ) ? \array_values( \array_filter( $stored, 'is_string' ) ) : array();
	}

	// endregion
}
