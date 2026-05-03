<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Plugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Plugin::class )]
final class PluginBootTest extends TestCase {
	public function test_get_instance_returns_singleton(): void {
		$first  = Plugin::get_instance();
		$second = Plugin::get_instance();

		self::assertSame( $first, $second );
	}

	public function test_boot_registers_admin_notices_hook(): void {
		Plugin::get_instance()->boot();

		self::assertNotFalse(
			has_action( 'admin_notices' ),
			'AdminNotice component should register at least one admin_notices callback after boot.'
		);
	}
}
