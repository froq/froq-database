<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\LinkException;
use PDO, PDOException;

/**
 * Link.
 *
 * Represents a PDO wrapper with some util methods.
 *
 * @package froq\database
 * @object  froq\database\Link
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0
 */
final class Link
{
    /** @var self */
    private static self $instance;

    /** @var ?PDO */
    private ?PDO $pdo = null;

    /** @var ?string */
    private ?string $pdoDriver = null;

    /** @var array */
    private array $options;

    /**
     * Constructor.
     *
     * @param array $options
     */
    private function __construct(array $options)
    {
        $this->options = self::prepareOptions($options);
    }

    /**
     * Get pdo property.
     *
     * @return ?PDO
     */
    public function pdo(): ?PDO
    {
        return $this->pdo;
    }

    /**
     * Get pdo driver property.
     *
     * @return ?string
     */
    public function pdoDriver(): ?string
    {
        return $this->pdoDriver;
    }

    /**
     * Get options property.
     *
     * @return array
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Connect with given options and set timezone & charset if provided.
     *
     * @return void
     */
    public function connect(): void
    {
        if (!$this->isConnected()) {
            ['dsn'     => $dsn,     'driver'   => $driver,
             'user'    => $user,    'pass'     => $pass,
             'charset' => $charset, 'timezone' => $timezone,
             'options' => $options] = $this->options;

            $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
            $options[PDO::ATTR_EMULATE_PREPARES] ??= true;
            $options[PDO::ATTR_DEFAULT_FETCH_MODE] ??= PDO::FETCH_ASSOC;

            // For a proper return that gives "1" always even with identical values in UPDATE queries.
            if ($driver == 'mysql') {
                $options[PDO::MYSQL_ATTR_FOUND_ROWS] ??= true;
            }

            try {
                $this->pdo = new PDO($dsn, $user, $pass, $options);
                $this->pdoDriver = $driver;
            } catch (PDOException $e) {
                // Which driver the FUCK?
                if ($e->getMessage() == 'could not find driver') {
                    throw new LinkException("Could not find driver '%s'", $driver);
                }
                throw new LinkException($e);
            } finally {
                // Safety, for dumping etc.
                $this->options['dsn'] = preg_replace(
                    '~dbname=[^;]+~', 'dbname=<****>', $this->options['dsn']
                );
                $this->options['user'] = '<****>';
                $this->options['pass'] = '<****>';
            }

            $charset && $this->setCharset($charset);
            $timezone && $this->setTimezone($timezone);
        }
    }

    /**
     * Disconnect and set pdo property to null.
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * Is connected.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->pdo != null;
    }

    /**
     * Set charset for current link.
     *
     * @param  string $charset
     * @return void
     */
    public function setCharset(string $charset): void
    {
        $this->pdo->exec('SET NAMES ' . $this->pdo->quote($charset));
    }

    /**
     * Set timezone for current link.
     *
     * @param  string $timezone
     * @return void
     */
    public function setTimezone(string $timezone): void
    {
        if ($this->pdoDriver == 'mysql') {
            $this->pdo->exec('SET time_zone = ' . $this->pdo->quote($timezone));
        } else {
            $this->pdo->exec('SET TIME ZONE ' . $this->pdo->quote($timezone));
        }
    }

    /**
     * Init a single link.
     *
     * @param  array $options
     * @return self
     */
    public static function init(array $options): self
    {
        return self::$instance ??= new self($options);
    }

    /**
     * Prepare options.
     *
     * @param  array $options
     * @return array
     * @throws froq\database\LinkException
     */
    private static function prepareOptions(array $options): array
    {
        if (empty($options['dsn'])) {
            throw new LinkException("Empty 'dsn' option given");
        }

        $dsn = trim((string) $options['dsn'], ';');
        if (preg_match('~^(\w+):~', $dsn, $match)) {
            $driver = $match[1];
        }

        if (empty($driver)) {
            // Throw a proper exeption instead of PDOException("could not find driver").
            throw new LinkException("Invalid scheme given in 'dsn' option, no driver specified");
        }

        if (empty($options['timezone'])) {
            // Timezone could already be given in "dsn" option (but not like ...;options=\'--timezone="+00:00"\').
            if (preg_match('~;timezone=([^;]+)~', $dsn, $match)) {
                $options['timezone'] = $match[1];
            }
        }

        return [
            'dsn'     => $dsn,                      'driver'   => $driver,
            'user'    => $options['user'] ?? '',    'pass'     => $options['pass'] ?? '',
            'charset' => $options['charset'] ?? '', 'timezone' => $options['timezone'] ?? '',
            'options' => $options['options'] ?? []
        ];
    }
}
