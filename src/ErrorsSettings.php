<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors;

use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\MultiSelectField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Modules\ModuleSettings;
use Atrium\Errors\Pipeline\Drivers;

/**
 * What an administrator can change, for every site at once. An empty field
 * means "as in config/atrium-errors.php and .env", which is where the
 * secrets are better kept.
 */
final class ErrorsSettings extends ModuleSettings
{
    public function description(): string
    {
        return __('atrium-errors::errors.settings.description');
    }

    public function fields(): array
    {
        $t = static fn (string $key): string => __('atrium-errors::errors.settings.'.$key);
        $drivers = array_keys(app(Drivers::class)->known());

        return [
            MultiSelectField::make('drivers')
                ->label($t('drivers'))
                ->hint($t('drivers_hint'))
                ->options(array_combine($drivers, array_map(static fn (string $name): string => trans()->has('atrium-errors::errors.drivers.'.$name) ? __('atrium-errors::errors.drivers.'.$name) : $name, $drivers)))
                ->rules(['nullable', 'array'])
                ->default(config('atrium-errors.drivers')),
            TextField::make('sentry_dsn')
                ->label($t('sentry_dsn'))
                ->hint($t('sentry_dsn_hint'))
                ->rules(['nullable', 'string', 'max:500', 'regex:#^https?://[^@\s]+@[^/\s]+/.*[A-Za-z0-9]$#']),
            NumberField::make('sample_rate')
                ->label($t('sample_rate'))
                ->hint($t('sample_rate_hint'))
                ->rules(['nullable', 'integer', 'min:1', 'max:100'])
                ->default(config('atrium-errors.sample_rate')),
            NumberField::make('per_minute')
                ->label($t('per_minute'))
                ->hint($t('per_minute_hint'))
                ->rules(['nullable', 'integer', 'min:0', 'max:100000'])
                ->default(config('atrium-errors.per_minute')),
            NumberField::make('retention_days')
                ->label($t('retention_days'))
                ->rules(['nullable', 'integer', 'min:1', 'max:3650'])
                ->default(config('atrium-errors.retention_days')),
            TextField::make('alert_email')
                ->label($t('alert_email'))
                ->hint($t('alert_email_hint'))
                ->rules(['nullable', 'string', 'max:1000']),
            TextField::make('telegram_token')
                ->label($t('telegram_token'))
                ->hint($t('telegram_token_hint'))
                ->rules(['nullable', 'string', 'max:200', 'regex:/^\d+:[A-Za-z0-9_-]+$/']),
            TextField::make('telegram_chat')
                ->label($t('telegram_chat'))
                ->rules(['nullable', 'string', 'max:100', 'regex:/^(-?\d+|@[A-Za-z0-9_]{5,})$/']),
            CheckboxField::make('alert_new')
                ->label($t('alert_new'))
                ->default((bool) config('atrium-errors.alerts.new')),
            CheckboxField::make('alert_regression')
                ->label($t('alert_regression'))
                ->default((bool) config('atrium-errors.alerts.regression')),
            NumberField::make('spike_per_hour')
                ->label($t('spike_per_hour'))
                ->hint($t('spike_per_hour_hint'))
                ->rules(['nullable', 'integer', 'min:0', 'max:1000000'])
                ->default(config('atrium-errors.alerts.spike_per_hour')),
            CheckboxField::make('browser_site')
                ->label($t('browser_site'))
                ->default((bool) config('atrium-errors.browser.site')),
            CheckboxField::make('browser_admin')
                ->label($t('browser_admin'))
                ->default((bool) config('atrium-errors.browser.admin')),
        ];
    }
}
