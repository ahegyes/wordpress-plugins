<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Support;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use PHPUnit\Framework\Attributes\After;

// A row registers its own component, whose hooks read settings the booted plugin read before the row changed them.
trait RegistersHooksTrait {
	protected array $registered = array();

	protected function register( ComponentInterface $component ): ComponentInterface {
		$component->register_hooks();
		$this->registered[] = $component;
		return $component;
	}

	#[After]
	protected function unregister(): void {
		foreach ( $GLOBALS['wp_filter'] as $hook => $wp_hook ) {
			foreach ( $wp_hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( \is_array( $callback['function'] ) && \in_array( $callback['function'][0], $this->registered, true ) ) {
						remove_filter( $hook, $callback['function'], $priority );
					}
				}
			}
		}

		$this->registered = array();
	}
}
