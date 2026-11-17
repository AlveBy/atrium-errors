<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Admin\Modules\Settings;
use Atrium\Errors\Capture\EventFactory;
use Atrium\Errors\Contracts\ErrorReporter;
use Atrium\Errors\Errors;
use Atrium\Errors\Event;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Pipeline\DeliverEvent;
use Atrium\Errors\Pipeline\Drivers;
use Atrium\Errors\Pipeline\Sender;
use Atrium\Errors\Support\FallbackLog;
use Atrium\Errors\Support\Limiter;
use Atrium\Errors\Support\Options;
use Atrium\Errors\Tests\TestCase;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use ReflectionProperty;
use RuntimeException;

/**
 * The way from a captured error to the drivers: sampling, the cap, the
 * queue, the buffer, and what happens when a driver or the database fails.
 */
final class PipelineTest extends TestCase
{
    public function test_sampling_keeps_the_given_share(): void
    {
        config(['atrium-errors.sample_rate' => 40]);
        $limiter = $this->app->make(Limiter::class);

        $limiter->useRandom(static fn (): float => 0.39);
        $this->assertNotNull($this->errors()->capture(new RuntimeException('kept')));

        $limiter->useRandom(static fn (): float => 0.4);
        $this->assertNull($this->errors()->capture(new RuntimeException('dropped')));

        $this->assertSame(['kept'], Issue::query()->pluck('message')->all());
    }

    public function test_the_cap_per_minute_drops_what_is_above_it(): void
    {
        $this->settings(['per_minute' => 3]);

        $kept = 0;
        for ($i = 0; $i < 10; $i++) {
            $kept += $this->errors()->capture(new RuntimeException('Loop '.$i)) !== null ? 1 : 0;
        }

        $this->assertSame(3, $kept);
        $this->assertSame(3, Occurrence::query()->count());
    }

    public function test_without_a_cache_the_cap_is_counted_by_the_process(): void
    {
        $broken = $this->createMock(Repository::class);
        $broken->method('add')->willThrowException(new RuntimeException('Redis is down'));
        $limiter = new Limiter($broken);
        config(['atrium-errors.per_minute' => 2]);

        $this->assertSame([true, true, false], [$limiter->allow(), $limiter->allow(), $limiter->allow()]);
    }

    public function test_when_the_database_is_down_the_event_goes_to_the_fallback_log(): void
    {
        $default = config('database.default');
        config(['database.connections.down' => ['driver' => 'sqlite', 'database' => '/nonexistent-folder/atrium.sqlite', 'prefix' => '']]);

        try {
            config(['database.default' => 'down']);
            $event = $this->errors()->capture(new RuntimeException('Disk is full'));
        } finally {
            config(['database.default' => $default]);
            DB::purge('down');
        }

        $this->assertNotNull($event);
        $lines = $this->fallbackLines();
        $this->assertCount(1, $lines);
        $this->assertSame($event->id, $lines[0]['event']['id']);
        $this->assertSame('Disk is full', $lines[0]['event']['message']);
        $this->assertNotEmpty($lines[0]['reason']);
        $this->assertSame(0, Occurrence::query()->count());
    }

    public function test_a_failing_driver_does_not_stop_the_others(): void
    {
        config([
            'atrium-errors.drivers' => ['broken', 'database'],
            'atrium-errors.reporters' => ['broken' => BrokenReporter::class],
        ]);

        $this->errors()->capture(new RuntimeException('Still stored'));

        $this->assertSame(1, Occurrence::query()->count());
        $this->assertStringContainsString('broken driver failed: '.RuntimeException::class.': Out of order', (string) $this->fallbackLines()[0]['note']);
    }

    public function test_an_error_inside_the_tracker_is_noted_not_thrown(): void
    {
        config(['atrium-errors.sample_rate' => 50]);
        $this->app->make(Limiter::class)->useRandom(static fn (): float => throw new RuntimeException('Dice broke'));

        $this->assertNull($this->errors()->capture(new RuntimeException('Lost')));

        $this->assertStringContainsString('could not capture an error: '.RuntimeException::class.': Dice broke', (string) $this->fallbackLines()[0]['note']);
    }

    public function test_an_error_inside_a_driver_is_not_captured_again(): void
    {
        config([
            'atrium-errors.drivers' => ['recursive', 'database'],
            'atrium-errors.reporters' => ['recursive' => RecursiveReporter::class],
        ]);

        $this->errors()->capture(new RuntimeException('Outer'));

        $this->assertSame(['Outer'], Issue::query()->pluck('message')->all());
        $this->assertSame(1, RecursiveReporter::$calls);
    }

    public function test_when_drivers_cannot_be_resolved_the_event_is_kept_in_the_fallback_log(): void
    {
        config([
            'atrium-errors.drivers' => ['unbuildable', 'database'],
            'atrium-errors.reporters' => ['unbuildable' => UnbuildableReporter::class],
        ]);

        $this->errors()->capture(new RuntimeException('Kept anyway'));

        $line = $this->fallbackLines()[0];
        $this->assertSame('Kept anyway', $line['event']['message']);
        $this->assertStringContainsString('Missing SDK', (string) $line['reason']);
    }

    public function test_unknown_drivers_and_classes_that_are_not_reporters_are_skipped(): void
    {
        config([
            'atrium-errors.drivers' => 'nowhere, database ,database',
            'atrium-errors.reporters' => ['fake' => \stdClass::class, 'number' => 5],
        ]);

        $drivers = $this->app->make(Drivers::class);

        $this->assertSame(['database', 'sentry', 'log'], array_keys($drivers->known()));
        $this->assertSame(['database'], array_keys($drivers->active()));
    }

    public function test_with_a_queue_the_event_is_queued_and_delivered_by_the_job(): void
    {
        Queue::fake();
        config(['atrium-errors.queue' => 'redis']);

        $event = $this->errors()->capture(new RuntimeException('Queued'));

        $this->assertSame(0, Occurrence::query()->count());
        Queue::assertPushed(DeliverEvent::class, function (DeliverEvent $job) use ($event): bool {
            $this->assertSame(1, $job->tries);
            $job->handle($this->app->make(Sender::class));

            return $job->event['id'] === $event?->id;
        });
        $this->assertSame(1, Occurrence::query()->count());
    }

    public function test_when_the_queue_refuses_the_event_is_delivered_at_once(): void
    {
        config(['atrium-errors.queue' => 'no-such-connection']);

        $this->errors()->capture(new RuntimeException('No queue'));

        $this->assertSame(1, Occurrence::query()->count());
    }

    public function test_in_a_web_request_events_wait_for_the_end_of_the_response(): void
    {
        $this->pretendWeb();
        $sender = $this->app->make(Sender::class);

        $this->errors()->capture(new RuntimeException('First'));
        $this->errors()->capture(new RuntimeException('Second'));

        $this->assertCount(2, $sender->pending());
        $this->assertSame(0, Occurrence::query()->count());

        $this->app->terminate();

        $this->assertSame([], $sender->pending());
        $this->assertSame(2, Occurrence::query()->count());
    }

    public function test_the_buffer_has_a_limit(): void
    {
        $this->pretendWeb();
        $sender = $this->app->make(Sender::class);
        $event = $this->app->make(EventFactory::class)->fromMessage('Flood');

        for ($i = 0; $i < Sender::BUFFER + 5; $i++) {
            $sender->send($event);
        }

        $this->assertCount(Sender::BUFFER, $sender->pending());
    }

    public function test_the_event_survives_the_queue_as_an_array(): void
    {
        $event = $this->app->make(EventFactory::class)->fromThrowable(new RuntimeException('Round trip'), 'job', ['job' => 'Export']);

        $copy = Event::fromArray(json_decode((string) json_encode($event->toArray()), true));

        $this->assertEquals($event->toArray(), $copy->toArray());
        $this->assertSame($event->culprit(), $copy->culprit());
    }

    public function test_the_culprit_falls_back_to_the_first_frame_and_to_nothing(): void
    {
        $make = static fn (array $frames): Event => new Event('id', 'php', 'error', 'E', 'm', [['class' => 'E', 'message' => 'm', 'frames' => $frames]], [], [], [], 'testing', '1', [], 'fp', CarbonImmutable::now());

        $this->assertSame('vendor/x.php:7', $make([['file' => 'vendor/x.php', 'line' => 7, 'function' => '', 'in_app' => false]])->culprit());
        $this->assertSame('Vendor\\Lib::run (vendor/x.php)', $make([['file' => 'vendor/x.php', 'line' => 7, 'function' => 'Vendor\\Lib::run', 'in_app' => false]])->culprit());
        $this->assertNull($make([])->culprit());
        $this->assertNull($make([])->userId());
    }

    public function test_the_log_driver_writes_a_line(): void
    {
        config(['atrium-errors.drivers' => ['log']]);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('log')->once()->withArgs(static fn (string $level, string $message, array $context): bool => $level === 'critical'
            && $message === '[atrium-errors] Error: Call to undefined method'
            && $context['kind'] === 'php'
            && strlen($context['fingerprint']) === 40);
        Log::shouldReceive('log')->once()->withArgs(static fn (string $level): bool => $level === 'warning');
        Log::shouldReceive('log')->once()->withArgs(static fn (string $level): bool => $level === 'info');
        Log::shouldReceive('log')->once()->withArgs(static fn (string $level): bool => $level === 'error');

        $this->errors()->capture(new \Error('Call to undefined method'));
        $this->errors()->message('Slow', 'warning');
        $this->errors()->message('Note', 'info');
        $this->errors()->capture(new RuntimeException('Plain'));

        $this->assertSame(0, Occurrence::query()->count());
    }

    public function test_the_fallback_log_creates_its_folder(): void
    {
        $folder = sys_get_temp_dir().'/atrium-errors-'.bin2hex(random_bytes(4));
        $log = new FallbackLog($folder.'/deep/errors.log');

        $log->note('Hello');

        $this->assertSame($folder.'/deep/errors.log', $log->path());
        $this->assertStringContainsString('"note":"Hello"', (string) file_get_contents($log->path()));
        @unlink($log->path());
        @rmdir($folder.'/deep');
        @rmdir($folder);
    }

    private function pretendWeb(): void
    {
        (new ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        $this->app->forgetInstance(Sender::class);
        $this->app->forgetInstance(Errors::class);
    }
}

final class BrokenReporter implements ErrorReporter
{
    public function report(Event $event): void
    {
        throw new RuntimeException('Out of order');
    }
}

final class RecursiveReporter implements ErrorReporter
{
    public static int $calls = 0;

    public function report(Event $event): void
    {
        self::$calls++;
        app(Errors::class)->capture(new RuntimeException('Inner'));
    }
}

final class UnbuildableReporter implements ErrorReporter
{
    public function __construct()
    {
        throw new RuntimeException('Missing SDK');
    }

    public function report(Event $event): void {}

    public function test_settings_fall_back_to_the_config_when_the_database_is_down(): void
    {
        config(['atrium-errors.drivers' => ['database', 'log']]);
        $this->settings(['drivers' => ['database']]);
        $options = $this->app->make(Options::class);
        $this->assertSame(['database'], $options->drivers());

        $default = config('database.default');
        config(['database.connections.down' => ['driver' => 'sqlite', 'database' => '/nonexistent-folder/atrium.sqlite', 'prefix' => '']]);

        try {
            config(['database.default' => 'down']);
            $this->app->make(Settings::class)->flush();
            $options->flush();
            $drivers = $options->drivers();
        } finally {
            config(['database.default' => $default]);
            DB::purge('down');
        }

        $this->assertSame(['database', 'log'], $drivers);
    }
}
