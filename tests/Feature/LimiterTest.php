<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Support\Limiter;
use Atrium\Errors\Tests\TestCase;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Sampling and the cap per minute, to the last digit of the settings.
 */
final class LimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Twelve seconds into a minute: not on a boundary.
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:07:12 UTC'));
    }

    #[DataProvider('shares')]
    public function test_the_share_of_events_kept_is_the_sample_rate(int $rate, float $dice, bool $kept): void
    {
        config(['atrium-errors.sample_rate' => $rate]);
        $limiter = new Limiter(Cache::store());
        $limiter->useRandom(static fn (): float => $dice);

        $this->assertSame($kept, $limiter->allow());
    }

    public function test_the_dice_given_to_the_constructor_is_the_one_rolled(): void
    {
        config(['atrium-errors.sample_rate' => 60]);
        $rolled = 0;
        $limiter = new Limiter(Cache::store(), static function () use (&$rolled): float {
            $rolled++;

            return 0.59;
        });

        $this->assertTrue($limiter->allow());
        $this->assertSame(1, $rolled);
    }

    /**
     * @return array<string,array{int,float,bool}>
     */
    public static function shares(): array
    {
        return [
            'everything at 100, the dice at its top' => [100, 1.0, true],
            'everything above 100' => [101, 1.0, true],
            'everything far above 100' => [250, 1.0, true],
            'nothing at 0' => [0, 0.0, false],
            'nothing at 0, whatever the dice' => [0, 0.5, false],
            'nothing below 0' => [-5, 0.0, false],
            'one percent, dice under it' => [1, 0.0099, true],
            'one percent, dice on it' => [1, 0.01, false],
            'ninety-nine, dice just under it' => [99, 0.9805, true],
            'ninety-nine, dice on it' => [99, 0.99, false],
            'ninety-nine, dice above it' => [99, 0.995, false],
            'half, dice under it' => [50, 0.499, true],
            'half, dice on it' => [50, 0.5, false],
        ];
    }

    public function test_a_dice_given_to_the_constructor_is_the_one_that_rolls(): void
    {
        config(['atrium-errors.sample_rate' => 50]);

        $this->assertTrue((new Limiter(Cache::store(), static fn (): float => 0.1))->allow());
        $this->assertFalse((new Limiter(Cache::store(), static fn (): float => 0.9))->allow());
    }

    public function test_without_a_dice_of_its_own_it_rolls_between_zero_and_one(): void
    {
        $limiter = new Limiter(Cache::store());
        mt_srand(2026);

        config(['atrium-errors.sample_rate' => 99]);
        $kept = 0;
        for ($i = 0; $i < 200; $i++) {
            $kept += $limiter->allow() ? 1 : 0;
        }
        // 99 of 100 stay: never all of them lost, never all kept by luck of a seed that is fixed.
        $this->assertGreaterThan(150, $kept);

        config(['atrium-errors.sample_rate' => 1]);
        $kept = 0;
        for ($i = 0; $i < 200; $i++) {
            $kept += $limiter->allow() ? 1 : 0;
        }
        $this->assertLessThan(50, $kept);
    }

    public function test_the_dice_is_not_rolled_when_everything_is_kept(): void
    {
        config(['atrium-errors.sample_rate' => 100]);
        $limiter = new Limiter(Cache::store());
        $limiter->useRandom(static fn (): float => throw new RuntimeException('Rolled'));

        $this->assertTrue($limiter->allow());
    }

    public function test_the_events_of_a_minute_are_counted_under_a_key_of_that_minute(): void
    {
        config(['atrium-errors.sample_rate' => 100, 'atrium-errors.per_minute' => 10]);
        $limiter = new Limiter(Cache::store());
        $minute = intdiv(CarbonImmutable::parse('2026-11-17 12:07:12 UTC')->getTimestamp(), 60);

        $limiter->allow();
        $limiter->allow();
        $limiter->allow();

        $this->assertSame(3, Cache::get('atrium-errors:minute:'.$minute));
        $this->assertNull(Cache::get('atrium-errors:minute:'.($minute + 1)));
        $this->assertNull(Cache::get('atrium-errors:minute:'.($minute - 1)));
    }

    public function test_the_minute_turns_exactly_on_the_sixtieth_second(): void
    {
        config(['atrium-errors.sample_rate' => 100, 'atrium-errors.per_minute' => 1]);
        $limiter = new Limiter(Cache::store());

        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:07:00 UTC'));
        $this->assertTrue($limiter->allow());
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:07:59 UTC'));
        $this->assertFalse($limiter->allow());
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:08:00 UTC'));
        $this->assertTrue($limiter->allow());
        $this->assertFalse($limiter->allow());
    }

    public function test_the_counter_of_a_minute_is_forgotten_after_two_minutes(): void
    {
        config(['atrium-errors.sample_rate' => 100, 'atrium-errors.per_minute' => 10]);
        $limiter = new Limiter(Cache::store());
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:07:00 UTC'));
        $key = 'atrium-errors:minute:'.intdiv(CarbonImmutable::parse('2026-11-17 12:07:00 UTC')->getTimestamp(), 60);

        $limiter->allow();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:08:59 UTC'));
        $this->assertSame(1, Cache::get($key));
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:09:00 UTC'));
        $this->assertNull(Cache::get($key));
    }

    #[DataProvider('caps')]
    public function test_the_cap_per_minute_lets_that_many_through(int $cap, int $sent, int $through): void
    {
        config(['atrium-errors.sample_rate' => 100, 'atrium-errors.per_minute' => $cap]);
        $limiter = new Limiter(Cache::store());

        $kept = 0;
        for ($i = 0; $i < $sent; $i++) {
            $kept += $limiter->allow() ? 1 : 0;
        }

        $this->assertSame($through, $kept);
    }

    /**
     * @return array<string,array{int,int,int}>
     */
    public static function caps(): array
    {
        return [
            'one' => [1, 4, 1],
            'two' => [2, 4, 2],
            'as many as sent' => [4, 4, 4],
            'no cap at zero' => [0, 5, 5],
            'no cap below zero' => [-3, 5, 5],
        ];
    }

    public function test_a_cache_that_answers_nothing_to_the_counter_does_not_stop_events(): void
    {
        config(['atrium-errors.sample_rate' => 100, 'atrium-errors.per_minute' => 5]);
        $cache = $this->createMock(Repository::class);
        $cache->method('add')->willReturn(false);
        $cache->method('increment')->willReturn(false);

        $this->assertTrue((new Limiter($cache))->allow());
    }

    public function test_without_a_cache_the_count_starts_again_with_every_minute(): void
    {
        config(['atrium-errors.sample_rate' => 100, 'atrium-errors.per_minute' => 2]);
        $broken = $this->createMock(Repository::class);
        $broken->method('add')->willThrowException(new RuntimeException('Redis is down'));
        $limiter = new Limiter($broken);

        $this->assertSame([true, true, false, false], [$limiter->allow(), $limiter->allow(), $limiter->allow(), $limiter->allow()]);

        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:08:01 UTC'));
        $this->assertSame([true, true, false], [$limiter->allow(), $limiter->allow(), $limiter->allow()]);
    }
}
