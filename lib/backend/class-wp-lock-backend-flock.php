<?php
namespace soulseekah\WP_Lock;

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
		$LOCK_NB = $blocking ? 0 : LOCK_NB;

		$fd = fopen( $path = $this->get_path_for_id( $id ), 'a+b' );
		flock( $fd, $LOCK_NB | LOCK_EX, $wouldblock );
		if ( $wouldblock && $LOCK_NB === LOCK_NB ) {
			fclose( $fd );
			return false;
		}

		if ( ! $locks = maybe_unserialize( file_get_contents( $path ) ) ) {
			$locks = array();
		}

		/**
		 * Prune expired locks.
		 */
		foreach ( $locks as $i => $lock ) {
			if ( $lock['expiration'] && ( $lock['expiration'] < microtime( true ) ) ) {
				unset( $locks[ $i ] );
			}
		}

		ftruncate( $fd, 0 );
		rewind( $fd );
		fwrite( $fd, serialize( $locks ) );
		fflush( $fd );

		switch ( $level ) {
			case WP_Lock::READ:
				/**
				 * While writing nobody can read.
				 */
				foreach ( $locks as $lock ) {
					if ( WP_Lock::WRITE === $lock['level'] ) {
						if ( ! $blocking ) {
							flock( $fd, LOCK_UN );
							fclose( $fd );
							return false;
						}
						
						flock( $fd, LOCK_UN );

						while ( true ) { // @todo this is a bad spinlock, try select()
							if ( $this->acquire( $id, $level, false, $expiration ) ) {
								return true;
							}
							usleep( 5000 );
						}
					}
				}
				break;
			case WP_Lock::WRITE:
				/**
				 * While reading or writing nobody can write or read.
				 */
				if ( $locks ) {
					if ( ! $blocking ) {
						flock( $fd, LOCK_UN );
						fclose( $fd );
						return false;
					}

					flock( $fd, LOCK_UN );

					while ( true ) { // @todo this is a bad spinlock, try select()
						if ( $this->acquire( $id, $level, false, $expiration ) ) {
							fclose( $fd );
							return true;
						}
						usleep( 5000 );
					}
				}
				break;
			default:
				return false;
		}

		$locks[] = array(
			'level'      => $level,
			'expiration' => $expiration ? time() + $expiration : 0,
			'pid'        => getmypid(),
		);

		ftruncate( $fd, 0 );
		rewind( $fd );
		fwrite( $fd, serialize( $locks ) );
		fflush( $fd );

		flock( $fd, LOCK_UN );
		fclose( $fd );
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function release( $id ) {
		$fd = fopen( $path = $this->get_path_for_id( $id ), 'a+b' );
		flock( $fd, LOCK_EX );

		if ( ! $locks = maybe_unserialize( file_get_contents( $path ) ) ) {
			$locks = array();
		}

		foreach ( $locks as $id => $lock ) {
			if ( getmypid() === $lock['pid'] ) {
				unset( $locks[ $id ] );
				break;
			}
		}

		ftruncate( $fd, 0 );
		rewind( $fd );
		fwrite( $fd, serialize( $locks ) );
		fflush( $fd );

		flock( $fd, LOCK_UN );
		fclose( $fd );
	}

	/**
	 * Get full path for given resource ID.
	 *
	 * @param string $id The resource ID.
	 *
	 * @return string The full path.
	 */
	private function get_path_for_id( $id ) {
		return $this->path . '/' . $this->prefix . md5( $id ) . '.lock';
	}
}
