<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Error tracking
|--------------------------------------------------------------------------
|
| Defaults for alveby/atrium-errors. Everything an administrator may want to
| change is also in the admin (Modules → Errors → Settings); a value saved
| there wins over the one here. Secrets (the Sentry DSN, the Telegram bot
| token) are best kept in .env and never committed.
|
*/

return [
    // Master switch. Off: nothing is captured, the admin screens stay.
    'enabled' => (bool) env('ATRIUM_ERRORS_ENABLED', true),

    /*
    | Where an event goes, several at once: database (the admin screens),
    | sentry (Sentry, GlitchTip or anything that speaks the Sentry protocol),
    | log (a line in the application log). Your own drivers are added in
    | `reporters` below and named here.
    */
    'drivers' => array_values(array_filter(explode(',', (string) env('ATRIUM_ERRORS_DRIVERS', 'database')))),

    // name => class implementing Atrium\Errors\Contracts\ErrorReporter.
    'reporters' => [
        // 'bugsnag' => App\Errors\BugsnagReporter::class,
    ],

    'sentry' => [
        'dsn' => env('ATRIUM_ERRORS_SENTRY_DSN', env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN'))),
        'timeout' => 3,
    ],

    // The release events are grouped by. Empty: the git commit of the
    // checkout, else the version Composer gives the application.
    'release' => env('ATRIUM_RELEASE'),

    // Percentage of events kept (1–100) and the hard cap per minute; what is
    // above is dropped before any work is done.
    'sample_rate' => (int) env('ATRIUM_ERRORS_SAMPLE_RATE', 100),
    'per_minute' => (int) env('ATRIUM_ERRORS_PER_MINUTE', 60),

    // A queue connection to store events through. Empty: they are kept in
    // memory and written after the response has gone to the visitor.
    'queue' => env('ATRIUM_ERRORS_QUEUE'),

    // Days an event is kept; issues without events go with them.
    'retention_days' => (int) env('ATRIUM_ERRORS_RETENTION_DAYS', 90),

    // When the database cannot be written to, the event goes here.
    'fallback_log' => storage_path('logs/atrium-errors.log'),

    // Recent queries and log lines kept with an event.
    'breadcrumbs' => 30,

    // Lines of code shown around each line of the trace.
    'context_lines' => 5,

    /*
    | Exceptions never captured. The framework's own "don't report" list
    | (validation, 404, authentication…) is respected anyway.
    */
    'ignore' => [
        // App\Exceptions\HarmlessException::class,
    ],

    /*
    | Extra key names whose values are never stored, on top of passwords,
    | tokens, cookies, Authorization, cards, emails and phones.
    */
    'scrub_keys' => [],

    'alerts' => [
        'email' => env('ATRIUM_ERRORS_ALERT_EMAIL'),
        'telegram_token' => env('ATRIUM_ERRORS_TELEGRAM_TOKEN'),
        'telegram_chat' => env('ATRIUM_ERRORS_TELEGRAM_CHAT'),
        'new' => true,
        'regression' => true,
        // An issue that gets this many events within an hour is a spike;
        // 0 turns spike alerts off.
        'spike_per_hour' => 100,
        // At most this many alerts in ten minutes, whatever happens.
        'max_per_10_minutes' => 10,
    ],

    'browser' => [
        // Catch JavaScript errors on the public site and in the admin.
        'site' => (bool) env('ATRIUM_ERRORS_BROWSER_SITE', false),
        'admin' => (bool) env('ATRIUM_ERRORS_BROWSER_ADMIN', false),
        // Reports a visitor may send per minute.
        'per_minute' => 10,
        // Largest accepted report, in bytes.
        'max_bytes' => 16384,
    ],
];
