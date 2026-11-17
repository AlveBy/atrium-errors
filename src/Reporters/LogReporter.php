<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Reporters;

use Atrium\Errors\Contracts\ErrorReporter;
use Atrium\Errors\Event;
use Illuminate\Log\LogManager;

/**
 * One line per event in the application log, scrubbed like everything else:
 * for a site that reads its logs elsewhere (Papertrail, Loki, a terminal).
 */
final class LogReporter implements ErrorReporter
{
    public function __construct(private readonly LogManager $log) {}

    public function report(Event $event): void
    {
        $level = match ($event->level) {
            'fatal' => 'critical',
            'warning' => 'warning',
            'info' => 'info',
            default => 'error',
        };

        $this->log->channel((string) config('logging.default'))->log($level, '[atrium-errors] '.$event->class.': '.$event->message, [
            'fingerprint' => $event->fingerprint,
            'kind' => $event->kind,
            'release' => $event->release,
            'culprit' => $event->culprit(),
            'path' => $event->request['path'] ?? null,
            'user_id' => $event->userId(),
        ]);
    }
}
