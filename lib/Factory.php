<?php

namespace Soulseekah\WP_Lock;

class Factory {
	public static function get_default_lock() {
		return new WP_Lock_Backend_flock();
	}
}
