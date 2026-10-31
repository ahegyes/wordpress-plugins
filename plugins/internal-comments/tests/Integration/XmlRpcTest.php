<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class XmlRpcTest extends TestCase {
	// XML-RPC's wp.getComments returns the comments of any status a user who moderates comments names, so a request defines a constant this process keeps.
	#[RunInSeparateProcess]
	public function test_xml_rpc_lists_no_internal_comments(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . WPINC . '/class-IXR.php';
		require_once ABSPATH . WPINC . '/class-wp-xmlrpc-server.php';
		$admin  = (int) wp_insert_user(
			array(
				'user_login' => 'ic-xmlrpc-admin',
				'user_pass'  => 'password',
				'user_email' => 'ic-xmlrpc-admin@example.org',
				'role'       => 'administrator',
			)
		);
		$editor = (int) wp_insert_user(
			array(
				'user_login' => 'ic-xmlrpc-editor',
				'user_pass'  => 'password',
				'user_email' => 'ic-xmlrpc-editor@example.org',
				'role'       => 'editor',
			)
		);
		$post   = (int) wp_insert_post(
			array(
				'post_title'  => 'XML-RPC',
				'post_status' => 'publish',
			)
		);
		dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => $post,
				'comment_content' => 'Secret',
				'comment_meta'    => array( DWS_IC_META_PRIVATE => 'yes' ),
			),
			get_user_by( 'id', $admin ) ?: null
		);
		\define( 'XMLRPC_REQUEST', true );
		add_filter( 'xmlrpc_enabled', '__return_true' );

		try {
			$comments = ( new \wp_xmlrpc_server() )->wp_getComments(
				array(
					1,
					'ic-xmlrpc-editor',
					'password',
					array(
						'post_id' => $post,
						'status'  => DWS_IC_COMMENT_STATUS,
					),
				)
			);
		} finally {
			wp_delete_post( $post, true );
			wp_delete_user( $admin );
			wp_delete_user( $editor );
		}

		self::assertSame( array(), $comments );
	}
}
