<?php
if ( class_exists( 'WP_Lock_Backend_flock' ) ) {
	return;
}

/**
 * An `flock` based lock backed implementation.
 */
class WP_Lock_Backend_flock implements WP_Lock_Backend {

	/**
	 * @var string The file system path where the files are created.
	 */
	private $path;

	/**
	 * @var string The temporary filename prefix.
	 */
	private $prefix;

	/**
	 * @var resource The file descriptor held by this lock backend.
	 */
	private $fd = null;

	/**
	 * Lock backend constructor.
	 *
	 * @param string $path   The file system path where files are created.
	 *                       Default: system temporary directory.
	 * @param string $prefix The file prefix. Default: empty.
	 */
	public function __construct( $path = null, $prefix = '' ) {
		$this->path   = $path;
		$this->prefix = $prefix;

		if ( ! $this->path ) {
			$this->path = sys_get_temp_dir();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function acquire( $id, $level, $blocking, $expiration ) {
		if ( $blocking ) {
			$blocking = LOCK_NB;
		} else {
			$blocking = 0;
		}

		/**
		 * This file descriptor is being used already.
		 */
		if ( $this->fd ) {
			return false;
		}

		switch ( $level ) {
			case WP_Lock::READ:
				/**
				 * While writing nobody can read.
				 */
				$fd = fopen( $this->get_path_for_id( $id ), 'w' );
				flock( $fd, $blocking | LOCK_SH, $wouldblock );
				if ( $blocking == LOCK_NB && $wouldblock ) {
					fclose( $fd );
					return false;
				}
			case WP_Lock::WRITE:
				/**
				 * While reading or writing nobody can write.
				 */
				$fd = fopen( $this->get_path_for_id( $id ), 'w' );
				flock( $fd, $blocking | LOCK_EX, $wouldblock );
				if ( $blocking == LOCK_NB && $wouldblock ) {
					fclose( $fd );
					return false;
				}
				break;
			default:
				return false;
		}

		$this->fd = $fd;

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function release( $id ) {
		flock( $this->fd, $blocking | LOCK_UN, $wouldblock );
		fclose( $this->fd );
		$this->fd = false;
	}

	private function get_path_for_id( $id ) {
		return $this->path . '/' . md5( $id ) . '.lock';
	}
}
