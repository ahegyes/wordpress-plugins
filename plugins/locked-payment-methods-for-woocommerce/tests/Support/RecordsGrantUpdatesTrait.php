<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait RecordsGrantUpdatesTrait {
	protected array $updates = array();

	#[Before]
	protected function record_grant_updates(): void {
		add_action( 'locked_payment_methods_for_woocommerce/grants/updated', array( $this, 'record_grant_update' ), 10, 4 );
	}

	#[After]
	protected function stop_recording_grant_updates(): void {
		remove_action( 'locked_payment_methods_for_woocommerce/grants/updated', array( $this, 'record_grant_update' ) );
	}

	public function record_grant_update( string $strategy_id, int $subject_id, array $ids, array $previous ): void {
		$this->updates[] = array( $strategy_id, $subject_id, $ids, $previous );
	}
}
