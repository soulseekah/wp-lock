<?php

namespace soulseekah\WP_Lock;

use Exception;
use soulseekah\WP_Lock\helpers\Database;

class WP_Lock_WPDB implements WP_Lock_Backend {
	const TABLE_NAME = 'lock';

	/**
	 * @var string[] The locked storages.
	 *
	 * Format: [lock_key => lock_id]
	 */
	private array $lock_ids = [];

	/**
	 * Lock backend constructor.
	 */
	public function __construct() {
		register_shutdown_function( function ( $lock ) {
			/**
			 * Always try to unlock all storages when exiting.
			 */
			array_map( [ $lock, 'release' ], array_filter( $lock->lock_ids ) );
		}, $this );
	}

	/**
	 * Return the lock store table name.
	 *
	 * @return string The database table name.
	 */
	public function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Get key name for given resource ID.
	 *
	 * @private
	 *
	 * @param string $id The resource ID.
	 *
	 * @return string The key name.
	 */
	private function get_lock_key( string $id ): string {
		return md5( $id );
	}

	/**
	 * Ghost lock is a lock that has no corresponding process and/or connection and has no expiration time.
	 * Search for a ghost lock for specific lock_id in the database and remove it.
	 *
	 * @return void
	 */
	public function drop_ghosts( $lock_id ) {
		global $wpdb;
		$lock_key = $this->get_lock_key( $lock_id );

		$locks  = $wpdb->get_results( "SELECT * FROM {$this->get_table_name()} WHERE `lock_key` = '$lock_key'", ARRAY_A );
		$ghosts = [];
		foreach ( $locks as $lock ) {
			if ( ! empty( $lock['expire'] ) && $lock['expire'] > microtime( true ) ) {
				// This is an unexpired lock, keep it actual. No matter if it's a ghost or not.
				continue;
			}

			if (
				( empty( $lock['pid'] ) && empty( $lock['cid'] ) ) ||
				( ! empty( $lock['pid'] ) && ! file_exists( "/proc/{$lock['pid']}" ) ) ||
				( ! empty( $lock['cid'] ) && empty(
					$wpdb->get_var(
						$wpdb->prepare( "SELECT id FROM information_schema.processlist WHERE id = %s", $lock['cid'] )
					)
					) )
			) {
				// This is a ghost lock, remove it.
				$ghosts[] = $lock['id'];
			}
		}

		if ( ! empty( $ghosts ) ) {
			$wpdb->query( "DELETE FROM {$this->get_table_name()} WHERE id IN (" . implode( ',', $ghosts ) . ")" );
		}
	}

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function acquire( $id, $level, $blocking, $expiration = 0 ) {
		global $wpdb;

		// Lock level policy. We can only acquire a lock if there are no write locks.
		$lock_level = ( $level == WP_Lock::READ ) ? " AND `level` > $level" : '';

		// Expired locks policy. We can only acquire a lock if there are no unexpired ones.
		$expired    = " AND (`expire` = 0 OR `expire` >= " . ( microtime( true ) ) . ")";

		$query      = "INSERT INTO {$this->get_table_name()} (`lock_key`, `original_key`, `level`, `pid`, `cid`, `expire`) " .
		              "SELECT '%s', '%s', '%s', '%s', '%s', '%s' " .
		              "WHERE NOT EXISTS (SELECT 1 FROM {$this->get_table_name()} WHERE `lock_key` = '%s'{$lock_level}{$expired})";

		$attempt  = 0;
		$suppress = false;
		do {
			// Suppress errors on first attempt when the table does not exist, to avoid polluting the log with table creation errors.
			$attempt || $suppress = $wpdb->suppress_errors();

			$acquired = $wpdb->query(
				$wpdb->prepare(
					$query,
					$this->get_lock_key( $id ),
					$id,
					$level,
					getmypid(),
					$wpdb->get_var( "SELECT CONNECTION_ID()" ),
					$expiration ? $expiration + time() : 0,
					$this->get_lock_key( $id )
				)
			);
			$db_error = $wpdb->last_error;

			// Stop suppressing errors after first attempt.
			$attempt || $wpdb->suppress_errors( $suppress );

			if ( $wpdb->last_error && ! $attempt ++ ) {
				// Maybe tables are not installed yet?
				self::install();
			}

		} while ( $db_error );

		// Acquiring is ok, return true.
		if ( $acquired ) {
			$this->lock_ids[ $this->get_lock_key( $id ) ] = $wpdb->insert_id;

			return ( bool ) $acquired;
		}

		if ( ! $blocking ) {
			return false;
		}

		// Maybe there are some ghost locks?
		$this->drop_ghosts( $id );

		// Spinlock.
		usleep( 5000 );

		return $this->acquire( $id, $level, $blocking, $expiration );
	}

	/**
	 * @inheritDoc
	 */
	public function release( $id ) {
		global $wpdb;

		$lock_key = $this->get_lock_key( $id );
		if ( ! isset( $this->lock_ids[ $lock_key ] ) ) {
			// This lock is not acquired.
			return false;
		}

		$lock_id = $this->lock_ids[ $lock_key ];
		unset( $this->lock_ids[ $lock_key ] );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->get_table_name()} WHERE id = %s", $lock_id ) );

		return true;
	}

	static function install() {
		Database::install_table(
			self::TABLE_NAME,
                "`id`            INT(10)     UNSIGNED NOT NULL AUTO_INCREMENT,
                `lock_key`      VARCHAR(50) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
	            `original_key`  VARCHAR(50) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
                `level`         SMALLINT(5) UNSIGNED NULL DEFAULT NULL,
                `pid`           INT(10)     UNSIGNED NULL DEFAULT NULL,
                `cid`           INT(10)     UNSIGNED NULL DEFAULT NULL,
                `expire`        INT(10)     UNSIGNED NULL DEFAULT NULL,
                PRIMARY KEY (id) USING BTREE,
                INDEX `id` (`id`) USING BTREE,
                INDEX `level` (`level`) USING BTREE",
				['upgrade_method' => 'query']
		);
	}
}
