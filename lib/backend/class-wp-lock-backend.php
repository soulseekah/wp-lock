<?php
namespace soulseekah\WP_Lock;

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
	 * @param bool $blocking   Whether acquiring the lock blocks or not.
	 * @param int  $expiration Auto-release after $expiration seconds.
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
