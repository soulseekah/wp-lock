<?php
class WP_Lock_Backend_flock_UnitTestCase extends WP_UnitTestCase {
	public function test_simple_acquire() {

		$lock_backend = new WP_Lock_Backend_flock();

		$this->assertTrue( $lock_backend->acquire( 'lock1', WP_Lock::READ, true, 0 ) );
	}
}
