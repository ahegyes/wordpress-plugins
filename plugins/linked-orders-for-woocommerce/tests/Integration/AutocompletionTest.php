<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class AutocompletionTest extends TestCase {
	use OrderStorage;

	protected mixed $stored = false;

	#[Before]
	protected function store_setting(): void {
		$this->stored = get_option( DWS_LOWC_OPTION_AUTOCOMPLETE );
		update_option( DWS_LOWC_OPTION_AUTOCOMPLETE, 'yes' );
	}

	#[After]
	protected function restore_setting(): void {
		update_option( DWS_LOWC_OPTION_AUTOCOMPLETE, $this->stored );
	}

	#[DataProvider( 'storages' )]
	public function test_completing_an_order_completes_its_descendants_with_a_note( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		list( $root, $child, $grandchild ) = $this->create_tree();

		$root->update_status( 'completed' );

		foreach ( array( $child, $grandchild ) as $descendant ) {
			$stored = wc_get_order( $descendant->get_id() );
			self::assertSame( 'completed', $stored->get_status() );
			self::assertContains( 'Child Order autocompleted. Order status changed from Pending payment to Completed.', \array_map( static fn ( \stdClass $note ): string => $note->content, wc_get_order_notes( array( 'order_id' => $descendant->get_id() ) ) ) );
		}
	}

	// v1 walked the completed order's whole tree, the order first, so its filters see the order too.
	#[DataProvider( 'storages' )]
	public function test_the_completed_order_passes_the_filters_before_its_descendants( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		list( $root, $child ) = $this->create_tree();

		$seen   = array();
		$record = static function ( bool $should, int $order_id ) use ( &$seen ): bool {
			$seen[] = $order_id;

			return $should;
		};
		add_filter( 'dws_lowc/autocompletion/should_autocomplete', $record, 10, 2 );

		try {
			$root->update_status( 'completed' );
		} finally {
			remove_filter( 'dws_lowc/autocompletion/should_autocomplete', $record );
		}

		self::assertSame( array( $root->get_id(), $child->get_id() ), \array_slice( $seen, 0, 2 ) );
	}

	#[DataProvider( 'storages' )]
	public function test_completing_a_child_leaves_its_parent_alone( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		list( $root, $child, $grandchild ) = $this->create_tree();

		$child->update_status( 'completed' );

		self::assertSame( 'pending', wc_get_order( $root->get_id() )->get_status() );
		self::assertSame( 'completed', wc_get_order( $grandchild->get_id() )->get_status() );
	}

	#[DataProvider( 'storages' )]
	public function test_nothing_is_autocompleted_while_the_setting_is_off( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		update_option( DWS_LOWC_OPTION_AUTOCOMPLETE, 'no' );
		list( $root, $child ) = $this->create_tree();

		$root->update_status( 'completed' );

		self::assertSame( 'pending', wc_get_order( $child->get_id() )->get_status() );
	}

	#[DataProvider( 'storages' )]
	public function test_a_filter_keeps_a_descendant_or_sets_another_status( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		list( $root, $child, $grandchild ) = $this->create_tree();

		$skip   = static fn ( bool $should, int $descendant_id ): bool => $should && $descendant_id !== $child->get_id();
		$status = static fn (): string => 'processing';
		add_filter( 'dws_lowc/autocompletion/should_autocomplete', $skip, 10, 2 );
		add_filter( 'dws_lowc/autocompletion/completed_status', $status );

		try {
			$root->update_status( 'completed' );
		} finally {
			remove_filter( 'dws_lowc/autocompletion/should_autocomplete', $skip );
			remove_filter( 'dws_lowc/autocompletion/completed_status', $status );
		}

		self::assertSame( 'pending', wc_get_order( $child->get_id() )->get_status() );
		self::assertSame( 'processing', wc_get_order( $grandchild->get_id() )->get_status() );
	}

	protected function create_tree(): array {
		$root       = $this->create_order();
		$child      = $this->create_order();
		$grandchild = $this->create_order();
		self::assertTrue( dws_lowc_link_orders( $root->get_id(), $child->get_id() ) );
		self::assertTrue( dws_lowc_link_orders( $child->get_id(), $grandchild->get_id() ) );

		return array( wc_get_order( $root->get_id() ), wc_get_order( $child->get_id() ), wc_get_order( $grandchild->get_id() ) );
	}
}
