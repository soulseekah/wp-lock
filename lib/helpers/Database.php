<?php

namespace soulseekah\WP_Lock\helpers;

final class Database {
	public static function register_table( $key, $name = false ) {
		global $wpdb;

		if ( ! $name ) {
			$name = $key;
		}

		$wpdb->tables[] = $name;
		$wpdb->$key     = $wpdb->prefix . $name;
	}

	public static function install_table( $key, $columns, $opts = [] ) {
		global $wpdb;

		$full_table_name = $wpdb->prefix . $key;

		if ( is_string( $opts ) ) {
			$opts = [ 'upgrade_method' => $opts ];
		}

		$opts = wp_parse_args( $opts, [
			'upgrade_method' => 'dbDelta',
			'table_options'  => '',
		] );

		$charset_collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if ( ! empty( $wpdb->collate ) ) {
				$charset_collate .= " COLLATE $wpdb->collate";
			}
		}

		$table_options = $charset_collate . ' ' . $opts['table_options'];

		if ( 'dbDelta' == $opts['upgrade_method'] ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( "CREATE TABLE $full_table_name ( $columns ) $table_options" );

			return;
		}

		if ( 'delete_first' == $opts['upgrade_method'] ) {
			$wpdb->query( "DROP TABLE IF EXISTS $full_table_name;" );
		}

		$wpdb->query( "CREATE TABLE IF NOT EXISTS $full_table_name ( $columns ) $table_options;" );
	}
}
