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
	protected const string SECTION = 'dws-linked-orders';

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
		// v1's group titles, and fields with checkboxes in place of its yes-or-no selects.
		$titles      = array(
			'general' => __( 'General settings', 'linked-orders-for-woocommerce' ),
			'plugin'  => __( 'Plugin settings', 'linked-orders-for-woocommerce' ),
		);
		$definitions = array(
			Options::MAX_DEPTH    => array(
				'title'             => __( 'Maximum linked orders depth', 'linked-orders-for-woocommerce' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'max'  => '99',
					'step' => '1',
				),
				'default'           => Options::DEFAULTS[ Options::MAX_DEPTH ],
				'placeholder'       => Options::DEFAULTS[ Options::MAX_DEPTH ],
				'desc'              => __( 'How many levels of children an order can have below it, such as 2 for children and grandchildren.', 'linked-orders-for-woocommerce' ),
			),
			Options::AUTOCOMPLETE => array(
				'title'   => __( 'Autocomplete descendant orders', 'linked-orders-for-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Set every descendant order to completed when its parent order is completed', 'linked-orders-for-woocommerce' ),
				'default' => Options::DEFAULTS[ Options::AUTOCOMPLETE ],
			),
			Options::DELETE_DATA  => array(
				'title'   => __( 'Remove all data on uninstallation', 'linked-orders-for-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Delete the plugin\'s settings and the links between orders when the plugin is deleted. This cannot be undone.', 'linked-orders-for-woocommerce' ),
				'default' => Options::DEFAULTS[ Options::DELETE_DATA ],
			),
		);

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
			$group_fields = apply_filters_deprecated( "linked_orders_for_woocommerce/{$group}_settings/get_group_fields", array( \array_map( static fn ( string $key ): array => $definitions[ $key ], $keys ) ), '2.0.0', 'woocommerce_get_settings_advanced' );

			$fields[] = array(
				'type'  => 'title',
				'id'    => "dws-lowc_{$group}_start",
				'title' => $titles[ $group ],
			);
			foreach ( $group_fields as $id => $field ) {
				$fields[] = array( 'id' => \is_string( $field['id'] ?? null ) ? $field['id'] : ( $keys[ $id ] ?? "dws-lowc_{$group}_$id" ) ) + $field; // A field a v1 callback adds keeps the option v1 stored it under, unless it names its own.
			}
			$fields[] = array(
				'type' => 'sectionend',
				'id'   => "dws-lowc_{$group}_end",
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
	 * Adds the link to the plugin's section first in its row on the Plugins screen, under v1's settings key.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string[] $links The row's action links.
	 *
	 * @return  string[]
	 */
	public function add_settings_link( array $links ): array {
		return array( 'settings' => \sprintf( '<a href="%s" aria-label="%s">%s</a>', esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=' . self::SECTION ) ), esc_attr__( 'View settings', 'linked-orders-for-woocommerce' ), esc_html__( 'Settings', 'linked-orders-for-woocommerce' ) ) ) + $links;
	}

	/**
	 * Adds the link to the plugin's support forum to its row on the Plugins screen, under v1's support key.
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
			$meta['support'] = \sprintf( '<a href="%s" aria-label="%s">%s</a>', esc_url( 'https://wordpress.org/support/plugin/linked-orders-for-woocommerce/' ), esc_attr__( 'Visit community forums', 'linked-orders-for-woocommerce' ), esc_html__( 'Community support', 'linked-orders-for-woocommerce' ) );
		}

		return $meta;
	}

	// endregion
}
