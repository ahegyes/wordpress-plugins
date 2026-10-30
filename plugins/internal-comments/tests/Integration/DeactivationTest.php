<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Scheduling\ScheduledHook;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DeactivationTest extends TestCase {
	#[After]
	protected function unschedule_hooks(): void {
		foreach ( dws_ic_get_footprint()['cron_hooks'] as $hook ) {
			dws_ic_plugin()->scheduler->unschedule_hook( $hook );
		}
	}

	public function test_deactivation_unschedules_every_hook_of_the_footprint(): void {
		$scheduler = dws_ic_plugin()->scheduler;
		$hooks     = \array_map( static fn ( string $hook ): ScheduledHook => new ScheduledHook( $hook ), dws_ic_get_footprint()['cron_hooks'] );
		foreach ( $hooks as $hook ) {
			self::assertTrue( $scheduler->enqueue( $hook ) );
		}

		do_action( 'deactivate_' . plugin_basename( DWS_IC_FILE ), false );

		foreach ( $hooks as $hook ) {
			self::assertFalse( $scheduler->is_scheduled( $hook ), "The hook $hook->hook is still scheduled." );
		}
	}
}
