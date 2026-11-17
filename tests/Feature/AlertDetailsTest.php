<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Alerts\Alert;
use Atrium\Errors\Alerts\Alerter;
use Atrium\Errors\Alerts\AlertMail;
use Atrium\Errors\Capture\EventFactory;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Reporters\DatabaseReporter;
use Atrium\Errors\Tests\TestCase;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The words of an alert, the keys that remember it was sent, and how long.
 */
final class AlertDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // Twelve past seven minutes into a ten-minute window: not on a boundary.
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:07:00 UTC'));
    }

    public function test_an_alert_tells_what_where_how_often_and_where_to_look(): void
    {
        $issue = $this->issue(['times_seen' => 3, 'users_seen' => 1]);
        $alert = new Alert($issue, Alerter::NEW);

        $this->assertSame([
            'App\\Booking\\RoomUnavailable: Room 12 is not free on the chosen dates',
            'Where: App\\Booking\\Reserve::handle (app/Booking/Reserve.php)',
            'Events: 3, users: 1',
            'Release: 2.4.0',
        ], $alert->lines());
        $this->assertSame(route('admin.errors.show', $issue->id), $alert->url());
        $this->assertSame(
            "New error on Northwind Travel: RoomUnavailable\n\n".implode("\n", $alert->lines())."\n\n".route('admin.errors.show', $issue->id),
            $alert->text(),
        );
    }

    #[DataProvider('messages')]
    public function test_the_message_of_an_alert_is_cut_by_characters_not_bytes(string $message, string $shown): void
    {
        $alert = new Alert($this->issue(['message' => $message, 'culprit' => null, 'last_release' => null]), Alerter::NEW);

        $this->assertSame('App\\Booking\\RoomUnavailable: '.$shown, $alert->lines()[0]);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function messages(): array
    {
        return [
            'short' => ['Room 12', 'Room 12'],
            'longest kept' => [str_repeat('a', 300), str_repeat('a', 300)],
            'one too long' => [str_repeat('a', 301), str_repeat('a', 300)],
            'Cyrillic' => [str_repeat('я', 400), str_repeat('я', 300)],
            'Chinese' => [str_repeat('花', 400), str_repeat('花', 300)],
            'emoji' => [str_repeat('🪑', 400), str_repeat('🪑', 300)],
        ];
    }

    public function test_the_mail_is_a_plain_text_view_of_the_alert(): void
    {
        $alert = new Alert($this->issue(), Alerter::SPIKE);
        $content = (new AlertMail($alert))->content();

        $this->assertSame('atrium-errors::mail.alert', $content->text);
        $this->assertSame(['text' => $alert->text()], $content->with);
    }

    public function test_the_site_name_in_the_subject_is_the_application_name(): void
    {
        config(['app.name' => 'Рога и копыта']);

        $this->assertSame('Error spike on Рога и копыта: RoomUnavailable', (new Alert($this->issue(), Alerter::SPIKE))->subject());
    }

    public function test_a_new_error_is_remembered_for_thirty_days(): void
    {
        $this->settings(['alert_email' => 'ops@example.com']);
        $alerter = $this->app->make(Alerter::class);
        $issue = $this->issue();

        $this->assertSame(Alerter::NEW, $alerter->consider($issue, DatabaseReporter::OUTCOME_NEW));
        $this->assertTrue(Cache::get('atrium-errors:alert:new:'.$issue->id));
        $this->assertFalse(Cache::has('atrium-errors:alert:new:'.($issue->id + 1)));

        $this->travel(30 * 86400 - 1)->seconds();
        $this->assertNull($alerter->consider($issue, DatabaseReporter::OUTCOME_NEW));

        $this->travel(1)->seconds();
        $this->assertSame(Alerter::NEW, $alerter->consider($issue, DatabaseReporter::OUTCOME_NEW));
    }

    public function test_a_spike_is_remembered_for_an_hour(): void
    {
        $this->settings(['alert_email' => 'ops@example.com', 'alert_new' => false, 'spike_per_hour' => 2]);
        $reporter = $this->app->make(DatabaseReporter::class);
        $alerter = $this->app->make(Alerter::class);
        $consider = function () use ($reporter, $alerter): ?string {
            [$issue, $outcome] = $reporter->record($this->app->make(EventFactory::class)->fromThrowable(new \RuntimeException('Spiking')));

            return $alerter->consider($issue, $outcome);
        };

        $consider();
        $this->assertSame(Alerter::SPIKE, $consider());
        $this->assertTrue(Cache::has('atrium-errors:alert:spike:'.Issue::query()->value('id')));

        $this->travel(3599)->seconds();
        $this->assertNull($consider());

        $this->travel(1)->seconds();
        $this->assertSame(Alerter::SPIKE, $consider());
    }

    public function test_a_regression_is_remembered_by_the_moment_it_came_back(): void
    {
        $this->settings(['alert_email' => 'ops@example.com']);
        $alerter = $this->app->make(Alerter::class);
        $back = CarbonImmutable::parse('2026-11-17 11:00:00 UTC');
        $issue = $this->issue(['regressed_at' => $back]);

        $this->assertSame(Alerter::REGRESSION, $alerter->consider($issue, DatabaseReporter::OUTCOME_REGRESSION));
        $this->assertTrue(Cache::has('atrium-errors:alert:regression:'.$issue->id.':'.$back->getTimestamp()));
        $this->assertNull($alerter->consider($issue, DatabaseReporter::OUTCOME_REGRESSION));
    }

    public function test_a_regression_without_a_moment_is_still_alerted_once(): void
    {
        $this->settings(['alert_email' => 'ops@example.com']);
        $alerter = $this->app->make(Alerter::class);
        $issue = $this->issue(['regressed_at' => null]);

        $this->assertSame(Alerter::REGRESSION, $alerter->consider($issue, DatabaseReporter::OUTCOME_REGRESSION));
        $this->assertTrue(Cache::has('atrium-errors:alert:regression:'.$issue->id.':'));
        $this->assertNull($alerter->consider($issue, DatabaseReporter::OUTCOME_REGRESSION));
    }

    public function test_the_ten_minutes_are_counted_in_windows_that_are_forgotten_after_fifteen(): void
    {
        $this->settings(['alert_email' => 'ops@example.com']);
        $alerter = $this->app->make(Alerter::class);
        $window = 'atrium-errors:alerts:'.intdiv(CarbonImmutable::now()->getTimestamp(), 600);

        $alerter->consider($this->issue(), DatabaseReporter::OUTCOME_NEW);
        $alerter->consider($this->issue(), DatabaseReporter::OUTCOME_NEW);

        $this->assertSame(2, Cache::get($window));

        $this->travel(899)->seconds();
        $this->assertSame(2, Cache::get($window));

        $this->travel(1)->seconds();
        $this->assertNull(Cache::get($window));
    }

    #[DataProvider('limits')]
    public function test_the_limit_of_alerts_in_ten_minutes(?int $limit, int $sent): void
    {
        $this->settings(['alert_email' => 'ops@example.com']);
        $alerts = ['email' => 'ops@example.com', 'new' => true, 'regression' => true, 'spike_per_hour' => 100];
        config(['atrium-errors.alerts' => $limit === null ? $alerts : [...$alerts, 'max_per_10_minutes' => $limit]]);
        $alerter = $this->app->make(Alerter::class);

        $reasons = [];
        for ($i = 0; $i < 14; $i++) {
            $reasons[] = $alerter->consider($this->issue(), DatabaseReporter::OUTCOME_NEW);
        }

        $this->assertCount($sent, array_filter($reasons));
        Mail::assertSentCount($sent);
    }

    /**
     * @return array<string,array{?int,int}>
     */
    public static function limits(): array
    {
        return [
            'not configured: ten' => [null, 10],
            'one' => [1, 1],
            'two' => [2, 2],
            'zero still lets one through' => [0, 1],
            'negative still lets one through' => [-5, 1],
            'more than there are errors' => [50, 14],
        ];
    }

    public function test_a_telegram_message_is_cut_to_four_thousand_characters_and_waits_five_seconds(): void
    {
        $timeouts = [];
        Http::globalMiddleware(static function (callable $handler) use (&$timeouts): callable {
            return static function ($request, array $options) use ($handler, &$timeouts) {
                $timeouts[] = $options['timeout'] ?? null;

                return $handler($request, $options);
            };
        });
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config(['app.name' => str_repeat('Я', 4100)]);
        $this->settings(['telegram_token' => '1:abc', 'telegram_chat' => '42']);

        $this->errors()->capture(new \RuntimeException('Long'));

        Http::assertSent(static fn (Request $request): bool => mb_strlen((string) $request['text']) === 4000 && str_starts_with((string) $request['text'], 'New error on '));
        $this->assertSame([5], $timeouts);
    }

    public function test_the_token_is_taken_out_of_a_connection_failure(): void
    {
        Http::fake(['api.telegram.org/*' => static fn () => throw new ConnectionException('cURL error 28: timed out for https://api.telegram.org/bot987:SECRET-token/sendMessage')]);
        $this->settings(['telegram_token' => '987:SECRET-token', 'telegram_chat' => '42']);

        $this->errors()->capture(new \RuntimeException('Unreachable'));

        $this->assertSame(
            'alert telegram failed: cURL error 28: timed out for https://api.telegram.org/bot[Filtered]/sendMessage',
            $this->fallbackLines()[0]['note'],
        );
    }

    #[DataProvider('halfConfiguredTelegram')]
    public function test_telegram_needs_both_the_token_and_the_chat(string $token, string $chat): void
    {
        Http::fake();
        $this->settings(['telegram_token' => $token, 'telegram_chat' => $chat]);

        $this->errors()->capture(new \RuntimeException('Half configured'));

        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function halfConfiguredTelegram(): array
    {
        return [
            'token only' => ['123:abc', ''],
            'chat only' => ['', '42'],
            'chat of spaces' => ['123:abc', '   '],
            'token of spaces' => ['   ', '42'],
        ];
    }
}
