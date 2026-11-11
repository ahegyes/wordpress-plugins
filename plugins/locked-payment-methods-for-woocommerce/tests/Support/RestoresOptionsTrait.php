<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait RestoresOptionsTrait {
	protected array $stored_options = array();

	#[Before]
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
