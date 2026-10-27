<?php

declare(strict_types=1);

namespace Oracle\OCI\Exception;

/**
 * An error reported by the oci8 extension.
 *
 * The message and code come from oci_error() when it has something to say;
 * otherwise the caller's fallback message is used with code 0.
 */
class OCIException extends \Exception
{
    private int $offset = 0;
    private string $sqlText = '';


    /**
     * @param resource|null $resource Connection or statement handle to read the error from,
     *                                or null for the most recent connection error
     * @param string|null $message Fallback message when oci_error() reports nothing
     */
    public function __construct(
        mixed $resource = null,
        string|null $message = null
    ) {
        /** @var array{code:int,message:string,offset:int,sqltext:string}|false $error */
        $error = oci_error($resource);
        if (!is_array($error)) {
            $error = null;
        }

        parent::__construct(
            $error['message'] ?? $message ?? '',
            $error['code'] ?? 0,
            null
        );

        $this->offset = $error['offset'] ?? 0;
        $this->sqlText = $error['sqltext'] ?? '';
    }


    /**
     * @return int Byte offset into the SQL text where Oracle found the problem, or 0 when not a statement error
     */
    public function getOffset(): int
    {
        return $this->offset;
    }


    /**
     * @return string The SQL text that failed, or an empty string when not a statement error
     */
    public function getSqlText(): string
    {
        return $this->sqlText;
    }
}
