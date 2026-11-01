<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Unit;

use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\CompositeComponentInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ComponentTreeTest extends TestCase {
	public function test_no_component_appears_twice_in_the_tree_whatever_its_gates(): void {
		$walked  = array();
		$pending = Plugin::COMPONENTS;
		while ( array() !== $pending ) {
			$class    = \array_shift( $pending );
			$walked[] = $class;
			if ( \is_a( $class, CompositeComponentInterface::class, true ) ) {
				\array_push( $pending, ...$class::get_child_component_classes() );
			}
		}

		self::assertSame( \array_values( \array_unique( $walked ) ), $walked );
	}
}
