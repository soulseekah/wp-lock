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
	 * Search for a ghost lock for specific lock_id in the database and remove it.
	 *
	 * @return void
	 */
	public function drop_ghosts( $lock_id ) {
		global $wpdb;
		$lock_key = $this->get_lock_key( $lock_id );

		$locks  = $wpdb->get_results( "SELECT * FROM {$this->get_table_name()} WHERE `key` = '$lock_key'", ARRAY_A );
		$ghosts = [];
		foreach ( $locks as $lock ) {
			if (
				( empty( $lock['pid'] ) && empty( $lock['cid'] ) ) ||
				( ( ! empty( $lock['pid'] ) ) && ( ! file_exists( "/proc/{$lock['pid']}" ) ) ) ||
				( ( ! empty( $lock['cid'] ) ) && empty(
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

		// Lock level policy.
		$lock_level = ( $level == WP_Lock::READ ) ? " AND `level` > $level" : '';
		$query      = "INSERT INTO {$this->get_table_name()} (`key`, `original_key`, `level`, `pid`, `cid`) " .
		              "SELECT '%s', '%s', '%s', '%s', '%s' " .
		              "WHERE NOT EXISTS (SELECT 1 FROM {$this->get_table_name()} WHERE `key` = '%s'{$lock_level})";

		$attempt = 0;
		do {
			// Suppress errors on first attempt, to avoid polluting the log with table creation errors.
			$attempt || $wpdb->suppress_errors();

			$acquired = $wpdb->query(
				$wpdb->prepare(
					$query,
					$this->get_lock_key( $id ),
					$id,
					$level,
					getmypid(),
					$wpdb->get_var( "SELECT CONNECTION_ID()" ),
					$this->get_lock_key( $id )
				)
			);
			// Stop suppressing errors after first attempt.
			$attempt || $wpdb->suppress_errors( false );

			if ( false === $acquired && ! $attempt ++ ) {
				// Maybe tables are not installed yet?
				$this->install();
			}
		} while ( false === $acquired && 2 > $attempt );

		if ( false === $acquired ) {
			throw new Exception( "Database refused inserting new lock with the words: [{$wpdb->last_error}]" );
		}

		// Acquiring is ok, return true.
		if ( $acquired ) {
			$this->lock_ids[ $this->get_lock_key( $id ) ] = $wpdb->insert_id;

			return !! $acquired;
		}

		if ( ! $blocking ) {
			return false;
		}

		$this->drop_ghosts( $id );

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

	private function install() {
		Database::install_table(
			self::TABLE_NAME,
			"
                `id`            INT(10)     UNSIGNED NOT NULL AUTO_INCREMENT,
                `key`           VARCHAR(50) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
	            `original_key`  VARCHAR(50) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
                `level`         SMALLINT(5) UNSIGNED NULL DEFAULT NULL,
                `pid`           INT(10)     UNSIGNED NULL DEFAULT NULL,
                `cid`           INT(10)     UNSIGNED NULL DEFAULT NULL,
                
                PRIMARY KEY (`id`) USING BTREE,
                INDEX `id` (`id`) USING BTREE,
                INDEX `level` (`level`) USING BTREE"
		);
	}
}
