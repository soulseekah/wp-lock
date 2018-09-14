<?php
/**
 * An `flock` based lock backed implementation.
 */
class WP_Lock_Backend_flock implements WP_Lock_Backend {
	/**
	 * Lock backend constructor.
	 *
	 * @param string $path   The file system path where files are created.
	 *                       Default: system temporary directory.
	 * @param string $prefix The file prefix. Default: empty.
	 */
	public function __construct( $path = null, $prefix = '' ) {
	}
}
