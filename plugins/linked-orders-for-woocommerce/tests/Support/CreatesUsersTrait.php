<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Support;

use PHPUnit\Framework\Attributes\After;

// A failed insert returns an error rather than an id, so the row stops before its cleanup could delete a user the site already had.
trait CreatesUsersTrait {
	protected array $users = array();

	protected function create_user( string $login, string $role ): int {
		$user = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		self::assertIsInt( $user );
		$this->users[] = $user;
		return $user;
	}

	#[After]
	protected function delete_users(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $this->users as $user ) {
			wp_delete_user( $user );
		}
		$this->users = array();
	}
}
