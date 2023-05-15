<?php

use soulseekah\WP_Lock\helpers\Database;
use soulseekah\WP_Lock\WP_Lock_Backend_DB;

// Do not run under test environment.
if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', function() {
		Database::register_table( WP_Lock_Backend_DB::TABLE_NAME );
	} );
}
