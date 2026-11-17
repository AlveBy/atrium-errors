<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Modules\Modules;
use Atrium\Errors\Capture\Breadcrumbs;
use Atrium\Errors\Capture\Frames;
use Atrium\Errors\Capture\Release;
use Atrium\Errors\Capture\Scrubber;
use Atrium\Errors\Console\PruneCommand;
use Atrium\Errors\Console\TestCommand;
use Atrium\Errors\Dashboard\ErrorsGadget;
use Atrium\Errors\Http\Controllers\BrowserController;
use Atrium\Errors\Http\Controllers\IssueController;
use Atrium\Errors\Http\Middleware\AuthorizeErrors;
use Atrium\Errors\Http\Middleware\InjectBrowserScript;
use Atrium\Errors\Pipeline\DeliverEvent;
use Atrium\Errors\Pipeline\Sender;
use Atrium\Errors\Reporters\Sentry\HttpTransport;
use Atrium\Errors\Reporters\Sentry\Transport;
use Atrium\Errors\Support\FallbackLog;
use Atrium\Errors\Support\Limiter;
use Atrium\Errors\Support\Options;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Hooks the tracker into the application without touching its code: the
 * exception handler, failed queue jobs, failed scheduled tasks, the database
 * and the log (for breadcrumbs). All of it only once the module is installed
 * (`php artisan atrium:module:install alveby/atrium-errors`).
 */
final class ErrorsServiceProvider extends ServiceProvider
{
    public const MODULE = 'alveby/atrium-errors';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atrium-errors.php', 'atrium-errors');

        $this->app->scoped(Options::class);
        $this->app->singleton(Breadcrumbs::class, fn (): Breadcrumbs => new Breadcrumbs((int) config('atrium-errors.breadcrumbs', 30)));
        $this->app->singleton(Release::class, fn (): Release => new Release($this->app->basePath(), config('atrium-errors.release')));
        $this->app->singleton(Frames::class, fn (): Frames => new Frames($this->app->basePath(), (int) config('atrium-errors.context_lines', 5)));
        $this->app->singleton(Scrubber::class, fn (): Scrubber => new Scrubber(array_filter((array) config('atrium-errors.scrub_keys', []), 'is_string')));
        $this->app->singleton(FallbackLog::class, fn (): FallbackLog => new FallbackLog((string) config('atrium-errors.fallback_log', storage_path('logs/atrium-errors.log'))));
        $this->app->singleton(Limiter::class);
        $this->app->singleton(Sender::class);
        $this->app->singleton(Errors::class);
        $this->app->bind(Transport::class, fn (): Transport => new HttpTransport($this->app->make(Http::class), (int) config('atrium-errors.sentry.timeout', 3)));
    }

    public static function installed(): bool
    {
        return app(Modules::class)->isInstalled(self::MODULE);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'atrium-errors');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'atrium-errors');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneCommand::class, TestCommand::class]);

            $this->publishes([
                __DIR__.'/../config/atrium-errors.php' => config_path('atrium-errors.php'),
            ], 'atrium-errors-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/atrium-errors'),
            ], 'atrium-errors-lang');
        }

        if (! self::installed()) {
            return;
        }

        $this->listen();
        $this->registerAdmin();

        RateLimiter::for('atrium-errors-browser', static fn (Request $request): Limit => Limit::perMinute((int) config('atrium-errors.browser.per_minute', 10))
            ->by('atrium-errors|'.$request->ip()));

        $this->app->make(HttpKernel::class)->appendMiddlewareToGroup('web', InjectBrowserScript::class);

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('atrium:errors:prune')->dailyAt('03:40')->withoutOverlapping();
        });

        // Once the panel has booted: its route macros and groups are there.
        $this->app->booted(function (): void {
            if (! $this->app->routesAreCached()) {
                $this->registerRoutes();
            }
        });
    }

    /**
     * Everything that feeds the tracker.
     */
    private function listen(): void
    {
        // An app with its own handler, not Laravel's, reports to us itself.
        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if ($handler instanceof Handler) {
                $handler->reportable(function (Throwable $e): void {
                    $this->app->make(Errors::class)->capture($e);
                });
            }
        });

        Events::listen(JobFailed::class, function (JobFailed $event): void {
            $name = $event->job->resolveName();

            // The tracker's own job failing must not feed itself.
            if ($name === DeliverEvent::class) {
                return;
            }

            $this->app->make(Errors::class)->capture($event->exception, 'job', [
                'job' => $name,
                'queue' => $event->job->getQueue(),
                'connection' => $event->connectionName,
                'attempts' => $event->job->attempts(),
            ]);
        });

        Events::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event): void {
            $this->app->make(Errors::class)->capture($event->exception, 'schedule', [
                'task' => mb_substr($event->task->getSummaryForDisplay(), 0, 255),
                'expression' => $event->task->expression,
            ]);
        });

        Events::listen(JobProcessing::class, fn () => $this->app->make(Breadcrumbs::class)->clear());
        Events::listen(QueryExecuted::class, fn (QueryExecuted $query) => $this->app->make(Breadcrumbs::class)->query($query));
        Events::listen(MessageLogged::class, fn (MessageLogged $log) => $this->app->make(Breadcrumbs::class)->log($log));
    }

    /**
     * The menu entry, the dashboard gadget and the alert account's purpose.
     */
    private function registerAdmin(): void
    {
        Hook::filter('atrium.admin.menu', static fn (array $menu, mixed $user = null): array => Menu::add($menu, $user));

        config()->push('atrium.dashboard.gadgets', ErrorsGadget::class);

        // The mail module lists it among the purposes an account can serve.
        config(['atrium-mail.purposes.errors' => 'atrium-errors::errors.alerts.purpose']);
    }

    private function registerRoutes(): void
    {
        Route::prefix((string) config('atrium.prefix', 'admin'))
            ->middleware([...config('atrium.middleware', ['web']), 'atrium.inertia', 'admin.auth', 'atrium.site', AuthorizeErrors::class])
            ->group(function (): void {
                Route::get('errors', [IssueController::class, 'index'])->name('admin.errors.index');
                Route::get('errors/{issue}', [IssueController::class, 'show'])->whereNumber('issue')->name('admin.errors.show');
                Route::post('errors/{issue}/status', [IssueController::class, 'status'])->whereNumber('issue')->name('admin.errors.status');
            });

        // The browser reports with a beacon: no CSRF token to send, so the
        // throttle and the size limit guard it instead.
        Route::middleware(['web', 'throttle:atrium-errors-browser'])
            ->withoutMiddleware([ValidateCsrfToken::class, InjectBrowserScript::class])
            ->post('atrium-errors/browser', [BrowserController::class, 'store'])
            ->name('atrium.errors.browser');

        Route::get('atrium-errors.js', [BrowserController::class, 'script'])->name('atrium.errors.script');
    }
}
