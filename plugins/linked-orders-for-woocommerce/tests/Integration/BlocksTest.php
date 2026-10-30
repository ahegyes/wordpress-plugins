<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Blocks;
use DeepWebSolutions\LinkedOrders\Plugin;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class BlocksTest extends TestCase {
	#[After]
	protected function dequeue(): void {
		wp_dequeue_script( Plugin::SLUG . '-editor' );
		wp_deregister_script( Plugin::SLUG . '-editor' );
	}

	public function test_the_example_block_registers_from_the_build_with_its_scripts_and_styles(): void {
		$block = \WP_Block_Type_Registry::get_instance()->get_registered( 'linked-orders-for-woocommerce/example-notice' );

		self::assertInstanceOf( \WP_Block_Type::class, $block );
		self::assertSame( array( 'linked-orders-for-woocommerce-example-notice-editor-script' ), $block->editor_script_handles );
		self::assertSame( array( 'linked-orders-for-woocommerce-example-notice-editor-style' ), $block->editor_style_handles );
		self::assertSame( array( 'linked-orders-for-woocommerce-example-notice-style' ), $block->style_handles );
	}

	public function test_the_block_editor_gets_the_editor_script_with_its_generated_dependencies(): void {
		$blocks = new Blocks();
		$blocks->register_hooks();
		self::assertSame( 10, has_action( 'enqueue_block_editor_assets', array( $blocks, 'enqueue_editor_script' ) ) );
		remove_action( 'init', array( $blocks, 'register_blocks' ) );
		remove_action( 'enqueue_block_editor_assets', array( $blocks, 'enqueue_editor_script' ) );

		$blocks->enqueue_editor_script();

		$asset = require plugin_dir_path( DWS_LOWC_FILE ) . 'assets/build/editor.asset.php';
		self::assertTrue( wp_script_is( Plugin::SLUG . '-editor', 'enqueued' ) );
		self::assertSame( $asset['dependencies'], wp_scripts()->registered[ Plugin::SLUG . '-editor' ]->deps );
		self::assertSame( $asset['version'], wp_scripts()->registered[ Plugin::SLUG . '-editor' ]->ver );
	}
}
