<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Support;

// Holds the memberships the stand-in for WooCommerce Memberships' wc_memberships_get_user_active_memberships() returns, by user id.
final class MembershipsDouble {
	public static array $memberships = array();

	public static function add( int $user_id, int $plan_id, bool $active = true ): void {
		self::$memberships[ $user_id ][] = new class( $plan_id, $active ) {
			public function __construct( protected int $plan_id, protected bool $active ) {}

			public function get_plan_id(): int {
				return $this->plan_id;
			}

			public function is_active(): bool {
				return $this->active;
			}
		};
	}
}
