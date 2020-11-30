<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\TransactionException;
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

    /** @alias of begin() */
    public function start() { $this->begin(); }

    /** @alias of commit() */
    public function end() { return $this->commit(); }

    /** @alias of rollback() */
    public function cancel() { return $this->rollback(); }
}
