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

/**
 * Abstract Sql.
 * @package froq\database\sql
 * @object  froq\database\sql\AbstractSql
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0
 */
abstract class AbstractSql
{
    /**
     * Content.
     * @var string
     */
    protected string $content;

    /**
     * Constructor.
     * @param  string $content
     * @throws froq\database\sql\SqlException
     */
    public function __construct(string $content)
    {
        $content = trim($content);
        if ($content == '') {
            throw new SqlException('Empty content given to "%s", non-empty content required',
                [static::class]);
        }

        $this->content = $content;
    }

    /**
     * String magic.
     */
    public function __toString()
    {
        return $this->content();
    }

    /**
     * Content.
     * @return string
     */
    public function content(): string
    {
        return $this->content;
    }
}
