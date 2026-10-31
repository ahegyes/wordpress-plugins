<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\Hosts\UserProfile;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormDefinitionInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Unlocks payment methods for a user from their profile, for all their future orders and their unpaid orders, and lists and filters the Users screen by those grants.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class UserProfileStrategy implements ComponentInterface, ConditionalComponentInterface, ObjectFormDefinitionInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The profile form's id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string FORM = Plugin::PREFIX . '_user_profile';

	/**
	 * The Users screen's column, whose id also names the filter's dropdowns.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string COLUMN = Plugin::PREFIX . '_unlocked_for';

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
		return dws_lpmwc_get_bool_option( DWS_LPMWC_OPTION_UNLOCK_VIA_USER_PROFILES );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_filter( 'dws_lpmwc/payment_method/should_lock', array( $this, 'unlock_granted_methods' ), 10, 3 );
		new ObjectForm( self::FORM, $this, new UserProfile(), $this->notices )->register_hooks();
		add_filter( 'manage_users_columns', array( $this, 'add_users_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_users_column' ), 10, 3 );
		add_action( 'restrict_manage_users', array( $this, 'render_users_filter' ) );
		add_action( 'pre_get_users', array( $this, 'filter_users' ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_object_form( string $form_id, int $object_id ): array {
		$options = dws_lpmwc_get_grant_choices( dws_lpmwc_get_user_unlocked_payment_method_ids( $object_id ), $object_id );

		return array(
			'title'      => __( 'Locked Payment Methods', 'locked-payment-methods-for-woocommerce' ),
			'capability' => DWS_LPMWC_CAP_UNLOCK_VIA_USER_PROFILES,
			'fields'     => array() === $options ? array() : array(
				array(
					'type'    => 'checkboxes',
					'id'      => dws_lpmwc_get_user_grants_key(),
					'title'   => __( 'Unlocked payment methods', 'locked-payment-methods-for-woocommerce' ),
					'desc'    => __( 'The user may pay with these methods for all their future orders and their unpaid orders.', 'locked-payment-methods-for-woocommerce' ),
					'options' => $options,
				),
			),
		);
	}

	// endregion

	// region HOOKS

	/**
	 * Returns false for a payment method the user's grants list holds, and the lock as given otherwise.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   bool   $lock       Whether the payment method stays locked.
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The paying user, or 0 for a guest.
	 *
	 * @return  bool
	 */
	public function unlock_granted_methods( bool $lock, string $gateway_id, int $user_id ): bool {
		return \in_array( $gateway_id, dws_lpmwc_get_user_unlocked_payment_method_ids( $user_id ), true ) ? false : $lock;
	}

	/**
	 * Adds the column of the locked payment methods' states after the Users screen's role column.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $columns The screen's column titles, by column id.
	 *
	 * @return  array<string, string>
	 */
	public function add_users_column( array $columns ): array {
		$position = \array_search( 'role', \array_keys( $columns ), true );
		$position = false === $position ? \count( $columns ) : $position + 1;

		return \array_slice( $columns, 0, $position, true ) + array( self::COLUMN => __( 'Unlocked for', 'locked-payment-methods-for-woocommerce' ) ) + \array_slice( $columns, $position, null, true );
	}

	/**
	 * Returns a user's cell of the column, one line per locked payment method naming its state for the user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $output  The cell's markup so far.
	 * @param   string $column  The column's id.
	 * @param   int    $user_id The user's id.
	 *
	 * @return  string
	 */
	public function render_users_column( string $output, string $column, int $user_id ): string {
		if ( self::COLUMN !== $column ) {
			return $output;
		}

		$granted = dws_lpmwc_get_user_unlocked_payment_method_ids( $user_id );
		$lines   = array();
		foreach ( dws_lpmwc_get_locked_payment_method_ids() as $id ) {
			$state = match ( true ) {
				\in_array( $id, $granted, true )                  => __( 'unlocked on the profile', 'locked-payment-methods-for-woocommerce' ),
				! dws_lpmwc_should_lock( true, $id, $user_id, 0 ) => __( 'unlocked by another rule', 'locked-payment-methods-for-woocommerce' ),
				default                                           => __( 'locked', 'locked-payment-methods-for-woocommerce' ),
			};
			/* translators: 1: payment method title, 2: the payment method's state for the user. */
			$lines[] = esc_html( \sprintf( __( '%1$s: %2$s', 'locked-payment-methods-for-woocommerce' ), dws_lpmwc_get_payment_method_title( $id ), $state ) );
		}

		return \implode( '<br />', $lines );
	}

	/**
	 * Prints the dropdown that filters the Users screen by a payment method unlocked on the profile, above and below the list.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $which Where the dropdown prints, 'top' or 'bottom'.
	 *
	 * @return  void
	 */
	public function render_users_filter( string $which ): void {
		$locked = dws_lpmwc_get_locked_payment_method_ids();
		if ( array() === $locked ) {
			return;
		}

		$name   = self::COLUMN . "_$which";
		$active = $this->get_active_filter();
		\printf( '<label class="screen-reader-text" for="%1$s">%2$s</label><select name="%1$s" id="%1$s"><option value="">%3$s</option>', esc_attr( $name ), esc_html__( 'Filter by unlocked payment method', 'locked-payment-methods-for-woocommerce' ), esc_html__( 'Unlocked for…', 'locked-payment-methods-for-woocommerce' ) );
		foreach ( $locked as $id ) {
			\printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $id ), selected( $active, $id, false ), esc_html( dws_lpmwc_get_payment_method_title( $id ) ) );
		}
		echo '</select>';
		submit_button( _x( 'Filter', 'users screen', 'locked-payment-methods-for-woocommerce' ), '', $which, false, array( 'id' => "{$name}_submit" ) );
	}

	/**
	 * Limits the Users screen's list to the users holding the filtered payment method on the profile.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  string $pagenow The admin screen's file.
	 *
	 * @param   \WP_User_Query $query The users query.
	 *
	 * @return  void
	 */
	public function filter_users( \WP_User_Query $query ): void {
		global $pagenow;

		$active = 'users.php' === $pagenow && is_admin() ? $this->get_active_filter() : '';
		if ( '' === $active ) {
			return;
		}

		$meta_query   = $query->get( 'meta_query' );
		$meta_query   = \is_array( $meta_query ) ? $meta_query : array();
		$meta_query[] = array(
			'key'     => dws_lpmwc_get_user_grants_key(),
			'value'   => '"' . $active . '"', // Matches the id as one element of the stored, serialized list.
			'compare' => 'LIKE',
		);
		$query->set( 'meta_query', $meta_query );
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the locked payment method the Users screen filters by, from the dropdown whose button submitted the list, or an empty string.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	protected function get_active_filter(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- A list filter, which changes nothing.
		$name  = self::COLUMN . ( isset( $_GET['bottom'] ) ? '_bottom' : '_top' );
		$value = isset( $_GET[ $name ] ) && \is_string( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return \in_array( $value, dws_lpmwc_get_locked_payment_method_ids(), true ) ? $value : '';
	}

	// endregion
}
