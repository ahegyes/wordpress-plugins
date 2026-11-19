<?php declare( strict_types=1 );

// A component's gate that throws fails the plugin's boot; WP-CLI hooks it before WordPress loads, and it throws only on the boot's read.
WP_CLI::add_wp_hook(
	'pre_option_dws_lpmwc_unlock_via_user_roles',
	static function ( mixed $value ): mixed {
		static $thrown = false;
		if ( ! $thrown ) {
			$thrown = true;
			throw new RuntimeException( 'A gate failed.' );
		}

		return $value;
	}
);
