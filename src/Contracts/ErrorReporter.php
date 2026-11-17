<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Contracts;

use Atrium\Errors\Event;

/**
 * A place errors are sent to: the site's database, Sentry, a log — or
 * Bugsnag, Rollbar, Honeybadger, your own webhook.
 *
 * Register the class under a name in config/atrium-errors.php
 * (`reporters` => ['bugsnag' => BugsnagReporter::class]) and add the name to
 * the drivers in the module's settings. It is resolved from the container,
 * so the constructor may ask for anything bound there.
 *
 * The event arrives already scrubbed of secrets and personal data, after
 * sampling and the per-minute cap. `report()` runs after the response has
 * gone (or in a queue job), but it should still be quick and must not rely
 * on the request. It may throw: the error is caught, written to the
 * fallback log and the other drivers still get the event.
 */
interface ErrorReporter
{
    public function report(Event $event): void;
}
