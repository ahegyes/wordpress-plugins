<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\AdminNotice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminNotice::class )]
final class AdminNoticeRenderTest extends TestCase {
	public function test_render_outputs_info_notice_with_expected_text(): void {
		\ob_start();
		( new AdminNotice() )->render();
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'class="notice notice-info"', $output );
		self::assertStringContainsString( 'Locked Payment Methods for WooCommerce is active.', $output );
	}
}
