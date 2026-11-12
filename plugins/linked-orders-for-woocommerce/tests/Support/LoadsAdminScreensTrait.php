<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Support;

use PHPUnit\Framework\Attributes\BeforeClass;

// WordPress and WooCommerce load their screen and meta box APIs only in admin requests.
trait LoadsAdminScreensTrait {
	#[BeforeClass]
	public static function load_admin_screens(): void {
		require_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
	}
}
