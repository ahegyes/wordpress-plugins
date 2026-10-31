<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods;

use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\WooCommerce\Setting\SectionFieldsDefinitionInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\WooCommerce\Setting\SettingsTab;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds the plugin's WooCommerce settings tab, which holds the greeting's settings and the consent to delete the plugin's data on uninstall.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Settings implements ComponentInterface, SectionFieldsDefinitionInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_settings_tab' ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_section_fields( string $tab_id, string $section_id ): array {
		return array(
			array(
				'type'  => 'title',
				'id'    => Plugin::PREFIX . '_greeting_options',
				'title' => __( 'Greeting', 'locked-payment-methods-for-woocommerce' ),
				'desc'  => __( 'Greets returning customers on their account dashboard.', 'locked-payment-methods-for-woocommerce' ),
			),
			array(
				'type'    => 'checkbox',
				'id'      => DWS_LPMWC_OPTION_FEATURE,
				'title'   => __( 'Greeting', 'locked-payment-methods-for-woocommerce' ),
				'desc'    => __( 'Greet returning customers', 'locked-payment-methods-for-woocommerce' ),
				'default' => wc_bool_to_string( DWS_LPMWC_OPTION_DEFAULTS[ DWS_LPMWC_OPTION_FEATURE ] ),
			),
			array(
				'type'        => 'text',
				'id'          => DWS_LPMWC_OPTION_GREETING,
				'title'       => __( 'Message', 'locked-payment-methods-for-woocommerce' ),
				'default'     => '',
				'placeholder' => dws_lpmwc_get_option_text( DWS_LPMWC_OPTION_GREETING ), // A default would be saved in the admin's language with the first save of the tab.
			),
			array(
				'type'              => 'number',
				'id'                => DWS_LPMWC_OPTION_GREETING_ORDERS,
				'title'             => __( 'Orders before the greeting', 'locked-payment-methods-for-woocommerce' ),
				'desc'              => __( 'Customers see the greeting once they have placed this many orders.', 'locked-payment-methods-for-woocommerce' ),
				'default'           => (string) DWS_LPMWC_OPTION_DEFAULTS[ DWS_LPMWC_OPTION_GREETING_ORDERS ],
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
				'validate'          => 'dws_lpmwc_validate_greeting_orders',
			),
			array(
				'type' => 'sectionend',
				'id'   => Plugin::PREFIX . '_greeting_options',
			),
			array(
				'type'  => 'title',
				'id'    => Plugin::PREFIX . '_data_options',
				'title' => __( 'Data', 'locked-payment-methods-for-woocommerce' ),
			),
			array(
				'type'    => 'checkbox',
				'id'      => DWS_LPMWC_OPTION_DELETE_DATA,
				'title'   => __( 'Data on uninstall', 'locked-payment-methods-for-woocommerce' ),
				'desc'    => __( 'Delete the plugin\'s data when it is uninstalled', 'locked-payment-methods-for-woocommerce' ),
				'default' => wc_bool_to_string( DWS_LPMWC_OPTION_DEFAULTS[ DWS_LPMWC_OPTION_DELETE_DATA ] ),
			),
			array(
				'type' => 'sectionend',
				'id'   => Plugin::PREFIX . '_data_options',
			),
		);
	}

	// endregion

	// region HOOKS

	/**
	 * Adds the plugin's settings tab, which WooCommerce's settings page class must exist to construct.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<int, \WC_Settings_Page> $pages The settings tabs.
	 *
	 * @return  array<int, \WC_Settings_Page>
	 */
	public function add_settings_tab( array $pages ): array {
		$pages[] = new SettingsTab(
			Plugin::PREFIX,
			array(
				'label'    => __( 'Locked Payment Methods for WooCommerce', 'locked-payment-methods-for-woocommerce' ),
				'sections' => array( '' => __( 'General', 'locked-payment-methods-for-woocommerce' ) ),
			),
			$this
		);

		return $pages;
	}

	// endregion
}
