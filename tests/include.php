<?php
/**
 * Parametrized callbacks compatible with PHP 5.2 and 7.2
 */
class WP_Lock_Backend_Callback {
	private $args;
	private $callback;

	public function __construct( $callback, $args = array() ) {
		$this->callback = $callback;
		$this->args = $args;
	}

	public function run() {
		return call_user_func_array( $this->callback, $this->args );
	}
}

/**
 * Fork and test in child process.
 */
function run_in_child( $callback ) {
	global $wpdb;

	$wpdb->close();

	if ( ! $child = pcntl_fork() ) {
		$wpdb->db_connect( false );
		call_user_func( $callback );

		exit;
	}

	$wpdb->db_connect( false );

	return $child;
}

// @todo temporary, remove
function debug_log( $message ) {
	if ( ! is_string( $message ) ) {
		$message = json_encode( $message );
	}
	file_put_contents( 'debug.log', sprintf( "[%d] $message\n", getmypid() ), FILE_APPEND );
}
