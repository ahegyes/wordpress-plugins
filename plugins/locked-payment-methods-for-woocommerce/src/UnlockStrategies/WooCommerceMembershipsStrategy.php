<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Capabilities;
use DeepWebSolutions\LockedPaymentMethods\EditScreen\Hosts\MembershipPlanTab;
use DeepWebSolutions\LockedPaymentMethods\EditScreen\Stores\GrantsStore;
use DeepWebSolutions\LockedPaymentMethods\Grants;
use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormDefinitionInterface;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

\defined( 'ABSPATH' ) || exit;

/**
 * Unlocks payment methods for the active members of a WooCommerce Memberships plan, edited in a tab of the plan's data box.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class WooCommerceMembershipsStrategy implements ComponentInterface, ConditionalComponentInterface, ObjectFormDefinitionInterface, \DWS_LPMWC_Unlock_Strategy {
	// region FIELDS AND CONSTANTS

	/**
	 * The strategy's id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string ID = 'wc_memberships';

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
	 * @param   PluginIdentity $identity The plugin's identity, which the form takes.
	 * @param   AdminNotices   $notices  The plugin's admin notices, which report the form's rejections.
	 */
	public function __construct(
		protected PluginIdentity $identity,
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
		return self::is_available() && Options::get_bool( Options::UNLOCK_VIA_WC_MEMBERSHIPS );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		UnlockStrategies::register( $this );
		$host = new MembershipPlanTab();
		new ObjectForm( $this->identity, self::FORM, $this, $host, $this->notices, new GrantsStore( self::ID, $host->get_default_store() ) )->register_hooks();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_object_form( string $form_id, int $object_id ): array {
		$options = GrantChoices::get( $this->get_granted_ids( $object_id ), 0 );
		return array(
			'title'      => _x( 'Unlocked Payment Methods', 'wc-memberships-integration', 'locked-payment-methods-for-woocommerce' ),
			'capability' => Capabilities::UNLOCK_VIA_WC_MEMBERSHIPS,
			'fields'     => array() === $options ? array() : array(
				array(
					'type'    => 'checkboxes',
					'id'      => Grants::FIELD,
					'title'   => __( 'Unlocked payment methods', 'locked-payment-methods-for-woocommerce' ),
					'desc'    => __( 'The plan\'s active members may pay with these methods.', 'locked-payment-methods-for-woocommerce' ),
					'options' => $options,
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_label(): string {
		return _x( 'WooCommerce Memberships', 'unlock-strategies', 'locked-payment-methods-for-woocommerce' );
	}

	/**
	 * Returns whether a plan the user is an active member of unlocks the payment method.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The paying user, or 0 for a guest.
	 * @param   int    $order_id   The order being paid, or 0 off its payment page.
	 *
	 * @return  bool
	 */
	#[\Override]
	public function unlocks( string $gateway_id, int $user_id, int $order_id ): bool {
		// Memberships' loader leaves its functions undefined where its own requirements fail.
		if ( 0 === $user_id || ! \function_exists( 'wc_memberships_get_user_active_memberships' ) ) {
			return false;
		}

		foreach ( (array) wc_memberships_get_user_active_memberships( $user_id ) as $membership ) {
			// Memberships sometimes returns cancelled memberships among the active ones.
			$plan_id = \is_object( $membership ) && \method_exists( $membership, 'is_active' ) && \method_exists( $membership, 'get_plan_id' ) && true === $membership->is_active() ? $membership->get_plan_id() : null;
			if ( \is_numeric( $plan_id ) && \in_array( $gateway_id, $this->get_granted_ids( (int) $plan_id ), true ) ) {
				return true;
			}
		}

		return false;
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
		// Memberships defines its functions on plugins_loaded at priority 15, after the boot, so the loader its main file declares tells that it is active.
		$version = get_option( 'wc_memberships_version', '0.0.0' );
		return \class_exists( \WC_Memberships_Loader::class ) && \is_string( $version ) && \version_compare( $version, '1.18.0', '>=' );
	}

	/**
	 * Returns the ids of the payment methods unlocked for a WooCommerce Memberships plan's active members.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $plan_id The plan's id.
	 *
	 * @return  string[]
	 */
	public function get_granted_ids( int $plan_id ): array {
		return Grants::get( self::ID, $plan_id );
	}

	// endregion
}
