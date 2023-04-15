<?php
/**
 * Plugin Name: WP Lock API
 * Description: Concurrency locks for WordPress.
 * Author: Gennady Kovshenin
 * Author URI: https://codeseekah.com
 * Version: 2.0
 * Plugin URI: https://github.com/soulseekah/wp-lock
 * License: GPL2+
 */

use Soulseekah\WP_Lock\WP_Lock_Backend;
use Soulseekah\WP_Lock\WP_Lock_Backend_flock;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

add_filter( 'wp_lock_backend', 'wp_lock_set_default_backend' );

/**
 * Set the default lock backend if null.
 *
 * Called via `wp_lock_backend`.
 *
 * @param WP_Lock_Backend|null $lock_backend The backend.
 *
 * @return WP_Lock_Backend A default lock backend.
 */
function wp_lock_set_default_backend( $lock_backend ) {
	if ( is_null( $lock_backend ) ) {
		return new WP_Lock_Backend_flock();
	}
	return $lock_backend;
}
