<?php
class WP_Lock_Backend_Generic_UnitTestCase extends WP_UnitTestCase {
	/**
	 * Generates a set of new lock backend instances.
	 */
	private function get_lock_backend_classes() {
		return array(
			'WP_Lock_Backend_flock',
		);
	}

	public function test_acquire_read() {
		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( 'lock1', WP_Lock::READ, true, 0 ) );

			$lock_backend_2 = new $lock_backend_class();
			$this->assertTrue( $lock_backend_2->acquire( 'lock1', WP_Lock::READ, false, 0 ) );
		}
	}

	public function test_acquire_write() {
		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( 'lock2', WP_Lock::WRITE, true, 0 ) );

			$lock_backend_2 = new $lock_backend_class();
			$this->assertFalse( $lock_backend_2->acquire( 'lock2', WP_Lock::WRITE, false, 0 ) );

			$lock_backend_3 = new $lock_backend_class();
			$this->assertFalse( $lock_backend_3->acquire( 'lock2', WP_Lock::READ, false, 0 ) );
		}
	}

	public function test_acquire_write_locked() {
		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( 'lock3', WP_Lock::READ, true, 0 ) );

			$lock_backend_2 = new $lock_backend_class();
			$this->assertFalse( $lock_backend_2->acquire( 'lock3', WP_Lock::WRITE, false, 0 ) );
		}
	}

	public function test_release_read_write() {
		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( 'lock4', WP_Lock::READ, true, 0 ) );

			$lock_backend_2 = new $lock_backend_class();
			$this->assertTrue( $lock_backend_2->acquire( 'lock4', WP_Lock::READ, false, 0 ) );

			$lock_backend_3 = new $lock_backend_class();
			$this->assertFalse( $lock_backend_2->acquire( 'lock4', WP_Lock::WRITE, false, 0 ) );

			$lock_backend->release( 'lock4' );
			$this->assertFalse( $lock_backend_2->acquire( 'lock4', WP_Lock::WRITE, false, 0 ) );

			$lock_backend_2->release( 'lock4' );
			$this->assertTrue( $lock_backend_2->acquire( 'lock4', WP_Lock::WRITE, false, 0 ) );
		}
	}

	public function test_release_write_read() {
		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( 'lock5', WP_Lock::WRITE, true, 0 ) );

			$lock_backend_2 = new $lock_backend_class();
			$this->assertFalse( $lock_backend_2->acquire( 'lock5', WP_Lock::READ, false, 0 ) );

			$lock_backend_3 = new $lock_backend_class();
			$this->assertFalse( $lock_backend_2->acquire( 'lock5', WP_Lock::WRITE, false, 0 ) );

			$lock_backend->release( 'lock5' );
			$this->assertTrue( $lock_backend_3->acquire( 'lock5', WP_Lock::WRITE, false, 0 ) );
			$this->assertFalse( $lock_backend_2->acquire( 'lock5', WP_Lock::READ, false, 0 ) );

			$lock_backend_3->release( 'lock5' );
			$this->assertTrue( $lock_backend_2->acquire( 'lock5', WP_Lock::READ, false, 0 ) );
		}
	}

	public function test_acquire_timeout() {
	}
}
