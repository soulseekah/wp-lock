<?php
class WP_Lock_Backend_Generic_UnitTestCase extends WP_UnitTestCase {
	/**
	 * Generates a set of new lock backend instances.
	 */
	private function get_lock_backend_classes() {
		return array(
			'WP_Lock_Backend_flock',
//			'WP_Lock_Backend_wpdb',
		);
	}

	/**
	 * Generate a unique resource ID.
	 */
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

	public function test_concurrency_simple() {
		if ( ! function_exists( 'pcntl_fork' ) ) {
			$this->markTestSkipped( 'PCNTL not available' );
		}

		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$resource_id = $this->generate_lock_resource_id();

			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( $resource_id, WP_Lock::WRITE, false, 0 ) );

			$callback = new WP_Lock_Backend_Callback(
				array( $this, '_test_concurrency_simple_child' ),
				array( $resource_id, $lock_backend_class )
			);

			run_in_child( array( $callback, 'run' ) );
		}
	}

	public function _test_concurrency_simple_child( $resource_id, $lock_backend_class ) {
		$lock_backend = new $lock_backend_class();
		$this->assertFalse( $lock_backend->acquire( $resource_id, WP_Lock::WRITE, false, 0 ) );
	}

	public function test_concurrency_pageviews() {
		if ( ! function_exists( 'pcntl_fork' ) ) {
			$this->markTestSkipped( 'PCNTL not available' );
		}

		global $wpdb;

		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$resource_id = $this->generate_lock_resource_id();

			$post_id = $this->factory->post->create();
			update_post_meta( $post_id, 'pageviews', 0 );

			$this->commit_transaction();

			$callback = new WP_Lock_Backend_Callback(
				array( $this, '_test_concurrency_pageviews_child' ),
				array( $post_id, $resource_id, $lock_backend_class )
			);

			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );

			foreach ( range( 1, 100 ) as $_ ) {
				$this->_test_concurrency_pageviews_increment_counter( $post_id, $resource_id, $lock_backend_class );
			}

			foreach ( $children as $child ) {
				pcntl_waitpid( $child, $_ );
			}

			$this->assertEquals( 600, get_post_meta( $post_id, 'pageviews', true ) );
		}
	}

	public function _test_concurrency_pageviews_child( $post_id, $resource_id, $lock_backend_class) {
		foreach ( range( 1, 100 ) as $_ ) {
			$this->_test_concurrency_pageviews_increment_counter( $post_id, $resource_id, $lock_backend_class );
		}
	}

	public function _test_concurrency_pageviews_increment_counter( $post_id, $resource_id, $lock_backend_class ) {
		$lock_backend = new $lock_backend_class();
		$this->assertTrue( $lock_backend->acquire( $resource_id, WP_Lock::WRITE, true, 0 ) );

		/**
		 * Critical section.
		 */
		$pageviews = get_post_meta( $post_id, 'pageviews', true );
		update_post_meta( $post_id, 'pageviews', $pageviews + 1 );

		$lock_backend->release( $resource_id );
	}
}

