<?php

declare(strict_types=1);

namespace Oracle\OCI;

use Oracle\OCI\Exception\OCIException;

/**
 * An Oracle database connection built on the oci8 extension.
 *
 * The connection is opened lazily: nothing talks to Oracle until connect() or
 * parse() is called. Every oci8 failure surfaces as an OCIException.
 */
class Connection
{
    /**
     * @var resource|false $handle
     */
    private mixed $handle = false;
    private string $username = "";
    private string $password = "";
    private string|null $connection = null;
    private string $charset = "";
    private int $sessionMode = \OCI_DEFAULT;
    private bool $persistent = false;
    private bool $exclusive = false;


    /**
     * Stores the connection settings without opening a connection.
     *
     * @param string $username Oracle user name
     * @param string $password Oracle password
     * @param string|null $connection Easy Connect string or tnsnames.ora entry, or null for the local default
     * @param string $charset Client character set, or an empty string for the NLS_LANG default
     * @param int $sessionMode One of OCI_DEFAULT, OCI_SYSOPER, OCI_SYSDBA, or OCI_CRED_EXT
     * @param bool $persistent Connect with oci_pconnect() so the handle outlives the request
     * @param bool $exclusive Connect with oci_new_connect() so the handle is not shared within the request
     */
    public function __construct(
        string $username = "",
        string $password = "",
        string|null $connection = null,
        string $charset = "",
        int $sessionMode = \OCI_DEFAULT,
        bool $persistent = false,
        bool $exclusive = false
    ) {
        $this
            ->setUsername($username)
            ->setPassword($password)
            ->setConnection($connection)
            ->setCharset($charset)
            ->setSessionMode($sessionMode)
            ->setPersistent($persistent)
            ->setExclusive($exclusive);
    }


    /**
     * Closes the connection handle when the object goes away.
     */
    public function __destruct()
    {
        $this->close();
    }


    /**
     * Opens the connection if it is not already open.
     *
     * Exclusive wins over persistent when both flags are set.
     *
     * @param bool $force Open a new handle even if one is already open
     * @return $this
     * @throws OCIException When Oracle refuses the connection
     */
    public function connect(bool $force = false): self
    {
        if (is_resource($this->handle) && $force === false) {
            return $this;
        }

        $connect = match (true) {
            $this->isExclusive() => \oci_new_connect(...),
            $this->isPersistent() => \oci_pconnect(...),
            default => \oci_connect(...)
        };

        $this->handle = $connect(
            $this->getUsername(),
            $this->getPassword(),
            $this->getConnection(),
            $this->getCharset(),
            $this->getSessionMode()
        );

        return is_resource($this->handle)
            ? $this
            : throw new OCIException(null, "Unable to create connection handle");
    }


    /**
     * Prepares a SQL statement, connecting first if needed.
     *
     * @param string|resource|false $sql SQL text, or a stream to read it from
     * @return Statement
     * @throws OCIException When $sql is not usable or Oracle cannot parse it
     */
    public function parse(mixed $sql): Statement
    {
        if (is_resource($sql)) {
            $sql = stream_get_contents($sql);
        }
        if (!is_string($sql)) {
            throw new OCIException(null, "Invalid sql string");
        }

        $stmt = oci_parse($this->connect()->getHandle(), $sql);

        return is_resource($stmt)
            ? new Statement($this, $stmt)
            : throw new OCIException($this->getHandle());
    }


    /**
     * Commits the open transaction.
     *
     * @return $this
     * @throws OCIException When there is no open connection or the commit fails
     */
    public function commit(): self
    {
        return \oci_commit($this->getHandle()) === true
            ? $this
            : throw new OCIException($this->getHandle());
    }


    /**
     * Rolls back the open transaction.
     *
     * @return $this
     * @throws OCIException When there is no open connection or the rollback fails
     */
    public function rollback(): self
    {
        return \oci_rollback($this->getHandle()) === true
            ? $this
            : throw new OCIException($this->getHandle());
    }


    /**
     * Closes the connection handle if one is open. Safe to call more than once.
     *
     * @return $this
     */
    public function close(): self
    {
        if (is_resource($this->handle)) {
            \oci_close($this->handle);
        }
        $this->handle = false;
        return $this;
    }


    #region Getters/Setters

    /**
     * Returns the open oci8 connection handle.
     *
     * @return resource
     * @throws OCIException When the connection has not been opened
     */
    protected function getHandle(): mixed
    {
        return is_resource($this->handle)
            ? $this->handle
            : throw new OCIException(null, "Connection handle not initialized");
    }


    /**
     * @return string Oracle user name
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username Oracle user name
     * @return $this
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }


    /**
     * @return string Oracle password
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password Oracle password
     * @return $this
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }


    /**
     * @return string|null Easy Connect string or tnsnames.ora entry, or null for the local default
     */
    public function getConnection(): string|null
    {
        return $this->connection;
    }

    /**
     * @param string|null $connection Easy Connect string or tnsnames.ora entry, or null for the local default
     * @return $this
     */
    public function setConnection(string|null $connection): self
    {
        $this->connection = $connection;
        return $this;
    }


    /**
     * @return string Client character set, or an empty string for the NLS_LANG default
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @param string $charset Client character set, or an empty string for the NLS_LANG default
     * @return $this
     */
    public function setCharset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }


    /**
     * @return int One of OCI_DEFAULT, OCI_SYSOPER, OCI_SYSDBA, or OCI_CRED_EXT
     */
    public function getSessionMode(): int
    {
        return $this->sessionMode;
    }

    /**
     * @param int $sessionMode One of OCI_DEFAULT, OCI_SYSOPER, OCI_SYSDBA, or OCI_CRED_EXT
     * @return $this
     */
    public function setSessionMode(int $sessionMode): self
    {
        $this->sessionMode = $sessionMode;
        return $this;
    }


    /**
     * @return bool Whether connect() uses oci_pconnect()
     */
    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    /**
     * @param bool $persistent Connect with oci_pconnect() so the handle outlives the request
     * @return $this
     */
    public function setPersistent(bool $persistent): self
    {
        $this->persistent = $persistent;
        return $this;
    }


    /**
     * @return bool Whether connect() uses oci_new_connect()
     */
    public function isExclusive(): bool
    {
        return $this->exclusive;
    }

    /**
     * @param bool $exclusive Connect with oci_new_connect() so the handle is not shared within the request
     * @return $this
     */
    public function setExclusive(bool $exclusive): self
    {
        $this->exclusive = $exclusive;
        return $this;
    }

    #endregion Getters/Setters
}
