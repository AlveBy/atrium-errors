<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests;

use Atrium\Admin\AtriumServiceProvider;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Settings;
use Atrium\Errors\Errors;
use Atrium\Errors\ErrorsServiceProvider;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Support\Options;
use Atrium\Theme\ThemeServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Tighten\Ziggy\ZiggyServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /** Where the tracker writes when it cannot write to the database. */
    protected string $fallback;

    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            ZiggyServiceProvider::class,
            AtriumServiceProvider::class,
            ThemeServiceProvider::class,
            ErrorsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $this->fallback = sys_get_temp_dir().'/atrium-errors-'.getmypid().'-'.bin2hex(random_bytes(4)).'.log';

        $config = $app['config'];
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $config->set('app.name', 'Northwind Travel');
        $target = env('DB_CONNECTION');
        $config->set('database.default', in_array($target, ['mysql', 'pgsql'], true) ? $target : 'testing');
        $config->set('cache.default', 'array');
        $config->set('mail.default', 'array');
        $config->set('atrium.demo', false);
        $config->set('atrium.modules', ['alveby/atrium-errors']);
        $config->set('atrium.auth.two_factor.enabled', false);
        $config->set('atrium.dashboard.timings.enabled', false);
        $config->set('atrium-errors.release', '2.4.0');
        $config->set('atrium-errors.fallback_log', $this->fallback);
        $config->set('atrium-errors.drivers', ['database']);
        $config->set('atrium-errors.per_minute', 0);
        $config->set('atrium-errors.alerts.email', null);
        $config->set('atrium-errors.alerts.telegram_token', null);
        $config->set('atrium-errors.alerts.telegram_chat', null);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        if (getenv('KEEP_FALLBACK') && is_file($this->fallback)) {
            copy($this->fallback, '/private/tmp/claude-501/fallback.log');
        }
        @unlink($this->fallback);

        parent::tearDown();
    }

    /**
     * On MySQL and PostgreSQL the whole suite shares one database, set up by
     * whichever package's tests run first: the error tables come with these.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function errors(): Errors
    {
        return $this->app->make(Errors::class);
    }

    /**
     * Settings as saved in the admin.
     *
     * @param  array<string,mixed>  $values
     */
    protected function settings(array $values): void
    {
        $this->app->make(Settings::class)->save('errors', $values);
        $this->app->make(Settings::class)->flush();
        $this->app->make(Options::class)->flush();
    }

    protected function admin(string $email = 'admin@example.com'): AdminUser
    {
        $admin = AdminUser::query()->create([
            'name' => 'Admin',
            'email' => $email,
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $admin->forceFill(['is_superadmin' => true])->save();

        return $admin;
    }

    /**
     * An administrator who is not a superadmin, with these permissions only.
     *
     * @param  array<int,string>  $permissions
     */
    protected function editor(array $permissions = []): AdminUser
    {
        $editor = AdminUser::query()->create([
            'name' => 'Editor',
            'email' => 'editor-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $role = AdminRole::query()->create(['name' => 'Role '.bin2hex(random_bytes(4)), 'permissions' => $permissions]);
        $editor->roles()->attach($role);

        return $editor->fresh() ?? $editor;
    }

    /**
     * An issue as the tracker leaves it, for the screens.
     *
     * @param  array<string,mixed>  $attributes
     */
    protected function issue(array $attributes = []): Issue
    {
        static $n = 0;
        $n++;

        return Issue::query()->create([
            'fingerprint' => sha1('issue-'.$n.'-'.microtime()),
            'kind' => 'php',
            'level' => 'error',
            'class' => 'App\\Booking\\RoomUnavailable',
            'message' => 'Room 12 is not free on the chosen dates',
            'culprit' => 'App\\Booking\\Reserve::handle (app/Booking/Reserve.php)',
            'first_release' => '2.4.0',
            'last_release' => '2.4.0',
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now(),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    protected function occurrence(Issue $issue, array $attributes = []): Occurrence
    {
        return Occurrence::query()->create([
            'issue_id' => $issue->id,
            'level' => $issue->level,
            'release' => '2.4.0',
            'environment' => 'production',
            'method' => 'GET',
            'path' => '/rooms',
            'payload' => ['id' => bin2hex(random_bytes(16)), 'kind' => 'php', 'exceptions' => [['class' => $issue->class, 'message' => $issue->message, 'frames' => []]], 'request' => [], 'context' => [], 'breadcrumbs' => [], 'runtime' => ['php' => PHP_VERSION]],
            'created_at' => CarbonImmutable::now(),
            ...$attributes,
        ]);
    }

    /**
     * Everything the tracker stored, as one string: what a secret must never be part of.
     */
    protected function stored(): string
    {
        return json_encode([
            DB::table('error_issues')->get()->all(),
            DB::table('error_events')->get()->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * The lines of the fallback log, decoded.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function fallbackLines(): array
    {
        if (! is_file($this->fallback)) {
            return [];
        }

        return array_map(
            static fn (string $line): array => (array) json_decode($line, true),
            array_values(array_filter(explode("\n", (string) file_get_contents($this->fallback)))),
        );
    }
}
