<?php

declare(strict_types=1);

namespace Oracle\OCI;

use Oracle\OCI\Exception\OCIException;

/**
 * A parsed oci8 statement: bind values, execute it, and fetch rows.
 *
 * Get one from Connection::parse() rather than constructing it directly.
 */
class Statement
{
    /**
     * @param Connection $connection The connection that parsed this statement
     * @param resource $statement The oci8 statement handle from oci_parse()
     */
    public function __construct(
        private Connection $connection,
        private mixed $statement
    ) {
    }


    /**
     * Frees the oci8 statement handle when the object goes away.
     */
    public function __destruct()
    {
        \oci_free_statement($this->statement);
    }


    /**
     * @return Connection The connection that parsed this statement
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }


    /**
     * Binds a PHP variable to a named placeholder.
     *
     * The variable is bound by reference, the same way oci_bind_by_name() works,
     * so its value is read at execute() time and OUT parameters are written back to it.
     *
     * @param string $name Placeholder name, with or without the leading colon
     * @param mixed $value Variable to bind
     * @param int $maxlength Buffer size for OUT values, or -1 to size from the current value
     * @param int $type One of the SQLT_* constants describing the bound value
     * @return self
     * @throws OCIException When Oracle rejects the bind
     */
    public function bindByName(
        string $name,
        mixed &$value,
        int $maxlength = -1,
        int $type = \SQLT_CHR
    ): self {
        return \oci_bind_by_name($this->statement, $name, $value, $maxlength, $type) === true
            ? $this
            : throw new OCIException($this->statement);
    }


    /**
     * Runs the statement.
     *
     * @param int $mode OCI_COMMIT_ON_SUCCESS to commit right away, or OCI_NO_AUTO_COMMIT to leave the transaction open
     * @return $this
     * @throws OCIException When Oracle reports an error
     */
    public function execute(int $mode = \OCI_COMMIT_ON_SUCCESS): self
    {
        return \oci_execute($this->statement, $mode) === true
            ? $this
            : throw new OCIException($this->statement);
    }


    /**
     * Executes the statement and yields each result row.
     *
     * Always executes with the default mode; call execute() and fetch() yourself
     * when you need OCI_NO_AUTO_COMMIT.
     *
     * @return iterable<int,mixed[]> Rows keyed by column name, numbered from 1
     * @throws OCIException When execution fails
     */
    public function query(): iterable
    {
        $rowNum = 0;
        for ($row = $this->execute()->fetch(); is_array($row); $row = $this->fetch()) {
            yield ++$rowNum => $row;
        }
    }


    /**
     * Fetches the next row from an executed statement.
     *
     * @return mixed[]|null Row keyed by column name, or null when there are no more rows
     */
    public function fetch(): array|null
    {
        $row = \oci_fetch_assoc($this->statement);
        return is_array($row) ? $row : null;
    }
}
