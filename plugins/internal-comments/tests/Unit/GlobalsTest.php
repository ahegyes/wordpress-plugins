<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Unit;

use DeepWebSolutions\InternalComments\Plugin;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class GlobalsTest extends TestCase {
	protected const string ROOT = __DIR__ . '/../..';

	// Any code can call a global, so the plugin declares only its contract there.
	public function test_every_global_is_tagged_api(): void {
		$includes = \glob( self::ROOT . '/includes/*.php' );
		$models   = \glob( self::ROOT . '/models/*.php' );
		self::assertIsArray( $includes );
		self::assertIsArray( $models );

		// The loader skips the includes whose names start with an underscore.
		$files = \array_filter( array( self::ROOT . '/' . Plugin::SLUG . '.php', self::ROOT . '/functions.php', ...$includes ), static fn ( string $file ): bool => ! \str_starts_with( \basename( $file ), '_' ) );

		$untagged = array();
		foreach ( $files as $file ) {
			\array_push( $untagged, ...self::find_untagged( $file, false ) );
		}
		foreach ( $models as $file ) {
			\array_push( $untagged, ...self::find_untagged( $file, true ) );
		}

		self::assertSame( array(), $untagged );
	}

	// A class-like declaration in models/ is a global, its members are not; elsewhere no class lives, so a named function or a define() call declares a global even inside a block, such as a function_exists() check.
	protected static function find_untagged( string $file, bool $models ): array {
		$tokens   = \array_values( \array_filter( \PhpToken::tokenize( (string) \file_get_contents( $file ) ), static fn ( \PhpToken $token ): bool => $token->is( \T_DOC_COMMENT ) || ! $token->isIgnorable() ) );
		$docs     = '';
		$import   = false;
		$untagged = array();
		foreach ( $tokens as $index => $token ) {
			if ( $token->is( \T_DOC_COMMENT ) ) {
				$docs = $token->text;
				continue;
			}

			// An import names symbols declared elsewhere, so nothing in it counts until its semicolon.
			if ( $token->is( \T_USE ) && true !== ( $tokens[ $index + 1 ] ?? null )?->is( '(' ) ) {
				$import = true;
			}

			$is_define = ! $models && $token->is( array( \T_STRING, \T_NAME_FULLY_QUALIFIED ) ) && 'define' === \strtolower( \ltrim( $token->text, '\\' ) ) && true === ( $tokens[ $index + 1 ] ?? null )?->is( '(' ) && true !== ( $tokens[ $index - 1 ] ?? null )?->is( array( \T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR, \T_DOUBLE_COLON ) );
			$kinds     = $models ? array( \T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM ) : array( \T_FUNCTION, \T_CONST );
			$name      = $tokens[ $index + ( $is_define ? 2 : 1 ) ] ?? null;
			if ( ! $import && ( $is_define || $token->is( $kinds ) ) && true === $name?->is( array( \T_STRING, \T_CONSTANT_ENCAPSED_STRING ) ) && 1 !== \preg_match( '/^[ \t]*\/?\*+[ \t]*@api\s/m', $docs ) ) {
				$untagged[] = \basename( $file ) . ': ' . \trim( $name->text, '\'"' );
			}

			// A docblock lasts until its statement ends or opens a block, so a guard such as defined() || define() or an attribute keeps it.
			if ( $token->is( array( ';', '{', '}' ) ) ) {
				$docs   = '';
				$import = $import && ! $token->is( ';' );
			}
		}

		return $untagged;
	}
}
