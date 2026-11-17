<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Http\Controllers;

use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Errors\Capture\Release;
use Atrium\Errors\Event;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Support\Frequency;
use Atrium\Errors\Support\SiteAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin screens: the list of issues and an issue with its trace,
 * occurrences and frequency.
 */
final class IssueController
{
    public const SORTS = ['last_seen_at', 'first_seen_at', 'times_seen', 'users_seen'];

    public function index(Request $request, Sites $sites): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => in_array($request->query('status'), [...Issue::STATUSES, 'all'], true) ? $request->query('status') : Issue::NEW,
            'level' => in_array($request->query('level'), Event::LEVELS, true) ? $request->query('level') : '',
            'kind' => in_array($request->query('kind'), Event::KINDS, true) ? $request->query('kind') : '',
            'site' => ctype_digit((string) $request->query('site')) ? (int) $request->query('site') : null,
            'release' => mb_substr(trim((string) $request->query('release', '')), 0, 255),
            'path' => mb_substr(trim((string) $request->query('path', '')), 0, 512),
            'sort' => in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'last_seen_at',
        ];

        $issues = Issue::query()
            ->visible()
            ->when($filters['status'] !== 'all', static fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['level'] !== '', static fn (Builder $query) => $query->where('level', $filters['level']))
            ->when($filters['kind'] !== '', static fn (Builder $query) => $query->where('kind', $filters['kind']))
            ->when($filters['q'] !== '', static function (Builder $query) use ($filters): void {
                // `%` and `_` are searched as they are. SQLite has no escape
                // character by default, so one is named, the same on every database.
                // Lower case on both sides: PostgreSQL's LIKE minds the case.
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q'])).'%';
                $query->where(static function (Builder $inner) use ($like): void {
                    foreach (['class', 'message', 'culprit'] as $column) {
                        $inner->orWhereRaw('lower('.$inner->getQuery()->getGrammar()->wrap($column).") like ? escape '!'", [$like]);
                    }
                });
            })
            ->when($filters['site'] !== null, static fn (Builder $query) => $query->whereHas('occurrences', static fn (Builder $o) => $o->where('site_id', $filters['site'])))
            ->when($filters['release'] !== '', static fn (Builder $query) => $query->whereHas('occurrences', static fn (Builder $o) => $o->where('release', $filters['release'])))
            ->when($filters['path'] !== '', static fn (Builder $query) => $query->whereHas('occurrences', static fn (Builder $o) => $o->where('path', '/'.ltrim((string) strtok($filters['path'], '?'), '/'))))
            ->orderByDesc($filters['sort'])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Issue $issue): array => $this->row($issue));

        return Inertia::render('Admin/Errors/Index', [
            'issues' => $issues,
            'filters' => $filters,
            'options' => [
                'statuses' => Issue::STATUSES,
                'levels' => Event::LEVELS,
                'kinds' => Event::KINDS,
                'sites' => $this->sites($sites)->map(static fn ($site): array => ['id' => $site->id, 'name' => $site->name])->values(),
                'releases' => Issue::query()->visible()->whereNotNull('last_release')->orderByDesc('last_seen_at')->limit(200)->pluck('last_release')->unique()->take(30)->values(),
            ],
            'strings' => $this->strings(),
        ]);
    }

    public function show(Request $request, Issue $issue, Frequency $frequency, Release $release, Sites $sites): Response
    {
        $this->ensureVisible($issue);

        $event = $issue->occurrences()
            ->visible()
            ->when(ctype_digit((string) $request->query('event')), static fn (Builder $query) => $query->whereKey((int) $request->query('event')))
            ->latest('id')
            ->first() ?? $issue->occurrences()->visible()->latest('id')->first();

        $occurrences = $issue->occurrences()
            ->visible()
            ->latest('id')
            ->paginate(20, ['id', 'site_id', 'release', 'environment', 'method', 'path', 'user_id', 'created_at'])
            ->withQueryString();

        $siteNames = $sites->all()->mapWithKeys(static fn ($site): array => [$site->id => $site->name]);

        return Inertia::render('Admin/Errors/Show', [
            'issue' => $this->row($issue) + [
                'message' => $issue->message,
                'resolved_in' => $issue->resolved_in,
                'first_release' => $issue->first_release,
                'resolved_at' => $issue->resolved_at?->toIso8601String(),
                'regressed_at' => $issue->regressed_at?->toIso8601String(),
            ],
            // The payload first: its own `id` (the event's uuid) must not
            // replace the row's.
            'event' => $event === null ? null : [
                ...$event->payload,
                'uuid' => $event->payload['id'] ?? null,
                'id' => $event->id,
                'created_at' => $event->created_at?->toIso8601String(),
                'release' => $event->release,
                'environment' => $event->environment,
                'site' => $event->site_id !== null ? ($siteNames[$event->site_id] ?? '#'.$event->site_id) : null,
            ],
            'occurrences' => $occurrences->through(static fn (Occurrence $o): array => [
                'id' => $o->id,
                'created_at' => $o->created_at?->toIso8601String(),
                'release' => $o->release,
                'environment' => $o->environment,
                'method' => $o->method,
                'path' => $o->path,
                'site' => $o->site_id !== null ? ($siteNames[$o->site_id] ?? '#'.$o->site_id) : null,
                'user_id' => $o->user_id,
            ]),
            'top' => [
                'paths' => $this->top($issue, 'path'),
                'releases' => $this->top($issue, 'release'),
            ],
            'chart' => [
                'hourly' => $frequency->hourly($issue->id),
                'daily' => $frequency->daily($issue->id),
            ],
            'currentRelease' => $release->current(),
            'strings' => $this->strings(),
        ]);
    }

    public function status(Request $request, Issue $issue, Release $release): RedirectResponse
    {
        $this->ensureVisible($issue);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', Issue::STATUSES)],
            'next_release' => ['boolean'],
        ]);

        $issue->status = $data['status'];
        $issue->resolved_at = $data['status'] === Issue::RESOLVED ? now() : null;
        $issue->resolved_in = $data['status'] === Issue::RESOLVED && ($data['next_release'] ?? false) ? $release->current() : null;
        $issue->save();

        return back()->with('success', __('atrium-errors::errors.status_saved.'.$issue->status));
    }

    /**
     * @return array<string,mixed>
     */
    private function row(Issue $issue): array
    {
        return [
            'id' => $issue->id,
            'class' => $issue->class,
            'short_class' => class_basename($issue->class),
            'title' => mb_substr($issue->message, 0, 200),
            'culprit' => $issue->culprit,
            'status' => $issue->status,
            'level' => $issue->level,
            'kind' => $issue->kind,
            'times_seen' => $issue->times_seen,
            'users_seen' => $issue->users_seen,
            'first_seen_at' => $issue->first_seen_at?->toIso8601String(),
            'last_seen_at' => $issue->last_seen_at?->toIso8601String(),
            'last_release' => $issue->last_release,
            'url' => route('admin.errors.show', $issue->id),
        ];
    }

    /**
     * An issue of another site is not there for an administrator of this one.
     */
    private function ensureVisible(Issue $issue): void
    {
        abort_unless(Issue::query()->visible()->whereKey($issue->id)->exists(), 404);
    }

    /**
     * @return Collection<int,Site>
     */
    private function sites(Sites $sites): Collection
    {
        $ids = SiteAccess::ids();

        return $sites->all()->filter(static fn ($site): bool => $ids === null || in_array((int) $site->id, $ids, true))->values();
    }

    /**
     * @return array<int,array{value:string,count:int}>
     */
    private function top(Issue $issue, string $column): array
    {
        return $issue->occurrences()
            ->visible()
            ->whereNotNull($column)
            ->select($column.' as value')
            ->selectRaw('count(*) as total')
            ->groupBy($column)
            ->orderByDesc('total')
            ->orderBy($column)
            ->limit(5)
            ->get()
            ->map(static fn ($row): array => ['value' => $row->getAttribute('value'), 'count' => (int) $row->getAttribute('total')])
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function strings(): array
    {
        return (array) trans('atrium-errors::errors');
    }
}
