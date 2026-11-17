<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Errors',
    'health' => 'Site health',
    'subtitle' => 'Errors of the site and its pages, one line per bug',

    'statuses' => [
        'new' => 'Unresolved',
        'resolved' => 'Resolved',
        'ignored' => 'Ignored',
        'all' => 'All',
    ],

    'levels' => [
        'fatal' => 'Fatal',
        'error' => 'Error',
        'warning' => 'Warning',
        'info' => 'Info',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Queue job',
        'schedule' => 'Scheduled task',
        'browser' => 'Browser',
        'message' => 'Message',
    ],

    'drivers' => [
        'database' => 'Site database (the admin screens)',
        'sentry' => 'Sentry or GlitchTip',
        'log' => 'Application log',
    ],

    'list' => [
        'search' => 'Search by class, message or place',
        'site' => 'Site',
        'all_sites' => 'All sites',
        'level' => 'Level',
        'any_level' => 'Any level',
        'kind' => 'Source',
        'any_kind' => 'Any source',
        'status' => 'Status',
        'release' => 'Release',
        'any_release' => 'Any release',
        'path' => 'Address',
        'path_placeholder' => '/catalog',
        'sort' => 'Sort',
        'sorts' => [
            'last_seen_at' => 'Last seen',
            'first_seen_at' => 'First seen',
            'times_seen' => 'Most events',
            'users_seen' => 'Most users',
        ],
        'filter' => 'Show',
        'reset' => 'Reset',
        'empty' => 'No errors here. Good news.',
        'issue' => 'Error',
        'events' => 'Events',
        'users' => 'Users',
        'last_seen' => 'Last seen',
        'first_seen' => 'First seen',
    ],

    'issue' => [
        'back' => 'All errors',
        'resolve' => 'Resolve',
        'resolve_next' => 'Resolve in next release',
        'resolve_next_hint' => 'It comes back only if it happens in a release after :release.',
        'ignore' => 'Ignore',
        'reopen' => 'Reopen',
        'trace' => 'Stack trace',
        'caused_by' => 'Caused by',
        'all_frames' => 'Show framework frames',
        'app_frames' => 'Hide framework frames',
        'request' => 'Request',
        'context' => 'Context',
        'breadcrumbs' => 'Before the error',
        'environment' => 'Environment',
        'occurrences' => 'Occurrences',
        'frequency' => 'Frequency',
        'last_24h' => '24 hours',
        'last_30d' => '30 days',
        'top_paths' => 'Addresses',
        'top_releases' => 'Releases',
        'first_release' => 'First release',
        'last_release' => 'Last release',
        'resolved_in' => 'Resolved in release :release',
        'regressed' => 'Came back',
        'no_event' => 'No events are kept for this error any more: they are older than the retention period.',
        'open' => 'Open',
        'shown' => 'Shown',
        'time' => 'Time',
        'user' => 'User',
        'method' => 'Method',
        'url' => 'Address',
        'route' => 'Route',
        'site' => 'Site',
        'locale' => 'Language',
        'release' => 'Release',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'The error is open again.',
        'resolved' => 'The error is resolved.',
        'ignored' => 'The error is ignored.',
    ],

    'gadget' => [
        'title' => 'Errors in 24 hours',
        'events' => 'events',
        'new' => 'new',
        'regressed' => 'came back',
        'unresolved' => 'unresolved',
        'none' => 'No errors in the last 24 hours.',
        'top' => 'Noisiest',
        'all' => 'All errors',
        'chart' => 'Events per hour',
    ],

    'permissions' => [
        'view' => 'View errors',
    ],

    'settings' => [
        'description' => 'Where errors are stored and sent, how many, and who is told. An empty field takes the value from config/atrium-errors.php or .env; keep secrets there.',
        'drivers' => 'Send errors to',
        'drivers_hint' => 'Several at once. The admin screens and alerts need the site database.',
        'sentry_dsn' => 'Sentry DSN',
        'sentry_dsn_hint' => 'From Sentry, GlitchTip or another Sentry-compatible service: https://key@host/project. Better in .env as ATRIUM_ERRORS_SENTRY_DSN.',
        'sample_rate' => 'Keep, %',
        'sample_rate_hint' => 'Share of events kept, 1 to 100.',
        'per_minute' => 'At most per minute',
        'per_minute_hint' => 'Events above this in one minute are dropped. 0: no limit.',
        'retention_days' => 'Keep events, days',
        'alert_email' => 'Alert emails',
        'alert_email_hint' => 'Addresses separated by commas.',
        'telegram_token' => 'Telegram bot token',
        'telegram_token_hint' => 'From @BotFather. Better in .env as ATRIUM_ERRORS_TELEGRAM_TOKEN.',
        'telegram_chat' => 'Telegram chat id',
        'alert_new' => 'Alert on a new error',
        'alert_regression' => 'Alert when a resolved error comes back',
        'spike_per_hour' => 'Spike: events of one error per hour',
        'spike_per_hour_hint' => 'More than this in an hour sends an alert. 0: off.',
        'browser_site' => 'Catch JavaScript errors on the site',
        'browser_admin' => 'Catch JavaScript errors in the admin',
    ],

    'alerts' => [
        'purpose' => 'Error alerts',
        'subject' => [
            'new' => 'New error on :site: :class',
            'regression' => 'An error came back on :site: :class',
            'spike' => 'Error spike on :site: :class',
        ],
        'where' => 'Where: :place',
        'counts' => 'Events: :events, users: :users',
        'release' => 'Release: :release',
    ],

    'prune' => [
        'invalid' => 'The number of days must be 1 or more.',
        'done' => 'Deleted :events events and :issues errors older than :days days.',
    ],

    'test' => [
        'no_drivers' => 'No error driver is switched on.',
        'message' => 'Atrium errors: a test error, safe to resolve',
        'skipped' => 'Not sent: tracking is off, or sampling or the per-minute cap dropped it.',
        'sent' => 'Test error :id sent to: :drivers.',
    ],
];
