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
	 * @var string The held locks.
	 */
	private $locks = array();

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

		switch ( $level ) {
			case WP_Lock::READ:
				/**
				 * Is the current resource being exclusively held?
				 */
				$fd = fopen( $this->get_path_for_id( $id ) . '.x', 'r' );
				flock( $fd, $blocking | LOCK_EX, $wouldblock );
				if ( $blocking == LOCK_NB && $wouldblock ) {
					fclose( $fd );
					return false;
				}
				flock( $fd, LOCK_UN );

				/**
				 * Is the current resource for write?
				 */
				$fd = fopen( $this->get_path_for_id( $id ) . '.x', 'r' );
				flock( $fd, $blocking | LOCK_EX, $wouldblock );
				if ( $blocking == LOCK_NB && $wouldblock ) {
					fclose( $fd );
					return false;
				}
				flock( $fd, LOCK_UN );

				/**
				 * Is the current resource being held for writing?
				 */
				$fd = fopen( $this->get_path_for_id( $id ) . '.r', 'r' );
				flock( $fd, $blocking | LOCK_SH, $wouldblock );
				if ( $blocking == LOCK_NB && $wouldblock ) {
					fclose( $fd );
					return false;
				}

				break;
			case WP_Lock::WRITE:
				/**
				 * Is the current resource being exclusively held?
				 */
				$fd = fopen( $this->get_path_for_id( $id ) . '.x', 'r' );
				flock( $fd, $blocking | LOCK_EX, $wouldblock );
				if ( $blocking == LOCK_NB && $wouldblock ) {
					fclose( $fd );
					return false;
				}
				flock( $fd, LOCK_UN );

				/**
				 * Is the current resource being held for writing?
				 */
				$fd = fopen( $this->get_path_for_id( $id ) . '.r', 'r' );
				flock( $fd, $blocking | LOCK_EX, $wouldblock );
				if ( $blocking == LOCK_NB && $wouldblock ) {
					fclose( $fd );
					return false;
				}

				break;
			case WP_Lock::EXCLUSIVE:
				/**
				 * Is the current resource being exclusively held?
				 */
				$fd = fopen( $this->get_path_for_id( $id ) . '.x', 'r' );
				flock( $fd, $blocking | LOCK_EX, $wouldblock );
				if ( $blocking == LOCK_NB && $wouldblock ) {
					fclose( $fd );
					return false;
				}

				/**
				 * Is the current resource being held by read or write?
				 */
				$fd = fopen( $this->get_path_for_id( $id ) . '.r', 'r' );
				flock( $fd, $blocking | LOCK_EX, $wouldblock );
				if ( $blocking == LOCK_NB && $wouldblock ) {
					fclose( $fd );
					return false;
				}

				break;
		}

		fclose( $fd );
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function release( $id ) {
	}

	private function get_path_for_id( $id ) {
		return $this->path . '/' . md5( $id ) . '.lock';
	}
}
