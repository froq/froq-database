<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database;

use PDO, PDOException;

/**
 * A wrapper class for PDO transactions with nesting support for MySQL, PostgreSQL
 * and SQLite platforms.
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

    /** Savepoint level. */
    private int $savepointLevel;

    /** Savepoint available state. */
    private bool $savepointAvailable;

    /**
     * Constructor.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo                = $pdo;
        $this->savepointLevel     = 0;
        $this->savepointAvailable = $this->supportsSavepoints();
    }

    /**
     * Check active state.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->savepointLevel > 0;
    }

    /**
     * Get savepoint level.
     *
     * @return int
     */
    public function savepointLevel(): int
    {
        return $this->savepointLevel;
    }

    /**
     * Get savepoint available state.
     *
     * @return bool
     */
    public function savepointAvailable(): bool
    {
        return $this->savepointAvailable;
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
            $result = false;

            if (!$this->savepointLevel || !$this->savepointAvailable) {
                $result = $this->pdo->beginTransaction();
            } else {
                $result = $this->pdo->exec('SAVEPOINT savepoint_' . $this->savepointLevel);
                if ($result === false) {
                    throw new TransactionException('Cannot create savepoint');
                }
            }

            $this->savepointLevel++;

            return $result !== false;
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
        if (!$this->isActive()) {
            throw new TransactionException('No active transaction to commit');
        }

        try {
            $result = false;

            $this->savepointLevel--;

            if (!$this->savepointLevel || !$this->savepointAvailable) {
                $result = $this->pdo->commit();
            } else {
                $result = $this->pdo->exec('RELEASE SAVEPOINT savepoint_' . $this->savepointLevel);
                if ($result === false) {
                    throw new TransactionException('Cannot release savepoint');
                }
            }

            return $result !== false;
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
        if (!$this->isActive()) {
            throw new TransactionException('No active transaction to rollback');
        }

        try {
            $result = false;

            $this->savepointLevel--;

            if (!$this->savepointLevel || !$this->savepointAvailable) {
                $result = $this->pdo->rollback();
            } else {
                $result = $this->pdo->exec('ROLLBACK TO SAVEPOINT savepoint_' . $this->savepointLevel);
                if ($result === false) {
                    throw new TransactionException('Cannot rollback savepoint');
                }
            }

            return $result !== false;
        } catch (PDOException $e) {
            throw new TransactionException($e);
        }
    }

    /**
     * Get support state for savepoints.
     * https://4js.com/techdocs/fjs-fgl-manual/index.html#fgl-topics/c_fgl_sql_programming_095.html
     */
    private function supportsSavepoints(): bool
    {
        return in_array(
            $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
            ['mysql', 'pgsql', 'sqlite'],
            true
        );
    }
}
