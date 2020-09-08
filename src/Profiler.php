<?php
/**
 * MIT License <https://opensource.org/licenses/mit>
 *
 * Copyright (c) 2015 Kerem Güneş
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\ProfilerException;

/**
 * Profiler.
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
