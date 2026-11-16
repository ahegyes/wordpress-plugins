<?php declare( strict_types=1 );
/**
 * Admin view: a post's internal comments in the meta box and the quick view, or the note that it has none.
 *
 * It reads only $args and assigns no WordPress global, since load_template() declares several in its scope.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 *
 * @var     array{comments: list<DWS_Internal_Comment>, empty: string} $args The post's top-level comments the user can see, and the note shown where it has none.
 */

defined( 'ABSPATH' ) || exit;

if ( array() === $args['comments'] ) : ?>
	<p class="dws-internal-comments__empty"><?php echo esc_html( $args['empty'] ); ?></p>
<?php else : ?>
	<ul class="dws-internal-comments__list">
		<?php
		foreach ( $args['comments'] as $dws_ic_comment ) {
			load_template(
				__DIR__ . '/html-comment.php',
				false,
				array(
					'comment' => $dws_ic_comment,
					'level'   => 0,
				)
			);
		}
		?>
	</ul>
	<?php
endif;
