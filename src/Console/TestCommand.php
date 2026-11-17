<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Console;

use Atrium\Errors\Errors;
use Atrium\Errors\Pipeline\Drivers;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Sends a test error through every driver that is on: the way to check a
 * Sentry DSN or the alert channels after setting them.
 */
final class TestCommand extends Command
{
    protected $signature = 'atrium:errors:test';

    protected $description = 'Send a test error to every error driver that is switched on';

    public function handle(Errors $errors, Drivers $drivers): int
    {
        $active = array_keys($drivers->active());

        if ($active === []) {
            $this->warn(__('atrium-errors::errors.test.no_drivers'));

            return self::FAILURE;
        }

        $event = $errors->capture(new RuntimeException(__('atrium-errors::errors.test.message')), 'php', ['test' => true]);

        if ($event === null) {
            $this->warn(__('atrium-errors::errors.test.skipped'));

            return self::FAILURE;
        }

        $this->info(__('atrium-errors::errors.test.sent', ['drivers' => implode(', ', $active), 'id' => $event->id]));

        return self::SUCCESS;
    }
}
