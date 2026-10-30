<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration\Feature;

use DeepWebSolutions\InternalComments\Feature\Feature;
use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\CompositeComponentInterface;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class FeatureTest extends TestCase {
	protected mixed $stored = false;

	public static function stored_toggles(): array {
		return array(
			'on'    => array( 'yes', true ),
			'off'   => array( 'no', false ),
			'unset' => array( null, false ),
		);
	}

	#[\Override]
	protected function setUp(): void {
		$this->stored = get_option( DWS_IC_OPTION_FEATURE );
	}

	#[After]
	protected function restore_row(): void {
		false === $this->stored ? delete_option( DWS_IC_OPTION_FEATURE ) : update_option( DWS_IC_OPTION_FEATURE, $this->stored );
	}

	#[DataProvider( 'stored_toggles' )]
	public function test_the_feature_loads_only_where_the_greeting_is_switched_on( ?string $stored, bool $expected ): void {
		\is_null( $stored ) ? delete_option( DWS_IC_OPTION_FEATURE ) : update_option( DWS_IC_OPTION_FEATURE, $stored );

		self::assertSame( $expected, Feature::should_load() );
	}

	#[RunInSeparateProcess]
	public function test_with_the_greeting_switched_on_every_declared_component_boots(): void {
		$declared = array();
		$pending  = Plugin::COMPONENTS;
		while ( array() !== $pending ) {
			$class      = \array_shift( $pending );
			$declared[] = $class;
			if ( \is_a( $class, CompositeComponentInterface::class, true ) ) {
				\array_unshift( $pending, ...$class::get_child_component_classes() );
			}
		}

		update_option( DWS_IC_OPTION_FEATURE, 'yes' );
		$plugin = new Plugin();
		$plugin->boot();

		self::assertSame( 'booted', $plugin->boot->status );
		self::assertSame( $declared, $plugin->kernel?->resolved );
		self::assertSame( array(), $plugin->kernel->skipped );
	}
}
