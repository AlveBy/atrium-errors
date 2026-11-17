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
use Atrium\Errors\Event;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Reporters\DatabaseReporter;
use Atrium\Errors\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Who hears about a new error, a returning one or a spike, and how often.
 */
final class AlertsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function telegramAnswers(int $status = 200): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => $status === 200], $status)]);
    }

    public function test_a_new_error_is_mailed_and_sent_to_telegram(): void
    {
        $this->telegramAnswers();
        $this->settings([
            'alert_email' => 'ops@example.com, oncall@example.com; not-an-address',
            'telegram_token' => '123456:ABC-def_ghi',
            'telegram_chat' => '-1001234567',
        ]);

        $this->errors()->capture(new RuntimeException('Checkout is broken'));
        $issue = Issue::query()->sole();

        Mail::assertSent(AlertMail::class, function (AlertMail $mail) use ($issue): bool {
            $this->assertSame('New error on Northwind Travel: RuntimeException', $mail->envelope()->subject);
            $this->assertTrue($mail->hasMetadata('atrium_purpose', 'errors'));
            $text = $mail->alert->text();
            $this->assertStringContainsString('RuntimeException: Checkout is broken', $text);
            $this->assertStringContainsString('Events: 1, users: 0', $text);
            $this->assertStringContainsString('Release: 2.4.0', $text);
            $this->assertStringContainsString(route('admin.errors.show', $issue->id), $text);
            $mail->assertSeeInText('Checkout is broken');

            return $mail->hasTo('ops@example.com') && $mail->hasTo('oncall@example.com') && ! $mail->hasTo('not-an-address');
        });

        Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://api.telegram.org/bot123456:ABC-def_ghi/sendMessage'
            && $request['chat_id'] === '-1001234567'
            && str_starts_with((string) $request['text'], 'New error on Northwind Travel: RuntimeException')
            && $request['disable_web_page_preview'] === true);
    }

    public function test_the_second_event_of_an_issue_alerts_nobody(): void
    {
        $this->settings(['alert_email' => 'ops@example.com']);

        foreach (['first', 'second', 'third'] as $text) {
            $this->errors()->capture(new RuntimeException('Same bug '.$text));
        }

        Mail::assertSentCount(1);
    }

    public function test_a_returning_error_alerts_again_each_time_it_returns(): void
    {
        $this->settings(['alert_email' => 'ops@example.com']);
        $reporter = $this->app->make(DatabaseReporter::class);
        $alerter = $this->app->make(Alerter::class);

        [$issue] = $reporter->record($this->event());
        $this->assertSame(Alerter::NEW, $alerter->consider($issue, DatabaseReporter::OUTCOME_NEW));

        foreach ([1, 2] as $round) {
            $issue->update(['status' => Issue::RESOLVED]);
            $this->travel(1)->minutes();
            [$issue, $outcome] = $reporter->record($this->event());
            $this->assertSame(DatabaseReporter::OUTCOME_REGRESSION, $outcome);
            $this->assertSame(Alerter::REGRESSION, $alerter->consider($issue, $outcome), 'Round '.$round);
        }

        Mail::assertSent(AlertMail::class, static fn (AlertMail $mail): bool => str_starts_with($mail->envelope()->subject, 'An error came back'));
    }

    public function test_a_spike_alerts_once_an_hour(): void
    {
        $this->settings(['alert_email' => 'ops@example.com', 'alert_new' => false, 'spike_per_hour' => 3]);
        $reporter = $this->app->make(DatabaseReporter::class);
        $alerter = $this->app->make(Alerter::class);

        $reasons = [];
        for ($i = 0; $i < 5; $i++) {
            [$issue, $outcome] = $reporter->record($this->event());
            $reasons[] = $alerter->consider($issue, $outcome);
        }

        $this->assertSame([null, null, Alerter::SPIKE, null, null], $reasons);

        $this->travel(61)->minutes();
        for ($i = 0; $i < 3; $i++) {
            [$issue, $outcome] = $reporter->record($this->event());
            $last = $alerter->consider($issue, $outcome);
        }
        $this->assertSame(Alerter::SPIKE, $last);
        Mail::assertSent(AlertMail::class, static fn (AlertMail $mail): bool => str_starts_with($mail->envelope()->subject, 'Error spike'));
    }

    public function test_no_more_than_the_limit_goes_out_in_ten_minutes(): void
    {
        $this->settings(['alert_email' => 'ops@example.com']);
        config(['atrium-errors.alerts.max_per_10_minutes' => 2]);

        foreach (range(1, 5) as $n) {
            $this->errors()->capture($this->elsewhere($n));
        }

        $this->assertSame(5, Issue::query()->count());
        Mail::assertSentCount(2);
    }

    public function test_switched_off_reasons_ignored_issues_and_no_channels_stay_silent(): void
    {
        Http::fake();
        $alerter = $this->app->make(Alerter::class);
        $issue = $this->issue();

        // No channel set.
        $this->assertNull($alerter->consider($issue, DatabaseReporter::OUTCOME_NEW));

        $this->settings(['alert_email' => 'ops@example.com', 'alert_new' => false, 'alert_regression' => false, 'spike_per_hour' => 0]);
        $this->assertNull($alerter->consider($issue, DatabaseReporter::OUTCOME_NEW));
        $this->assertNull($alerter->consider($issue, DatabaseReporter::OUTCOME_REGRESSION));
        $this->assertNull($alerter->consider($issue, DatabaseReporter::OUTCOME_SEEN));

        $this->settings(['alert_email' => 'ops@example.com', 'alert_new' => true]);
        $issue->update(['status' => Issue::IGNORED]);
        $this->assertNull($alerter->consider($issue, DatabaseReporter::OUTCOME_NEW));

        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_a_failing_channel_is_noted_without_the_token_and_the_other_still_works(): void
    {
        $this->telegramAnswers(400);
        $this->settings(['alert_email' => 'ops@example.com', 'telegram_token' => '987:SECRET-token', 'telegram_chat' => '@northwind_ops']);

        $this->errors()->capture(new RuntimeException('Telegram is wrong'));

        Mail::assertSentCount(1);
        $notes = implode("\n", array_column($this->fallbackLines(), 'note'));
        $this->assertStringContainsString('alert telegram failed', $notes);
        $this->assertStringNotContainsString('SECRET-token', $notes);
    }

    public function test_a_failing_mailer_is_noted_and_telegram_still_gets_it(): void
    {
        $this->telegramAnswers();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP refused'));
        $this->settings(['alert_email' => 'ops@example.com', 'telegram_token' => '1:abc', 'telegram_chat' => '42']);

        $this->errors()->capture(new RuntimeException('Mail is down'));

        Http::assertSentCount(1);
        $this->assertSame('alert email failed: SMTP refused', $this->fallbackLines()[0]['note']);
    }

    public function test_an_alert_without_place_or_release_and_without_the_admin_route(): void
    {
        $issue = $this->issue(['culprit' => null, 'last_release' => null, 'message' => str_repeat('m', 400)]);
        $alert = new Alert($issue, Alerter::NEW);

        $this->assertSame([$issue->class.': '.str_repeat('m', 300), 'Events: 0, users: 0'], $alert->lines());
        $this->assertNotNull($alert->url());
    }

    private function event(): Event
    {
        return $this->app->make(EventFactory::class)->fromThrowable(new RuntimeException('Repeating'));
    }

    private function elsewhere(int $n): \Exception
    {
        // Another class per issue: five different bugs.
        $classes = [\LogicException::class, \DomainException::class, \RangeException::class, \UnexpectedValueException::class, \OverflowException::class];

        return new $classes[$n - 1]('Bug '.$n);
    }
}
