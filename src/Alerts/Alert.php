<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Alerts;

use Atrium\Errors\Models\Issue;
use Illuminate\Support\Facades\Route;

/**
 * What an alert says, the same in every channel: the reason, the error, how
 * often, the release and a link to the issue in the admin.
 */
final class Alert
{
    public function __construct(
        public readonly Issue $issue,
        public readonly string $reason,
    ) {}

    public function subject(): string
    {
        return __('atrium-errors::errors.alerts.subject.'.$this->reason, [
            'site' => config('app.name', 'Atrium'),
            'class' => class_basename($this->issue->class),
        ]);
    }

    /**
     * @return array<int,string>
     */
    public function lines(): array
    {
        $issue = $this->issue;

        return array_values(array_filter([
            $issue->class.': '.mb_substr($issue->message, 0, 300),
            $issue->culprit !== null ? __('atrium-errors::errors.alerts.where', ['place' => $issue->culprit]) : null,
            __('atrium-errors::errors.alerts.counts', ['events' => $issue->times_seen, 'users' => $issue->users_seen]),
            $issue->last_release !== null ? __('atrium-errors::errors.alerts.release', ['release' => $issue->last_release]) : null,
        ]));
    }

    public function url(): ?string
    {
        return Route::has('admin.errors.show') ? route('admin.errors.show', $this->issue->id) : null;
    }

    public function text(): string
    {
        return implode("\n", [$this->subject(), '', ...$this->lines(), ...($this->url() !== null ? ['', $this->url()] : [])]);
    }
}
