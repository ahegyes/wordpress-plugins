<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Unit;

use DeepWebSolutions\InternalComments\CommentStatusMove;
use DeepWebSolutions\InternalComments\Migrations;
use DeepWebSolutions\InternalComments\Options;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class MigrationsTest extends TestCase {
	// The fingerprint of the key set each seeding step stores, in the order Migrations::get_steps() lists the steps; a release that adds settings appends its step and its fingerprint.
	protected const array SEEDED_KEY_SETS = array( '9aa6a6b08ad403d2' );

	public function test_each_seeding_step_records_the_key_set_it_stores(): void {
		$seeding = \array_filter( Migrations::get_steps(), static fn ( mixed $step ): bool => $step instanceof \Closure && 'add_settings' === new \ReflectionFunction( $step )->getName() );

		$keys = \array_keys( Options::DEFAULTS );
		\sort( $keys );
		$fingerprint = \hash( 'xxh3', \implode( "\n", $keys ) );

		self::assertCount( \count( self::SEEDED_KEY_SETS ), $seeding, 'Each seeding step needs its fingerprint in SEEDED_KEY_SETS.' );
		self::assertSame( \array_last( self::SEEDED_KEY_SETS ), $fingerprint, "Options::DEFAULTS' keys changed: append self::add_settings( ... ) to Migrations::get_steps() and '$fingerprint' to SEEDED_KEY_SETS." );
	}

	// The move's pending check reads the revision its step reaches, which its place in the list sets.
	public function test_the_comment_move_names_the_revision_its_step_reaches(): void {
		$index = \array_find_key( Migrations::get_steps(), static fn ( mixed $step ): bool => $step instanceof CommentStatusMove );

		self::assertIsInt( $index );
		self::assertSame( $index + 1, new \ReflectionClassConstant( CommentStatusMove::class, 'REVISION' )->getValue() );
	}
}
