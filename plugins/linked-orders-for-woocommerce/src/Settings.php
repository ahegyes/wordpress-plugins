<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use Automattic\WooCommerce\Admin\Settings\SettingsSectionRegistry;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Settings\Forms;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\WooCommerce\Setting\SectionFieldsDefinitionInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\WooCommerce\Setting\SettingsSection;

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
	 * The section's id, which v1 used, so links to it keep working.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string SECTION = 'dws-linked-orders';

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
		add_filter( 'plugin_action_links_' . plugin_basename( DWS_LOWC_FILE ), array( $this, 'add_settings_link' ) );
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
		$fields = array();
		foreach ( DWS_LOWC_SETTINGS_GROUPS as $group ) {
			/**
			 * Filters a group's fields, keyed by their v1 ids.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use dws_lowc/settings/{group}_fields.
			 *
			 * @param       array<string, Field> $fields The group's fields.
			 */
			$group_fields = apply_filters_deprecated( "linked_orders_for_woocommerce/{$group}_settings/get_group_fields", array( $this->get_group_fields( $group ) ), '2.0.0', "dws_lowc/settings/{$group}_fields" );

			$wc_fields = array();
			foreach ( $group_fields as $id => $field ) {
				$wc_fields[] = array( 'id' => "dws-lowc_{$group}_{$id}" ) + $field;
			}

			/**
			 * Filters a group's fields, as WooCommerce settings fields whose ids are their options.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   list<Field> $fields The group's fields.
			 */
			$wc_fields = apply_filters( "dws_lowc/settings/{$group}_fields", $wc_fields );

			$fields = \array_merge(
				$fields,
				array(
					array(
						'type'  => 'title',
						'id'    => "dws-lowc_{$group}",
						'title' => 'general' === $group ? __( 'General Settings', 'linked-orders-for-woocommerce' ) : __( 'Plugin Settings', 'linked-orders-for-woocommerce' ),
					),
				),
				$wc_fields,
				array(
					array(
						'type' => 'sectionend',
						'id'   => "dws-lowc_{$group}",
					),
				)
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
					'label' => _x( 'Linked Orders', 'settings', 'linked-orders-for-woocommerce' ),
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
		\array_unshift( $links, \sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=' . self::SECTION ) ), esc_html__( 'Settings', 'linked-orders-for-woocommerce' ) ) );

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
		if ( plugin_basename( DWS_LOWC_FILE ) === $basename ) {
			$meta[] = \sprintf( '<a href="%s">%s</a>', esc_url( 'https://wordpress.org/support/plugin/linked-orders-for-woocommerce/' ), esc_html__( 'Community support', 'linked-orders-for-woocommerce' ) );
		}

		return $meta;
	}

	// endregion

	// region HELPERS

	/**
	 * Returns a group's fields, keyed by their v1 ids, as v1 defined them but for checkboxes in place of yes-or-no selects.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $group The group's v1 id.
	 *
	 * @return  array<string, Field>
	 */
	protected function get_group_fields( string $group ): array {
		if ( 'general' === $group ) {
			return array(
				'max-depth'                => array(
					'title'             => __( 'Maximum linked orders depth', 'linked-orders-for-woocommerce' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'min'  => '1',
						'max'  => '99',
						'step' => '1',
					),
					'default'           => (string) DWS_LOWC_OPTION_DEFAULTS[ DWS_LOWC_OPTION_MAX_DEPTH ],
					'desc_tip'          => __( 'The maximum number of linked orders levels. Root orders are considered level 0.', 'linked-orders-for-woocommerce' ),
					'validate'          => 'dws_lowc_validate_max_depth',
				),
				'autocomplete-descendants' => array(
					'title'   => __( 'Autocomplete descendant orders', 'linked-orders-for-woocommerce' ),
					'type'    => 'checkbox',
					'desc'    => __( 'Set every descendant order to completed when its parent order is completed', 'linked-orders-for-woocommerce' ),
					'default' => wc_bool_to_string( DWS_LOWC_OPTION_DEFAULTS[ DWS_LOWC_OPTION_AUTOCOMPLETE ] ),
				),
			);
		}

		return array(
			'remove-data-uninstall' => array(
				'title'   => __( 'Remove all data on uninstallation', 'linked-orders-for-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Delete the plugin\'s data when it is uninstalled, so it must be configured again if it is installed later', 'linked-orders-for-woocommerce' ),
				'default' => wc_bool_to_string( DWS_LOWC_OPTION_DEFAULTS[ DWS_LOWC_OPTION_DELETE_DATA ] ),
			),
		);
	}

	// endregion
}
