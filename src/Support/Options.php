<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Support;

use Atrium\Admin\Modules\Settings;

/**
 * The module's settings: saved in the admin when they are, the config
 * (and .env) otherwise.
 *
 * Read while an error is being handled, possibly with the database gone: a
 * failure to read the saved values is not an error, the config answers.
 */
final class Options
{
    /** Setting name => config key under atrium-errors. */
    public const MAP = [
        'drivers' => 'drivers',
        'sentry_dsn' => 'sentry.dsn',
        'sample_rate' => 'sample_rate',
        'per_minute' => 'per_minute',
        'retention_days' => 'retention_days',
        'alert_email' => 'alerts.email',
        'telegram_token' => 'alerts.telegram_token',
        'telegram_chat' => 'alerts.telegram_chat',
        'alert_new' => 'alerts.new',
        'alert_regression' => 'alerts.regression',
        'spike_per_hour' => 'alerts.spike_per_hour',
        'browser_site' => 'browser.site',
        'browser_admin' => 'browser.admin',
    ];

    /** @var array<string,mixed>|null */
    private ?array $saved = null;

    public function get(string $name): mixed
    {
        $saved = $this->saved();

        if (array_key_exists($name, $saved) && $saved[$name] !== null && $saved[$name] !== '' && $saved[$name] !== []) {
            return $saved[$name];
        }

        return config('atrium-errors.'.(self::MAP[$name] ?? $name));
    }

    public function int(string $name): int
    {
        return (int) $this->get($name);
    }

    public function bool(string $name): bool
    {
        return filter_var($this->get($name), FILTER_VALIDATE_BOOLEAN);
    }

    public function string(string $name): string
    {
        $value = $this->get($name);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @return array<int,string>
     */
    public function drivers(): array
    {
        $drivers = $this->get('drivers');
        $drivers = is_string($drivers) ? explode(',', $drivers) : (array) $drivers;

        return array_values(array_unique(array_filter(array_map(static fn (mixed $name): string => trim((string) $name), $drivers))));
    }

    /**
     * Forget what was read: after the settings were saved, and between tests.
     */
    public function flush(): void
    {
        $this->saved = null;
    }

    /**
     * @return array<string,mixed>
     */
    private function saved(): array
    {
        if ($this->saved !== null) {
            return $this->saved;
        }

        // Settings answers [] by itself when the database is down or not migrated.
        return $this->saved = app(Settings::class)->stored('errors');
    }
}
