<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Http\Middleware\AuthorizeErrors;
use Atrium\Errors\Tests\TestCase;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use RuntimeException;

/**
 * The module's routes follow the host's middleware, and none of it is there
 * while the module is not installed.
 */
final class WiringTest extends TestCase
{
    #[DefineEnvironment('twoMiddleware')]
    public function test_the_admin_routes_take_the_middleware_of_the_host_in_order(): void
    {
        $this->assertSame(['web', 'throttle:90,1', 'atrium.inertia', 'admin.auth', 'atrium.site', AuthorizeErrors::class], $this->adminRoute()->middleware());
    }

    #[DefineEnvironment('noMiddleware')]
    public function test_a_host_that_names_no_middleware_gets_the_web_group(): void
    {
        $this->assertSame(['web', 'atrium.inertia', 'admin.auth', 'atrium.site', AuthorizeErrors::class], $this->adminRoute()->middleware());
    }

    public function test_an_exception_whose_class_is_named_in_the_ignore_list_as_text_is_ignored(): void
    {
        config(['atrium-errors.ignore' => RuntimeException::class]);

        $this->assertNull($this->errors()->capture(new RuntimeException('Quiet')));
    }

    public function twoMiddleware(Application $app): void
    {
        $app['config']->set('atrium.middleware', ['web', 'throttle:90,1']);
    }

    public function noMiddleware(Application $app): void
    {
        $atrium = $app['config']->get('atrium');
        unset($atrium['middleware']);
        $app['config']->set('atrium', $atrium);
    }

    private function adminRoute(): \Illuminate\Routing\Route
    {
        $route = Route::getRoutes()->getByName('admin.errors.index');
        $this->assertNotNull($route);

        return $route;
    }
}
