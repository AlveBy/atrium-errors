<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Support;

use Atrium\Errors\Models\Occurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Events counted per hour or per day, for the charts. Counted by the database
 * (one grouped query), every bucket present, empty ones as zero.
 */
final class Frequency
{
    /**
     * The last 24 hours, hour by hour.
     *
     * @return array<int,array{label:string,value:int}>
     */
    public function hourly(?int $issueId = null): array
    {
        $now = CarbonImmutable::now()->startOfHour();

        return $this->series($issueId, $now->subHours(23), 24, 'hour', 'H:00');
    }

    /**
     * The last 30 days, day by day.
     *
     * @return array<int,array{label:string,value:int}>
     */
    public function daily(?int $issueId = null): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        return $this->series($issueId, $today->subDays(29), 30, 'day', 'D MMM');
    }

    /**
     * @return array<int,array{label:string,value:int}>
     */
    private function series(?int $issueId, CarbonImmutable $from, int $buckets, string $unit, string $format): array
    {
        $expression = self::bucketExpression((new Occurrence)->getConnection()->getDriverName(), $unit);

        $counts = Occurrence::query()
            ->visible()
            ->when($issueId !== null, static fn (Builder $query) => $query->where('issue_id', $issueId))
            ->where('created_at', '>=', $from)
            ->selectRaw($expression.' as bucket, count(*) as total')
            ->groupByRaw($expression)
            ->pluck('total', 'bucket')
            ->all();

        $series = [];

        for ($i = 0; $i < $buckets; $i++) {
            $moment = $unit === 'hour' ? $from->addHours($i) : $from->addDays($i);
            $key = $moment->format($unit === 'hour' ? 'Y-m-d H' : 'Y-m-d');
            $series[] = ['label' => $moment->locale(app()->getLocale())->isoFormat($format), 'value' => (int) ($counts[$key] ?? 0)];
        }

        return $series;
    }

    /**
     * The SQL that turns created_at into "2026-11-17 14" or "2026-11-17".
     */
    public static function bucketExpression(string $driver, string $unit): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => $unit === 'hour' ? "DATE_FORMAT(created_at, '%Y-%m-%d %H')" : "DATE_FORMAT(created_at, '%Y-%m-%d')",
            'pgsql' => $unit === 'hour' ? "to_char(created_at, 'YYYY-MM-DD HH24')" : "to_char(created_at, 'YYYY-MM-DD')",
            default => $unit === 'hour' ? "strftime('%Y-%m-%d %H', created_at)" : "strftime('%Y-%m-%d', created_at)",
        };
    }
}
