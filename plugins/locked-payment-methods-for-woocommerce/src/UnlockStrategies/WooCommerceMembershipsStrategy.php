<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\EditScreen\Hosts\MembershipPlanTab;
use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormDefinitionInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Unlocks payment methods for the active members of a WooCommerce Memberships plan, edited in a tab of the plan's data box.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class WooCommerceMembershipsStrategy implements ComponentInterface, ConditionalComponentInterface, ObjectFormDefinitionInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The plan tab's form id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string FORM = Plugin::PREFIX . '_membership_plan';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotices $notices The plugin's admin notices, which report the form's rejections.
	 */
	public function __construct(
		protected AdminNotices $notices
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public static function should_load(): bool {
		return self::is_available() && dws_lpmwc_get_bool_option( DWS_LPMWC_OPTION_UNLOCK_VIA_WC_MEMBERSHIPS );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_filter( 'dws_lpmwc/payment_method/should_lock', array( $this, 'unlock_member_methods' ), 10, 3 );
		new ObjectForm( self::FORM, $this, new MembershipPlanTab(), $this->notices )->register_hooks();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_object_form( string $form_id, int $object_id ): array {
		$options = dws_lpmwc_get_grant_choices( dws_lpmwc_get_plan_unlocked_payment_method_ids( $object_id ), 0 );
		return array(
			'title'      => __( 'Unlocked Payment Methods', 'locked-payment-methods-for-woocommerce' ),
			'capability' => DWS_LPMWC_CAP_UNLOCK_VIA_WC_MEMBERSHIPS,
			'fields'     => array() === $options ? array() : array(
				array(
					'type'    => 'checkboxes',
					'id'      => DWS_LPMWC_META_UNLOCKED_METHODS,
					'title'   => __( 'Unlocked payment methods', 'locked-payment-methods-for-woocommerce' ),
					'desc'    => __( 'The plan\'s active members may pay with these methods.', 'locked-payment-methods-for-woocommerce' ),
					'options' => $options,
				),
			),
		);
	}

	// endregion

	// region METHODS

	/**
	 * Returns whether WooCommerce Memberships is active at the oldest version v1 supported.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	public static function is_available(): bool {
		$version = get_option( 'wc_memberships_version', '0.0.0' );
		return \function_exists( 'wc_memberships_get_user_active_memberships' ) && \is_string( $version ) && \version_compare( $version, '1.18.0', '>=' );
	}

	/**
	 * Returns whether a plan the user is an active member of unlocks the payment method.
	 *
	 * @internal
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The user's id, or 0 for a guest.
	 *
	 * @return  bool
	 */
	public static function unlocks( string $gateway_id, int $user_id ): bool {
		if ( 0 === $user_id ) {
			return false;
		}

		foreach ( (array) wc_memberships_get_user_active_memberships( $user_id ) as $membership ) {
			// Memberships sometimes returns cancelled memberships among the active ones.
			$plan_id = \is_object( $membership ) && \method_exists( $membership, 'is_active' ) && \method_exists( $membership, 'get_plan_id' ) && true === $membership->is_active() ? $membership->get_plan_id() : null;
			if ( \is_numeric( $plan_id ) && \in_array( $gateway_id, dws_lpmwc_get_plan_unlocked_payment_method_ids( (int) $plan_id ), true ) ) {
				return true;
			}
		}

		return false;
	}

	// endregion

	// region HOOKS

	/**
	 * Returns false for a payment method a plan the user is an active member of unlocks, and the lock as given otherwise.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $lock       Whether the payment method stays locked, as earlier callbacks left it, passed on unchanged since only an explicit false unlocks.
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The paying user, or 0 for a guest.
	 *
	 * @return  mixed
	 */
	public function unlock_member_methods( mixed $lock, string $gateway_id, int $user_id ): mixed {
		return self::unlocks( $gateway_id, $user_id ) ? false : $lock;
	}

	// endregion
}
