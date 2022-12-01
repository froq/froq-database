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
     * @return PDOStatement
     * @throws froq\database\DatabaseException
     */
    public function prepareStatement(string $input): PDOStatement
    {
        $input = $this->prepareNameInput($input);
        $input || throw new DatabaseException('Empty input');

        try {
            return $this->link()->pdo()->prepare($input);
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }
    }

    /**
     * Prepare & execute given input returning a `PDOStatement` object.
     *
     * @param  string|PDOStatement $input
     * @param  array|null          $params
     * @return PDOStatement
     * @causes froq\database\DatabaseException
     */
    public function executeStatement(string|PDOStatement $input, array $params = null): PDOStatement
    {
        if (is_string($input)) {
            $statement = $this->prepareStatement($input);
        }

        $statement->execute($params);

        return $statement;
    }
}
