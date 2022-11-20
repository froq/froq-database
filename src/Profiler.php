<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\util\{Timer, storage\Storage};
use PDOStatement;

/**
 * A profiling class for database connection & queries.
 *
 * @package froq\database
 * @object  froq\database\Profiler
 * @author  Kerem Güneş
 * @since   4.0
 */
final class Profiler
{
    /**
     * Profiles.
     *
     * @var froq\util\Storage
     */
    private Storage $profiles;

    /**
     * Query count.
     *
     * @var int
     */
    private int $queryCount = 0;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->profiles = new Storage();
    }

    /**
     * Hide all debug info.
     */
    public function __debugInfo()
    {}

    /**
     * Get profiles.
     *
     * @return array
     */
    public function profiles(): array
    {
        return $this->profiles->toArray();
    }

    /**
     * Get connection profile.
     *
     * @return array|null
     */
    public function connectionProfile(): array|null
    {
        return $this->profiles->connection;
    }

    /**
     * Get query profile.
     *
     * @return array|null
     */
    public function queryProfile(): array|null
    {
        return $this->profiles->query;
    }

    /**
     * Get query count.
     *
     * @return int
     */
    public function queryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * Profile.
     *
     * @param  string   $type
     * @param  callable $call
     * @return PDOStatement|int|null
     */
    public function profile(string $type, callable $call): PDOStatement|int|null
    {
        $this->start($type); $ret = $call(); $this->end($type);

        return $ret;
    }

    /**
     * Profile a connection.
     *
     * @param  callable $call
     * @return void
     */
    public function profileConnection(callable $call): void
    {
        $this->profile('connection', $call);
    }

    /**
     * Profile a query.
     *
     * @param  string   $query
     * @param  callable $call
     * @return PDOStatement|int|null
     */
    public function profileQuery(string $query, callable $call): PDOStatement|int|null
    {
        $this->profiles->query[++$this->queryCount]['string'] = $query;

        return $this->profile('query', $call);
    }

    /**
     * Get last query.
     *
     * @return float|string|array|null
     */
    public function lastQuery(string $key = null): float|string|array|null
    {
        return $key ? $this->profiles->query[$this->queryCount][$key] ?? null
                    : $this->profiles->query[$this->queryCount]       ?? null;
    }

    /**
     * Get last query time.
     *
     * @return float|null
     */
    public function lastQueryTime(): float|null
    {
        return $this->lastQuery('time');
    }

    /**
     * Get last query string.
     *
     * @return string|null
     */
    public function lastQueryString(): string|null
    {
        return $this->lastQuery('string');
    }

    /**
     * Get total time of existing profiles.
     *
     * @param  bool $timeOnly
     * @return float|string|null
     */
    public function totalTime(bool $timeOnly = true): float|string|null
    {
        if (!$this->profiles->count()) {
            return null;
        }

        $totalTime  = 0.0;
        $totalTimes = [];

        if ($this->profiles->connection) {
            $totalTime += $this->profiles->connection['time'];
            if (!$timeOnly) {
                $totalTimes[] = 'connection('. $totalTime .')';
            }
        }

        if ($this->profiles->query) {
            foreach ($this->profiles->query as $i => $profile) {
                $totalTime += $profile['time'];
                if (!$timeOnly) {
                    $totalTimes[] = 'query('. $i .': '. $profile['time'] .')';
                }
            }
        }

        if (!$timeOnly) {
            $totalTimes[] = 'total('. $totalTime .')';
        }

        return $timeOnly ? $totalTime : join(' ', $totalTimes);
    }

    /**
     * Create a collusion-free marker.
     *
     * @param  string $id
     * @return string
     * @since  5.0
     */
    public static function marker(string $id): string
    {
        return sprintf('%s-%s', $id, uuid());
    }

    /**
     * Mark a profile entry starting its timer.
     *
     * @param  string $id
     * @return void
     * @since  5.0
     */
    public static function mark(string $id): void
    {
        Storage::store($id, new Timer());
    }

    /**
     * Unmark a profile entry returning its elapsed time.
     *
     * @param  string $id
     * @return float|null
     * @since  5.0
     */
    public static function unmark(string $id): float|null
    {
        return Storage::unstore($id)?->getTime();
    }

    /**
     * Start a profile entry for a connection or query.
     */
    private function start(string $type): void
    {
        switch ($type) {
            case 'connection':
                $this->profiles->connection['timer'] = new Timer();
                break;
            case 'query':
                $this->profiles->query[$this->queryCount]['timer'] = new Timer();
                break;
            default:
                throw new ProfilerException(
                    'Invalid type `%s` [valids: connection, query]',
                    $type
                );
        }
    }

    /**
     * End a profile entry for a connection or query.
     */
    private function end(string $type): void
    {
        switch ($type) {
            case 'connection':
                $timer = $this->profiles->connection['timer'];
                $this->profiles->connection = $timer->stop()->toArray();

                unset($this->profiles->connection['timer']);
                break;
            case 'query':
                $timer = $this->profiles->query[$this->queryCount]['timer'];
                $this->profiles->query[$this->queryCount] += $timer->stop()->toArray();

                unset($this->profiles->query[$this->queryCount]['timer']);
                break;
            default:
                throw new ProfilerException(
                    'Invalid type `%s` [valids: connection, query]',
                    $type
                );
        }
    }
}
