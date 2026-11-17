<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Dashboard;

use Atrium\Admin\Dashboard\Gadget;
use Atrium\Errors\Http\Middleware\AuthorizeErrors;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Support\Frequency;

/**
 * "Errors in 24 hours" on the dashboard: how many events, how many new and
 * returned issues, the noisiest ones and an hour-by-hour chart. Speed and
 * uptime are the monitoring module's tiles.
 */
final class ErrorsGadget extends Gadget
{
    public function component(): string
    {
        return 'ErrorsGadget';
    }

    public function title(): string
    {
        return (string) $this->option('title', __('atrium-errors::errors.gadget.title'));
    }

    public function width(): string
    {
        return $this->option('width', 'half') === 'full' ? 'full' : 'half';
    }

    public function isVisible(): bool
    {
        return parent::isVisible() && AuthorizeErrors::allows(auth((string) config('atrium.auth.guard', 'admin'))->user());
    }

    public function data(): array
    {
        $since = now()->subDay();

        $top = Occurrence::query()
            ->visible()
            ->where('created_at', '>=', $since)
            ->selectRaw('issue_id, count(*) as total')
            ->groupBy('issue_id')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'issue_id')
            ->all();

        $issues = Issue::query()->whereKey(array_keys($top))->get()->keyBy('id');

        return [
            'events' => Occurrence::query()->visible()->where('created_at', '>=', $since)->count(),
            'new' => Issue::query()->visible()->where('first_seen_at', '>=', $since)->count(),
            'regressed' => Issue::query()->visible()->where('regressed_at', '>=', $since)->count(),
            'unresolved' => Issue::query()->visible()->where('status', Issue::NEW)->count(),
            'top' => collect($top)
                ->filter(static fn (mixed $total, int $id): bool => $issues->has($id))
                ->map(static fn (mixed $total, int $id): array => [
                    'id' => $id,
                    'class' => class_basename((string) $issues[$id]->class),
                    'title' => mb_substr((string) $issues[$id]->message, 0, 120),
                    'count' => (int) $total,
                    'url' => route('admin.errors.show', $id),
                ])
                ->values()
                ->all(),
            'chart' => app(Frequency::class)->hourly(),
            'url' => route('admin.errors.index'),
            'strings' => (array) trans('atrium-errors::errors.gadget'),
        ];
    }
}
