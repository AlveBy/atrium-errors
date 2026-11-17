<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Capture\Breadcrumbs;
use Atrium\Errors\Capture\Frames;
use Atrium\Errors\Capture\Scrubber;
use Atrium\Errors\Dashboard\ErrorsGadget;
use Atrium\Errors\Errors;
use Atrium\Errors\ErrorsServiceProvider;
use Atrium\Errors\ErrorsSettings;
use Atrium\Errors\Event;
use Atrium\Errors\Menu;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Reporters\LogReporter;
use Atrium\Errors\Support\Options;
use Atrium\Errors\Tests\TestCase;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Log\LogManager;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The small things around the tracker: the menu, the prune command, the log
 * and test drivers, the dashboard tile, the settings form and the wiring.
 */
final class ModuleDetailsTest extends TestCase
{
    // ---- the menu -------------------------------------------------------

    public function test_a_route_that_only_ends_or_starts_like_ours_is_not_ours(): void
    {
        $other = [['label' => 'X', 'route' => 'other.admin.errors.index'], ['label' => 'Y', 'route' => 'admin.errors.index.legacy']];

        $menu = Menu::add($other);

        $this->assertCount(3, $menu);
        $this->assertSame(Menu::GROUP, $menu[2]['key']);
    }

    public function test_the_entry_is_not_added_twice(): void
    {
        $menu = [['label' => 'X', 'route' => 'admin.errors.index']];

        $this->assertSame($menu, Menu::add($menu));
    }

    public function test_the_entry_joins_a_group_with_several_children_and_leaves_them_all(): void
    {
        $menu = [
            ['label' => 'Home', 'route' => 'admin.dashboard'],
            ['key' => Menu::GROUP, 'label' => 'Health', 'children' => [['route' => 'one'], ['route' => 'two'], ['route' => 'three']]],
            ['label' => 'Modules', 'route' => 'admin.modules.index'],
        ];

        $joined = Menu::add($menu);

        $this->assertCount(3, $joined);
        $this->assertSame(['one', 'two', 'three', 'admin.errors.index'], array_column($joined[1]['children'], 'route'));
        $this->assertSame($menu[0], $joined[0]);
        $this->assertSame($menu[2], $joined[2]);
    }

    public function test_a_group_without_children_gets_ours_as_the_only_one(): void
    {
        $joined = Menu::add([['key' => Menu::GROUP, 'label' => 'Health']]);

        $this->assertCount(1, $joined);
        $this->assertSame(['admin.errors.index'], array_column($joined[0]['children'], 'route'));
    }

    public function test_the_group_goes_before_modules_and_nothing_else_is_lost(): void
    {
        $menu = [
            ['label' => 'Home', 'route' => 'admin.dashboard'],
            ['label' => 'Modules', 'route' => 'admin.modules.index'],
            ['label' => 'Settings', 'route' => 'admin.settings'],
            ['label' => 'Help', 'route' => 'admin.help'],
        ];

        $added = Menu::add($menu);

        $this->assertSame(['admin.dashboard', null, 'admin.modules.index', 'admin.settings', 'admin.help'], array_map(static fn (array $item): mixed => $item['route'] ?? null, $added));
        $this->assertSame(Menu::GROUP, $added[1]['key']);
        $this->assertSame([['label' => 'atrium-errors::errors.title', 'route' => 'admin.errors.index', 'icon' => 'info']], $added[1]['children']);
    }

    public function test_the_group_goes_first_when_modules_is_first(): void
    {
        $added = Menu::add([['label' => 'Modules', 'route' => 'admin.modules.index']]);

        $this->assertSame(Menu::GROUP, $added[0]['key']);
        $this->assertSame('admin.modules.index', $added[1]['route']);
    }

    // ---- prune ----------------------------------------------------------

    public function test_prune_with_one_day_is_valid_and_with_none_is_not(): void
    {
        $this->artisan('atrium:errors:prune', ['--days' => '1'])
            ->expectsOutput('Deleted 0 events and 0 errors older than 1 days.')
            ->assertSuccessful();
        $this->artisan('atrium:errors:prune', ['--days' => '0'])->expectsOutputToContain('days')->assertFailed();
        $this->artisan('atrium:errors:prune', ['--days' => '-5'])->assertFailed();
    }

    public function test_prune_with_nothing_old_deletes_nothing_and_says_zero(): void
    {
        $issue = $this->issue();
        $this->occurrence($issue);

        $this->artisan('atrium:errors:prune')->expectsOutput('Deleted 0 events and 0 errors older than 90 days.')->assertSuccessful();

        $this->assertSame(1, Occurrence::query()->count());
    }

    public function test_prune_takes_the_days_from_the_settings_without_the_option(): void
    {
        $this->settings(['retention_days' => 7]);
        $issue = $this->issue();
        $this->occurrence($issue, ['created_at' => now()->subDays(8)]);
        $this->occurrence($issue, ['created_at' => now()->subDays(6)]);

        $this->artisan('atrium:errors:prune')->expectsOutput('Deleted 1 events and 0 errors older than 7 days.')->assertSuccessful();
    }

    public function test_prune_deletes_a_thousand_events_at_a_time(): void
    {
        $issue = $this->issue();
        $rows = [];
        for ($i = 0; $i < 1001; $i++) {
            $rows[] = ['issue_id' => $issue->id, 'level' => 'error', 'payload' => '{}', 'created_at' => now()->subDays(100)->toDateTimeString()];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('error_events')->insert($chunk);
        }
        $deletes = 0;
        DB::listen(static function ($query) use (&$deletes): void {
            $deletes += str_starts_with($query->sql, 'delete from "error_events"') || str_starts_with($query->sql, 'delete from `error_events`') ? 1 : 0;
        });

        $this->artisan('atrium:errors:prune', ['--days' => '90'])->expectsOutput('Deleted 1001 events and 0 errors older than 90 days.')->assertSuccessful();

        $this->assertSame(2, $deletes);
        $this->assertSame(0, Occurrence::query()->count());
    }

    // ---- the test command -----------------------------------------------

    public function test_the_test_command_names_the_event_it_sent(): void
    {
        Artisan::call('atrium:errors:test');

        $output = Artisan::output();
        $this->assertStringContainsString('Test error '.Occurrence::query()->sole()->payload['id'].' sent to: database.', $output);
    }

    // ---- the log driver -------------------------------------------------

    public function test_the_log_driver_writes_what_is_needed_to_find_the_error_again(): void
    {
        config(['logging.default' => 'stack']);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')->with(
            'critical',
            '[atrium-errors] App\\Boom: It broke',
            [
                'fingerprint' => 'fp-1',
                'kind' => 'job',
                'release' => '2.4.0',
                'culprit' => 'Boom::run (app/Boom.php)',
                'path' => '/rooms/12',
                'user_id' => 7,
            ],
        );
        $manager = $this->createMock(LogManager::class);
        $manager->expects($this->once())->method('channel')->with('stack')->willReturn($logger);

        (new LogReporter($manager))->report(new Event(
            'evt-1', 'job', 'fatal', 'App\\Boom', 'It broke',
            [['class' => 'App\\Boom', 'message' => 'It broke', 'frames' => [['file' => 'app/Boom.php', 'line' => 3, 'function' => 'Boom::run', 'in_app' => true]]]],
            ['path' => '/rooms/12', 'user_id' => 7], [], [], 'production', '2.4.0', [], 'fp-1', CarbonImmutable::now(),
        ));
    }

    // ---- the error handler ----------------------------------------------

    public function test_the_tracker_says_whether_it_is_on(): void
    {
        $this->assertTrue($this->errors()->enabled());

        config(['atrium-errors.enabled' => false]);

        $this->assertFalse($this->errors()->enabled());
    }

    // ---- the models -----------------------------------------------------

    public function test_the_counters_of_an_issue_are_integers_even_when_the_database_hands_out_strings(): void
    {
        $id = $this->issue(['times_seen' => 3, 'users_seen' => 2])->id;
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);

        try {
            $issue = Issue::query()->findOrFail($id);
            $seen = [$issue->times_seen, $issue->users_seen];
        } finally {
            $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        }

        $this->assertSame([3, 2], $seen);
    }

    // ---- the dashboard tile ---------------------------------------------

    public function test_the_tile_lists_five_errors_at_most_the_most_frequent_first(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $issue = $this->issue(['class' => 'App\\Err'.$i]);
            for ($n = 0; $n < $i; $n++) {
                $this->occurrence($issue);
            }
        }

        $top = (new ErrorsGadget)->data()['top'];

        $this->assertSame([6, 5, 4, 3, 2], array_column($top, 'count'));
        $this->assertSame(['Err6', 'Err5', 'Err4', 'Err3', 'Err2'], array_column($top, 'class'));
    }

    public function test_the_tile_cuts_a_title_to_a_hundred_and_twenty_characters(): void
    {
        $this->occurrence($this->issue(['message' => str_repeat('я', 130)]));
        $this->occurrence($this->issue(['message' => str_repeat('я', 120)]));

        $titles = array_column((new ErrorsGadget)->data()['top'], 'title');

        $this->assertSame([str_repeat('я', 120), str_repeat('я', 120)], $titles);
    }

    public function test_the_tile_counts_are_integers_even_when_the_database_hands_out_strings(): void
    {
        $this->occurrence($this->issue());
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);

        try {
            $top = (new ErrorsGadget)->data()['top'];
        } finally {
            $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        }

        $this->assertSame(1, $top[0]['count']);
    }

    public function test_the_tile_words_are_always_a_list_even_when_a_translation_is_a_plain_string(): void
    {
        Lang::addLines(['errors.gadget' => 'plain'], 'en', 'atrium-errors');

        $this->assertSame(['plain'], (new ErrorsGadget)->data()['strings']);
    }

    // ---- the settings form ----------------------------------------------

    public function test_the_drivers_to_choose_from_are_named_in_words_and_the_unknown_ones_by_name(): void
    {
        config(['atrium-errors.reporters' => ['rollbar' => BrokenReporter::class]]);

        $drivers = collect((new ErrorsSettings)->fields())->map(static fn ($field) => $field->toArray())->firstWhere('name', 'drivers');

        $this->assertSame([
            ['value' => 'database', 'label' => 'Site database (the admin screens)'],
            ['value' => 'sentry', 'label' => 'Sentry or GlitchTip'],
            ['value' => 'log', 'label' => 'Application log'],
            ['value' => 'rollbar', 'label' => 'rollbar'],
        ], $drivers['options']);
    }

    public function test_every_setting_may_be_left_empty(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->put('/admin/modules/errors/settings', [
            'drivers' => null, 'sentry_dsn' => null, 'sample_rate' => null, 'per_minute' => null, 'retention_days' => null,
            'alert_email' => null, 'telegram_token' => null, 'telegram_chat' => null, 'spike_per_hour' => null,
        ])->assertSessionDoesntHaveErrors();
    }

    /**
     * @param  array<string,mixed>  $values
     */
    #[DataProvider('limits')]
    public function test_a_setting_is_refused_just_outside_its_limits_and_taken_on_them(string $field, mixed $inside, mixed $outside): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->put('/admin/modules/errors/settings', [$field => $inside])->assertSessionDoesntHaveErrors();
        $this->put('/admin/modules/errors/settings', [$field => $outside])->assertSessionHasErrors($field);
    }

    /**
     * @return array<string,array{string,mixed,mixed}>
     */
    public static function limits(): array
    {
        return [
            'sample rate, at its lowest' => ['sample_rate', 1, 0],
            'sample rate, at its highest' => ['sample_rate', 100, 101],
            'per minute, at its lowest' => ['per_minute', 0, -1],
            'per minute, at its highest' => ['per_minute', 100000, 100001],
            'retention, at its lowest' => ['retention_days', 1, 0],
            'retention, at its highest' => ['retention_days', 3650, 3651],
            'spike, at its lowest' => ['spike_per_hour', 0, -1],
            'spike, at its highest' => ['spike_per_hour', 1000000, 1000001],
            'the emails, at their longest' => ['alert_email', str_repeat('a', 1000), str_repeat('a', 1001)],
            'the dsn, as a dsn' => ['sentry_dsn', 'https://key@sentry.example.test/9', 'https://key@sentry.example.test/'],
            'the token, as a token' => ['telegram_token', '123:abc_DEF', '123abc'],
            'the chat, as a number' => ['telegram_chat', '-1001234', 'chat'],
            'the chat, as a name' => ['telegram_chat', '@north_ops', '@abc'],
        ];
    }

    // ---- the wiring -----------------------------------------------------

    public function test_the_options_are_one_for_a_request(): void
    {
        $this->assertSame($this->app->make(Options::class), $this->app->make(Options::class));
    }

    public function test_the_breadcrumbs_keep_thirty_by_default_and_as_many_as_configured(): void
    {
        $this->withoutConfig('breadcrumbs');
        $this->app->forgetInstance(Breadcrumbs::class);
        $this->assertCount(30, $this->crumbs());

        config(['atrium-errors.breadcrumbs' => 4]);
        $this->app->forgetInstance(Breadcrumbs::class);
        $this->assertCount(4, $this->crumbs());
    }

    public function test_the_code_around_a_throw_is_five_lines_by_default_and_as_many_as_configured(): void
    {
        $file = sys_get_temp_dir().'/atrium-errors-code-'.bin2hex(random_bytes(4)).'.php';
        file_put_contents($file, implode("\n", array_map(static fn (int $n): string => 'line '.$n, range(1, 40)))."\n");

        try {
            $this->withoutConfig('context_lines');
            $this->app->forgetInstance(Frames::class);
            $context = $this->app->make(Frames::class)->of($this->thrownAt($file, 20))[0]['context'];
            $this->assertCount(5, $context['pre']);
            $this->assertCount(5, $context['post']);

            config(['atrium-errors.context_lines' => 2]);
            $this->app->forgetInstance(Frames::class);
            $context = $this->app->make(Frames::class)->of($this->thrownAt($file, 20))[0]['context'];
            $this->assertCount(2, $context['pre']);
            $this->assertCount(2, $context['post']);
        } finally {
            @unlink($file);
        }
    }

    public function test_the_scrubber_takes_the_extra_keys_of_the_config_that_are_strings(): void
    {
        config(['atrium-errors.scrub_keys' => [5 => 'x_custom', 'k' => 'y_custom', 7 => 99, 8 => null]]);
        $this->app->forgetInstance(Scrubber::class);

        $scrubbed = $this->app->make(Scrubber::class)->scrub(['x_custom' => 'a', 'y_custom' => 'b', 'plain' => 'c']);

        $this->assertSame(['x_custom' => Scrubber::FILTERED, 'y_custom' => Scrubber::FILTERED, 'plain' => 'c'], $scrubbed);
    }

    public function test_the_config_and_the_words_can_be_published(): void
    {
        $dir = dirname((string) (new \ReflectionClass(ErrorsServiceProvider::class))->getFileName());

        $this->assertSame(
            [$dir.'/../config/atrium-errors.php' => config_path('atrium-errors.php')],
            ServiceProvider::pathsToPublish(ErrorsServiceProvider::class, 'atrium-errors-config'),
        );
        $this->assertSame(
            [$dir.'/../lang' => $this->app->langPath('vendor/atrium-errors')],
            ServiceProvider::pathsToPublish(ErrorsServiceProvider::class, 'atrium-errors-lang'),
        );
    }

    public function test_a_failed_job_is_captured_with_where_it_ran(): void
    {
        $job = $this->createStub(Job::class);
        $job->method('resolveName')->willReturn('App\\Jobs\\Sync');
        $job->method('getQueue')->willReturn('emails');
        $job->method('attempts')->willReturn(3);

        event(new JobFailed('redis', $job, new RuntimeException('Sync failed')));

        $this->assertSame(['job' => 'App\\Jobs\\Sync', 'queue' => 'emails', 'connection' => 'redis', 'attempts' => 3], Occurrence::query()->sole()->payload['context']);
        $this->assertSame('job', Issue::query()->sole()->kind);
    }

    #[DataProvider('tasks')]
    public function test_a_failed_scheduled_task_is_captured_with_its_name_cut_to_two_hundred_and_fifty_five_characters(string $name, string $shown): void
    {
        $task = (new Schedule)->call(static fn () => null)->description($name)->cron('5 4 * * *');

        event(new ScheduledTaskFailed($task, new RuntimeException('Task failed')));

        $this->assertSame(['task' => $shown, 'expression' => '5 4 * * *'], Occurrence::query()->sole()->payload['context']);
        $this->assertSame('schedule', Issue::query()->sole()->kind);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function tasks(): array
    {
        return [
            'short' => ['Nightly report', 'Nightly report'],
            'exactly as long as it may be' => [str_repeat('я', 255), str_repeat('я', 255)],
            'one over' => [str_repeat('я', 256), str_repeat('я', 255)],
            'far over' => [str_repeat('я', 400), str_repeat('я', 255)],
        ];
    }

    public function test_the_error_handler_reports_an_exception_once_whatever_the_kind(): void
    {
        $error = new RuntimeException('Once');

        $this->assertNotNull($this->app->make(Errors::class)->capture($error, 'job'));
        $this->assertNull($this->app->make(Errors::class)->capture($error, 'php'));
        $this->assertSame(1, Occurrence::query()->count());
    }

    private function crumbs(): array
    {
        $ring = $this->app->make(Breadcrumbs::class);
        for ($i = 0; $i < 40; $i++) {
            $ring->add('cat', 'message '.$i);
        }

        return $ring->all();
    }

    private function withoutConfig(string $key): void
    {
        $all = config('atrium-errors');
        unset($all[$key]);
        config(['atrium-errors' => $all]);
    }

    private function thrownAt(string $file, int $line): Exception
    {
        return new class($file, $line) extends Exception
        {
            public function __construct(string $file, int $line)
            {
                parent::__construct('Somewhere');
                $this->file = $file;
                $this->line = $line;
            }
        };
    }
}
