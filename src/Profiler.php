<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use PDOStatement;

/**
 * Profiler.
 *
 * @package froq\database
 * @object  froq\database\Profiler
 * @author  Kerem Güneş
 * @since   4.0
 */
final class Profiler
{
    /** @var array */
    private array $profiles = [];

    /** @var int */
    private int $queryCount = 0;

    /** @var array */
    private static array $marks;

    /**
     * Constructor.
     */
    public function __construct()
    {
        self::$marks = [];
    }

    /**
     * Hide all debug info.
     *
     * @return void
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
        return $this->profiles;
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
     * Get marks.
     *
     * @return array
     */
    public static function marks(): array
    {
        return self::$marks;
    }

    /**
     * Profile.
     *
     * @param  string   $mark
     * @param  callable $call
     * @param  ...      $callArgs
     * @return PDOStatement|int|null
     */
    public function profile(string $mark, callable $call, ...$callArgs): PDOStatement|int|null
    {
        $this->start($mark);
        $ret = $call(...$callArgs);
        $this->end($mark);

        return $ret;
    }

    /**
     * Profile a connection.
     *
     * @param  callable $call
     * @param  ...      $callArgs
     * @return void
     */
    public function profileConnection(callable $call, ...$callArgs): void
    {
        $this->profile('connection', $call, ...$callArgs);
    }

    /**
     * Profile a query.
     *
     * @param  string   $query
     * @param  callable $call
     * @param  ...      $callArgs
     * @return PDOStatement|int|null
     */
    public function profileQuery(string $query, callable $call, ...$callArgs): PDOStatement|int|null
    {
        $this->profiles['query'][++$this->queryCount]['string'] = $query;

        return $this->profile('query', $call, ...$callArgs);
    }

    /**
     * Get last query.
     *
     * @return float|string|array|null
     */
    public function lastQuery(string $key = null): float|string|array|null
    {
        return $key ? $this->profiles['query'][$this->queryCount][$key] ?? null
             : $this->profiles['query'][$this->queryCount] ?? null;
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
        if (empty($this->profiles)) {
            return null;
        }

        $totalTime  = 0.0;
        $totalTimes = [];

        if (isset($this->profiles['connection'])) {
            $totalTime += $this->profiles['connection']['time'];
            if (!$timeOnly) {
                $totalTimes[] = 'connection('. $totalTime .')';
            }
        }

        if (isset($this->profiles['query'])) {
            foreach ($this->profiles['query'] as $i => $profile) {
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
     * Create a marker (to prevent name collusion).
     *
     * @param  string $name
     * @return string
     * @since  5.0
     */
    public static function marker(string $name): string
    {
        return sprintf('%s-%.10F', $name, microtime(true));
    }

    /**
     * Mark a profile entry returning its started time.
     *
     * @param  string $name
     * @return float
     * @throws froq\database\ProfilerException
     * @since  5.0
     */
    public static function mark(string $name): float
    {
        if (isset(self::$marks[$name])) {
            throw new ProfilerException(
                'Existing mark name `%s` given, call unmark() to drop it',
                $name
            );
        }

        return self::$marks[$name] = microtime(true);
    }

    /**
     * Unmark a profile entry returning its elapsed time.
     *
     * @param  string $name
     * @return float
     * @throws froq\database\ProfilerException
     * @since  5.0
     */
    public static function unmark(string $name): float
    {
        if (!isset(self::$marks[$name])) {
            throw new ProfilerException(
                'Could not find a mark with given name `%s`',
                $name
            );
        }

        $time = round(microtime(true) - self::$marks[$name], 10);
        unset(self::$marks[$name]);

        return $time;
    }

    /**
     * Start a profile entry for a connection or query.
     *
     * @param  string $mark
     * @return void
     * @throws froq\database\ProfilerException
     */
    private function start(string $mark): void
    {
        $start = microtime(true);
        switch ($mark) {
            case 'connection':
                $this->profiles[$mark] = [
                    'start' => $start, 'end' => 0.0, 'time' => 0.0
                ];
                break;
            case 'query':
                $i = $this->queryCount;
                if (isset($this->profiles[$mark][$i])) {
                    $this->profiles[$mark][$i] += [
                        'start' => $start, 'end' => 0.0, 'time' => 0.0
                    ];
                }
                break;
            default:
                throw new ProfilerException(
                    'Invalid mark `%s`, valids are: connection, query',
                    $mark
                );
        }
    }

    /**
     * End a profile entry for a connection or query.
     *
     * @param  string $mark
     * @return void
     * @throws froq\database\ProfilerException
     */
    private function end(string $mark): void
    {
        if (!isset($this->profiles[$mark])) {
            throw new ProfilerException(
                'Could not find a profile with given `%s` mark',
                $mark
            );
        }

        $end = microtime(true);

        switch ($mark) {
            case 'connection':
                $time = round($end - $this->profiles[$mark]['start'], 10);

                $this->profiles[$mark]['end']  = $end;
                $this->profiles[$mark]['time'] = $time;
                break;
            case 'query':
                $i = $this->queryCount;
                if (isset($this->profiles[$mark][$i])) {
                    $time = round($end - $this->profiles[$mark][$i]['start'], 10);

                    $this->profiles[$mark][$i]['end']  = $end;
                    $this->profiles[$mark][$i]['time'] = $time;
                }
                break;
            default:
                throw new ProfilerException(
                    'Invalid mark `%s`, valids are: connection, query',
                    $mark
                );
        }
    }
}
