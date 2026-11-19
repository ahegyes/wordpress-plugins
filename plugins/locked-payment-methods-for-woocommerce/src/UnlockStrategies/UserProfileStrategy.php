<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Capabilities;
use DeepWebSolutions\LockedPaymentMethods\EditScreen\Stores\GrantsStore;
use DeepWebSolutions\LockedPaymentMethods\Grants;
use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Safeguards;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\Hosts\UserProfile;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormDefinitionInterface;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

\defined( 'ABSPATH' ) || exit;

/**
 * Unlocks payment methods for a user from their profile, for all their future orders and their unpaid orders, and lists and filters the Users screen by those grants.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class UserProfileStrategy implements ComponentInterface, ConditionalComponentInterface, ObjectFormDefinitionInterface, \DWS_LPMWC_Grant_Strategy {
	// region FIELDS AND CONSTANTS

	/**
	 * The strategy's id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string ID = 'user_profile';

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
	 * The Users screen's column of each user's locked payment methods, under v1's id, which keeps the column's screen options.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string COLUMN = 'dws_lpm_unlocked_for';

	/**
	 * The Users screen's filter by unlocked payment method, whose two dropdowns post it with a _top or _bottom suffix.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string FILTER = Plugin::PREFIX . '_unlocked_for';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   PluginIdentity   $identity   The plugin's identity, which the form takes.
	 * @param   AdminNotices     $notices    The plugin's admin notices, which report the form's rejections.
	 * @param   UnlockStrategies $strategies The registered strategies, which name the rule that unlocks a method on the Users screen.
	 */
	public function __construct(
		protected PluginIdentity $identity,
		protected AdminNotices $notices,
		protected UnlockStrategies $strategies
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
		return Options::get_bool( Options::UNLOCK_VIA_USER_PROFILES );
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
		$host = new UserProfile();
		new ObjectForm( $this->identity, self::FORM, $this, $host, $this->notices, new GrantsStore( self::ID, $host->get_default_store() ) )->register_hooks();
		add_filter( 'manage_users_columns', array( $this, 'add_users_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_users_column' ), 10, 3 );
		add_action( 'restrict_manage_users', array( $this, 'render_users_filter' ) );
		add_filter( 'users_list_table_query_args', array( $this, 'filter_users' ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_object_form( string $form_id, int $object_id ): array {
		$options = GrantChoices::get( $this->get_granted_ids( $object_id ), $object_id );
		return array(
			'title'      => __( 'Locked Payment Methods', 'locked-payment-methods-for-woocommerce' ),
			'capability' => Capabilities::UNLOCK_VIA_USER_PROFILES,
			'fields'     => array() === $options ? array() : array(
				array(
					'type'    => 'checkboxes',
					'id'      => Grants::FIELD,
					'title'   => __( 'Unlocked payment methods', 'locked-payment-methods-for-woocommerce' ),
					'desc'    => __( 'The user may pay with these methods for all their future orders and their unpaid orders.', 'locked-payment-methods-for-woocommerce' ),
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
		return _x( 'User profiles', 'unlock-strategies', 'locked-payment-methods-for-woocommerce' );
	}

	/**
	 * Returns whether the user's grants list on the current site holds the payment method.
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
		return \in_array( $gateway_id, $this->get_granted_ids( $user_id ), true );
	}


	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_granted_ids( int $subject_id ): array {
		return Grants::get( self::ID, $subject_id );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function grant( int $subject_id, string ...$gateway_ids ): true|\WP_Error {
		return Grants::grant( self::ID, $subject_id, $gateway_ids );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function revoke( int $subject_id, string ...$gateway_ids ): true|\WP_Error {
		return Grants::revoke( self::ID, $subject_id, $gateway_ids );
	}

	// endregion

	// region HOOKS

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
	 * Returns a user's cell of the column, one line per locked payment method naming its state for the user and the rule that unlocks it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $output  The cell's markup so far, which an earlier callback that only echoes its own leaves null.
	 * @param   string $column  The column's id.
	 * @param   int    $user_id The user's id.
	 *
	 * @return  mixed
	 */
	public function render_users_column( mixed $output, string $column, int $user_id ): mixed {
		if ( self::COLUMN !== $column ) {
			return $output;
		}

		$lines = array();
		foreach ( Safeguards::get_locked_payment_method_ids() as $id ) {
			if ( Safeguards::should_lock( true, $id, $user_id, 0 ) ) {
				$state = __( 'locked', 'locked-payment-methods-for-woocommerce' );
			} else {
				$rule = $this->strategies->find( $id, $user_id, 0 )?->get_label();
				/* translators: %s: the name of the rule that unlocks the payment method, such as User roles. */
				$state = \is_null( $rule ) ? __( 'unlocked by another rule', 'locked-payment-methods-for-woocommerce' ) : \sprintf( __( 'unlocked by %s', 'locked-payment-methods-for-woocommerce' ), $rule );
			}

			/* translators: 1: payment method title, 2: the payment method's state for the user. */
			$lines[] = esc_html( \sprintf( __( '%1$s: %2$s', 'locked-payment-methods-for-woocommerce' ), GrantChoices::get_payment_method_title( $id ), $state ) );
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
		$locked = Safeguards::get_locked_payment_method_ids();
		if ( array() === $locked ) {
			return;
		}

		$name   = self::FILTER . "_$which";
		$active = $this->get_active_filter();
		// Core floats the list table's controls, so one inline block keeps the label, the select and the button together after its role changer.
		\printf( '<span style="display: inline-block;"><label class="screen-reader-text" for="%1$s">%2$s</label><select name="%1$s" id="%1$s"><option value="">%3$s</option>', esc_attr( $name ), esc_html__( 'Filter by unlocked payment method', 'locked-payment-methods-for-woocommerce' ), esc_html__( 'Unlocked for...', 'locked-payment-methods-for-woocommerce' ) );
		foreach ( $locked as $id ) {
			\printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $id ), selected( $active, $id, false ), esc_html( GrantChoices::get_payment_method_title( $id ) ) );
		}
		echo '</select>';
		submit_button( _x( 'Filter', 'users table', 'locked-payment-methods-for-woocommerce' ), '', $which, false, array( 'id' => "{$name}_submit" ) );
		echo '</span>';
	}

	/**
	 * Returns the Users screen's query arguments limited to the users holding the filtered payment method on the profile.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $args The list table's query arguments.
	 *
	 * @return  array<string, mixed>
	 */
	public function filter_users( array $args ): array {
		$active = $this->get_active_filter();
		if ( '' === $active ) {
			return $args;
		}

		$clause             = array(
			'key'     => Grants::get_user_key(),
			'value'   => '"' . $active . '"', // Matches the id as one element of the stored, serialized list.
			'compare' => 'LIKE',
		);
		$existing           = $args['meta_query'] ?? array();
		$args['meta_query'] = array( $clause ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The filter matches a user meta value, which only a meta query reaches.
		if ( \is_array( $existing ) && array() !== $existing ) {
			// Another plugin's clauses may join by OR, so they nest whole beside the plugin's own.
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The same query, nested.
				'relation' => 'AND',
				$existing,
				$clause,
			);
		}
		return $args;
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
		$name  = self::FILTER . ( isset( $_GET['bottom'] ) ? '_bottom' : '_top' );
		$value = isset( $_GET[ $name ] ) && \is_string( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return \in_array( $value, Safeguards::get_locked_payment_method_ids(), true ) ? $value : '';
	}

	// endregion
}
