<?php

use soulseekah\WP_Lock\helpers\Database;
use soulseekah\WP_Lock\WP_Lock;
use Soulseekah\WP_Lock\WP_Lock_Backend;
use soulseekah\WP_Lock\WP_Lock_Backend_DB;

class WP_Lock_Backend_Generic_UnitTestCase extends WP_UnitTestCase {

    /**
     * @return void
     */
    protected function setUp(): void {
	    Database::register_table( WP_Lock_Backend_DB::TABLE_NAME );
    }

	/**
	 * Generates a set of new lock backend instances.
	 */
	private function get_lock_backend_classes() {
		return array(
			'\soulseekah\WP_Lock\\WP_Lock_Backend_flock',
			'\soulseekah\WP_Lock\\WP_Lock_Backend_DB',
		);
	}

	/**
	 * Prevent internal testing transactions.
	 *
	 * These get in the way when we're forking children.
	 */
	private function prevent_transactions() {
		global $wpdb;
		$wpdb->close();
		$wpdb->db_connect( false );
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

            /** @var WP_Lock_Backend $lock_backend_class */
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

		$this->prevent_transactions();

		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$resource_id = $this->generate_lock_resource_id();

			$lock_backend = new $lock_backend_class();
			$this->assertTrue( $lock_backend->acquire( $resource_id, WP_Lock::WRITE, false, 0 ) );

			$callback = new WP_Lock_Backend_Callback(
				array( $this, '_test_concurrency_simple_child' ),
				array( $resource_id, $lock_backend_class )
			);

			$children[] = run_in_child( array( $callback, 'run' ) );
		}

		foreach ( $children as $child ) {
			pcntl_waitpid( $child, $status );
			$this->assertEquals(0, pcntl_wexitstatus($status), "Unexpected exit code in _test_concurrency_simple_child PID $child");
		}
	}

	public function _test_concurrency_simple_child( $resource_id, $lock_backend_class ) {
		$lock_backend = new $lock_backend_class();
		$result = $lock_backend->acquire( $resource_id, WP_Lock::WRITE, false, 0 );
		return false === $result;
	}

	public function test_concurrency_pageviews() {
		if ( ! function_exists( 'pcntl_fork' ) ) {
			$this->markTestSkipped( 'PCNTL not available' );
		}

		global $wpdb;

		$this->prevent_transactions();
		$suppress_errors = $wpdb->suppress_errors( true );

		foreach ( $this->get_lock_backend_classes() as $lock_backend_class ) {
			$resource_id = $this->generate_lock_resource_id();

			$post_id = $this->factory()->post->create();
			update_post_meta( $post_id, 'pageviews', 0 );

			$callback = new WP_Lock_Backend_Callback(
				array( $this, '_test_concurrency_pageviews_child' ),
				array( $post_id, $resource_id, $lock_backend_class )
			);

            $children = [];

			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );

			foreach ( range( 1, 100 ) as $_ ) {
				$this->_test_concurrency_pageviews_increment_counter( $post_id, $resource_id, $lock_backend_class );
			}

			foreach ( $children as $child ) {
				pcntl_waitpid( $child, $status );
				$this->assertEquals(0, pcntl_wexitstatus($status), "Unexpected exit code in _test_concurrency_pageviews_child PID $child");
			}

			$this->assertEquals( 600, intval( get_post_meta( $post_id, 'pageviews', true ) ) );
		}

		$wpdb->suppress_errors( $suppress_errors );
	}

	public function _test_concurrency_pageviews_child( $post_id, $resource_id, $lock_backend_class) {
		foreach ( range( 1, 100 ) as $_ ) {
			$this->_test_concurrency_pageviews_increment_counter( $post_id, $resource_id, $lock_backend_class );
		}
		return true;
	}

	public function _test_concurrency_pageviews_increment_counter( $post_id, $resource_id, $lock_backend_class ) {
		$lock_backend = new $lock_backend_class();
		$lock_backend->acquire( $resource_id, WP_Lock::WRITE, true, 0 );

		/**
		 * Critical section.
		 */
		$pageviews = get_post_meta( $post_id, 'pageviews', true );
		update_post_meta( $post_id, 'pageviews', intval( $pageviews ) + 1 );

		$lock_backend->release( $resource_id );
	}
}

