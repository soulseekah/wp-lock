<?php
/**
 * The lock backend interface.
 */
interface WP_Lock_Backend {
	/**
	 * Acquire a lock.
	 *
	 * @todo Write more about how to write a backend and atomicity.
	 *
	 * @param int  $level      Lock level. One of:
	 *                             WP_Lock::READ
	 *                             WP_Lock::WRITE
	 *                             WP_Lock::EXCLUSIVE
	 *                         Default: WP_Lock::WRITE
	 * @param bool $blocking   Whether acquiring the lock blocks or not. Default: true.
	 * @param int  $expiration Auto-release after $expiration seconds. Default: 0 (no auto-release)
	 *
	 * @return bool Whether the lock has been acquired or not.
	 */
	public function acquire( $id, $level, $blocking, $expiration );

	/**
	 * Release a lock.
	 *
	 * @return void
	 */
	public function release( $id );
}
