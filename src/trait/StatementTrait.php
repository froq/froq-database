<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\trait;

use froq\database\DatabaseException;
use PDOStatement, PDOException;

/**
 * A trait, provides `PDOStatement` related stuff & used by `Database` only.
 *
 * @package froq\database\trait
 * @class   froq\database\trait\StatementTrait
 * @author  Kerem Güneş
 * @since   6.0
 */
trait StatementTrait
{
    /**
     * Prepare given input returning a `PDOStatement` object.
     *
     * @param  string $input
     * @param  bool   $raw
     * @return PDOStatement
     * @throws froq\database\DatabaseException
     */
    public function prepareStatement(string $input, bool $raw = true): PDOStatement
    {
        $input = $raw ? trim($input) : $this->prepareNameInput($input);
        $input || throw new DatabaseException('Empty input');

        try {
            return $this->pdo()->prepare($input);
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }
    }

    /**
     * Prepare & execute given input returning a `PDOStatement` object.
     *
     * @param  string|PDOStatement $input
     * @param  array|null          $params
     * @param  bool                $raw
     * @return PDOStatement
     * @causes froq\database\DatabaseException
     */
    public function executeStatement(string|PDOStatement $input, array $params = null, bool $raw = true): PDOStatement
    {
        $statement = is_string($input) ? $this->prepareStatement($input, $raw) : $input;

        try {
            $statement->execute($params);
            return $statement;
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }
    }
}
