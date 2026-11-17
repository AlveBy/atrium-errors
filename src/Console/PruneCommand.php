<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Console;

use Atrium\Errors\Models\Issue;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Support\Options;
use Illuminate\Console\Command;

/**
 * Deletes events older than the retention period (90 days by default) and
 * the issues left without any. Scheduled daily by the module; the counters
 * of the issues that stay are kept as they were — "seen 1 200 times" stays
 * true even when only the last 90 days can still be opened.
 */
final class PruneCommand extends Command
{
    protected $signature = 'atrium:errors:prune {--days= : Keep this many days instead of the setting}';

    protected $description = 'Delete error events older than the retention period';

    public function handle(Options $options): int
    {
        $days = (int) ($this->option('days') ?? $options->int('retention_days'));

        if ($days < 1) {
            $this->error(__('atrium-errors::errors.prune.invalid'));

            return self::FAILURE;
        }

        $before = now()->subDays($days);
        $events = 0;

        // In chunks: a year of a noisy bug is millions of rows.
        do {
            $ids = Occurrence::query()->where('created_at', '<', $before)->limit(1000)->pluck('id');
            $events += $ids->isEmpty() ? 0 : Occurrence::query()->whereKey($ids)->delete();
        } while ($ids->count() === 1000);

        $issues = Issue::query()
            ->where('last_seen_at', '<', $before)
            ->whereDoesntHave('occurrences')
            ->delete();

        $this->info(__('atrium-errors::errors.prune.done', ['events' => $events, 'issues' => $issues, 'days' => $days]));

        return self::SUCCESS;
    }
}
