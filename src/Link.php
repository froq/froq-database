<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\common\trait\FactoryTrait;
use PDO, PDOException;

/**
 * A wrapper class for PDO with some utilities.
 *
 * @package froq\database
 * @object  froq\database\Link
 * @author  Kerem Güneş
 * @since   4.0
 */
final class Link
{
    use FactoryTrait;

    /** @var ?PDO */
    private ?PDO $pdo = null;

    /** @var ?string */
    private ?string $driver = null;

    /** @var array */
    private array $options;

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->options = self::prepareOptions($options);
    }

    /**
     * Hide all debug info.
     */
    public function __debugInfo()
    {}

    /**
     * Return valid properties.
     */
    public function __sleep()
    {
        return ['options'];
    }

    /**
     * Re-connect.
     */
    public function __wakeup()
    {
        $this->connect();
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
    public function driver(): ?string
    {
        return $this->driver;
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
     * Connect with given options, set charset & timezone if provided.
     *
     * @return void
     * @throws froq\database\LinkException
     */
    public function connect(): void
    {
        if ($this->isAlive()) {
            return;
        }

        ['dsn'     => $dsn,     'driver'   => $driver,
         'user'    => $user,    'pass'     => $pass,
         'charset' => $charset, 'timezone' => $timezone,
         'options' => $options] = $this->options;

        $options[PDO::ATTR_ERRMODE]              = PDO::ERRMODE_EXCEPTION;
        $options[PDO::ATTR_EMULATE_PREPARES]   ??= true;
        $options[PDO::ATTR_DEFAULT_FETCH_MODE] ??= PDO::FETCH_ASSOC;

        if ($driver == 'mysql') {
            // For a proper return that gives '1' always even with identical values in UPDATE queries.
            $options[PDO::MYSQL_ATTR_FOUND_ROWS] ??= true;
            // For a proper memory usage.
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] ??= false;
        }

        try {
            $this->pdo    = new PDO($dsn, $user, $pass, $options);
            $this->driver = $driver;
        } catch (PDOException $e) {
            $code         = $e->getCode();
            $message      = $e->getMessage();

            // Which driver the FUCK?
            if ($message == 'could not find driver') {
                $message = sprintf('Could not find driver `%s`', $driver);
            }

            throw new LinkException($message, code: $code, cause: $e);
        }

        $charset  && $this->setCharset($charset);
        $timezone && $this->setTimezone($timezone);
    }

    /**
     * Disconnect and set pdo property as null.
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * Check connection state.
     *
     * @return bool
     * @since  5.0
     */
    public function isAlive(): bool
    {
        return $this->pdo != null;
    }

    /**
     * Set charset for current link.
     *
     * @param  string $charset
     * @return void
     * @throws froq\database\LinkException
     */
    public function setCharset(string $charset): void
    {
        $this->isAlive() || throw new LinkException('Link is dead');

        $this->pdo->exec('SET NAMES ' . $this->pdo->quote($charset));
    }

    /**
     * Set timezone for current link.
     *
     * @param  string $timezone
     * @return void
     * @throws froq\database\LinkException
     */
    public function setTimezone(string $timezone): void
    {
        $this->isAlive() || throw new LinkException('Link is dead');

        if ($this->driver == 'mysql') {
            $this->pdo->exec('SET time_zone = ' . $this->pdo->quote($timezone));
        } else {
            $this->pdo->exec('SET TIME ZONE ' . $this->pdo->quote($timezone));
        }
    }

    /**
     * Prepare options.
     *
     * @throws froq\database\LinkException
     */
    private static function prepareOptions(array $options): array
    {
        static $optionsDefault = [
            'dsn'     => null, 'driver'   => null,
            'user'    => null, 'pass'     => null,
            'charset' => null, 'timezone' => null,
            'options' => null
        ];

        if (empty($options['dsn'])) {
            throw new LinkException('Empty `dsn` option given');
        }

        // Drop (in case).
        $options['driver'] = null;

        $dsn = trim((string) $options['dsn'], ';');
        if (preg_match('~^(\w+):~', $dsn, $match)) {
            $options['driver'] = $match[1];
        }

        // Throw a proper exeption instead of PDOException('could not find driver').
        if (empty($options['driver'])) {
            throw new LinkException('Invalid scheme given in `dsn` option, no driver specified');
        }

        return [...$optionsDefault, ...$options];
    }
}
