# Test plan

Manual verification for gsu-cetloe/cetloelib-oracle-php. Everything below needs PHP with the oci8 extension loaded and a reachable Oracle database. Use a scratch schema, not production. Check items off as you go, and add a line whenever a public method is added or changed.

## Before you start

- [ ] `php -m | grep oci8` prints `oci8`
- [ ] You have a user name, password, and connect string (`host:port/service`) for a scratch schema
- [ ] `composer install` finished without errors

## Static checks

- [ ] `composer phpstan` reports no errors
- [ ] `composer phpcs` reports no violations

## Oracle\OCI\Connection

- [ ] `new Connection(...)` with valid settings does not open a session until `connect()` or `parse()` is called
- [ ] `connect()` with valid settings returns the same `Connection`, and calling it again does nothing
- [ ] `connect(true)` opens a fresh handle even when one is already open
- [ ] `connect()` with a wrong password throws `OCIException`; `getMessage()` and `getCode()` describe the Oracle error (ORA-01017)
- [ ] `setPersistent(true)` followed by `connect()` works
- [ ] `setExclusive(true)` followed by `connect()` works
- [ ] `parse()` with a string returns a `Statement`
- [ ] `parse()` with a stream resource reads the SQL from the stream
- [ ] `parse()` with a value that is neither throws `OCIException` with the message "Invalid sql string"
- [ ] `commit()` after `execute(OCI_NO_AUTO_COMMIT)` makes the change visible from a second session
- [ ] `rollback()` after `execute(OCI_NO_AUTO_COMMIT)` discards the change
- [ ] `commit()` on a connection that was never opened throws `OCIException` with the message "Connection handle not initialized"
- [ ] `close()` releases the handle, and a later `parse()` reconnects on its own
- [ ] Each getter returns what the constructor or matching setter was given

## Oracle\OCI\Statement

- [ ] `bindByName()` binds a variable, and changing the variable before `execute()` changes what Oracle sees
- [ ] `bindByName()` with a placeholder name that is not in the SQL throws `OCIException`
- [ ] `execute()` on an UPDATE commits by default; the change is visible from a second session without calling `commit()`
- [ ] `execute(OCI_NO_AUTO_COMMIT)` leaves the change uncommitted
- [ ] `execute()` on invalid SQL throws `OCIException` with a non-zero `getCode()`, the SQL in `getSqlText()`, and `getOffset()` pointing at the bad token
- [ ] `query()` yields associative rows keyed by column name, numbered from 1
- [ ] `query()` on a statement that matches no rows yields nothing
- [ ] `fetch()` returns the next row, then `null` after the last one
- [ ] `getConnection()` returns the `Connection` that created the statement

## Oracle\OCI\Exception\OCIException

- [ ] Thrown from a failed `execute()`: `getMessage()` is the ORA message and `getCode()` is the ORA number
- [ ] Constructed with no resource and a custom message: `getMessage()` is that message and `getCode()` is 0
- [ ] `getOffset()` is 0 and `getSqlText()` is empty when the error did not come from a statement

## Oracle\Doctrine\OracleDriver

- [ ] `DriverManager::getConnection()` with `driverClass => OracleDriver::class` and valid `host`, `port`, and `servicename` connects, and `SELECT 1 FROM dual` returns 1
- [ ] A wrong password throws a Doctrine exception describing the login failure
- [ ] `persistent => true` connects
- [ ] `persistent => true` together with `driverOptions => ['exclusive' => true]` throws `InvalidConfiguration`
- [ ] `persistent => "1"` (a string) does not turn on persistence, because the driver checks for boolean `true`
- [ ] `$conn->getDatabasePlatform()->getDateFormatString()` returns `d-M-y H.i.s.u A`
- [ ] Inserting a PHP `DateTime` into a DATE column through Doctrine's DATE type succeeds and reads back the same date
