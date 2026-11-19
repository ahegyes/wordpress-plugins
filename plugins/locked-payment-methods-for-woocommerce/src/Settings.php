<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods;

use Automattic\WooCommerce\Admin\Settings\SettingsSectionRegistry;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\Forms;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\WooCommerce\Setting\SectionFieldsDefinitionInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\WooCommerce\Setting\SettingsSection;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\UnlockStrategySettings;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds the plugin's section to WooCommerce's Advanced tab under v1's id, and links to it and to the support forum from the plugin's row.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Field from Forms
 */
final class Settings implements ComponentInterface, SectionFieldsDefinitionInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The section's id, which v1 used on the Payments tab.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string SECTION = 'dws-locked-payment-methods';

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'woocommerce_settings_sections_registration', array( $this, 'register_section' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DWS_LPMWC_FILE ), array( $this, 'add_settings_link' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_support_link' ), 10, 2 );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_section_fields( string $tab_id, string $section_id ): array {
		$titles = array(
			'general'      => __( 'General Settings', 'locked-payment-methods-for-woocommerce' ),
			'integrations' => __( 'Integrations Settings', 'locked-payment-methods-for-woocommerce' ),
			'plugin'       => __( 'Plugin Settings', 'locked-payment-methods-for-woocommerce' ),
		);

		$definitions = array();
		foreach ( array( ...$this->get_fields(), ...UnlockStrategySettings::get_fields() ) as $field ) {
			$definitions[ $field['id'] ?? '' ] = \array_diff_key( $field, array( 'id' => true ) );
		}

		$fields = array();
		foreach ( Options::V1_SETTINGS as $group => $keys ) {
			/**
			 * Filters a group's fields, keyed by their v1 ids.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use woocommerce_get_settings_advanced.
			 *
			 * @param       array<string, Field> $fields The group's fields.
			 */
			$group_fields = apply_filters_deprecated( "locked_payment_methods_for_woocommerce/{$group}_settings/get_group_fields", array( \array_filter( \array_map( static fn ( string $key ): ?array => $definitions[ $key ] ?? null, $keys ), 'is_array' ) ), '2.0.0', 'woocommerce_get_settings_advanced' );
			if ( array() === $group_fields ) {
				continue;
			}

			$fields[] = array(
				'type'  => 'title',
				'id'    => "dws-wc-lpm_$group",
				'title' => $titles[ $group ],
			);
			foreach ( $group_fields as $id => $field ) {
				$fields[] = array( 'id' => $keys[ $id ] ?? "dws-wc-lpm_{$group}_$id" ) + $field; // A field a v1 callback adds keeps the option v1 stored it under.
			}
			$fields[] = array(
				'type' => 'sectionend',
				'id'   => "dws-wc-lpm_$group",
			);
		}

		return $fields;
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the plugin's section on the Advanced tab.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsSectionRegistry $registry WooCommerce's registry of settings sections.
	 *
	 * @return  void
	 */
	public function register_section( SettingsSectionRegistry $registry ): void {
		$registry->register(
			new SettingsSection(
				self::SECTION,
				array(
					'tab'   => 'advanced',
					'label' => _x( 'Locked Payment Methods', 'settings', 'locked-payment-methods-for-woocommerce' ),
				),
				$this
			)
		);
	}

	/**
	 * Adds the link to the plugin's section to its row on the Plugins screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string[] $links The row's action links.
	 *
	 * @return  string[]
	 */
	public function add_settings_link( array $links ): array {
		\array_unshift( $links, \sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=' . self::SECTION ) ), esc_html__( 'Settings', 'locked-payment-methods-for-woocommerce' ) ) );
		return $links;
	}

	/**
	 * Adds the link to the plugin's support forum to its row on the Plugins screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string[] $meta     The row's meta links.
	 * @param   string   $basename The basename of the row's plugin.
	 *
	 * @return  string[]
	 */
	public function add_support_link( array $meta, string $basename ): array {
		if ( plugin_basename( DWS_LPMWC_FILE ) === $basename ) {
			$meta[] = \sprintf( '<a href="%s">%s</a>', esc_url( 'https://wordpress.org/support/plugin/locked-payment-methods-for-woocommerce/' ), esc_html__( 'Community support', 'locked-payment-methods-for-woocommerce' ) );
		}

		return $meta;
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the fields of the settings no strategy owns, each under the option it stores.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<Field>
	 */
	protected function get_fields(): array {
		$choices = \array_map(
			static fn ( \WC_Payment_Gateway $gateway ): string => 'yes' === $gateway->enabled // @phpstan-ignore argument.type (WooCommerce's registry holds only WC_Payment_Gateway instances, which its stubs leave untyped.)
				? $gateway->get_title()
				/* translators: %s: payment method title. */
				: \sprintf( __( '%s (disabled)', 'locked-payment-methods-for-woocommerce' ), $gateway->get_title() ),
			WC()->payment_gateways()->payment_gateways()
		);
		// WooCommerce saves only the choices it renders, so a locked method whose plugin is inactive stays a choice, or a save would unlock it.
		foreach ( Options::get_list( Options::LOCKED_METHODS ) as $id ) {
			/* translators: %s: payment method id. */
			$choices[ $id ] ??= \sprintf( __( '%s (not available)', 'locked-payment-methods-for-woocommerce' ), $id );
		}

		return array(
			array(
				'id'      => Options::LOCKED_METHODS,
				'title'   => __( 'Payment methods which are locked by default', 'locked-payment-methods-for-woocommerce' ),
				'type'    => 'multiselect',
				'class'   => 'wc-enhanced-select',
				'options' => $choices,
				'default' => Options::DEFAULTS[ Options::LOCKED_METHODS ],
				'desc'    => __( 'A disabled payment method stays locked, so it is still locked once it is enabled again.', 'locked-payment-methods-for-woocommerce' ),
			),
			array(
				'id'      => Options::DELETE_DATA,
				'title'   => __( 'Remove all data on uninstallation?', 'locked-payment-methods-for-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Delete the plugin\'s data when it is uninstalled, so it must be configured again if it is installed later', 'locked-payment-methods-for-woocommerce' ),
				'default' => Options::DEFAULTS[ Options::DELETE_DATA ],
			),
		);
	}

	// endregion
}
