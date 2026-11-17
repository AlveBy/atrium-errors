<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Capture\EventFactory;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Pipeline\DeliverEvent;
use Atrium\Errors\Tests\Fixtures\FailingJob;
use Atrium\Errors\Tests\TestCase;
use ErrorException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use LogicException;
use PDOException;
use RuntimeException;
use TypeError;

/**
 * Every way an error reaches the tracker: the exception handler, a failed
 * queue job, a failed scheduled task, a message — the browser has its own test.
 */
final class CaptureTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware(['web', 'atrium.site'])->get('/rooms/{room}', static function (): never {
            throw new RuntimeException('Room is not available');
        })->name('rooms.show');

        Route::middleware('web')->get('/admin-only', static function (): never {
            throw new LogicException('Price list is empty');
        });
    }

    public function test_an_exception_of_a_page_is_stored_with_the_request(): void
    {
        $this->get('/rooms/12?ref=newsletter&token=open-sesame')->assertStatus(500);

        $issue = Issue::query()->sole();
        $this->assertSame(RuntimeException::class, $issue->class);
        $this->assertSame('Room is not available', $issue->message);
        $this->assertSame('php', $issue->kind);
        $this->assertSame('error', $issue->level);
        $this->assertSame(Issue::NEW, $issue->status);
        $this->assertSame(1, $issue->times_seen);
        $this->assertSame('2.4.0', $issue->first_release);
        $this->assertNotNull($issue->culprit);

        $event = Occurrence::query()->sole();
        $this->assertSame('/rooms/12', $event->path);
        $this->assertSame('GET', $event->method);
        $this->assertSame('rooms.show', $event->route);
        $this->assertSame('en', $event->locale);
        $this->assertNotNull($event->site_id);
        $this->assertSame('testing', $event->environment);

        $payload = $event->payload;
        $this->assertStringContainsString('ref=newsletter', $payload['request']['url']);
        $this->assertStringNotContainsString('open-sesame', $payload['request']['url']);
        $this->assertSame(PHP_VERSION, $payload['runtime']['php']);
        $this->assertSame(app()->version(), $payload['runtime']['laravel']);

        // The frame of the throw comes first, with the code around it.
        $frame = $payload['exceptions'][0]['frames'][0];
        $this->assertStringEndsWith('CaptureTest.php', $frame['file']);
        $this->assertStringContainsString("throw new RuntimeException('Room is not available')", $frame['context']['line']);
        $this->assertNotEmpty($frame['context']['pre']);
        $this->assertArrayNotHasKey('args', $frame);
    }

    public function test_the_user_id_is_kept_and_nothing_else_about_the_user(): void
    {
        $admin = $this->admin('ops@example.com');

        $this->actingAs($admin, 'admin')->get('/admin-only')->assertStatus(500);

        $event = Occurrence::query()->sole();
        $this->assertSame($admin->id, $event->user_id);
        $this->assertSame(1, Issue::query()->sole()->users_seen);
        $this->assertStringNotContainsString('ops@example.com', $this->stored());
    }

    public function test_the_same_bug_twice_is_one_issue_with_two_events_and_one_user(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get('/admin-only');
        $this->get('/admin-only');

        $issue = Issue::query()->sole();
        $this->assertSame(2, $issue->times_seen);
        $this->assertSame(1, $issue->users_seen);
        $this->assertSame(2, $issue->occurrences()->count());
    }

    public function test_breadcrumbs_keep_queries_without_values_and_log_lines(): void
    {
        DB::select('select ? as guest', ['anna.lind@example.org']);
        Log::info('Checkout started');

        $this->errors()->capture(new RuntimeException('Card declined'));

        $crumbs = Occurrence::query()->sole()->payload['breadcrumbs'];
        $categories = array_column($crumbs, 'category');
        $this->assertContains('query', $categories);
        $this->assertContains('log.info', $categories);
        $this->assertStringContainsString('select ? as guest', json_encode($crumbs) ?: '');
        $this->assertStringNotContainsString('anna.lind', $this->stored());
    }

    public function test_a_failed_queue_job_is_stored_once_with_the_job(): void
    {
        config(['queue.default' => 'sync']);

        try {
            dispatch(new FailingJob('Supplier feed timed out'));
            $this->fail('The job should have thrown');
        } catch (RuntimeException $e) {
            // The handler reports it too: still one event.
            $this->app->make(ExceptionHandler::class)->report($e);
        }

        $issue = Issue::query()->sole();
        $this->assertSame('job', $issue->kind);
        $this->assertSame('Supplier feed timed out', $issue->message);

        $context = Occurrence::query()->sole()->payload['context'];
        $this->assertSame(FailingJob::class, $context['job']);
        $this->assertSame('sync', $context['connection']);
        $this->assertSame(1, $context['attempts']);
    }

    public function test_a_failed_scheduled_task_is_stored_with_the_task(): void
    {
        $this->app->make(Schedule::class)
            ->call(static function (): never {
                throw new RuntimeException('Nightly sitemap failed');
            })
            ->everyMinute()
            ->name('nightly-sitemap');

        $this->artisan('schedule:run')->assertSuccessful();

        $issue = Issue::query()->sole();
        $this->assertSame('schedule', $issue->kind);
        $this->assertSame('Nightly sitemap failed', $issue->message);

        $context = Occurrence::query()->sole()->payload['context'];
        $this->assertSame('nightly-sitemap', $context['task']);
        $this->assertSame('* * * * *', $context['expression']);
    }

    public function test_a_message_is_stored_without_an_exception(): void
    {
        $event = $this->errors()->message('Payment gateway answered in 9 s', 'warning', ['gateway' => 'demo-pay']);

        $this->assertNotNull($event);
        $issue = Issue::query()->sole();
        $this->assertSame('message', $issue->kind);
        $this->assertSame('warning', $issue->level);
        $this->assertSame('Message', $issue->class);
        $this->assertSame(['gateway' => 'demo-pay'], Occurrence::query()->sole()->payload['context']);
    }

    public function test_an_unknown_message_level_becomes_info(): void
    {
        $this->errors()->message('Cache warmed', 'shouting');

        $this->assertSame('info', Issue::query()->sole()->level);
    }

    public function test_levels_follow_the_kind_of_error(): void
    {
        $levels = array_map(fn ($e): ?string => $this->errors()->capture($e)?->level, [
            new ErrorException('Undefined array key "room"', 0, E_WARNING),
            new ErrorException('Allowed memory size exhausted', 0, E_ERROR),
            new TypeError('Argument #1 must be of type int'),
            new InvalidArgumentException('Unknown currency'),
        ]);

        $this->assertSame(['warning', 'fatal', 'fatal', 'error'], $levels);
        // The two ErrorExceptions come from the same place: one issue, at the last level.
        $this->assertSame(3, Issue::query()->count());
        $this->assertSame('fatal', Issue::query()->where('class', ErrorException::class)->sole()->level);
    }

    public function test_previous_exceptions_are_kept_up_to_three(): void
    {
        $e = new RuntimeException('level 0', 0, new RuntimeException('level 1', 0, new RuntimeException('level 2', 0, new RuntimeException('level 3', 0, new RuntimeException('level 4')))));

        $this->errors()->capture($e);

        $this->assertSame(['level 0', 'level 1', 'level 2', 'level 3'], array_column(Occurrence::query()->sole()->payload['exceptions'], 'message'));
    }

    public function test_a_query_error_keeps_the_sql_without_the_values(): void
    {
        $e = new QueryException('testing', 'select * from guests where email = ? and card = ?', ['mila.berg@example.net', '4111111111111111'], new PDOException('SQLSTATE[42S22]: Column not found: 1054 Unknown column'));

        $this->errors()->capture($e);

        $message = Issue::query()->sole()->message;
        $this->assertStringContainsString('Column not found', $message);
        $this->assertStringContainsString('(SQL: select * from guests where email = ? and card = ?)', $message);
        $this->assertStringNotContainsString('mila.berg', $this->stored());
        $this->assertStringNotContainsString('4111111111111111', $this->stored());
    }

    public function test_ignored_classes_and_switched_off_tracking_store_nothing(): void
    {
        config(['atrium-errors.ignore' => [InvalidArgumentException::class, 123]]);

        $this->assertNull($this->errors()->capture(new InvalidArgumentException('Expected')));
        $this->assertNotNull($this->errors()->capture(new LogicException('Not expected')));

        config(['atrium-errors.enabled' => false]);
        $this->assertNull($this->errors()->capture(new RuntimeException('While off')));
        $this->assertNull($this->errors()->message('While off'));

        $this->assertSame([LogicException::class], Issue::query()->pluck('class')->all());
    }

    public function test_the_same_exception_object_is_captured_once(): void
    {
        $e = new RuntimeException('Twice reported');

        $this->assertNotNull($this->errors()->capture($e));
        $this->assertNull($this->errors()->capture($e));

        $this->assertSame(1, Occurrence::query()->count());
    }

    public function test_a_command_or_a_worker_keeps_no_request_but_a_browser_report_does(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->errors()->capture(new RuntimeException('In a worker'));
            $browser = $this->app->make(EventFactory::class)
                ->fromBrowser(['message' => 'x is null'], Request::create('/atrium-errors/browser', 'POST', server: ['HTTP_REFERER' => 'http://localhost/rooms']));
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame([], Occurrence::query()->sole()->payload['request']);
        $this->assertSame('/rooms', $browser->request['path']);
    }

    public function test_a_guard_that_cannot_be_built_does_not_hide_the_administrator(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');
        // The site's default guard points at a driver nobody registered.
        config(['auth.defaults.guard' => 'site', 'auth.guards.site' => ['driver' => 'no-such-driver']]);

        $this->errors()->capture(new RuntimeException('Behind a broken guard'));

        $this->assertSame($admin->id, Occurrence::query()->sole()->user_id);
    }

    public function test_the_trackers_own_job_failing_is_not_captured(): void
    {
        $job = $this->createStub(Job::class);
        $job->method('resolveName')->willReturn(DeliverEvent::class);

        event(new JobFailed('redis', $job, new RuntimeException('Delivery failed')));

        $this->assertSame(0, Issue::query()->count());
    }
}
