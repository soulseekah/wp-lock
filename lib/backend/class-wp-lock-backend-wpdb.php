<?php
namespace soulseekah\WP_Lock;

/**
 * A `wpdb` based lock backed implementation.
 */
class WP_Lock_Backend_wpdb implements WP_Lock_Backend {
	/**
	 * @var string The key name prefix.
	 */
	private $prefix;

	/**
	 * @var string[] The locked storages.
	 */
	private $storages = array();

	/**
	 * Lock backend constructor.
	 *
	 * @param string $prefix The key name prefix. Default: empty.
	 */
	public function __construct( $prefix = '_lock_' ) {
		$this->prefix = $prefix;

		register_shutdown_function( function( $lock ) {
			/**
			 * Always try to unlock all storages when exiting.
			 */
			array_map( array( $lock, 'unlock_storage' ), array_filter( $lock->storages ) );
		}, $this );
	}

	/**
	 * @inheritDoc
	 */
	public function acquire( $id, $level, $blocking, $expiration ) {
		global $wpdb;

		$this->ensure_lock_exists( $id );

		$table_name = $this->get_table_name();
		list( $key_column, $value_column ) = $this->get_table_columns();

		if ( ! $blocking ) {
			if ( ! $this->lock_storage( $id ) ) {
				return false;
			}
		} else {
			while ( ! $this->lock_storage( $id ) ) { /** @todo come up with a better spinlock */
				usleep( 5000 );
			}
		}

		$locks = $this->read_locks( $id );

		/**
		 * Prune expired locks.
		 */
		foreach ( $locks as $i => $lock ) {
			if ( $lock['expiration'] && $lock['expiration'] < microtime( true ) ) {
				unset( $locks[ $i ] );
			}
		}

		$this->write_locks( $id, $locks );

		switch ( $level ) {
			case WP_Lock::READ:
				/**
				 * While writing nobody can read.
				 */
				foreach ( $locks as $lock ) {
					if ( WP_Lock::WRITE === $lock['level'] ) {
						if ( ! $blocking ) {
							$this->unlock_storage( $id );
							return false;
						}
						
						$this->unlock_storage( $id );

						while ( true ) { // @todo this is a bad spinlock
							if ( $this->acquire( $id, $level, false, $expiration ) ) {
								return true;
							}
							usleep( 5000 );
						}
					}
				}
				break;
			case WP_Lock::WRITE:
				/**
				 * While reading or writing nobody can write or read.
				 */
				if ( $locks ) {
					if ( ! $blocking ) {
						$this->unlock_storage( $id );
						return false;
					}

					$this->unlock_storage( $id );

					while ( true ) { // @todo this is a bad spinlock
						if ( $this->acquire( $id, $level, false, $expiration ) ) {
							return true;
						}
						usleep( 5000 );
					}
				}
				break;
			default:
				$this->unlock_storage( $id );
				return false;
		}

		$locks[] = array(
			'level'      => $level,
			'expiration' => $expiration ? time() + $expiration : 0,
			'pid'        => getmypid(),
		);

		$this->write_locks( $id, $locks );
		$this->unlock_storage( $id );

		return true;
	}

	/*
	 * Write the locks to locked storage.
	 *
	 * @param string $id    The resource ID to create a lock for.
	 * @param array  $locks The locks.
	 *
	 * @return void
	 */
	private function write_locks( $id, $locks ) {
		global $wpdb;

		$table_name = $this->get_table_name();
		list( $key_column, $value_column ) = $this->get_table_columns();

		$storage = maybe_unserialize( $wpdb->get_var( $sql = $wpdb->prepare(
			"SELECT $value_column FROM $table_name WHERE $key_column = %s",
			$this->get_key_for_id( $id ) . '.locked'
		) ) );

		$storage['locks'] = $locks;

		$wpdb->update(
			$table_name,
			array( $value_column => serialize( $storage ) ),
			array( $key_column   => $this->get_key_for_id( $id ) . '.locked' )
		);
	}

	/**
	 * Read the locks for locked storage.
	 *
	 * @param string $id The resource ID to create a lock for.
	 *
	 * @return array The locks.
	 */
	private function read_locks( $id ) {
		global $wpdb;

		$table_name = $this->get_table_name();
		list( $key_column, $value_column ) = $this->get_table_columns();

		$locks = maybe_unserialize(
			$wpdb->get_var( $wpdb->prepare(
				"SELECT $value_column FROM $table_name WHERE $key_column = %s",
				$this->get_key_for_id( $id ) . '.locked'
			) )
		);


		if ( ! isset( $locks['locks'] ) ) {
			return array();
		}

		return $locks['locks'];
	}

	/**
	 * Try to lock the storage.
	 *
	 * @param string $id The resource ID to create a lock for.
	 *
	 * @return bool Whether the storage was locked or not.
	 */
	private function lock_storage( $id ) {
		global $wpdb;

		$table_name = $this->get_table_name();
		list( $key_column, $value_column ) = $this->get_table_columns();

		$locked = !! $wpdb->update( $table_name,
			array( $key_column => $this->get_key_for_id( $id ) . '.locked' ),
			array( $key_column => $this->get_key_for_id( $id )  )
		);

		$storage = maybe_unserialize( $wpdb->get_var( $sql = $wpdb->prepare(
			"SELECT $value_column FROM $table_name WHERE $key_column = %s",
			$this->get_key_for_id( $id ) . '.locked'
		) ) );

		if ( ! $locked ) {
			/**
			 * Reset lock if PID or CID do no longer exist.
			 */
			if ( empty( $storage['pid'] ) && empty( $storage['cid'] ) ) {
				$this->unlock_storage( $id ); // Reset as the state owner is unknown.
				return $this->lock_storage( $id );
			}

			if ( ( ! empty( $storage['pid'] ) ) && ( ! file_exists( "/proc/{$storage['pid']}" ) ) ) {
				$this->unlock_storage( $id ); // Reset as the state owner PID is no longer running.
				return $this->lock_storage( $id );
			} else if ( ( ! empty( $storage['cid'] ) ) && empty( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM information_schema.processlist WHERE id = %s", $storage['cid'] ) ) ) ) {
				$this->unlock_storage( $id ); // Reset as the state owner CID is no longer present.
				return $this->lock_storage( $id );
			}
		} else {
			/**
			 * Record PID and CID of storage locker.
			 */
			$storage['pid'] = getmypid();
			$storage['cid'] = $wpdb->get_var( "SELECT CONNECTION_ID()" );

			$wpdb->update( $table_name,
				array( $value_column => serialize( $storage ) ),
				array( $key_column => $this->get_key_for_id( $id ) . '.locked' )
			);
		}

		return !! $this->storages[ $id ] = ( $locked ? $id : false );
	}

	/**
	 * Unlock the storage.
	 *
	 * @param string $id The resource ID to create a lock for.
	 *
	 * @return bool Whether the storage was locked or not.
	 */
	private function unlock_storage( $id ) {
		global $wpdb;

		$table_name = $this->get_table_name();
		list( $key_column, $value_column ) = $this->get_table_columns();

		$unlocked = !! $wpdb->update( $table_name,
			array( $key_column => $this->get_key_for_id( $id )  ),
			array( $key_column => $this->get_key_for_id( $id ) . '.locked' )
		);

		return !! $this->storages[ $id ] = ( $unlocked ? false : $id );
	}

	/**
	 * @inheritDoc
	 */
	public function release( $id ) {
		global $wpdb;

		while ( ! $this->lock_storage( $id ) ) { /** @todo come up with a better spinlock */
			usleep( 5000 );
		}

		$locks = $this->read_locks( $id );

		foreach ( $locks as $i => $lock ) {
			if ( getmypid() === $lock['pid'] ) {
				unset( $locks[ $i ] );
				break;
			}
		}

		$this->write_locks( $id, $locks );
		$this->unlock_storage( $id );
	}

	/**
	 * Make sure a lock exists, created if not.
	 *
	 * @private
	 * @param string $id The resource ID to create a lock for.
	 *
	 * @return void
	 */
	public function ensure_lock_exists( $id ) {
		global $wpdb;

		$key_name = $this->get_key_for_id( $id );
		$table_name = $this->get_table_name();
		list( $key_column, $value_column ) = $this->get_table_columns();

		$not_exists = "SELECT 1 FROM $table_name WHERE $key_column IN (%s, %s) LIMIT 1";
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO $table_name ($key_column, $value_column, autoload) SELECT %s, %s, 'no' FROM DUAL WHERE NOT EXISTS ($not_exists)",
			$key_name, serialize( array( 'id' => $id, 'locks' => array() ) ), $key_name, $key_name . '.locked'
		) );
	}

	/**
	 * Return the lock store table name.
	 *
	 * We always use the first site's storage in multisite mode
	 * to allow lock sharing. Use the $prefix parameter to split
	 * by site ID.
	 *
	 * @return string The databse table name.
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'options';
	}

	/**
	 * Return the lock store table columns.
	 *
	 * @return array The key and value column names.
	 */
	public function get_table_columns() {
		/**
		 * @todo What about multisite?
		 * @todo What about multinetwork?
		 */
		return array(
			'option_name',
			'option_value'
		);
	}

	/**
	 * Get key name for given resource ID.
	 *
	 * @private
	 * @param string $id The resource ID.
	 *
	 * @return string The key name.
	 */
	public function get_key_for_id( $id ) {
		return $this->prefix . md5( $id );
	}

	/**
	 * Test proxy to self::lock_storage()
	 *
	 * @private
	 *
	 * @return bool The result of the implementation call.
	 */
	public function _lock_storage_t() {
		if ( ! defined( 'DOING_TESTS' ) ) {
			trigger_error( 'Test proxy called without test context.' );
			return false;
		}

		return call_user_func_array( array( $this, 'lock_storage' ), func_get_args() );
	}
}
