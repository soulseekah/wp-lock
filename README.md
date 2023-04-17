# `WP_Lock`

## because WordPress is not thread-safe

![example branch parameter](https://github.com/soulseekah/wp-lock/actions/workflows/tests.yml/badge.svg?branch=master)

WordPress is no longer just a blogging platform. It's a framework. And like all mature frameworks it drastically needs a lock API.

## Example

Consider the following user balance topup function that is susceptible to a race condition:

```php
// topup function that is not thread-safe
public function topup_user_balance( $user_id, $topup ) {
	$balance = get_user_meta( $user_id, 'balance', true );
	$balance = $balance + $topup;
	update_user_meta( $user_id, 'balance', $balance );
	return $balance;
}
```

Try to call the above code 100 times in 16 threads. The balance will be less than it is supposed to be.

```php
// Be aware you have to declare the used namespace since v2.0  
use \Soulseekah\WP_Lock\WP_Lock;

// A thread-safe version of the above topup function.
public function topup_user_balance( $user_id, $topup ) {
	$user_balance_lock = new WP_Lock( "$user_id:meta:balance" );
	$user_balance_lock->acquire( WP_Lock::WRITE );

	$balance = get_user_meta( $user_id, 'balance', true );
	$balance = $balance + $topup;
	update_user_meta( $user_id, 'balance', $balance );

	$user_balance_lock->release();

	return $balance;
}
```

The above code is thread safe.

## Lock levels

- `WP_Lock::READ` - other processes can acquire READ but not WRITE until the original lock is released. A shared read lock.
- `WP_Lock::WRITE` (default) - other processes can't acquire READ or WRITE locks until the original lock is released. An exclusive read-write lock

# Release Log

| Version | Notice                                                                                        |
|---------|-----------------------------------------------------------------------------------------------|
| *2.0*   | Namespaces are introduced. Plugin is available like the PHP package. <br/> Requires php >=7.4 |



# Credits

Sponsored by [threadsafe.org](https://threadsafe.org)
