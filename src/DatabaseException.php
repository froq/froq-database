<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\common\Exception;
use Throwable, PDOException;

/**
 * Database Exception.
 *
 * @package froq\database
 * @object  froq\database\DatabaseException
 * @author  Kerem Güneş
 * @since   1.0
 */
class DatabaseException extends Exception
{
    /** @var string */
    private string $sqlState = '';

    /**
     * Constructor.
     *
     * @param string|Throwable $message
     * @param any|null         $messageParams
     * @param int|string|null  $code
     * @param Throwable|null   $previous
     * @param Throwable|null   $cause
     */
    public function __construct(string|Throwable $message = null, $messageParams = null, int|string $code = null,
        Throwable $previous = null, Throwable $cause = null)
    {
        if ($message != null) {
            if (is_string($message)) {
                $errorInfo = $this->parseMessageInfo($message);
            } else {
                $errorInfo = isset($message->errorInfo)
                    ? ($message->errorInfo ?: $this->parseMessageInfo($message->getMessage()))
                    : $this->parseMessageInfo($message->getMessage());
            }

            // Update sql-state & code.
            $this->sqlState = (string) $errorInfo[0];

            if (is_string($code)) {
                $this->sqlState = $code;
                $code = 0;
            }

            // Override if null.
            $code ??= (int) $errorInfo[1];
        }

        parent::__construct($message, $messageParams, $code, $previous, $cause);
    }

    /**
     * Get sql state.
     *
     * @return string.
     */
    public function getSqlState(): string
    {
        return $this->sqlState;
    }

    /**
     * Parse message info.
     *
     * @param  string $message
     * @return array<string, string>
     */
    private function parseMessageInfo(string $message): array
    {
        // For all those FUCKs..
        // SQLSTATE[08006] [7] FATAL:  password authentication failed for user "root
        // SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: YES)
        // SQLSTATE[42601]: Syntax error: 7 ERROR:  unterminated quoted identifier at or near ...
        // SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax ...
        if (preg_match('~^(?:
            SQLSTATE\[(\w+)\]\s+\[(\d+)\]\s+(?:.*) |
            SQLSTATE\[(\w+)\]:?\s+(?:.*):\s+(\d+)\s+(?:.*)
        )~x', $message, $match)) {
            $match = array_values(array_filter($match, 'strlen'));

            return [$match[1], $match[2]];
        }

        return ['', ''];
    }
}
