<?php
class WP_Lock_Backend_wpdb_UnitTestCase extends WP_UnitTestCase {
	public function test_select_for_update_engines() {
		if ( ! function_exists( 'pcntl_fork' ) ) {
			$this->markTestSkipped( 'PCNTL not available' );
		}

		global $wpdb;

		$engines = array( 'InnoDB', 'MyISAM' );

		foreach ( $engines as $engine ) {
			$wpdb->query( "DROP /** NOT TEMPORARY */ TABLE IF EXISTS locks" );
			$wpdb->query( "CREATE /** NOT TEMPORARY */ TABLE locks ( id INT AUTO_INCREMENT, name VARCHAR(191), value TEXT, PRIMARY KEY (`id`) ) ENGINE=$engine" );
			$wpdb->query( "INSERT INTO locks VALUES(NULL, 'lock', 0)" );
			$wpdb->query( "INSERT INTO locks VALUES(NULL, 'lock', 0)" );
			$wpdb->query( "INSERT INTO locks VALUES(NULL, 'lock', 0)" );
			$this->commit_transaction();

			$callback = new WP_Lock_Backend_Callback(
				array( $this, '_test_select_for_update_engines_child' )
			);

			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );

			array_map( array( $this, '_test_select_for_update_engines_increment_lock' ), range( 1, 100 ) );

			foreach ( $children as $child ) {
				pcntl_waitpid( $child, $_ );
			}

			$this->assertEquals( array( 500, 500, 500 ), $wpdb->get_col( "SELECT value FROM locks" ) );

			$wpdb->query( "DROP /** NOT TEMPORARY */ TABLE locks" );
		}
	}

	private function _test_select_for_update_engines_increment_lock() {
		global $wpdb;

		while ( true ) {
			if ( ! $wpdb->query( "UPDATE locks SET name = 'lock|locked' WHERE name = 'lock'" ) ) {
				continue;
			}
			break;
		}

		$value = $wpdb->get_var( "SELECT value FROM locks" );
		$wpdb->query( $wpdb->prepare( "UPDATE locks SET value = %d", $value + 1 ) );

		$wpdb->query( "UPDATE locks SET name = 'lock' WHERE name = 'lock|locked'" );
	}

	public function _test_select_for_update_engines_child() {
		array_map( array( $this, '_test_select_for_update_engines_increment_lock' ), range( 1, 100 ) );
	}

	public function test_insert_not_exists_engines() {
		if ( ! function_exists( 'pcntl_fork' ) ) {
			$this->markTestSkipped( 'PCNTL not available' );
		}

		global $wpdb;

		$engines = array( 'InnoDB', 'MyISAM' );

		foreach ( $engines as $engine ) {
			$wpdb->query( "DROP /** NOT TEMPORARY */ TABLE IF EXISTS locks" );
			$wpdb->query( "CREATE /** NOT TEMPORARY */ TABLE locks ( id INT AUTO_INCREMENT, name VARCHAR(191), value TEXT, PRIMARY KEY (`id`) ) ENGINE=$engine" );
			$this->commit_transaction();

			$callback = new WP_Lock_Backend_Callback(
				array( $this, '_test_insert_not_exists_engines_child' )
			);

			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );
			$children[] = run_in_child( array( $callback, 'run' ) );

			array_map( array( $this, '_test_insert_not_exists_engines_do' ), range( 1, 100 ) );

			foreach ( $children as $child ) {
				pcntl_waitpid( $child, $_ );
			}

			$this->assertEquals( array( 1 ), $wpdb->get_col( "SELECT value FROM locks" ) );

			$wpdb->query( "DROP /** NOT TEMPORARY */ TABLE locks" );
		}
	}

	private function _test_insert_not_exists_engines_do() {
		global $wpdb;
		$not_exists = "SELECT id FROM locks WHERE name = 1 LIMIT 1";
		$wpdb->query( "INSERT INTO locks (id, name, value) SELECT NULL, 1, 1 FROM DUAL WHERE NOT EXISTS ($not_exists)" );
	}

	public function _test_insert_not_exists_engines_child() {
		array_map( array( $this, '_test_insert_not_exists_engines_do' ), range( 1, 100 ) );
	}
}
