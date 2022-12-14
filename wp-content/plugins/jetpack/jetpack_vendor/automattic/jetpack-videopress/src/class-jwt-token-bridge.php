<?php
/**
 * VideoPress Jwt_Token_Bridge
 *
 * @package automattic/jetpack-videopress
 */

namespace Automattic\Jetpack\VideoPress;

use Automattic\Jetpack\Assets;

/**
 * VideoPress Jwt_Token_Bridge class.
 */
class Jwt_Token_Bridge {

	/**
	 * The handle used to enqueue the script
	 *
	 * @var string
	 */
	const SCRIPT_HANDLE = 'media-video-jwt-bridge';

	/**
	 * Initializer
	 *
	 * This method should be called only once by the Initializer class. Do not call this method again.
	 */
	public static function init() {

		if ( ! Status::is_active() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_jwt_token_bridge' ), 1 );

	}

	/**
	 * Gets the bridge script URL depending on the environment we are in
	 *
	 * @return string
	 */
	public static function get_bridge_url() {
		// TODO: use minified version in production.
		return Assets::get_file_url_for_environment(
			'../build/lib/videopress-token-bridge.js', // <- production
			'client/lib/videopress-token-bridge.js', // <- development
			__FILE__
		);
	}

	/**
	 * Enqueues the jwt bridge script.
	 */
	public static function enqueue_jwt_token_bridge() {
		global $post;
		$post_id = isset( $post->ID ) ? absint( $post->ID ) : 0;

		$bridge_url = self::get_bridge_url();

		self::enqueue_script();

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'videopressAjax',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'bridgeUrl' => $bridge_url,
				'post_id'   => $post_id,
			)
		);
	}

	/**
	 * Enqueues only the JS script
	 *
	 * @param string $handle The script handle to identify the script.
	 * @return bool True if the script was successfully localized, false otherwise.
	 */
	public static function enqueue_script( $handle = self::SCRIPT_HANDLE ) {
		return wp_enqueue_script(
			$handle,
			self::get_bridge_url(),
			array(),
			Package_Version::PACKAGE_VERSION,
			false
		);
	}
}
