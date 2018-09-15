# `WP_Lock`

## because WordPress is not thread-safe

[![Build Status](https://travis-ci.org/soulseekah/wp-lock.svg?branch=master)](https://travis-ci.org/soulseekah/wp-lock)

WordPress is no longer just a blogging platform. It's a framework. And like all mature frameworks it drastically needs a lock API.

## Example

Consider the following user balance topup function that is susceptible to a race condition:

```php
// A thread-safe version of the above topup function.
public function topup_user_balance( $user_id, $topup ) {
	$balance = get_user_meta( $user_id, 'balance', true );
	$balance = $balance + $topup;
	update_user_meta( $user_id, 'balance', $balance = get_user_meta( $user_id, 'balance', true ) + $topup );
	return $balance;
}
```

Try to call the above code 100 times in 16 threads. The balance will be less than it is supposed to be.

```php
// A thread-safe version of the above topup function.
public function topup_user_balance( $user_id, $topup ) {
	$user_balance_lock = new WP_Lock( "$user_id:meta:balance" );
	$user_balance_lock->acquire( WP_Lock::WRITE );

	$balance = get_user_meta( $user_id, 'balance', true );
	$balance = $balance + $topup;
	update_user_meta( $user_id, 'balance', $balance = get_user_meta( $user_id, 'balance', true ) + $topup );

	$user_balance_lock->release();

	return $balance;
}
```

The above code is thread safe.

## Lock levels

- `WP_Lock::READ` - other processes can acquire READ but not WRITE until the original lock is released. A shared read lock.
- `WP_Lock::WRITE` (default) - other processes can't acquire READ or WRITE locks until the original lock is released. An exclusive read-write lock
