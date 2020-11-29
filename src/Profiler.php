<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\ProfilerException;

/**
 * Profiler.
 *
 * @package froq\database
 * @object  froq\database\Profiler
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0
 */
final class Profiler
{
    /**
     * Profiles.
     * @var array
     */
    private array $profiles = [];

    /**
     * Query count.
     * @var int
     */
    private int $queryCount = 0;

    /**
     * Constructor.
     */
    public function __construct()
    {}

    /**
     * Get profiles.
     * @return array
     */
    public function getProfiles(): array
    {
        return $this->profiles;
    }

    /**
     * Get query count.
     * @return int
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * Profile.
     * @param  string   $type
     * @param  callable $call
     * @param  ...      $callArgs
     * @return PDOStatement|int
     */
    public function profile(string $type, callable $call, ...$callArgs)
    {
        $this->start($type);
        $ret = $call(...$callArgs);
        $this->end($type);

        return $ret;
    }

    /**
     * Profile connection.
     * @param  callable $call
     * @param  ...      $callArgs
     * @return void
     */
    public function profileConnection(callable $call, ...$callArgs): void
    {
        $this->profile('connection', $call, ...$callArgs);
    }

    /**
     * Profile query.
     * @param  string   $query
     * @param  callable $call
     * @param  ...      $callArgs
     * @return PDOStatement|int
     */
    public function profileQuery(string $query, callable $call, ...$callArgs)
    {
        $this->profiles['query'][++$this->queryCount]['string'] = $query;

        return $this->profile('query', $call, ...$callArgs);
    }

    /**
     * Get last query.
     * @return ?float|?string|?array
     */
    public function getLastQuery(string $key = null)
    {
        return $key ? $this->profiles['query'][$this->queryCount][$key] ?? null
            : $this->profiles['query'][$this->queryCount] ?? null;
    }

    /**
     * Get last query time.
     * @return ?float
     */
    public function getLastQueryTime(): ?float
    {
        return $this->getLastQuery('time');
    }

    /**
     * Get last query string.
     * @return ?string
     */
    public function getLastQueryString(): ?string
    {
        return $this->getLastQuery('string');
    }

    /**
     * Get total time.
     * @param  bool $timeOnly
     * @return float|string
     */
    public function getTotalTime(bool $timeOnly = true)
    {
        if (!$this->profiles) return;

        $totalTime = 0.00;
        $totalTimeString = '';
        if (isset($this->profiles['connection'])) {
            $totalTime += $this->profiles['connection']['time'];
            if (!$timeOnly) {
                $totalTimeString .= 'connection('. $totalTime .')';
            }
        }

        if (isset($this->profiles['query'])) {
            foreach ($this->profiles['query'] as $i => $profile) {
                $totalTime += $profile['time'];
                if (!$timeOnly) {
                    $totalTimeString .= ' query('. $i .', '. $profile['time'] .')';
                }
            }
        }

        if (!$timeOnly) {
            $totalTimeString .= ' total('. $totalTime .')';
        }

        return $timeOnly ? $totalTime : $totalTimeString;
    }

    /**
     * Start.
     * @param  string $type
     * @return void
     * @throws froq\database\ProfilerException
     */
    private function start(string $type): void
    {
        $start = microtime(true);
        switch ($type) {
            case 'connection':
                $this->profiles[$type] = ['start' => $start, 'end' => 0.00, 'time' => 0.00];
                break;
            case 'query':
                $i = $this->queryCount;
                if (isset($this->profiles[$type][$i])) {
                    $this->profiles[$type][$i] += ['start' => $start, 'end' => 0.00, 'time' => 0.00];
                }
                break;
            default:
                throw new ProfilerException('Invalid type "%s" given, valids are: connection, query', [$type]);
        }
    }

    /**
     * End.
     * @param  string $type
     * @return void
     * @throws froq\database\ProfilerException
     */
    private function end(string $type): void
    {
        if (!isset($this->profiles[$type])) {
            throw new ProfilerException('Could not find a profile with given "%s" type', [$type]);
        }

        $end = microtime(true);
        switch ($type) {
            case 'connection':
                $this->profiles[$type]['end'] = $end;
                $this->profiles[$type]['time'] = round($end - $this->profiles[$type]['start'], 10);
                break;
            case 'query':
                $i = $this->queryCount;
                if (isset($this->profiles[$type][$i])) {
                    $this->profiles[$type][$i]['end'] = $end;
                    $this->profiles[$type][$i]['time'] = round($end - $this->profiles[$type][$i]['start'], 10);
                }
                break;
            default:
                throw new ProfilerException('Invalid type "%s" given, valids are: connection, query', [$type]);
        }
    }
}
