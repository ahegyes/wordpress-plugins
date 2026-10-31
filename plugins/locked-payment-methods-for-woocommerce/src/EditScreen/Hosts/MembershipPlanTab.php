<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\EditScreen\Hosts;

use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormHostInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectStoreInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\Stores\MetadataStore;

\defined( 'ABSPATH' ) || exit;

/**
 * Shows an object form as a tab of WooCommerce Memberships' plan data box and saves it with the box, storing each field as the plan's post meta.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class MembershipPlanTab implements ObjectFormHostInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The form the host serves.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     ObjectForm
	 */
	protected ObjectForm $form;

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks( ObjectForm $form ): void {
		$this->form = $form;
		add_filter( 'wc_membership_plan_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'wc_membership_plan_data_panels', array( $this, 'render_panel' ) );
		add_action( 'wc_memberships_save_meta_box', array( $this, 'save_panel' ), 10, 3 );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_object_capability(): string {
		return 'edit_post';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_default_store(): ObjectStoreInterface {
		return new MetadataStore( 'post' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function render_rows( string $title, array $rows ): void {
		foreach ( $rows as $row ) {
			echo '<div class="form-field"><p><strong>' . $row['title'] . '</strong></p>' . $row['control'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The form escapes titles and controls.
		}
	}

	// endregion

	// region HOOKS

	/**
	 * Adds the form's tab to the plan data box, for a user who may edit the plan with it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, array<string, string>> $tabs The box's tabs, by id.
	 *
	 * @return  array<string, array<string, string>>
	 */
	public function add_tab( array $tabs ): array {
		$plan_id = (int) get_the_ID();
		if ( 0 !== $plan_id && $this->form->can_render( $plan_id ) ) {
			$tabs[ $this->form->get_id() ] = array(
				'label'  => $this->form->get_title( $plan_id ),
				'target' => $this->get_panel_id(),
			);
		}

		return $tabs;
	}

	/**
	 * Prints the form's panel in the plan data box.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function render_panel(): void {
		echo '<div id="' . esc_attr( $this->get_panel_id() ) . '" class="panel woocommerce_options_panel">';
		$this->form->render( (int) get_the_ID() );
		echo '</div>';
	}

	/**
	 * Saves the form with the plan's data box.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $post_data   The data Memberships saves.
	 * @param   mixed $meta_box_id The id of the box Memberships saves.
	 * @param   int   $plan_id     The plan's id.
	 *
	 * @return  void
	 */
	public function save_panel( mixed $post_data, mixed $meta_box_id, int $plan_id ): void {
		$this->form->save( $plan_id );
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the id of the form's panel.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	protected function get_panel_id(): string {
		return \str_replace( '_', '-', $this->form->get_id() ) . '-data';
	}

	// endregion
}
