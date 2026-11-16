<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Asset\Assets;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds a column to the lists of the selected post types and order types, which counts each post's internal comments and opens a quick view of them.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class ListColumn implements ComponentInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The id of the posts list column.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string COLUMN = 'dws-internal-comments';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Assets $assets The registrar of the plugin's built scripts and styles.
	 */
	public function __construct( protected Assets $assets ) {}

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
		foreach ( Options::get_list( Options::POST_TYPES ) as $post_type ) {
			// WooCommerce rebuilds its orders' and coupons' columns on this hook at the default priority.
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_posts_column' ), 20 );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_cell' ), 10, 2 );

			// WooCommerce's order tables list each order type through hooks of its own, which other post types never reach.
			add_filter( "woocommerce_{$post_type}_list_table_columns", array( $this, 'add_orders_column' ) );
			add_action( "woocommerce_{$post_type}_list_table_custom_column", array( $this, 'render_order_cell' ), 10, 2 );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_list_assets' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Adds the internal comments column to the list of the post type whose columns hook runs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $columns The list's columns.
	 *
	 * @return  array<string, string>
	 */
	public function add_posts_column( array $columns ): array {
		// One callback serves every selected post type, so the running hook names the list's.
		return 1 === \preg_match( '/^manage_(.+)_posts_columns$/', (string) current_filter(), $matches ) ? $this->add_column( $columns, $matches[1] ) : $columns;
	}

	/**
	 * Adds the internal comments column to the order tables' list of the order type whose columns hook runs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $columns The list's columns.
	 *
	 * @return  array<string, string>
	 */
	public function add_orders_column( array $columns ): array {
		// One callback serves every selected order type, so the running hook names the list's.
		return 1 === \preg_match( '/^woocommerce_(.+)_list_table_columns$/', (string) current_filter(), $matches ) ? $this->add_column( $columns, $matches[1] ) : $columns;
	}

	/**
	 * Prints an order's cell of the internal comments column in the order tables' list.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $column The column's id.
	 * @param   mixed  $order  The row's order.
	 *
	 * @return  void
	 */
	public function render_order_cell( string $column, mixed $order ): void {
		if ( $order instanceof \WC_Abstract_Order ) {
			$this->render_cell( $column, $order->get_id() );
		}
	}

	/**
	 * Prints a post's cell of the internal comments column: the number of its comments the user can see, linked to them on the Comments screen where the user may list them, and a button that opens their quick view.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $column  The column's id.
	 * @param   int    $post_id The row's post id.
	 *
	 * @return  void
	 */
	public function render_cell( string $column, int $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$is_trashed = 'trash' === get_post_status( $post_id );
		if ( $is_trashed ) {
			$count = \count( dws_ic_get_internal_comments_from_trash_meta( $post_id ) );
		} elseif ( current_user_can( Capabilities::SEE ) && current_user_can( Capabilities::SEE_PRIVATE ) && current_user_can( 'edit_post', $post_id ) ) {
			// A user who sees every comment of the post needs no check per comment.
			$count = \count(
				get_comments(
					array(
						'post_id' => $post_id,
						'type'    => Comments::TYPE,
						'status'  => Comments::STATUS,
						'fields'  => 'ids',
					)
				)
			);
		} else {
			$count = \count( dws_ic_get_internal_comments( $post_id, array( 'hierarchical' => false ) ) );
		}
		if ( 0 === $count ) {
			echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__( 'No internal comments', 'internal-comments' ) . '</span>';
			return;
		}

		$label = \sprintf(
			/* translators: %s: The number of internal comments. */
			_n( '%s internal comment', '%s internal comments', $count, 'internal-comments' ),
			number_format_i18n( $count )
		);

		$number  = '<span class="comment-count" aria-hidden="true">' . esc_html( number_format_i18n( $count ) ) . '</span><span class="screen-reader-text">' . esc_html( $label ) . '</span>';
		$popover = 'dws-ic-quick-view-' . $post_id;
		$link    = add_query_arg(
			array(
				'p'            => $post_id,
				'comment_type' => Comments::TYPE,
			),
			admin_url( 'edit-comments.php' )
		);
		?>
		<div class="post-com-count-wrapper dws-ic-column">
			<?php if ( ! $is_trashed && current_user_can( Capabilities::SEE_TABLE ) ) : ?>
				<a class="post-com-count" href="<?php echo esc_url( $link ); ?>"><?php echo $number; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></a>
			<?php else : ?>
				<span class="post-com-count"><?php echo $number; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></span>
			<?php endif; ?>
			<button type="button" class="button-link" popovertarget="<?php echo esc_attr( $popover ); ?>"><?php esc_html_e( 'Quick view', 'internal-comments' ); ?></button>
			<div id="<?php echo esc_attr( $popover ); ?>" class="dws-ic-quick-view" popover role="dialog" aria-label="<?php echo esc_attr( $label ); ?>" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
				<button type="button" class="button-link dws-ic-quick-view__close" popovertarget="<?php echo esc_attr( $popover ); ?>" popovertargetaction="hide"><?php esc_html_e( 'Close', 'internal-comments' ); ?></button>
				<div class="dws-ic-quick-view__comments" aria-live="polite"><?php esc_html_e( 'Loading…', 'internal-comments' ); ?></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Loads the quick view's script and stylesheet on the list of a selected post type, for a user who may see its column.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $hook_suffix The admin screen's hook suffix.
	 *
	 * @return  void
	 */
	public function enqueue_list_assets( string $hook_suffix ): void {
		$screen    = \function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = null;
		if ( 'edit.php' === $hook_suffix ) {
			$post_type = \is_null( $screen ) || '' === $screen->post_type ? 'post' : $screen->post_type;
		} elseif ( \function_exists( 'wc_get_page_screen_id' ) && ! isset( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tells the order tables' list from their edit screen, which shares its hook suffix.
			$post_type = \array_find( Options::get_list( Options::POST_TYPES ), static fn ( string $type ): bool => wc_get_page_screen_id( $type ) === $hook_suffix );
		}

		if ( \is_null( $post_type ) || ! $this->is_shown_for( $post_type ) ) {
			return;
		}

		if ( $this->assets->register_script( Plugin::SLUG . '-quick-view', 'assets/build/quick-view.js', array( 'strategy' => 'defer' ) ) ) {
			wp_enqueue_script( Plugin::SLUG . '-quick-view' );
		}
		if ( $this->assets->register_style( Plugin::SLUG . '-quick-view', 'assets/build/quick-view.css' ) ) {
			wp_enqueue_style( Plugin::SLUG . '-quick-view' );
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Returns a list's columns with the internal comments column after the comments column, or after the title where the list has none, where the column shows.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $columns   The list's columns.
	 * @param   string                $post_type The list's post type.
	 *
	 * @return  array<string, string>
	 */
	protected function add_column( array $columns, string $post_type ): array {
		if ( ! $this->is_shown_for( $post_type ) ) {
			return $columns;
		}

		$preceding = isset( $columns['comments'] ) ? 'comments' : 'title';

		/**
		 * Filters the column the internal comments column follows in a posts list.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use internal_comments/list_table/preceding_column.
		 *
		 * @param       string      $preceding The id of the column it follows.
		 * @param       string|null $post_type The list's post type.
		 */
		$preceding = apply_filters_deprecated( 'internal_comments/wp_table_output/insert_after', array( $preceding, $post_type ), '2.0.0', 'internal_comments/list_table/preceding_column' );

		/**
		 * Filters the column the internal comments column follows in a posts list, which it ends where that column is missing.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   string $preceding The id of the column it follows.
		 * @param   string $post_type The list's post type.
		 */
		$preceding = apply_filters( 'internal_comments/list_table/preceding_column', $preceding, $post_type );

		$position = \array_search( $preceding, \array_keys( $columns ), true );
		$position = false === $position ? \count( $columns ) : $position + 1;
		return \array_slice( $columns, 0, $position, true ) + array( self::COLUMN => __( 'Internal Comments', 'internal-comments' ) ) + \array_slice( $columns, $position, null, true );
	}

	/**
	 * Returns whether the column shows in the list of a post or order type, which takes a selected type and a user who may see internal comments and the column.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $post_type The list's post or order type.
	 *
	 * @return  bool
	 */
	protected function is_shown_for( string $post_type ): bool {
		return \in_array( $post_type, Options::get_list( Options::POST_TYPES ), true ) && current_user_can( Capabilities::SEE ) && current_user_can( Capabilities::SEE_COLUMN );
	}

	// endregion
}
