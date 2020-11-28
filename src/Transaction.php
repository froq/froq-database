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

use froq\database\{TransactionException};
use PDO, PDOException;

/**
 * Transaction.
 *
 * A transaction wrapper that wraps all exceptions into TransactionException.
 *
 * @package froq\database
 * @object  froq\database\Transaction
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   5.0
 */
final class Transaction
{
    /**
     * Pdo.
     * @var PDO
     */
    private PDO $pdo;

    /**
     * Constructor.
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Begin a transaction.
     *
     * @return void
     * @throws froq\database\TransactionException
     */
    public function begin(): void
    {
        try {
            if (!$this->pdo->beginTransaction()) {
                throw new TransactionException('Failed to begin transaction');
            }
        } catch (PDOException $e) {
            throw new TransactionException($e->getMessage(), null, null, $e);
        }
    }

    /**
     * Commit a transaction.
     *
     * @return bool
     * @throws froq\database\TransactionException
     */
    public function commit(): bool
    {
        try {
            return $this->pdo->commit();
        } catch (PDOException $e) {
            throw new TransactionException($e->getMessage(), null, null, $e);
        }
    }

    /**
     * Rollback a transaction.
     *
     * @return bool
     * @throws froq\database\TransactionException
     */
    public function rollback(): bool
    {
        try {
            return $this->pdo->rollback();
        } catch (PDOException $e) {
            throw new TransactionException($e->getMessage(), null, null, $e);
        }
    }

    /** @aliasOf begin() */
    public function start() { $this->begin(); }

    /** @aliasOf commit() */
    public function end() { return $this->commit(); }

    /** @aliasOf rollback() */
    public function cancel() { return $this->rollback(); }
}
