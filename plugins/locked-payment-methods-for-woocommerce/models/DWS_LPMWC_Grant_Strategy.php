<?php declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Unlocks locked payment methods by the grants each of its subjects holds, such as a user or an order, which other plugins may change.
 *
 * @api
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface DWS_LPMWC_Grant_Strategy extends DWS_LPMWC_Unlock_Strategy {
	/**
	 * Returns the ids of the payment methods granted to a subject.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $subject_id The subject's id, such as a user's or an order's.
	 *
	 * @return  string[]
	 */
	public function get_granted_ids( int $subject_id ): array;

	/**
	 * Grants payment methods to a subject beside those it holds, without checking any capability, and returns true once it holds them.
	 *
	 * It returns an error for a subject that does not exist, a paid order, whose grants no longer change, or a write that failed.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int    $subject_id     The subject's id, such as a user's or an order's.
	 * @param   string ...$gateway_ids The payment methods' ids.
	 *
	 * @return  true|WP_Error
	 */
	public function grant( int $subject_id, string ...$gateway_ids ): true|WP_Error;

	/**
	 * Revokes payment methods from a subject, keeping its other grants, without checking any capability, and returns true once it holds none of them.
	 *
	 * Another strategy may still unlock a revoked payment method. It returns an error for a subject that does not exist, a paid order, whose grants no longer change, or a write that failed.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int    $subject_id     The subject's id, such as a user's or an order's.
	 * @param   string ...$gateway_ids The payment methods' ids.
	 *
	 * @return  true|WP_Error
	 */
	public function revoke( int $subject_id, string ...$gateway_ids ): true|WP_Error;
}
