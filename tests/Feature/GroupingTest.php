<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Capture\EventFactory;
use Atrium\Errors\Capture\Fingerprint;
use Atrium\Errors\Capture\Release;
use Atrium\Errors\Event;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Reporters\DatabaseReporter;
use Atrium\Errors\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

/**
 * What makes two events one issue, and what a resolved issue does when the
 * bug comes back.
 */
final class GroupingTest extends TestCase
{
    public function test_the_same_place_on_another_line_is_the_same_issue(): void
    {
        $first = $this->errors()->capture(new RuntimeException('Room 12 is taken'));

        $second = $this->errors()->capture(new RuntimeException('Room 31 is taken'));

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first->frames()[0]['line'], $second->frames()[0]['line']);
        $this->assertSame($first->fingerprint, $second->fingerprint);
        $this->assertSame(1, Issue::query()->count());
        $this->assertSame('Room 31 is taken', Issue::query()->sole()->message);
    }

    public function test_another_class_or_another_place_is_another_issue(): void
    {
        $a = $this->errors()->capture(new RuntimeException('Same text'));
        $b = $this->errors()->capture(new LogicException('Same text'));
        $c = $this->throwElsewhere();

        $this->assertCount(3, array_unique([$a?->fingerprint, $b?->fingerprint, $c?->fingerprint]));
        $this->assertSame(3, Issue::query()->count());
    }

    public function test_line_numbers_never_decide(): void
    {
        $fingerprint = new Fingerprint;
        $frames = static fn (int $shift): array => [
            ['file' => 'app/Booking/Reserve.php', 'line' => 40 + $shift, 'function' => 'App\\Booking\\Reserve::handle', 'in_app' => true],
            ['file' => 'vendor/laravel/framework/src/Pipeline.php', 'line' => 100, 'function' => 'Illuminate\\Pipeline::then', 'in_app' => false],
            ['file' => 'app/Http/Controllers/BookingController.php', 'line' => 12 + $shift, 'function' => 'App\\Http\\Controllers\\BookingController::{closure}', 'in_app' => true],
        ];

        $this->assertSame($fingerprint->of('Error', 'x', $frames(0)), $fingerprint->of('Error', 'y', $frames(7)));
    }

    public function test_build_hashes_of_views_and_bundles_do_not_split_an_issue(): void
    {
        $fingerprint = new Fingerprint;
        $view = static fn (string $hash): array => [['file' => 'storage/framework/views/'.$hash.'.php', 'line' => 3, 'function' => 'require', 'in_app' => false]];
        $bundle = static fn (string $hash): array => [['file' => '/build/assets/app-'.$hash.'.js', 'line' => 1, 'function' => 'render', 'in_app' => true]];

        $this->assertSame($fingerprint->of('ErrorException', 'a', $view('3f2a9c0d1e')), $fingerprint->of('ErrorException', 'a', $view('9b8c7d6e5f')));
        $this->assertSame($fingerprint->of('TypeError', 'a', $bundle('Bx91kQ2z')), $fingerprint->of('TypeError', 'a', $bundle('Zz00aa11')));
    }

    public function test_without_frames_the_message_decides_with_numbers_and_ids_blanked(): void
    {
        $fingerprint = new Fingerprint;
        $empty = [['file' => '', 'line' => 0, 'function' => '', 'in_app' => false]];

        $this->assertSame(
            $fingerprint->of('TypeError', "Cannot read properties of undefined (reading 'price') at 12", []),
            $fingerprint->of('TypeError', "Cannot read properties of undefined (reading 'title') at 99", $empty),
        );
        $this->assertNotSame($fingerprint->of('TypeError', 'Script error', []), $fingerprint->of('TypeError', 'Network down', []));
        $this->assertSame('Order <n> of <hex> for <str>', Fingerprint::normalizeMessage('Order 42 of 0x9f3a2b1c7d for "Anna"'));
    }

    public function test_a_resolved_issue_reopens_when_the_bug_comes_back(): void
    {
        $this->recordAgain('Tax rate missing');
        $issue = Issue::query()->sole();
        $issue->update(['status' => Issue::RESOLVED, 'resolved_at' => now()]);

        [$again, $outcome] = $this->recordAgain('Tax rate missing');

        $this->assertSame(DatabaseReporter::OUTCOME_REGRESSION, $outcome);
        $this->assertSame(Issue::NEW, $again->status);
        $this->assertNotNull($again->regressed_at);
        $this->assertNull($again->resolved_at);
        $this->assertSame(2, $again->times_seen);
    }

    public function test_resolved_in_the_next_release_stays_resolved_in_this_one(): void
    {
        $this->recordAgain('Old cache key');
        Issue::query()->sole()->update(['status' => Issue::RESOLVED, 'resolved_in' => '2.4.0', 'resolved_at' => now()]);

        // The same release is still running: late events change nothing.
        [$same, $outcome] = $this->recordAgain('Old cache key');
        $this->assertSame(DatabaseReporter::OUTCOME_SEEN, $outcome);
        $this->assertSame(Issue::RESOLVED, $same->status);

        // The fix went out in 2.5.0 and the bug is still there.
        config(['atrium-errors.release' => '2.5.0']);
        $this->app->forgetInstance(Release::class);
        [$next, $outcome] = $this->recordAgain('Old cache key');

        $this->assertSame(DatabaseReporter::OUTCOME_REGRESSION, $outcome);
        $this->assertSame(Issue::NEW, $next->status);
        $this->assertNull($next->resolved_in);
        $this->assertSame('2.5.0', $next->last_release);
        $this->assertSame('2.4.0', $next->first_release);
    }

    public function test_an_ignored_issue_keeps_counting_and_stays_ignored(): void
    {
        $this->recordAgain('Bot hit a dead link');
        Issue::query()->sole()->update(['status' => Issue::IGNORED]);

        [$issue, $outcome] = $this->recordAgain('Bot hit a dead link');

        $this->assertSame(DatabaseReporter::OUTCOME_SEEN, $outcome);
        $this->assertSame(Issue::IGNORED, $issue->status);
        $this->assertSame(2, $issue->times_seen);
    }

    public function test_an_issue_created_meanwhile_by_another_process_is_reused(): void
    {
        $event = $this->app->make(EventFactory::class)->fromThrowable(new RuntimeException('Race'));

        // Another worker inserts the same issue right after our look-up
        // found nothing, and before our insert.
        $done = false;
        DB::listen(static function (QueryExecuted $query) use (&$done, $event): void {
            if (! $done && str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'error_issues')) {
                $done = true;
                DB::table('error_issues')->insert(['fingerprint' => $event->fingerprint, 'kind' => 'php', 'level' => 'error', 'class' => 'RuntimeException', 'message' => 'Race', 'status' => 'new', 'times_seen' => 0, 'users_seen' => 0, 'first_seen_at' => now()]);
            }
        });

        [$issue, $outcome] = $this->app->make(DatabaseReporter::class)->record($event);

        $this->assertSame(DatabaseReporter::OUTCOME_SEEN, $outcome);
        $this->assertSame(1, Issue::query()->count());
        $this->assertSame(1, $issue->times_seen);
    }

    /**
     * An event from always the same place, through the reporter, to see its outcome.
     *
     * @return array{0:Issue,1:string}
     */
    private function recordAgain(string $message): array
    {
        $event = $this->app->make(EventFactory::class)->fromThrowable(new RuntimeException($message));

        return $this->app->make(DatabaseReporter::class)->record($event);
    }

    private function throwElsewhere(): ?Event
    {
        return $this->errors()->capture(new RuntimeException('Same text'));
    }
}
