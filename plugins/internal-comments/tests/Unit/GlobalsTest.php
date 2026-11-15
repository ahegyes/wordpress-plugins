<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class GlobalsTest extends TestCase {
	// Any code can call a global, so the plugin declares only its contract there.
	public function test_every_global_function_and_constant_is_tagged_api(): void {
		$files = \glob( \dirname( __DIR__, 2 ) . '/includes/*.php' );
		self::assertIsArray( $files );

		$untagged = array();
		foreach ( array( \dirname( __DIR__, 2 ) . '/functions.php', ...$files ) as $file ) {
			$tokens = \array_values( \array_filter( \PhpToken::tokenize( (string) \file_get_contents( $file ) ), static fn ( \PhpToken $token ): bool => $token->is( \T_DOC_COMMENT ) || ! $token->isIgnorable() ) );
			$docs   = '';
			$import = false;
			foreach ( $tokens as $index => $token ) {
				if ( $token->is( \T_DOC_COMMENT ) ) {
					$docs = $token->text;
					continue;
				}

				// An import names symbols declared elsewhere, so nothing in it counts until its semicolon.
				if ( $token->is( \T_USE ) && true !== ( $tokens[ $index + 1 ] ?? null )?->is( '(' ) ) {
					$import = true;
				}

				// No class lives in these files, so a named function or a define() call declares a global even inside a block, such as a function_exists() check, and carries its own docblock there.
				$is_define = $token->is( array( \T_STRING, \T_NAME_FULLY_QUALIFIED ) ) && 'define' === \strtolower( \ltrim( $token->text, '\\' ) ) && true === ( $tokens[ $index + 1 ] ?? null )?->is( '(' ) && true !== ( $tokens[ $index - 1 ] ?? null )?->is( array( \T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR, \T_DOUBLE_COLON ) );
				$name      = $tokens[ $index + ( $is_define ? 2 : 1 ) ] ?? null;
				if ( ! $import && ( $is_define || $token->is( array( \T_FUNCTION, \T_CONST ) ) ) && true === $name?->is( array( \T_STRING, \T_CONSTANT_ENCAPSED_STRING ) ) && 1 !== \preg_match( '/^[ \t]*\/?\*+[ \t]*@api\s/m', $docs ) ) {
					$untagged[] = \basename( $file ) . ': ' . \trim( $name->text, '\'"' );
				}

				// A docblock lasts until its statement ends or opens a block, so a guard such as defined() || define() or an attribute keeps it.
				if ( $token->is( array( ';', '{', '}' ) ) ) {
					$docs   = '';
					$import = $import && ! $token->is( ';' );
				}
			}
		}

		self::assertSame( array(), $untagged );
	}
}
