<?php
/**
 * Plugin Name: WP Lock API
 * Description: Concurrency locks for WordPress.
 * Author: Gennady Kovshenin
 * Author URI: https://codeseekah.com
 * Version: 1.0
 * Plugin URI: https://github.com/soulseekah/wp-lock
 * License: GPL2+
 */

defined( 'ABSPATH' ) || exit;

/**
 * WP_Lock class.
 */
if ( ! class_exists( 'WP_Lock' ) ) {
	require_once dirname( __FILE__ ) . '/lib/class-wp-lock.php';
}

/**
 * WP_Lock_Backend class.
 */
if ( ! class_exists( 'WP_Lock_Backend' ) ) {
	require_once dirname( __FILE__ ) . '/lib/backend/class-wp-lock-backend.php';
}

foreach ( glob( dirname( __FILE__ ) . '/lib/backend/class-wp-lock-backend-*.php' ) as $backend_class_path ) {
	require_once $backend_class_path;
}

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
