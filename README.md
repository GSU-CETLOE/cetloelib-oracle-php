# gsu-cetloe/cetloelib-oracle-php

A small PHP library for talking to Oracle through the oci8 extension. It has two parts.

`Oracle\OCI` is a thin object wrapper around the oci8 functions. `Connection` opens the connection and parses SQL, `Statement` binds values and fetches rows, and `OCIException` carries the Oracle error code, message, offset, and SQL text when something fails.

`Oracle\Doctrine\OracleDriver` is a Doctrine DBAL driver for Oracle. It connects the same way Doctrine's bundled OCI8 driver does, but the platform it returns uses `d-M-y H.i.s.u A` as the date format string.

## Requirements

- PHP 8.1 or newer
- The oci8 PHP extension, which needs Oracle Instant Client on the machine
- Composer

## Setup

Install Composer if you don't have it yet. Instructions are at https://getcomposer.org/.

The package isn't on Packagist, so point Composer at the GitHub repository before requiring it:

```sh
composer config repositories.cetloe-oracle vcs https://github.com/GSU-CETLOE/cetloelib-oracle-php
composer require gsu-cetloe/cetloelib-oracle-php:dev-main
```

To work on the library itself:

```sh
git clone https://github.com/GSU-CETLOE/cetloelib-oracle-php.git
cd cetloelib-oracle-php
composer install
```

## Usage

### Plain OCI

```php
use Oracle\OCI\Connection;
use Oracle\OCI\Exception\OCIException;

$db = new Connection('scott', 'tiger', 'dbhost.example.edu:1521/ORCLPDB1', 'AL32UTF8');

$deptNo = 10;
$statement = $db->parse('SELECT empno, ename FROM emp WHERE deptno = :deptno')
    ->bindByName(':deptno', $deptNo);

foreach ($statement->query() as $rowNumber => $row) {
    echo $rowNumber, ': ', $row['ENAME'], PHP_EOL;
}
```

A few things to know:

- The connection is lazy. Nothing talks to Oracle until `parse()` or `connect()` runs. Call `connect()` yourself if you want to fail early.
- `bindByName()` takes the variable by reference, just like `oci_bind_by_name()`, so pass a variable rather than a literal.
- `execute()` commits on success by default. Pass `OCI_NO_AUTO_COMMIT` to it and call `commit()` or `rollback()` on the connection when you want to control the transaction. `query()` always uses the default mode, so for a transaction use `execute()` and `fetch()` directly.
- Any oci8 failure throws `OCIException`. On top of `getMessage()` and `getCode()`, it has `getOffset()` and `getSqlText()`, which Oracle fills in for statement errors.

### Doctrine DBAL

```php
use Doctrine\DBAL\DriverManager;
use Oracle\Doctrine\OracleDriver;

$conn = DriverManager::getConnection([
    'driverClass' => OracleDriver::class,
    'user' => 'scott',
    'password' => 'tiger',
    'host' => 'dbhost.example.edu',
    'port' => 1521,
    'servicename' => 'ORCLPDB1',
    'charset' => 'AL32UTF8',
]);
```

The driver reads the same connection parameters as Doctrine's own OCI8 driver, including `persistent` and `driverOptions.exclusive`. Those two have to be boolean `true`. A string like `"1"` won't turn them on.

## Development

```sh
composer phpstan   # static analysis at level 10
composer phpcs     # PSR-12 style check
```

PHPStan writes its cache to `working/`, which git ignores.

There are no automated tests yet. Nothing in this library can run without a live Oracle database, so verification is manual and follows [TESTPLAN.md](TESTPLAN.md).

## License

MIT. See [LICENSE](LICENSE).
