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

	private function generate_lock_resource_id() {
		return 'lock_' . microtime( true );
	}

	public function test_acquire_read() {
		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$resource_id = $this->generate_lock_resource_id();

			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( $resource_id, WP_Lock::READ, true, 0 ) );

			$lock_backend_2 = new $lock_backend_class();
			$this->assertTrue( $lock_backend_2->acquire( $resource_id, WP_Lock::READ, false, 0 ) );
		}
	}

	public function test_acquire_write() {
		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$resource_id = $this->generate_lock_resource_id();

			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( $resource_id, WP_Lock::WRITE, true, 0 ) );

			$lock_backend_2 = new $lock_backend_class();
			$this->assertFalse( $lock_backend_2->acquire( $resource_id, WP_Lock::WRITE, false, 0 ) );

			$lock_backend_3 = new $lock_backend_class();
			$this->assertFalse( $lock_backend_3->acquire( $resource_id, WP_Lock::READ, false, 0 ) );
		}
	}

	public function test_acquire_write_locked() {
		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$resource_id = $this->generate_lock_resource_id();

			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( $resource_id, WP_Lock::READ, true, 0 ) );

			$lock_backend_2 = new $lock_backend_class();
			$this->assertFalse( $lock_backend_2->acquire( $resource_id, WP_Lock::WRITE, false, 0 ) );
		}
	}

	public function test_release_read_write() {
		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$resource_id = $this->generate_lock_resource_id();

			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( $resource_id, WP_Lock::READ, true, 0 ) );

			$lock_backend_2 = new $lock_backend_class();
			$this->assertTrue( $lock_backend_2->acquire( $resource_id, WP_Lock::READ, false, 0 ) );

			$lock_backend_3 = new $lock_backend_class();
			$this->assertFalse( $lock_backend_2->acquire( $resource_id, WP_Lock::WRITE, false, 0 ) );

			$lock_backend->release( $resource_id );
			$this->assertFalse( $lock_backend_2->acquire( $resource_id, WP_Lock::WRITE, false, 0 ) );

			$lock_backend_2->release( $resource_id );
			$this->assertTrue( $lock_backend_2->acquire( $resource_id, WP_Lock::WRITE, false, 0 ) );
		}
	}

	public function test_release_write_read() {
		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$resource_id = $this->generate_lock_resource_id();

			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( $resource_id, WP_Lock::WRITE, true, 0 ) );

			$lock_backend_2 = new $lock_backend_class();
			$this->assertFalse( $lock_backend_2->acquire( $resource_id, WP_Lock::READ, false, 0 ) );

			$lock_backend_3 = new $lock_backend_class();
			$this->assertFalse( $lock_backend_2->acquire( $resource_id, WP_Lock::WRITE, false, 0 ) );

			$lock_backend->release( $resource_id );
			$this->assertTrue( $lock_backend_3->acquire( $resource_id, WP_Lock::WRITE, false, 0 ) );
			$this->assertFalse( $lock_backend_2->acquire( $resource_id, WP_Lock::READ, false, 0 ) );

			$lock_backend_3->release( $resource_id );
			$this->assertTrue( $lock_backend_2->acquire( $resource_id, WP_Lock::READ, false, 0 ) );
		}
	}

	public function test_acquire_timeout() {
		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$resource_id = $this->generate_lock_resource_id();

			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( $resource_id, WP_Lock::WRITE, false, 2 ) );

			$lock_backend_2 = new $lock_backend_class();

			$this->assertFalse( $lock_backend_2->acquire( $resource_id, WP_Lock::WRITE, false, 0 ) );
			sleep( 3 );
			$this->assertTrue( $lock_backend_2->acquire( $resource_id, WP_Lock::WRITE, false, 0 ) );
		}
	}
}
