<?php
/**
 * The WP_Lock class.
 */
class WP_Lock {
	/**
	 * @var int A non-exclusive protected read lock.
	 * Other processes can read, but not write. A shared lock.
	 */
	const READ  = 8;

	/**
	 * @var int An exclusive write lock.
	 * Other processes can neither read, nor write.
	 */
	const WRITE = 32;


	/**
	 * @var string The lock identifier.
	 */
	public $id;

	/**
	 * @var WP_Lock_Backend The lock storage.
	 */
	private $lock_backend;

	/**
	 * @var int The number of current locks.
	 */
	private $locks = 0;

	/**
	 * Create a resource concurrency lock.
	 *
	 * @param string          $resource_id  The lock identifier. An arbitrary string.
	 * @param WP_Lock_Backend $lock_backend The lock storage provider/backend instance.
	 */
	public function __construct( $resource_id, $lock_backend = null ) {
		$this->id = $resource_id;

		/**
		 * Filter the lock backend used.
		 *
		 * @param WP_Lock_Backend|null $lock_backend The lock backend requested.
		 * @param string               $resource_id  The lock identifier. An arbitrary string.
		 */
		$this->lock_backend = apply_filters( 'wp_lock_backend', $lock_backend, $resource_id );

		register_shutdown_function( function( $lock ) {
			if ( $lock->locks ) {
				trigger_error( 'Not all locks released for ' . $lock->id );
			}
		}, $this );
	}

	/**
	 * Acquire a lock.
	 *
	 * @todo Write more about locks and their dangers here.
	 *
	 * @param int  $level      Lock level. One of:
	 *                             WP_Lock::READ
	 *                             WP_Lock::WRITE
	 *                         Default: WP_Lock::WRITE
	 * @param bool $blocking   Whether acquiring the lock blocks or not. Default: true.
	 * @param int  $expiration Auto-release after $expiration seconds. Default: 30
	 *                         Setting this value to 0 can cause zombie locks that
	 *                         will linger forever (even across reboots) if you don't
	 *                         know what you are doing.
	 *
	 * @return bool Whether the lock has been acquired or not.
	 */
	public function acquire( $level = self::WRITE, $blocking = true, $expiration = 30 ) {
		$this->locks++;
		return $this->lock_backend->acquire( $this->id, $level, $blocking, $expiration );
	}

	/**
	 * Release a lock.
	 *
	 * @return void
	 */
	public function release() {
		if ( ! $this->locks ) {
			trigger_error( 'Releasing unaquired lock.' );
		}

		$this->locks--;

		$this->lock_backend->release( $this->id );
	}
}
