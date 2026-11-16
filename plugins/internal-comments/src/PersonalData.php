<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds internal comments to WordPress's personal data export and erasure, whose own comment exporter and eraser query only the public statuses.
 *
 * The eraser anonymizes each comment's author as WordPress's own eraser does, and the user each of their revisions names, and keeps the text, which the comment's thread still needs; the administrator decides each request by running it.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class PersonalData implements ComponentInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The number of comments each page of an export or erasure handles, WordPress's own, which keeps a request within its time limit.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     int
	 */
	protected const int PAGE_SIZE = 500;

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'add_eraser' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Returns WordPress's personal data exporters with the one for internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $exporters The exporters, by id.
	 *
	 * @return  array<string, mixed>
	 */
	public function add_exporter( array $exporters ): array {
		$exporters['internal-comments'] = array(
			'exporter_friendly_name' => __( 'Internal Comments', 'internal-comments' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Returns WordPress's personal data erasers with the one for internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $erasers The erasers, by id.
	 *
	 * @return  array<string, mixed>
	 */
	public function add_eraser( array $erasers ): array {
		$erasers['internal-comments'] = array(
			'eraser_friendly_name' => __( 'Internal Comments', 'internal-comments' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Returns one page of the internal comments an email address wrote, under any status, in WordPress's export format.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $email_address The address whose data is exported.
	 * @param   int    $page          The page, from 1.
	 *
	 * @return  array{data: list<array{group_id: string, group_label: string, group_description: string, item_id: string, data: list<array{name: string, value: string}>}>, done: bool}
	 */
	public function export( string $email_address, int $page = 1 ): array {
		$comments = $this->get_comments( $email_address, $page );
		$data     = array();
		foreach ( $comments as $comment ) {
			$fields = array();
			foreach (
				array(
					__( 'Comment Author', 'internal-comments' )            => $comment->comment_author,
					__( 'Comment Author Email', 'internal-comments' )      => $comment->comment_author_email,
					__( 'Comment Author URL', 'internal-comments' )        => $comment->comment_author_url,
					__( 'Comment Author IP', 'internal-comments' )         => $comment->comment_author_IP,
					__( 'Comment Author User Agent', 'internal-comments' ) => $comment->comment_agent,
					__( 'Comment Date', 'internal-comments' )              => $comment->comment_date,
					__( 'Comment Content', 'internal-comments' )           => $comment->comment_content,
				) as $name => $value
			) {
				if ( '' !== $value ) {
					$fields[] = array(
						'name'  => (string) $name,
						'value' => $value,
					);
				}
			}

			$data[] = array(
				'group_id'          => 'internal-comments',
				'group_label'       => __( 'Internal Comments', 'internal-comments' ),
				'group_description' => __( 'The internal comments the user wrote.', 'internal-comments' ),
				'item_id'           => "internal-comment-{$comment->comment_ID}",
				'data'              => $fields,
			);
		}

		return array(
			'data' => $data,
			'done' => \count( $comments ) < self::PAGE_SIZE,
		);
	}

	/**
	 * Anonymizes the author of each internal comment an email address wrote, under any status, and the address's user in each revision they made, on any comment, and keeps the text.
	 *
	 * It always reads the first page of each, since an anonymized comment or revision no longer matches the address, and stops at a page that anonymizes nothing, so a row whose write fails cannot hold it in a loop.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  \wpdb $wpdb The WordPress database connection.
	 *
	 * @param   string $email_address The address whose data is erased.
	 *
	 * @return  array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public function erase( string $email_address ): array {
		global $wpdb;
		$comments = '' === $email_address ? array() : $this->get_comments( $email_address, 1 );
		$removed  = false;
		$retained = false;
		foreach ( $comments as $comment ) {
			// The update guard keeps an internal comment's author through wp_update_comment(), so the eraser writes as WordPress's own does.
			$anonymized = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- WordPress's own comment eraser writes the same way, and the cache is cleaned below.
				$wpdb->comments,
				array(
					'comment_author'       => __( 'Anonymous', 'internal-comments' ),
					'comment_author_email' => '',
					'comment_author_url'   => '',
					'comment_author_IP'    => wp_privacy_anonymize_data( 'ip', $comment->comment_author_IP ),
					'comment_agent'        => '',
					'user_id'              => 0,
				),
				array( 'comment_ID' => (int) $comment->comment_ID )
			);
			if ( 1 === $anonymized ) {
				$removed = true;
				clean_comment_cache( (int) $comment->comment_ID );
			} else {
				$retained = true;
			}
		}

		$user      = '' === $email_address ? false : get_user_by( 'email', $email_address );
		$revisions = false === $user ? array() : $wpdb->get_results( $wpdb->prepare( 'SELECT meta_id, comment_id, meta_key FROM %i WHERE meta_key LIKE %s AND meta_value = %s LIMIT %d', $wpdb->commentmeta, $wpdb->esc_like( Comments::META_CHANGED_BY ) . '%', (string) $user->ID, self::PAGE_SIZE ), \ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The meta API reads no rows by key prefix and value, and the writes below clean the cache.
		foreach ( \is_array( $revisions ) ? $revisions : array() as $revision ) {
			$meta_id    = (int) \filter_var( $revision['meta_id'] ?? 0, \FILTER_VALIDATE_INT );
			$comment_id = (int) \filter_var( $revision['comment_id'] ?? 0, \FILTER_VALIDATE_INT );
			$change_id  = \substr( \is_string( $revision['meta_key'] ?? null ) ? $revision['meta_key'] : '', \strlen( Comments::META_CHANGED_BY ) );
			$rekeyed    = true;
			// 1.2.4 began each change id with its user's id, so such a revision takes a new id, in its record and in each of its keys.
			if ( ! wp_is_uuid( $change_id, 4 ) ) {
				$uuid = wp_generate_uuid4();
				// One statement renames the three keys and rewrites the record, so a failed write leaves the revision whole.
				$rekeyed = false !== $wpdb->query( $wpdb->prepare( 'UPDATE %i SET meta_key = REPLACE( meta_key, %s, %s ), meta_value = IF( meta_key = %s, %s, meta_value ) WHERE comment_id = %d AND ( meta_key IN ( %s, %s, %s ) OR ( meta_key = %s AND meta_value = %s ) )', $wpdb->commentmeta, $change_id, $uuid, Comments::META_CHANGE_ID, $uuid, $comment_id, Comments::META_PREVIOUS_CONTENT . $change_id, Comments::META_CHANGED_BY . $change_id, Comments::META_CHANGED_TIME . $change_id, Comments::META_CHANGE_ID, $change_id ) ?? '' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The meta API renames no key, and the cache is cleaned below.
				wp_cache_delete( $comment_id, 'comment_meta' );
			}

			if ( $rekeyed && update_metadata_by_mid( 'comment', $meta_id, '0' ) ) {
				$removed = true;
			} else {
				$retained = true;
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => array(),
			'done'           => ( \count( $comments ) < self::PAGE_SIZE && \count( (array) $revisions ) < self::PAGE_SIZE ) || ! $removed,
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Returns one page of the internal comments an email address wrote, under any status, oldest first.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $email_address The comments' author email address.
	 * @param   int    $page          The page, from 1.
	 *
	 * @return  list<\WP_Comment>
	 */
	protected function get_comments( string $email_address, int $page ): array {
		$comments = get_comments(
			array(
				'author_email'              => $email_address,
				'type'                      => Comments::TYPE,
				'status'                    => 'any',
				'number'                    => self::PAGE_SIZE,
				'paged'                     => \max( 1, $page ),
				'orderby'                   => 'comment_ID',
				'order'                     => 'ASC',
				'update_comment_meta_cache' => false,
			)
		);
		return \is_array( $comments ) ? \array_values( \array_filter( $comments, static fn ( mixed $comment ): bool => $comment instanceof \WP_Comment ) ) : array();
	}

	// endregion
}
