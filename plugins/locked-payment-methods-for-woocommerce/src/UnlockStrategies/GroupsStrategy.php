<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Capabilities;
use DeepWebSolutions\LockedPaymentMethods\Grants;
use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\Notice;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\Forms;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

\defined( 'ABSPATH' ) || exit;

/**
 * Unlocks payment methods for the members of a Groups group and of its subgroups, edited on Groups' own group forms.
 *
 * Groups verifies its forms' nonce and its own capability before it fires the actions that save the field, and its add form has no group to bind a nonce to, so the field rides Groups' form rather than an object form.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Field from Forms
 */
final class GroupsStrategy implements ComponentInterface, ConditionalComponentInterface, \DWS_LPMWC_Unlock_Strategy {
	// region FIELDS AND CONSTANTS

	/**
	 * The strategy's id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string ID = 'groups';

	/**
	 * The plugin's entry of Groups' form.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string ENTRY = Plugin::PREFIX . '_group';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotices $notices The plugin's admin notices, which report the field's rejections.
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
		return self::is_available() && Options::get_bool( Options::UNLOCK_VIA_GROUPS );
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
		add_filter( 'groups_admin_groups_add_form_after_fields', array( $this, 'render_group_field' ), 999 );
		add_filter( 'groups_admin_groups_edit_form_after_fields', array( $this, 'render_group_field' ), 999, 2 );
		add_action( 'groups_admin_groups_add_submit_success', array( $this, 'save_group_field' ) );
		add_action( 'groups_admin_groups_edit_submit_success', array( $this, 'save_group_field' ) );
		add_action( 'groups_deleted_group', array( $this, 'forget_group' ) );
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
		return _x( 'Groups', 'unlock-strategies', 'locked-payment-methods-for-woocommerce' );
	}

	/**
	 * Returns whether a group of the user, or a group above one, unlocks the payment method.
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
		if ( 0 === $user_id ) {
			return false;
		}

		foreach ( (array) new \Groups_User( $user_id )->group_ids_deep as $group_id ) { // @phpstan-ignore property.notFound (Groups_User resolves its group ids through __get, as at v1's oldest Groups.)
			if ( \is_numeric( $group_id ) && \in_array( $gateway_id, $this->get_granted_ids( (int) $group_id ), true ) ) {
				return true;
			}
		}

		return false;
	}

	// endregion

	// region METHODS

	/**
	 * Returns whether Groups is active at the oldest version v1 supported.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	public static function is_available(): bool {
		$version = \defined( 'GROUPS_CORE_VERSION' ) ? \constant( 'GROUPS_CORE_VERSION' ) : null;
		return \is_string( $version ) && \class_exists( \Groups_User::class ) && \version_compare( $version, '2.11.0', '>=' );
	}

	/**
	 * Returns the ids of the payment methods unlocked for a Groups group's members.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $group_id The group's id.
	 *
	 * @return  string[]
	 */
	public function get_granted_ids( int $group_id ): array {
		return Grants::get( self::ID, $group_id );
	}

	// endregion

	// region HOOKS

	/**
	 * Returns Groups' form markup with the field appended, for a user holding both the plugin's and Groups' capability.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $output   The markup Groups prints after its own fields, which an earlier callback that returns nothing leaves null.
	 * @param   int   $group_id Optional. The edited group's id. Default 0, on the add form.
	 *
	 * @return  mixed
	 */
	public function render_group_field( mixed $output, int $group_id = 0 ): mixed {
		if ( ! $this->can_edit() ) {
			return $output;
		}

		$granted = $this->get_granted_ids( $group_id );
		$field   = $this->get_field( $granted );
		if ( array() === $field['options'] ) {
			return $output;
		}

		return ( \is_string( $output ) ? $output : '' ) . '<div class="field"><span class="field-label">' . esc_html( $field['title'] ) . '</span>' . Forms::render_control( $field, $granted, self::ENTRY . '[' . Grants::FIELD . ']', Grants::FIELD ) . '</div>';
	}

	/**
	 * Stores the checked payment methods under the group Groups just saved, when its form carried the field.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $group_id The saved group's id.
	 *
	 * @return  void
	 */
	public function save_group_field( int $group_id ): void {
		if ( ! $this->can_edit() ) {
			return;
		}

		$submitted = isset( $_POST[ self::ENTRY ] ) && \is_array( $_POST[ self::ENTRY ] ) ? wp_unslash( $_POST[ self::ENTRY ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Groups verified its form's nonce, and Forms::sanitize_fields() sanitizes the field.
		$field     = $this->get_field( $this->get_granted_ids( $group_id ) );
		$result    = Forms::sanitize_fields( array( $field ), $submitted );
		if ( isset( $result['errors'][ Grants::FIELD ] ) ) {
			// The notice shows on the screen after Groups' own, so it names the group.
			$group = \Groups_Group::read( $group_id );
			/* translators: 1: the field's title, 2: the Groups group's name. */
			$title = \sprintf( __( '%1$s of the group %2$s', 'locked-payment-methods-for-woocommerce' ), $field['title'], \is_object( $group ) && isset( $group->name ) && \is_string( $group->name ) ? $group->name : (string) $group_id );
			$this->notices->add( new Notice( 'rejection-' . Grants::FIELD, esc_html( Forms::get_rejection_message( $title, $result['errors'][ Grants::FIELD ] ) ) ) );
		}
		if ( ! isset( $result['values'][ Grants::FIELD ] ) ) {
			return;
		}

		Grants::update( self::ID, $group_id, \is_array( $result['values'][ Grants::FIELD ] ) ? $result['values'][ Grants::FIELD ] : array() );
	}

	/**
	 * Forgets the payment methods a deleted group unlocked.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $group_id The deleted group's id.
	 *
	 * @return  void
	 */
	public function forget_group( int $group_id ): void {
		Grants::update( self::ID, $group_id, array() );
	}

	// endregion

	// region HELPERS

	/**
	 * Returns whether the current user holds the plugin's capability and Groups' own, which its group screens require.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	protected function can_edit(): bool {
		return current_user_can( Capabilities::UNLOCK_VIA_GROUPS ) && \Groups_User::current_user_can( 'groups_admin_groups' );
	}

	/**
	 * Returns the group's field, which offers the locked payment methods and the group's other grants.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string[] $granted The ids of the payment methods granted to the group.
	 *
	 * @return  array{type: string, id: string, title: string, desc: string, options: array<string, string>}
	 */
	protected function get_field( array $granted ): array {
		return array(
			'type'    => 'checkboxes',
			'id'      => Grants::FIELD,
			'title'   => __( 'Unlocked payment methods', 'locked-payment-methods-for-woocommerce' ),
			'desc'    => __( 'The group\'s members, and the members of its subgroups, may pay with these methods.', 'locked-payment-methods-for-woocommerce' ),
			'options' => GrantChoices::get( $granted, 0 ),
		);
	}

	// endregion
}
