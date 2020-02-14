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

namespace froq\database\sql;

use froq\database\sql\SqlException;
use DateTime, DateTimeZone;

/**
 * Abstract Sql Date.
 * @package froq\database\sql
 * @object  froq\database\sql\AbstractSqlDate
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0
 */
abstract class AbstractSqlDate extends AbstractSql
{
    /**
     * Format.
     * @var string
     */
    protected string $format;

    /**
     * Timezone.
     * @var string
     */
    protected string $timezone;

    /**
     * Constructor.
     * @param  string|int|null $content
     * @param  string|null     $timezone
     * @throws froq\database\sql\SqlException
     */
    public function __construct($datetime = null, string $timezone = null)
    {
        $datetime = $datetime ?: '';   // Accepted, but not null.
        $timezone = $timezone ?: null; // Not Accepted, but null, weird..

        if ($timezone != null) {
            $timezone = new DateTimeZone($timezone);
        }

        if (is_int($datetime)) {
            $content = new DateTime('', $timezone);
            $content->setTimestamp($datetime);
        } elseif (is_string($datetime)) {
            $content = new DateTime($datetime, $timezone);
        } else {
            throw new SqlException('Invalid datetime type "%s" given, valids are: int, string, null',
                [gettype($datetime)]);
        }

        $this->content  = $content->format($this->format);
        $this->timezone = $content->getTimezone()->getName();
    }

    /**
     * Get format.
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Get timezone.
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }
}
