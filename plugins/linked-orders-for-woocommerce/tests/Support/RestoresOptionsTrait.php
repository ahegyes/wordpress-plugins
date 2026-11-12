<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait RestoresOptionsTrait {
	protected array $stored_options = array();

	// The snapshot runs before every other hook, so a class's own setup cannot taint it.
	#[Before( priority: 1 )]
	protected function store_options(): void {
		foreach ( self::OPTIONS as $key ) {
			$this->stored_options[ $key ] = get_option( $key );
		}
	}

	#[After]
	protected function restore_options(): void {
		foreach ( $this->stored_options as $key => $value ) {
			false === $value ? delete_option( $key ) : update_option( $key, $value );
		}
	}
}
