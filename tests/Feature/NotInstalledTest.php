<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Errors\Dashboard\ErrorsGadget;
use Atrium\Errors\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * The module is in the code base but not switched on for the site: it adds
 * nothing to the panel.
 */
final class NotInstalledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.modules', []);
    }

    public function test_a_module_that_is_not_installed_adds_nothing(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('admin.errors.index'));
        $this->assertNull(Route::getRoutes()->getByName('atrium.errors.browser'));
        $this->assertNull(RateLimiter::limiter('atrium-errors-browser'));
        $this->assertNotContains(ErrorsGadget::class, (array) config('atrium.dashboard.gadgets', []));
        $this->assertNull(config('atrium-mail.purposes.errors'));
        $this->assertSame([['label' => 'Home']], Hook::apply('atrium.admin.menu', [['label' => 'Home']]));
        $this->assertSame([], array_filter($this->app->make(Schedule::class)->events(), static fn ($event): bool => str_contains((string) $event->command, 'atrium:errors:prune')));
    }
}
