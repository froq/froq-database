<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database;

use PDO, PDOException;

/**
 * A wrapper class for PDO transactions with some utilities.
 *
 * @package froq\database
 * @class   froq\database\Transaction
 * @author  Kerem Güneş
 * @since   5.0
 */
class Transaction
{
    /** PDO instance. */
    private PDO $pdo;

    /**
     * Constructor.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Begin a transaction.
     *
     * @return bool
     * @throws froq\database\TransactionException
     */
    public function begin(): bool
    {
        try {
            return $this->pdo->beginTransaction()
                || throw new TransactionException('Failed to begin transaction');
        } catch (PDOException $e) {
            throw new TransactionException($e);
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
            throw new TransactionException($e);
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
            throw new TransactionException($e);
        }
    }

    /** Aliases */
    public function start()  { return $this->begin(); }
    public function end()    { return $this->commit(); }
    public function cancel() { return $this->rollback(); }
}
