<?php
class WP_Lock_Backend_wpdb_UnitTestCase extends WP_UnitTestCase {
	public function test_ensure_lock_exists() {
		if ( ! function_exists( 'pcntl_fork' ) ) {
			$this->markTestSkipped( 'PCNTL not available' );
		}

		global $wpdb;

		$backend = new WP_Lock_Backend_wpdb();
		$table = $backend->get_table_name();

		$engines = array( 'MyISAM', 'InnoDB' );

		foreach ( $engines as $engine ) {
			$wpdb->query( "ALTER TABLE $table ENGINE = $engine" );
			$this->commit_transaction();

			$id = $engine;

			$callback = new WP_Lock_Backend_Callback(
				array( $this, '_test_ensure_lock_exists_child' ),
				array( $backend, $id )
			);

			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );

			foreach ( range( 1, 100 ) as $_ ) {
				$backend->ensure_lock_exists( $id );
			}

			foreach ( $children as $child ) {
				pcntl_waitpid( $child, $_ );
			}

			list( $key_column, $value_column ) = $backend->get_table_columns();
			$this->assertEquals( array( 'a:0:{}' ), $wpdb->get_col( $wpdb->prepare(
				"SELECT $value_column FROM $table WHERE $key_column = %s",
				$backend->get_key_for_id( $id )
			) ) );
		}
	}

	public function _test_ensure_lock_exists_child( $backend, $id ) {
		foreach ( range( 1, 100 ) as $_ ) {
			$backend->ensure_lock_exists( $id );
		}
	}
}
