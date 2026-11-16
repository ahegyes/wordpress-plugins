<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPageDefinitionInterface;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Settings\Forms;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds the plugin's settings page under Settings, at v1's slug, with one tab for each of v1's settings groups, whose fields each store in an option of their own.
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
	protected const string PAGE = 'dws-internal-comments';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   PluginIdentity $identity The plugin's identity, which the page takes.
	 */
	public function __construct(
		protected PluginIdentity $identity
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
	public function register_hooks(): void {
		new OptionsPage( $this->identity, self::PAGE, $this )->register_hooks();
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
		return array(
			'parent'     => 'options-general.php',
			'capability' => Capabilities::MANAGE_OPTIONS,
			'page_title' => __( 'Internal Comments Settings', 'internal-comments' ),
			'menu_title' => __( 'Internal Comments', 'internal-comments' ),
			'tabs'       => array(
				array(
					'slug'  => 'general',
					'title' => _x( 'General', 'settings tab', 'internal-comments' ),
				),
				array(
					'slug'  => 'plugin',
					'title' => _x( 'Plugin', 'settings tab', 'internal-comments' ),
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
	public function get_tab_fields( string $page_id, string $tab_slug ): array {
		// Keyed by v1's field ids, which v1's group filter passes on.
		$group_fields = 'general' === $tab_slug ? array(
			'post-types'              => array(
				'type'    => 'checkboxes',
				'title'   => __( 'Post types that support internal comments', 'internal-comments' ),
				'options' => \array_map( static fn ( \WP_Post_Type $type ): string => (string) $type->label, get_post_types( array( 'show_ui' => true ), 'objects' ) ),
				'default' => Options::DEFAULTS[ Options::POST_TYPES ],
			),
			'enable-private-comments' => array(
				'type'    => 'checkbox',
				'title'   => __( 'Private comments', 'internal-comments' ),
				'desc'    => __( 'Allow internal comments that only their author and the users allowed to read private comments can see', 'internal-comments' ),
				'default' => Options::DEFAULTS[ Options::PRIVATE_COMMENTS ],
			),
		) : array(
			'remove-data-uninstall' => array(
				'type'    => 'checkbox',
				'title'   => __( 'Remove all data on uninstallation', 'internal-comments' ),
				'desc'    => __( 'Delete the plugin\'s settings and every internal comment when it is uninstalled, which cannot be undone', 'internal-comments' ),
				'default' => Options::DEFAULTS[ Options::DELETE_DATA ],
			),
		);

		/**
		 * Filters the fields of one of the settings page's groups, keyed by field id.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use internal_comments/options_page/fields.
		 *
		 * @param       array<string, Field> $fields The group's fields, keyed by id.
		 */
		$group_fields = apply_filters_deprecated( "internal_comments/{$tab_slug}_settings/get_group_fields", array( $group_fields ), '2.0.0', 'internal_comments/options_page/fields' );

		$fields = array();
		foreach ( $group_fields as $id => $field ) {
			// v1 passed a list of fields, each naming its own id, so a field a v1 callback appends names the row v1's getters read.
			$setting = Options::find_v1_setting( \is_string( $field['id'] ?? null ) ? $field['id'] : (string) $id, $tab_slug );
			if ( ! \is_null( $setting ) ) {
				$fields[] = array( 'id' => $setting[2] ) + $field; // A field a v1 callback adds stores in a row of its own, which v1's getters read.
			}
		}

		return $fields;
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
}
