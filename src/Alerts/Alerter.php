<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Alerts;

use Atrium\Errors\Models\Issue;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Reporters\DatabaseReporter;
use Atrium\Errors\Support\Options;
use Illuminate\Contracts\Cache\Repository;

/**
 * Decides whether an event is worth a message: a new issue, a resolved one
 * that came back, or one that fires more than the spike threshold within an
 * hour. Ignored issues never alert.
 *
 * Throttled twice: each issue alerts once per reason (a spike once an hour),
 * and no more than `max_per_10_minutes` alerts go out in ten minutes in all,
 * so a broken deploy sends a handful of messages, not a thousand.
 */
final class Alerter
{
    public const NEW = 'new';

    public const REGRESSION = 'regression';

    public const SPIKE = 'spike';

    public function __construct(
        private readonly Options $options,
        private readonly Repository $cache,
        private readonly Channels $channels,
    ) {}

    public function consider(Issue $issue, string $outcome): ?string
    {
        if ($issue->status === Issue::IGNORED || ! $this->channels->any()) {
            return null;
        }

        $reason = $this->reason($issue, $outcome);

        if ($reason === null || ! $this->claim($issue, $reason)) {
            return null;
        }

        $this->channels->send(new Alert($issue, $reason));

        return $reason;
    }

    private function reason(Issue $issue, string $outcome): ?string
    {
        if ($outcome === DatabaseReporter::OUTCOME_NEW && $this->options->bool('alert_new')) {
            return self::NEW;
        }

        if ($outcome === DatabaseReporter::OUTCOME_REGRESSION && $this->options->bool('alert_regression')) {
            return self::REGRESSION;
        }

        $threshold = $this->options->int('spike_per_hour');

        if ($threshold > 0 && $issue->times_seen >= $threshold
            && Occurrence::query()->where('issue_id', $issue->id)->where('created_at', '>=', now()->subHour())->count() >= $threshold) {
            return self::SPIKE;
        }

        return null;
    }

    /**
     * Takes the right to send this alert, or says it was taken.
     */
    private function claim(Issue $issue, string $reason): bool
    {
        $key = match ($reason) {
            self::REGRESSION => 'atrium-errors:alert:regression:'.$issue->id.':'.$issue->regressed_at?->getTimestamp(),
            default => 'atrium-errors:alert:'.$reason.':'.$issue->id,
        };

        if (! $this->cache->add($key, true, $reason === self::SPIKE ? 3600 : 86400 * 30)) {
            return false;
        }

        $window = 'atrium-errors:alerts:'.intdiv(now()->getTimestamp(), 600);
        $this->cache->add($window, 0, 900);

        return $this->cache->increment($window) <= max(1, (int) config('atrium-errors.alerts.max_per_10_minutes', 10));
    }
}
