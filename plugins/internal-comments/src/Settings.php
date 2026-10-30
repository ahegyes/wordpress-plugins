<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPageDefinitionInterface;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Settings\Forms;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds the plugin's settings page under Settings, at v1's slug, with one tab for each of v1's settings groups.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Field from Forms
 */
final class Settings implements ComponentInterface, OptionsPageDefinitionInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The settings page's menu slug, which v1's settings URL names.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string PAGE = 'dws-internal-comments';

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
		new OptionsPage( self::PAGE, $this )->register_hooks();
		add_filter( 'plugin_action_links_' . plugin_basename( DWS_IC_FILE ), array( $this, 'add_settings_link' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_support_link' ), 10, 2 );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_options_page( string $page_id ): array {
		$tabs = array();
		foreach ( DWS_IC_SETTINGS_GROUPS as $group => $row ) {
			/**
			 * Filters the fields of one of the settings page's groups, keyed by field id.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use dws_ic/settings/{$group}_fields.
			 *
			 * @param       array<string, Field> $fields The group's fields, keyed by id.
			 */
			$group_fields = apply_filters_deprecated( "internal_comments/{$group}_settings/get_group_fields", array( $this->get_group_fields( $group ) ), '2.0.0', "dws_ic/settings/{$group}_fields" );

			$fields = array();
			foreach ( $group_fields as $id => $field ) {
				$fields[] = array( 'id' => (string) $id ) + $field;
			}

			/**
			 * Filters the fields of one of the settings page's groups, general or plugin, which store in the group's row.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   list<Field> $fields The group's fields.
			 */
			$fields = apply_filters( "dws_ic/settings/{$group}_fields", $fields );

			$tabs[] = array(
				'slug'   => $group,
				'title'  => 'general' === $group ? _x( 'General', 'settings tab', 'internal-comments' ) : _x( 'Plugin', 'settings tab', 'internal-comments' ),
				'option' => $row,
				'fields' => $fields,
			);
		}

		return array(
			'parent'     => 'options-general.php',
			'capability' => DWS_IC_CAP_MANAGE_OPTIONS,
			'page_title' => __( 'Internal Comments Settings', 'internal-comments' ),
			'menu_title' => __( 'Internal Comments', 'internal-comments' ),
			'tabs'       => $tabs,
		);
	}

	// endregion

	// region HOOKS

	/**
	 * Adds a link to the settings page in front of the plugin's links on the Plugins screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<array-key, string> $links The plugin's action links.
	 *
	 * @return  array<array-key, string>
	 */
	public function add_settings_link( array $links ): array {
		\array_unshift( $links, \sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ), esc_html__( 'Settings', 'internal-comments' ) ) );

		return $links;
	}

	/**
	 * Adds a link to the plugin's support forum to its row on the Plugins screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<array-key, string> $meta     The row's meta links.
	 * @param   string                   $basename The basename of the plugin the row shows.
	 *
	 * @return  array<array-key, string>
	 */
	public function add_support_link( array $meta, string $basename ): array {
		if ( plugin_basename( DWS_IC_FILE ) === $basename ) {
			$meta[] = \sprintf( '<a href="%s">%s</a>', esc_url( 'https://wordpress.org/support/plugin/internal-comments/' ), esc_html__( 'Community support', 'internal-comments' ) );
		}

		return $meta;
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the fields of one of v1's settings groups, keyed by v1 field id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $group The group, general or plugin.
	 *
	 * @return  array<string, Field>
	 */
	protected function get_group_fields( string $group ): array {
		if ( 'general' === $group ) {
			return array(
				DWS_IC_SETTING_POST_TYPES       => array(
					'type'    => 'checkboxes',
					'title'   => __( 'Post types that support internal comments', 'internal-comments' ),
					'options' => \array_map( static fn ( \WP_Post_Type $type ): string => (string) $type->label, get_post_types( array( 'show_ui' => true ), 'objects' ) ),
					'default' => DWS_IC_SETTING_DEFAULTS[ DWS_IC_OPTION_GENERAL ][ DWS_IC_SETTING_POST_TYPES ],
				),
				DWS_IC_SETTING_PRIVATE_COMMENTS => array(
					'type'    => 'checkbox',
					'title'   => __( 'Private comments', 'internal-comments' ),
					'desc'    => __( 'Allow internal comments that only their author and administrators can see', 'internal-comments' ),
					'default' => DWS_IC_SETTING_DEFAULTS[ DWS_IC_OPTION_GENERAL ][ DWS_IC_SETTING_PRIVATE_COMMENTS ],
				),
			);
		}

		return array(
			DWS_IC_SETTING_DELETE_DATA => array(
				'type'    => 'checkbox',
				'title'   => __( 'Remove all data on uninstallation', 'internal-comments' ),
				'desc'    => __( 'Delete the plugin\'s settings and every internal comment when it is uninstalled, which cannot be undone', 'internal-comments' ),
				'default' => DWS_IC_SETTING_DEFAULTS[ DWS_IC_OPTION_PLUGIN ][ DWS_IC_SETTING_DELETE_DATA ],
			),
		);
	}

	// endregion
}
