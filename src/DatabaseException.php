<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
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
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   1.0
 */
class DatabaseException extends Exception
{
    /**
     * SQL State.
     * @var string
     */
    private string $sqlState = '';

    /**
     * Constructor.
     * @param string|Throwable  $message
     * @param string|array|null $messageParams
     * @param int|null          $code
     * @param Throwable|null    $previous
     */
    public function __construct($message = null, $messageParams = null, int $code = null,
        Throwable $previous = null)
    {
        if ($message) {
            if (is_string($message)) {
                $errorInfo = $this->parseMessageInfo($message);
            } elseif (is_object($message) && $message instanceof Throwable) {
                $errorInfo = isset($message->errorInfo)
                    ? ($message->errorInfo ?: $this->parseMessageInfo($message->getMessage()))
                    : $this->parseMessageInfo($message->getMessage());
            } else {
                throw new Exception(
                    'Invalid message type "%s" given to "%s", valids are: string, Throwable',
                    [is_object($message) ? get_class($message) : gettype($message), static::class]
                );
            }

            $this->sqlState = (string) $errorInfo[0];

            // Override.
            if (is_null($code)) {
                $code = (int) $errorInfo[1];
            }
        }

        parent::__construct($message, $messageParams, $code, $previous);
    }

    /**
     * Get sql state.
     * @return string.
     */
    public function getSqlState(): string
    {
        return $this->sqlState;
    }

    /**
     * Parse message info.
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
