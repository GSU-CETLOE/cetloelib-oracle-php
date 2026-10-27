<?php

declare(strict_types=1);

namespace Oracle\Doctrine;

use Doctrine\DBAL\Driver\AbstractOracleDriver;
use Doctrine\DBAL\Driver\OCI8\Connection;
use Doctrine\DBAL\Driver\OCI8\Exception\ConnectionFailed;
use Doctrine\DBAL\Driver\OCI8\Exception\InvalidConfiguration;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\ServerVersionProvider;

/**
 * A Doctrine DBAL driver for Oracle over oci8.
 *
 * It connects the same way Doctrine's bundled OCI8 driver does, but the platform
 * it returns formats DATE values as 'd-M-y H.i.s.u A' instead of Doctrine's default.
 */
final class OracleDriver extends AbstractOracleDriver
{
    /**
     * Returns an OraclePlatform whose date format string is 'd-M-y H.i.s.u A'.
     *
     * @param ServerVersionProvider $versionProvider Unused; the platform is the same for every server version
     * @return OraclePlatform
     */
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): OraclePlatform
    {
        return new class extends OraclePlatform {
            /**
             * @return string PHP date() format Doctrine uses when writing DATE values
             */
            public function getDateFormatString(): string
            {
                return 'd-M-y H.i.s.u A';
            }
        };
    }


    /**
     * {@inheritDoc}
     *
     * The persistent and exclusive flags must be boolean true; truthy strings are ignored.
     */
    public function connect(
        #[\SensitiveParameter]
        array $params,
    ): Connection {
        $args = [
            $params['user'] ?? '',
            $params['password'] ?? '',
            $this->getEasyConnectString($params),
            $params['charset'] ?? '',
            $params['sessionMode'] ?? OCI_DEFAULT
        ];

        $persistent = ($params['persistent'] ?? null) === true;
        $exclusive = ($params['driverOptions']['exclusive'] ?? null) === true;
        if ($persistent && $exclusive) {
            throw InvalidConfiguration::forPersistentAndExclusive();
        }

        $connection = match (true) {
            $persistent => @\oci_pconnect(...$args),
            $exclusive => @\oci_new_connect(...$args),
            default => @\oci_connect(...$args)
        };

        if ($connection === false) {
            throw ConnectionFailed::new();
        }

        return new Connection($connection);
    }
}
