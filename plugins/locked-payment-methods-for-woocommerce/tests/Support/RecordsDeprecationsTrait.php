<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait RecordsDeprecationsTrait {
	protected array $deprecated = array();

	protected array $listened = array();

	#[Before]
	protected function record_deprecations(): void {
		add_filter( 'deprecated_function_trigger_error', '__return_false' );
		add_filter( 'deprecated_hook_trigger_error', '__return_false' );
		add_action( 'deprecated_function_run', array( $this, 'record_deprecation' ), 10, 2 );
		add_action( 'deprecated_hook_run', array( $this, 'record_deprecation' ), 10, 2 );
	}

	#[After]
	protected function stop_recording_deprecations(): void {
		foreach ( $this->listened as $hook ) {
			remove_filter( $hook, array( $this, 'pass_through' ) );
		}

		remove_action( 'deprecated_hook_run', array( $this, 'record_deprecation' ) );
		remove_action( 'deprecated_function_run', array( $this, 'record_deprecation' ) );
		remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		remove_filter( 'deprecated_function_trigger_error', '__return_false' );
	}

	public function record_deprecation( string $name, string $replacement ): void {
		$this->deprecated[] = array( $name, $replacement );
	}

	// WordPress reports a deprecated hook only when something listens on it.
	protected function listen_on( string ...$hooks ): void {
		foreach ( $hooks as $hook ) {
			add_filter( $hook, array( $this, 'pass_through' ) );
			$this->listened[] = $hook;
		}
	}

	public function pass_through( mixed $value = null ): mixed {
		return $value;
	}
}
