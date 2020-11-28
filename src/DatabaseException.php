<?php
/**
 * MIT License <https://opensource.org/licenses/mit>
 *
 * Copyright (c) 2015 Kerem Güneş
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
declare(strict_types=1);

namespace froq\database;

use froq\common\Exception;
use Throwable, PDOException;

/**
 * Database Exception.
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
