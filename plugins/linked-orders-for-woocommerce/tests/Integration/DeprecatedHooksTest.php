<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DeprecatedHooksTest extends TestCase {
	use OrderStorage;

	protected array $deprecated = array();

	public static function renamed_hooks(): array {
		return array(
			'supported-order-types'        => array( 'post_type/supported_order_types', 'dws_lowc/post_type/supported_order_types', 'read_supported_order_types' ),
			'valid-statuses-for-new-child' => array( 'post_type/valid_statuses_for_new_child', 'dws_lowc/post_type/valid_statuses_for_new_child', 'read_valid_statuses' ),
			'creation-args'                => array( 'create_linked_order_args', 'dws_lowc/linked_order/creation_args', 'create_linked_child' ),
			'created'                      => array( 'created_linked_order', 'dws_lowc/linked_order/created', 'create_linked_child' ),
			'status-name'                  => array( 'node/status_name', 'dws_lowc/node/status_name', 'format_node_name' ),
			'formatted-name'               => array( 'node/get_formatted_name', 'dws_lowc/node/formatted_name', 'format_node_name' ),
			'can-create-child'             => array( 'node/can_create_child', 'dws_lowc/node/can_create_child', 'check_child_creation' ),
			'should-autocomplete'          => array( 'autocompletion/should_autocomplete', 'dws_lowc/autocompletion/should_autocomplete', 'complete_parent' ),
			'completed-status'             => array( 'autocompletion/completed_status', 'dws_lowc/autocompletion/completed_status', 'complete_parent' ),
		);
	}

	#[Before]
	protected function record_deprecations(): void {
		wp_set_current_user( 1 );
		add_filter( 'deprecated_hook_trigger_error', '__return_false' );
		add_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ), 10, 2 );
	}

	#[After]
	protected function stop_recording(): void {
		wp_set_current_user( 0 );
		remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		remove_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ) );
	}

	public function record_deprecated_hook( string $hook, string $replacement ): void {
		$this->deprecated[] = array( $hook, $replacement );
	}

	#[DataProvider( 'renamed_hooks' )]
	public function test_a_renamed_hook_fires_its_v1_literal_then_its_v2_name( string $v1_leaf, string $v2, string $trigger ): void {
		$v1    = "linked_orders_for_woocommerce/$v1_leaf";
		$fired = array();
		$spy   = static function ( mixed $value = null ) use ( &$fired ): mixed {
			$fired[] = current_filter();

			return $value;
		};
		add_filter( $v1, $spy );
		add_filter( $v2, $spy );

		try {
			$this->{$trigger}();
		} finally {
			remove_filter( $v1, $spy );
			remove_filter( $v2, $spy );
		}

		self::assertSame( array( $v1, $v2 ), $fired );
		self::assertSame( array( array( $v1, $v2 ) ), $this->deprecated );
	}

	protected function read_supported_order_types(): void {
		dws_lowc_get_supported_order_types();
	}

	protected function read_valid_statuses(): void {
		dws_lowc_get_valid_statuses_for_new_child();
	}

	protected function create_linked_child(): void {
		$id = dws_lowc_create_linked_child( $this->create_order()->get_id() );
		self::assertIsInt( $id );
		$this->orders[] = $id;
	}

	protected function format_node_name(): void {
		dws_lowc_get_order_node( $this->create_order() )?->get_formatted_name();
	}

	protected function check_child_creation(): void {
		dws_lowc_get_order_node( $this->create_order() )?->can_create_child();
	}

	protected function complete_parent(): void {
		$parent = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $parent->get_id(), $this->create_order()->get_id() ) );
		$stored = get_option( DWS_LOWC_OPTION_AUTOCOMPLETE );
		update_option( DWS_LOWC_OPTION_AUTOCOMPLETE, 'yes' );

		try {
			wc_get_order( $parent->get_id() )->update_status( 'completed' );
		} finally {
			update_option( DWS_LOWC_OPTION_AUTOCOMPLETE, $stored );
		}
	}
}
