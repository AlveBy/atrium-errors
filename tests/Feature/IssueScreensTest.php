<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Admin\Models\Site;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Tests\TestCase;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the list of errors and an error hand to the screen, value by value.
 */
final class IssueScreensTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:07:00 UTC'));
        $this->actingAs($this->admin(), 'admin');
    }

    public function test_a_row_of_the_list_says_everything_the_screen_shows(): void
    {
        $issue = $this->issue([
            'class' => 'App\\Booking\\RoomUnavailable',
            'message' => 'Room 12 is not free',
            'culprit' => 'Reserve::handle (app/Booking/Reserve.php)',
            'status' => Issue::NEW,
            'level' => 'fatal',
            'kind' => 'job',
            'times_seen' => 7,
            'users_seen' => 3,
            'first_seen_at' => '2026-11-10 08:00:00',
            'last_seen_at' => '2026-11-17 11:00:00',
            'last_release' => '2.5.0',
        ]);

        $row = $this->props('/admin/errors')['issues']['data'][0];

        $this->assertSame([
            'id' => $issue->id,
            'class' => 'App\\Booking\\RoomUnavailable',
            'short_class' => 'RoomUnavailable',
            'title' => 'Room 12 is not free',
            'culprit' => 'Reserve::handle (app/Booking/Reserve.php)',
            'status' => 'new',
            'level' => 'fatal',
            'kind' => 'job',
            'times_seen' => 7,
            'users_seen' => 3,
            'first_seen_at' => '2026-11-10T08:00:00+00:00',
            'last_seen_at' => '2026-11-17T11:00:00+00:00',
            'last_release' => '2.5.0',
            'url' => route('admin.errors.show', $issue->id),
        ], $row);
    }

    public function test_the_title_of_a_row_is_the_first_two_hundred_characters_of_the_message(): void
    {
        $this->issue(['message' => str_repeat('я', 201)]);

        $this->assertSame(str_repeat('я', 200), $this->props('/admin/errors')['issues']['data'][0]['title']);
    }

    public function test_a_row_without_moments_has_none(): void
    {
        $this->issue(['first_seen_at' => null, 'last_seen_at' => null]);

        $row = $this->props('/admin/errors')['issues']['data'][0];

        $this->assertNull($row['first_seen_at']);
        $this->assertNull($row['last_seen_at']);
    }

    public function test_the_filters_come_back_as_they_were_understood(): void
    {
        $this->assertSame([
            'q' => '',
            'status' => 'new',
            'level' => '',
            'kind' => '',
            'site' => null,
            'release' => '',
            'path' => '',
            'sort' => 'last_seen_at',
        ], $this->props('/admin/errors')['filters']);

        $this->assertSame([
            'q' => 'timeout',
            'status' => 'ignored',
            'level' => 'warning',
            'kind' => 'schedule',
            'site' => 12,
            'release' => '2.3.0',
            'path' => '/checkout?step=2',
            'sort' => 'users_seen',
        ], $this->props('/admin/errors?'.http_build_query([
            'q' => '  timeout ',
            'status' => 'ignored',
            'level' => 'warning',
            'kind' => 'schedule',
            'site' => '12',
            'release' => ' 2.3.0 ',
            'path' => ' /checkout?step=2  ',
            'sort' => 'users_seen',
        ]))['filters']);
    }

    public function test_the_filters_are_trimmed_even_when_the_host_does_not_trim_requests(): void
    {
        $this->withoutMiddleware(TrimStrings::class);

        $filters = $this->props('/admin/errors?'.http_build_query(['q' => '  timeout ', 'release' => ' 2.3.0 ', 'path' => ' /checkout  ']))['filters'];

        $this->assertSame(['timeout', '2.3.0', '/checkout'], [$filters['q'], $filters['release'], $filters['path']]);
    }

    #[DataProvider('sorts')]
    public function test_every_sort_orders_by_its_own_column_and_not_by_the_last_seen(string $sort): void
    {
        $low = $this->issue(['times_seen' => 1, 'users_seen' => 1, 'first_seen_at' => now()->subDays(9), 'last_seen_at' => now()]);
        $high = $this->issue(['times_seen' => 9, 'users_seen' => 9, 'first_seen_at' => now()->subDays(1), 'last_seen_at' => now()->subHour()]);

        $props = $this->props('/admin/errors?sort='.$sort);

        $this->assertSame($sort, $props['filters']['sort']);
        $this->assertSame(
            $sort === 'last_seen_at' ? [$low->id, $high->id] : [$high->id, $low->id],
            array_column($props['issues']['data'], 'id'),
        );
    }

    /**
     * @return array<string,array{string}>
     */
    public static function sorts(): array
    {
        return [
            'last seen' => ['last_seen_at'],
            'first seen' => ['first_seen_at'],
            'times seen' => ['times_seen'],
            'users seen' => ['users_seen'],
        ];
    }

    public function test_an_unknown_sort_is_the_last_seen_and_not_a_column_of_the_request(): void
    {
        $older = $this->issue(['times_seen' => 9, 'last_seen_at' => now()->subHour()]);
        $newer = $this->issue(['times_seen' => 1, 'last_seen_at' => now()]);

        $props = $this->props('/admin/errors?sort=message');

        $this->assertSame('last_seen_at', $props['filters']['sort']);
        $this->assertSame([$newer->id, $older->id], array_column($props['issues']['data'], 'id'));
    }

    public function test_the_search_finds_the_words_anywhere_in_the_class_message_or_culprit(): void
    {
        $byMessage = $this->issue(['class' => 'A', 'message' => 'The gateway timeout came', 'culprit' => 'x']);
        $byClass = $this->issue(['class' => 'App\\GatewayError', 'message' => 'm', 'culprit' => 'x']);
        $byCulprit = $this->issue(['class' => 'B', 'message' => 'm', 'culprit' => 'in the Gateway::send']);
        $this->issue(['class' => 'C', 'message' => 'nothing', 'culprit' => 'x']);

        $ids = array_column($this->props('/admin/errors?q='.urlencode('  gateway '))['issues']['data'], 'id');

        $this->assertEqualsCanonicalizing([$byMessage->id, $byClass->id, $byCulprit->id], $ids);
        $this->assertSame([$byMessage->id], array_column($this->props('/admin/errors?q=timeout')['issues']['data'], 'id'));
        $this->assertSame([$byMessage->id], array_column($this->props('/admin/errors?q=came')['issues']['data'], 'id'));
    }

    public function test_the_path_may_be_given_with_or_without_the_slash_and_with_a_query(): void
    {
        $checkout = $this->issue();
        $this->occurrence($checkout, ['path' => '/checkout']);
        $other = $this->issue();
        $this->occurrence($other, ['path' => '/rooms']);

        foreach (['/checkout', 'checkout', '/checkout?step=2', '//checkout', 'checkout?a=b#c'] as $path) {
            $this->assertSame([$checkout->id], array_column($this->props('/admin/errors?path='.urlencode($path))['issues']['data'], 'id'), $path);
        }
    }

    public function test_the_release_and_the_path_are_cut_by_characters_and_trimmed(): void
    {
        $release = $this->props('/admin/errors?release='.urlencode(' '.str_repeat('я', 300)))['filters']['release'];
        $path = $this->props('/admin/errors?path='.urlencode(' '.str_repeat('ж', 600)))['filters']['path'];

        $this->assertSame(str_repeat('я', 255), $release);
        $this->assertSame(str_repeat('ж', 512), $path);
    }

    public function test_the_release_and_the_path_are_kept_whole_when_they_fit(): void
    {
        $this->assertSame(str_repeat('a', 255), $this->props('/admin/errors?release='.str_repeat('a', 255))['filters']['release']);
        $this->assertSame(str_repeat('a', 512), $this->props('/admin/errors?path='.str_repeat('a', 512))['filters']['path']);
    }

    public function test_the_list_has_twenty_five_to_a_page(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $this->issue(['last_seen_at' => now()->subMinutes($i)]);
        }

        $first = $this->props('/admin/errors')['issues'];
        $second = $this->props('/admin/errors?page=2')['issues'];

        $this->assertSame(25, $first['per_page']);
        $this->assertCount(25, $first['data']);
        $this->assertSame(26, $first['total']);
        $this->assertCount(1, $second['data']);
    }

    public function test_the_sites_to_choose_from_are_named_by_id(): void
    {
        $site = Site::query()->create(['name' => 'Second site', 'code' => 'second', 'domains' => ['second.example.test'], 'is_active' => true]);

        $sites = $this->props('/admin/errors')['options']['sites'];

        $this->assertContains(['id' => $site->id, 'name' => 'Second site'], $sites);
    }

    public function test_the_releases_to_choose_from_are_the_thirty_newest_of_the_last_two_hundred_errors(): void
    {
        // 199 errors of one release, the 200th of another, the 201st of a third, then 33 more, older still.
        for ($i = 1; $i <= 199; $i++) {
            $this->issue(['last_release' => 'A', 'last_seen_at' => now()->subMinutes($i)]);
        }
        $this->issue(['last_release' => 'B', 'last_seen_at' => now()->subMinutes(200)]);
        $this->issue(['last_release' => 'C', 'last_seen_at' => now()->subMinutes(201)]);

        $this->assertSame(['A', 'B'], $this->props('/admin/errors?status=all')['options']['releases']);
    }

    public function test_the_releases_to_choose_from_are_cut_at_thirty(): void
    {
        for ($i = 1; $i <= 35; $i++) {
            $this->issue(['last_release' => sprintf('r%02d', $i), 'last_seen_at' => now()->subMinutes($i)]);
        }

        $releases = $this->props('/admin/errors')['options']['releases'];

        $this->assertCount(30, $releases);
        $this->assertSame('r01', $releases[0]);
        $this->assertSame('r30', $releases[29]);
    }

    public function test_an_error_tells_the_whole_story_of_its_row_and_more(): void
    {
        $issue = $this->issue([
            'message' => 'Room 12 is not free',
            'status' => Issue::RESOLVED,
            'resolved_in' => '2.6.0',
            'first_release' => '2.3.0',
            'resolved_at' => '2026-11-16 10:00:00',
            'regressed_at' => '2026-11-17 09:30:00',
        ]);

        $props = $this->props('/admin/errors/'.$issue->id);

        $this->assertSame('Room 12 is not free', $props['issue']['message']);
        $this->assertSame('2.6.0', $props['issue']['resolved_in']);
        $this->assertSame('2.3.0', $props['issue']['first_release']);
        $this->assertSame('2026-11-16T10:00:00+00:00', $props['issue']['resolved_at']);
        $this->assertSame('2026-11-17T09:30:00+00:00', $props['issue']['regressed_at']);
        $this->assertSame($issue->id, $props['issue']['id']);
        $this->assertSame('resolved', $props['issue']['status']);
        $this->assertSame('Errors', $props['strings']['title']);
    }

    public function test_an_error_that_was_never_resolved_has_no_such_moments(): void
    {
        $issue = $this->issue(['resolved_at' => null, 'regressed_at' => null]);

        $props = $this->props('/admin/errors/'.$issue->id);

        $this->assertArrayHasKey('resolved_at', $props['issue']);
        $this->assertNull($props['issue']['resolved_at']);
        $this->assertNull($props['issue']['regressed_at']);
    }

    public function test_the_event_of_an_error_is_the_payload_with_the_columns_over_it(): void
    {
        $site = Site::query()->create(['name' => 'Second site', 'code' => 'second', 'domains' => ['second.example.test'], 'is_active' => true]);
        $issue = $this->issue();
        $event = $this->occurrence($issue, ['site_id' => $site->id, 'release' => '2.4.1', 'environment' => 'staging', 'created_at' => '2026-11-17 11:15:00']);

        $shown = $this->props('/admin/errors/'.$issue->id)['event'];

        $this->assertSame($event->id, $shown['id']);
        $this->assertSame($event->payload['id'], $shown['uuid']);
        $this->assertSame('2026-11-17T11:15:00+00:00', $shown['created_at']);
        $this->assertSame('2.4.1', $shown['release']);
        $this->assertSame('staging', $shown['environment']);
        $this->assertSame('Second site', $shown['site']);
        $this->assertSame('php', $shown['kind']);
    }

    public function test_an_event_of_no_site_names_none_and_of_an_unknown_one_its_number(): void
    {
        $issue = $this->issue();
        $this->occurrence($issue, ['site_id' => null]);

        $this->assertNull($this->props('/admin/errors/'.$issue->id)['event']['site']);

        $this->occurrence($issue, ['site_id' => 4242]);

        $this->assertSame('#4242', $this->props('/admin/errors/'.$issue->id)['event']['site']);
    }

    public function test_an_event_without_a_moment_still_shows(): void
    {
        $issue = $this->issue();
        $this->occurrence($issue, ['created_at' => null]);

        $props = $this->props('/admin/errors/'.$issue->id);

        $this->assertNull($props['event']['created_at']);
        $this->assertNull($props['occurrences']['data'][0]['created_at']);
    }

    public function test_the_occurrences_are_listed_newest_first_twenty_to_a_page(): void
    {
        $site = Site::query()->create(['name' => 'Second site', 'code' => 'second', 'domains' => ['second.example.test'], 'is_active' => true]);
        $issue = $this->issue();
        $made = [];
        for ($i = 0; $i < 21; $i++) {
            $made[] = $this->occurrence($issue, ['path' => '/rooms/'.$i, 'user_id' => $i, 'site_id' => $i === 20 ? $site->id : ($i === 19 ? 777 : null), 'release' => '2.'.$i, 'environment' => 'env'.$i, 'method' => $i === 20 ? 'POST' : 'GET']);
        }

        $props = $this->props('/admin/errors/'.$issue->id);
        $page = $props['occurrences'];

        $this->assertSame(20, $page['per_page']);
        $this->assertSame(21, $page['total']);
        $this->assertCount(20, $page['data']);
        $this->assertSame([
            'id' => $made[20]->id,
            'created_at' => '2026-11-17T12:07:00+00:00',
            'release' => '2.20',
            'environment' => 'env20',
            'method' => 'POST',
            'path' => '/rooms/20',
            'site' => 'Second site',
            'user_id' => 20,
        ], $page['data'][0]);
        $this->assertSame('#777', $page['data'][1]['site']);
        $this->assertNull($page['data'][2]['site']);
        $this->assertSame($made[1]->id, $page['data'][19]['id']);
        $this->assertSame([$made[0]->id], array_column($this->props('/admin/errors/'.$issue->id.'?page=2')['occurrences']['data'], 'id'));
    }

    public function test_the_top_lists_hold_five_each_most_often_first_then_by_name(): void
    {
        $issue = $this->issue();
        foreach (['/b' => 2, '/a' => 2, '/c' => 3, '/d' => 1, '/e' => 1, '/f' => 1, '/g' => 1] as $path => $times) {
            for ($i = 0; $i < $times; $i++) {
                $this->occurrence($issue, ['path' => $path, 'release' => $path === '/c' ? '2.5.0' : '2.4.0']);
            }
        }
        $this->occurrence($issue, ['path' => null, 'release' => null]);

        $top = $this->props('/admin/errors/'.$issue->id)['top'];

        $this->assertSame([
            ['value' => '/c', 'count' => 3],
            ['value' => '/a', 'count' => 2],
            ['value' => '/b', 'count' => 2],
            ['value' => '/d', 'count' => 1],
            ['value' => '/e', 'count' => 1],
        ], $top['paths']);
        $this->assertSame([
            ['value' => '2.4.0', 'count' => 8],
            ['value' => '2.5.0', 'count' => 3],
        ], $top['releases']);
    }

    public function test_the_top_counts_are_numbers_even_when_the_database_hands_out_strings(): void
    {
        $issue = $this->issue();
        $this->occurrence($issue, ['path' => '/rooms']);
        $this->occurrence($issue, ['path' => '/rooms']);
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);

        try {
            $top = $this->props('/admin/errors/'.$issue->id)['top'];
        } finally {
            $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        }

        $this->assertSame([['value' => '/rooms', 'count' => 2]], $top['paths']);
    }

    public function test_a_status_without_the_next_release_flag_is_accepted(): void
    {
        $issue = $this->issue();

        $this->post('/admin/errors/'.$issue->id.'/status', ['status' => 'resolved'])->assertSessionHasNoErrors();

        $this->assertSame(Issue::RESOLVED, $issue->refresh()->status);
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function searches(): array
    {
        return [
            'percent is a percent' => ['50%', ['Discount of 50% refused']],
            'digits without it' => ['500', ['Discount of 500 refused']],
            'underscore is an underscore' => ['a_b', ['Field a_b missing']],
            'the escape character itself' => ['!x', ['Code !x invalid']],
            'a backslash' => ['C:\\temp', ['Cannot write C:\\temp']],
            'class' => ['CouponExpired', ['Coupon gone']],
            'place' => ['Checkout::apply', ['Discount of 50% refused']],
            'nothing like it' => ['50%off', []],
            'small letters find capitals' => ['coupon GONE', ['Coupon gone']],
            'capitals of any alphabet' => ['ЗАНЯТ', ['Номер занят']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('searches')]
    public function test_the_search_finds_signs_as_they_are_typed(string $q, array $expected): void
    {
        $this->issue(['message' => 'Discount of 50% refused', 'culprit' => 'App\\Shop\\Checkout::apply (app/Shop/Checkout.php)']);
        $this->issue(['message' => 'Discount of 500 refused']);
        $this->issue(['message' => 'Field a_b missing']);
        $this->issue(['message' => 'Field axb missing']);
        $this->issue(['message' => 'Code !x invalid']);
        $this->issue(['message' => 'Code x invalid']);
        $this->issue(['message' => 'Cannot write C:\\temp']);
        $this->issue(['message' => 'Coupon gone', 'class' => 'App\\Shop\\CouponExpired']);
        $this->issue(['message' => 'Номер занят']);

        $found = array_column($this->props('/admin/errors?'.http_build_query(['q' => $q]))['issues']['data'], 'title');

        $this->assertSame($expected, $found);
    }

    /**
     * The props Inertia handed to the page.
     *
     * @return array<string,mixed>
     */
    private function props(string $url): array
    {
        return $this->get($url)->assertOk()->viewData('page')['props'];
    }
}
