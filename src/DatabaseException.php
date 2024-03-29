<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database;

/**
 * @package froq\database
 * @class   froq\database\DatabaseException
 * @author  Kerem Güneş
 * @since   1.0
 */
class DatabaseException extends \froq\common\Exception
{
    /** SQL state. */
    private ?string $sqlState = null;

    /**
     * @override
     */
    public function __construct(string|\Throwable $message = null, mixed $messageParams = null, int|string $code = null,
        mixed ...$arguments)
    {
        if ($message) {
            if (is_string($message)) {
                $errorInfo = self::parseErrorInfo($message);
            } else {
                $errorInfo = isset($message->errorInfo)
                    ? ($message->errorInfo ?: self::parseErrorInfo($message->getMessage()))
                    : self::parseErrorInfo($message->getMessage());
            }

            if (is_string($code)) {
                $this->sqlState = $code;
                $code = 0;
            } else {
                $this->sqlState = (string) $errorInfo[0];
            }

            // Override if null.
            $code ??= (int) $errorInfo[1];
        } else {
            $code = (int) $code;
        }

        parent::__construct($message, $messageParams, $code, ...$arguments);
    }

    /**
     * Get sql state.
     *
     * @return string|null
     */
    public function getSqlState(): string|null
    {
        return $this->sqlState;
    }

    /**
     * Parse error info.
     *
     * @param  string $message
     * @return string
     */
    public static function parseErrorInfo(string $message): array
    {
        // For all those FUCKs..
        // SQLSTATE[08006] [7] FATAL:  password authentication failed for user "root" ..
        // SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: ..
        // SQLSTATE[42601]: Syntax error: 7 ERROR:  unterminated quoted identifier at or near ..
        // SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax ..
        if (preg_match(
            '~^(?:
                SQLSTATE\[(\w+)\]\s+\[(\d+)\]\s+(?:.*) |
                SQLSTATE\[(\w+)\]:?\s+(?:.*):\s+(\d+)\s+(?:.*)
            )~x',
            $message, $match
        )) {
            $match = array_filter_list($match, 'strlen');

            return [$match[1], $match[2]];
        }

        return ['', ''];
    }
}
