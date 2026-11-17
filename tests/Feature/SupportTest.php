<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Capture\Breadcrumbs;
use Atrium\Errors\Capture\Frames;
use Atrium\Errors\Capture\Release;
use Atrium\Errors\ErrorsServiceProvider;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Support\Frequency;
use Atrium\Errors\Tests\TestCase;
use Composer\InstalledVersions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * The commands, the release, the chart buckets and the small pieces.
 */
final class SupportTest extends TestCase
{
    /** @var array<int,string> */
    private array $folders = [];

    protected function tearDown(): void
    {
        foreach ($this->folders as $folder) {
            File::deleteDirectory($folder);
        }

        parent::tearDown();
    }

    public function test_prune_deletes_old_events_and_issues_left_empty(): void
    {
        $old = $this->issue(['last_seen_at' => now()->subDays(100)]);
        $this->occurrence($old, ['created_at' => now()->subDays(100)]);
        $mixed = $this->issue();
        $this->occurrence($mixed, ['created_at' => now()->subDays(95)]);
        $this->occurrence($mixed);
        $fresh = $this->issue(['last_seen_at' => now()->subDays(10)]);
        $this->occurrence($fresh, ['created_at' => now()->subDays(10)]);
        // Seen just now, its event not written yet (sampled away, still queued): stays.
        $pending = $this->issue();

        $this->artisan('atrium:errors:prune')
            ->expectsOutput('Deleted 2 events and 1 errors older than 90 days.')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing([$mixed->id, $fresh->id, $pending->id], Issue::query()->pluck('id')->all());
        $this->assertSame(2, Occurrence::query()->count());
        // The counters stay as they were.
        $this->assertSame($mixed->times_seen, $mixed->fresh()?->times_seen);

        $this->artisan('atrium:errors:prune', ['--days' => 5])->expectsOutput('Deleted 1 events and 1 errors older than 5 days.');
        $this->assertEqualsCanonicalizing([$mixed->id, $pending->id], Issue::query()->pluck('id')->all());
    }

    public function test_prune_works_in_chunks_and_follows_the_setting(): void
    {
        $issue = $this->issue(['last_seen_at' => now()->subDays(40)]);
        $rows = array_fill(0, 1001, ['issue_id' => $issue->id, 'level' => 'error', 'release' => '1', 'environment' => 'production', 'payload' => '{}', 'created_at' => now()->subDays(40)]);
        foreach (array_chunk($rows, 250) as $chunk) {
            Occurrence::query()->insert($chunk);
        }
        $this->settings(['retention_days' => 30]);

        $this->artisan('atrium:errors:prune')->expectsOutput('Deleted 1001 events and 1 errors older than 30 days.')->assertSuccessful();
    }

    public function test_prune_refuses_a_zero_period(): void
    {
        $this->artisan('atrium:errors:prune', ['--days' => 0])
            ->expectsOutput('The number of days must be 1 or more.')
            ->assertFailed();
    }

    public function test_prune_is_scheduled_daily(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(static fn ($event): bool => str_contains((string) $event->command, 'atrium:errors:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('40 3 * * *', $events->first()->expression);
    }

    public function test_the_test_command_sends_a_test_error(): void
    {
        $this->artisan('atrium:errors:test')
            ->expectsOutputToContain('sent to: database')
            ->assertSuccessful();

        $this->assertSame('Atrium errors: a test error, safe to resolve', Issue::query()->sole()->message);
        $this->assertSame(['test' => true], Occurrence::query()->sole()->payload['context']);
    }

    public function test_the_test_command_says_when_nothing_can_be_sent(): void
    {
        config(['atrium-errors.drivers' => []]);
        $this->artisan('atrium:errors:test')->expectsOutput('No error driver is switched on.')->assertFailed();

        config(['atrium-errors.drivers' => ['database'], 'atrium-errors.enabled' => false]);
        $this->artisan('atrium:errors:test')->expectsOutputToContain('Not sent')->assertFailed();
    }

    public function test_the_release_comes_from_the_config_then_git_then_composer(): void
    {
        $this->assertSame('v3.0.1', (new Release('/nowhere', '  v3.0.1 '))->current());

        $detached = $this->folder();
        File::put($detached.'/.git/HEAD', str_repeat('a1', 20)."\n");
        $this->assertSame(str_repeat('a1', 6), (new Release($detached))->current());

        $branch = $this->folder();
        File::put($branch.'/.git/HEAD', "ref: refs/heads/main\n");
        File::ensureDirectoryExists($branch.'/.git/refs/heads');
        File::put($branch.'/.git/refs/heads/main', str_repeat('b2', 20)."\n");
        $this->assertSame(str_repeat('b2', 6), (new Release($branch))->current());

        $packed = $this->folder();
        File::put($packed.'/.git/HEAD', "ref: refs/heads/release\n");
        File::put($packed.'/.git/packed-refs', "# pack-refs\n".str_repeat('c3', 20)." refs/heads/release\n");
        $this->assertSame(str_repeat('c3', 6), (new Release($packed))->current());

        // A worktree: .git is a file, the refs are in the common folder.
        $main = $this->folder();
        $worktree = $this->folder(false);
        File::ensureDirectoryExists($main.'/.git/worktrees/feature/');
        File::put($worktree.'/.git', 'gitdir: '.$main.'/.git/worktrees/feature');
        File::put($main.'/.git/worktrees/feature/HEAD', "ref: refs/heads/feature\n");
        File::put($main.'/.git/worktrees/feature/commondir', '../..');
        File::ensureDirectoryExists($main.'/.git/refs/heads');
        File::put($main.'/.git/refs/heads/feature', str_repeat('d4', 20));
        $this->assertSame(str_repeat('d4', 6), (new Release($worktree))->current());

        $relative = $this->folder(false);
        File::ensureDirectoryExists($relative.'/real-git');
        File::put($relative.'/.git', 'gitdir: real-git');
        File::put($relative.'/real-git/HEAD', str_repeat('e5', 20));
        $this->assertSame(str_repeat('e5', 6), (new Release($relative))->current());

        // An unknown ref and no git at all: Composer's version of the app.
        $broken = $this->folder();
        File::put($broken.'/.git/HEAD', "ref: refs/heads/gone\n");
        $composer = InstalledVersions::getRootPackage()['pretty_version'];
        $this->assertSame($composer, (new Release($broken))->current());
        $odd = $this->folder();
        File::put($odd.'/.git/HEAD', 'garbage');
        $this->assertSame($composer, (new Release($odd))->current());
    }

    public function test_chart_buckets_for_every_database(): void
    {
        $this->assertSame("DATE_FORMAT(created_at, '%Y-%m-%d %H')", Frequency::bucketExpression('mysql', 'hour'));
        $this->assertSame("DATE_FORMAT(created_at, '%Y-%m-%d')", Frequency::bucketExpression('mariadb', 'day'));
        $this->assertSame("to_char(created_at, 'YYYY-MM-DD HH24')", Frequency::bucketExpression('pgsql', 'hour'));
        $this->assertSame("to_char(created_at, 'YYYY-MM-DD')", Frequency::bucketExpression('pgsql', 'day'));
        $this->assertSame("strftime('%Y-%m-%d %H', created_at)", Frequency::bucketExpression('sqlite', 'hour'));
        $this->assertSame("strftime('%Y-%m-%d', created_at)", Frequency::bucketExpression('sqlite', 'day'));
    }

    public function test_the_daily_chart_counts_thirty_days(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $issue = $this->issue();
        $this->occurrence($issue, ['created_at' => now()->subDays(3)]);
        $this->occurrence($issue, ['created_at' => now()->subDays(3)->subHour()]);
        $this->occurrence($issue, ['created_at' => now()->subDays(40)]);
        $other = $this->issue();
        $this->occurrence($other);

        $daily = $this->app->make(Frequency::class)->daily($issue->id);

        $this->assertCount(30, $daily);
        $this->assertSame(2, $daily[26]['value']);
        $this->assertSame(2, array_sum(array_column($daily, 'value')));
        $this->assertSame(now()->locale('en')->isoFormat('D MMM'), $daily[29]['label']);
        $this->assertSame(3, array_sum(array_column($this->app->make(Frequency::class)->daily(), 'value')));
    }

    public function test_the_chart_counts_are_numbers_even_when_the_database_hands_out_strings(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $issue = $this->issue();
        $this->occurrence($issue, ['created_at' => now()->subDays(3)]);
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);

        try {
            $daily = $this->app->make(Frequency::class)->daily($issue->id);
        } finally {
            $pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
        }

        $this->assertSame(1, $daily[26]['value']);
        $this->assertSame(0, $daily[25]['value']);
    }

    public function test_breadcrumbs_are_a_ring_and_start_clean_for_each_job(): void
    {
        $ring = new Breadcrumbs(2);
        $ring->add('a', '1');
        $ring->add('b', '2');
        $ring->add('c', '3');
        $this->assertSame(['b', 'c'], array_column($ring->all(), 'category'));

        $off = new Breadcrumbs(0);
        $off->add('a', '1');
        $this->assertSame([], $off->all());

        $shared = $this->app->make(Breadcrumbs::class);
        $shared->add('log.info', 'before the job');
        event(new JobProcessing('sync', $this->createMock(Job::class)));
        $this->assertSame([], $shared->all());
    }

    public function test_frames_have_limits_and_skip_unreadable_files(): void
    {
        $deep = static function (int $n) use (&$deep): never {
            $n === 0 ? throw new RuntimeException('Deep') : $deep($n - 1);
        };

        try {
            $deep(80);
        } catch (RuntimeException $e) {
            $frames = (new Frames(base_path(), 2))->of($e);
        }

        $this->assertCount(Frames::MAX_FRAMES, $frames);
        $this->assertCount(2, $frames[0]['context']['pre']);
        $this->assertCount(15, array_filter($frames, static fn (array $frame): bool => isset($frame['context'])));

        $without = (new Frames(base_path(), 0))->of(new RuntimeException('No code'));
        $this->assertArrayNotHasKey('context', $without[0]);

        $eval = eval('return new RuntimeException("From eval");');
        $this->assertArrayNotHasKey('context', (new Frames(base_path()))->of($eval)[0]);
    }

    public function test_the_module_listens_only_when_installed(): void
    {
        config(['atrium.modules' => []]);
        $provider = new ErrorsServiceProvider($this->app);

        $this->assertFalse($provider::installed());
        $provider->boot();

        config(['atrium.modules' => ['alveby/atrium-errors']]);
        $this->assertTrue($provider::installed());
    }

    private function folder(bool $git = true): string
    {
        $folder = sys_get_temp_dir().'/atrium-release-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($git ? $folder.'/.git' : $folder);
        $this->folders[] = $folder;

        return $folder;
    }

    public function test_a_browser_stack_is_cut_at_the_frame_limit(): void
    {
        $frames = (new Frames(base_path()))->ofBrowserStack(str_repeat("at render (http://localhost/app.js:1:1)\n", Frames::MAX_FRAMES + 5), 'http://localhost');

        $this->assertCount(Frames::MAX_FRAMES, $frames);
    }

    public function test_a_line_past_the_end_of_the_file_has_no_code(): void
    {
        $e = new RuntimeException('Moved code');
        (new \ReflectionProperty($e, 'line'))->setValue($e, 99999);

        $frame = (new Frames(base_path()))->of($e)[0];

        $this->assertSame(99999, $frame['line']);
        $this->assertArrayNotHasKey('context', $frame);
    }
}
