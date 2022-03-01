<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\common\trait\InstanceTrait;
use PDO, PDOException;

/**
 * Link.
 *
 * Represents a PDO wrapper with some util methods.
 *
 * @package froq\database
 * @object  froq\database\Link
 * @author  Kerem Güneş
 * @since   4.0
 */
final class Link
{
    /**
     * @see froq\common\trait\InstanceTrait
     * @since 5.0
     */
    use InstanceTrait;

    /** @var PDO|null */
    private PDO|null $pdo;

    /** @var string */
    private string $driver;

    /** @var array */
    private array $options = [
        'dsn'     => null, 'driver'   => null,
        'user'    => null, 'pass'     => null,
        'charset' => null, 'timezone' => null,
        'options' => null
    ];

    /**
     * Constructor.
     *
     * @param array $options
     */
    private function __construct(array $options)
    {
        $this->options = array_merge($this->options, self::prepareOptions($options));
    }

    /**
     * Hide all debug info.
     *
     * @return void
     */
    public function __debugInfo()
    {}

    /**
     * Get pdo property.
     *
     * @return PDO|null
     */
    public function pdo(): PDO|null
    {
        return $this->pdo ?? null;
    }

    /**
     * Get pdo driver property.
     *
     * @return string|null
     */
    public function driver(): string|null
    {
        return $this->driver ?? null;
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
     * Connect with given options, set timezone & charset if provided.
     *
     * @return void
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

        // For a proper return that gives '1' always even with identical values in UPDATE queries.
        if ($driver == 'mysql') {
            $options[PDO::MYSQL_ATTR_FOUND_ROWS] ??= true;
        }

        try {
            $this->pdo    = new PDO($dsn, $user, $pass, $options);
            $this->driver = $driver;
        } catch (PDOException $e) {
            $code         = $e->getCode();
            $message      = $e->getMessage();

            // Which driver the FUCK?
            if ($message == 'could not find driver') {
                throw new LinkException('Could not find driver `%s`', $driver, code: $code, cause: $e);
            }

            throw new LinkException($message, code: $code, cause: $e);
        }

        $charset  && $this->setCharset($charset);
        $timezone && $this->setTimezone($timezone);
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
     * Check connection state.
     *
     * @return bool
     * @since  5.0 Replaced with isConnected().
     */
    public function isAlive(): bool
    {
        return isset($this->pdo);
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
        $this->isAlive() || throw new LinkException('Link is gone');

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
        $this->isAlive() || throw new LinkException('Link is gone');

        if ($this->driver == 'mysql') {
            $this->pdo->exec('SET time_zone = ' . $this->pdo->quote($timezone));
        } else {
            $this->pdo->exec('SET TIME ZONE ' . $this->pdo->quote($timezone));
        }
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
            throw new LinkException('Empty `dsn` option given');
        }

        $dsn = trim((string) $options['dsn'], ';');
        if (preg_match('~^(\w+):~', $dsn, $match)) {
            $driver = $match[1];
        }

        // Throw a proper exeption instead of PDOException('could not find driver').
        if (empty($driver)) {
            throw new LinkException('Invalid scheme given in `dsn` option, no driver specified');
        }

        return ['dsn' => $dsn, 'driver' => $driver] + $options;
    }
}
