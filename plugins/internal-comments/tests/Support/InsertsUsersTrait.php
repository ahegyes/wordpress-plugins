<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Support;

// An insert an aborted run left a login for answers a WP_Error, whose cast to an id is 1, so a cleanup would delete the site's administrator.
trait InsertsUsersTrait {
	protected static function insert_user( array $args ): int {
		$user = wp_insert_user( $args );
		self::assertIsInt( $user, is_wp_error( $user ) ? $user->get_error_message() : '' );
		return $user;
	}
}
