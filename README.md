# `WP_Lock`

## because WordPress is not thread-safe

This is, what hopes to be a part of WordPress Core someday.

## Example

Consider the following user balance topup function that is susceptible to a race condition:

```php
// A thread-safe version of the above topup function.
public function topup_user_balance( $topup ) {
	$balance = get_user_meta( $user_id, 'balance', true );
	$balance = $balance + $topup;
	update_user_meta( $user_id, 'balance', $balance = get_user_meta( $user_id, 'balance', true ) + $topup );
	return $balace;
}
```

Try to call the above code 100 times in 16 threads. The balance will be less than it is supposed to be.

```php
// A thread-safe version of the above topup function.
public function topup_user_balance( $topup ) {
	$user_balance_lock = new WP_Lock( "$user_id:meta:balance" );
	$user_balance_lock->acquire( WP_Lock::EXCLUSIVE );

	$balance = get_user_meta( $user_id, 'balance', true );
	$balance = $balance + $topup;
	update_user_meta( $user_id, 'balance', $balance = get_user_meta( $user_id, 'balance', true ) + $topup );

	$user_balance_lock->release();

	return $balance;
}
```

The above code is thread safe.

## Lock levels

- `WP_Lock::READ`, `WP_Lock::WRITE` - other processes can acquire READ but not WRITE, EXCLUSIVE until the original lock is released.
- `WP_Lock::EXCLUSIVE` - other processes can't acquire READ, WRITE, EXCLUSIVE locks until the original lock is released.
