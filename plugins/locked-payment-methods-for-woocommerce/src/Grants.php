<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods;

use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\GroupsStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\OrderStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\UserProfileStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\WooCommerceMembershipsStrategy;

\defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the grants, the ids of the payment methods a strategy unlocks for each of its subjects, and announces each change the plugin makes to them.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Grants {
	// region FIELDS AND CONSTANTS

	/**
	 * The user meta key of a user's grants, after each site's table prefix.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string USER_OPTION = 'dws_lpmwc_unlocked_payment_methods';

	/**
	 * The meta key of an order's grants and of a WooCommerce Memberships plan's.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string META_KEY = '_dws_lpmwc_unlocked_payment_methods';

	/**
	 * The id of the grants field on every grants form.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string FIELD = 'dws_lpmwc_unlocked_payment_methods';

	/**
	 * The name of a Groups group's option, which holds its grants, as a format of the group's id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string GROUP_OPTION = 'dws_lpmwc_group_%d_unlocked_payment_methods';

	// endregion

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
	 * Returns the ids of the payment methods a strategy grants a subject, each once, and none for a strategy without grants or an order before the plugin has booted.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $strategy_id The strategy's id.
	 * @param   int    $subject_id  The subject's id: a user's, an order's, a Groups group's or a WooCommerce Memberships plan's.
	 *
	 * @return  list<string>
	 */
	public static function get( string $strategy_id, int $subject_id ): array {
		$stored = match ( $strategy_id ) {
			UserProfileStrategy::ID            => get_user_meta( $subject_id, self::get_user_key(), true ),
			OrderStrategy::ID                  => OrderStrategy::get_order( $subject_id )?->get_meta( self::META_KEY ),
			GroupsStrategy::ID                 => get_option( \sprintf( self::GROUP_OPTION, $subject_id ) ),
			WooCommerceMembershipsStrategy::ID => get_post_meta( $subject_id, self::META_KEY, true ),
			default                            => null,
		};

		return self::get_strings( $stored );
	}

	/**
	 * Grants payment methods to a subject beside those it holds, and returns true once it holds them, or an error.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $strategy_id The strategy's id.
	 * @param   int      $subject_id  The subject's id.
	 * @param   string[] $gateway_ids The payment methods' ids.
	 *
	 * @return  true|\WP_Error
	 */
	public static function grant( string $strategy_id, int $subject_id, array $gateway_ids ): true|\WP_Error {
		return self::update( $strategy_id, $subject_id, array( ...self::get( $strategy_id, $subject_id ), ...$gateway_ids ) );
	}

	/**
	 * Revokes payment methods from a subject, keeping its other grants, and returns true once it holds none of them, or an error.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $strategy_id The strategy's id.
	 * @param   int      $subject_id  The subject's id.
	 * @param   string[] $gateway_ids The payment methods' ids.
	 *
	 * @return  true|\WP_Error
	 */
	public static function revoke( string $strategy_id, int $subject_id, array $gateway_ids ): true|\WP_Error {
		return self::update( $strategy_id, $subject_id, \array_diff( self::get( $strategy_id, $subject_id ), $gateway_ids ) );
	}

	/**
	 * Stores the ids of the payment methods a strategy grants a subject, and returns true once the subject holds them, or an error for a user or an order that does not exist, a paid order or a write that failed.
	 *
	 * It checks no capability, and it fires locked_payment_methods_for_woocommerce/grants/updated once the grants change.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string  $strategy_id The strategy's id.
	 * @param   int     $subject_id  The subject's id: a user's, an order's, a Groups group's or a WooCommerce Memberships plan's.
	 * @param   mixed[] $ids         The payment methods' ids, from which anything but a string is dropped, as a form may post it.
	 *
	 * @return  true|\WP_Error
	 */
	public static function update( string $strategy_id, int $subject_id, array $ids ): true|\WP_Error {
		$order = OrderStrategy::ID === $strategy_id ? OrderStrategy::get_order( $subject_id ) : null;
		if ( ( UserProfileStrategy::ID === $strategy_id && ! get_userdata( $subject_id ) instanceof \WP_User ) || ( OrderStrategy::ID === $strategy_id && \is_null( $order ) ) ) {
			return new \WP_Error( 'dws_lpmwc_subject_not_found', __( 'The user or order does not exist.', 'locked-payment-methods-for-woocommerce' ) );
		}
		if ( true === $order?->is_paid() ) {
			return new \WP_Error( 'dws_lpmwc_order_paid', __( 'This order is paid, so its unlocked payment methods no longer change.', 'locked-payment-methods-for-woocommerce' ) );
		}

		$ids      = self::get_strings( $ids );
		$previous = self::get( $strategy_id, $subject_id );
		// A form posts its choices in the order it renders them, so the same methods in another order are no change, which equal counts with nothing new show, since both lists hold each method once.
		if ( \count( $ids ) === \count( $previous ) && array() === \array_diff( $ids, $previous ) ) {
			return true;
		}

		if ( $order instanceof \WC_Order ) {
			$order->update_meta_data( self::META_KEY, $ids );
			$order->save();
		} elseif ( UserProfileStrategy::ID === $strategy_id ) {
			update_user_meta( $subject_id, self::get_user_key(), wp_slash( $ids ) );
		} elseif ( WooCommerceMembershipsStrategy::ID === $strategy_id ) {
			update_post_meta( $subject_id, self::META_KEY, wp_slash( $ids ) );
		} elseif ( GroupsStrategy::ID === $strategy_id && array() === $ids ) {
			delete_option( \sprintf( self::GROUP_OPTION, $subject_id ) );
		} elseif ( GroupsStrategy::ID === $strategy_id ) {
			update_option( \sprintf( self::GROUP_OPTION, $subject_id ), $ids, true );
		}

		// WooCommerce's order CRUD reports no refused write, so every write is judged by what reads back.
		if ( self::get( $strategy_id, $subject_id ) !== $ids ) {
			return new \WP_Error( 'dws_lpmwc_grants_not_stored', __( 'The unlocked payment methods could not be stored. Please try again.', 'locked-payment-methods-for-woocommerce' ) );
		}

		/**
		 * Fires after a subject's grants change through a grants form, the user_profile or order strategy's grant() or revoke(), or the deletion of a Groups group.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   string   $strategy_id The strategy's id: user_profile, order, groups or wc_memberships.
		 * @param   int      $subject_id  The subject's id: a user's, an order's, a Groups group's or a WooCommerce Memberships plan's.
		 * @param   string[] $ids         The ids of the payment methods granted now.
		 * @param   string[] $previous    The ids of the payment methods granted before.
		 */
		do_action( 'locked_payment_methods_for_woocommerce/grants/updated', $strategy_id, $subject_id, $ids, $previous );

		return true;
	}

	/**
	 * Returns the current site's user meta key of a user's grants, which the plugin reads as user meta, since get_user_option() falls back to a key every site shares.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  \wpdb $wpdb WordPress's database access.
	 *
	 * @return  string
	 */
	public static function get_user_key(): string {
		global $wpdb;
		return $wpdb->get_blog_prefix() . self::USER_OPTION;
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the strings a stored or posted list holds, each once and in order, or none for a value that is not an array.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $ids The list.
	 *
	 * @return  list<string>
	 */
	protected static function get_strings( mixed $ids ): array {
		return \is_array( $ids ) ? \array_values( \array_unique( \array_filter( $ids, 'is_string' ) ) ) : array();
	}

	// endregion
}
