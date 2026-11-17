<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Throwable;

/**
 * Sampling and the hard cap per minute, decided before an event is even
 * built: a loop that throws ten thousand times a minute must cost ten
 * thousand cheap checks, not ten thousand inserts.
 *
 * The cap is counted in the cache, so all PHP processes of the site share
 * it. Without a working cache it falls back to a counter of this process.
 */
final class Limiter
{
    private int $localMinute = 0;

    private int $localCount = 0;

    /** @var Closure(): float */
    private Closure $random;

    public function __construct(
        private readonly Repository $cache,
        ?Closure $random = null,
    ) {
        $this->random = $random ?? static fn (): float => mt_rand() / mt_getrandmax();
    }

    /**
     * Whether this event may go on.
     */
    public function allow(): bool
    {
        $rate = $this->options()->int('sample_rate');

        if ($rate < 100 && ($this->random)() * 100 >= $rate) {
            return false;
        }

        $cap = $this->options()->int('per_minute');

        return $cap <= 0 || $this->count() <= $cap;
    }

    /**
     * Use a fixed dice in tests.
     *
     * @param  Closure(): float  $random
     */
    public function useRandom(Closure $random): void
    {
        $this->random = $random;
    }

    /**
     * Resolved each time: this object lives as long as the process, the
     * settings only as long as a request or a job.
     */
    private function options(): Options
    {
        return app(Options::class);
    }

    private function count(): int
    {
        $minute = intdiv(now()->getTimestamp(), 60);
        $key = 'atrium-errors:minute:'.$minute;

        try {
            $this->cache->add($key, 0, 120);

            return (int) $this->cache->increment($key);
        } catch (Throwable) {
            if ($this->localMinute !== $minute) {
                $this->localMinute = $minute;
                $this->localCount = 0;
            }

            return ++$this->localCount;
        }
    }
}
