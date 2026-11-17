<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Reporters;

use Atrium\Errors\Alerts\Alerter;
use Atrium\Errors\Contracts\ErrorReporter;
use Atrium\Errors\Event;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Models\Occurrence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The site's own database: the event under its issue, the issue's counters,
 * and the issue reopened when a resolved bug comes back.
 *
 * Then the alerts: a new issue, a regression or a spike.
 */
final class DatabaseReporter implements ErrorReporter
{
    public const OUTCOME_NEW = 'new';

    public const OUTCOME_REGRESSION = 'regression';

    public const OUTCOME_SEEN = 'seen';

    public function __construct(private readonly Alerter $alerter) {}

    public function report(Event $event): void
    {
        [$issue, $outcome] = $this->record($event);

        $this->alerter->consider($issue, $outcome);
    }

    /**
     * @return array{0:Issue,1:string}
     */
    public function record(Event $event): array
    {
        return DB::transaction(function () use ($event): array {
            $now = $event->timestamp;
            $issue = Issue::query()->where('fingerprint', $event->fingerprint)->lockForUpdate()->first();
            $outcome = self::OUTCOME_SEEN;

            if ($issue === null) {
                try {
                    // A savepoint: on PostgreSQL a failed insert spoils the
                    // whole transaction otherwise.
                    $issue = DB::transaction(fn (): Issue => Issue::query()->create([
                        'fingerprint' => $event->fingerprint,
                        'kind' => $event->kind,
                        'level' => $event->level,
                        'class' => mb_substr($event->class, 0, 255),
                        'message' => $event->message,
                        'culprit' => $this->cut($event->culprit()),
                        'first_release' => $event->release,
                        'first_seen_at' => $now,
                    ]));
                    $outcome = self::OUTCOME_NEW;
                } catch (UniqueConstraintViolationException) {
                    // Another process created it a moment ago.
                    $issue = Issue::query()->where('fingerprint', $event->fingerprint)->lockForUpdate()->firstOrFail();
                }
            }

            if ($issue->reopensIn($event->release)) {
                $issue->status = Issue::NEW;
                $issue->resolved_in = null;
                $issue->resolved_at = null;
                $issue->regressed_at = $now;
                $outcome = self::OUTCOME_REGRESSION;
            }

            $userId = $event->userId();
            $newUser = $userId !== null && ! Occurrence::query()->where('issue_id', $issue->id)->where('user_id', $userId)->exists();

            Occurrence::query()->create([
                'issue_id' => $issue->id,
                'site_id' => $event->request['site_id'] ?? null,
                'level' => $event->level,
                'release' => $event->release,
                'environment' => mb_substr($event->environment, 0, 32),
                'method' => isset($event->request['method']) ? mb_substr((string) $event->request['method'], 0, 8) : null,
                'path' => $event->request['path'] ?? null,
                'route' => $this->cut($event->request['route'] ?? null),
                'locale' => isset($event->request['locale']) ? mb_substr((string) $event->request['locale'], 0, 16) : null,
                'user_id' => $userId,
                'payload' => [
                    'id' => $event->id,
                    'kind' => $event->kind,
                    'exceptions' => $event->exceptions,
                    'request' => $event->request,
                    'context' => $event->context,
                    'breadcrumbs' => $event->breadcrumbs,
                    'runtime' => $event->runtime,
                ],
                'created_at' => $now,
            ]);

            $issue->times_seen++;
            $issue->users_seen += $newUser ? 1 : 0;
            $issue->level = $event->level;
            $issue->message = $event->message;
            $issue->last_release = $event->release;
            $issue->last_seen_at = $now;
            $issue->save();

            return [$issue, $outcome];
        });
    }

    private function cut(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, 255);
    }
}
