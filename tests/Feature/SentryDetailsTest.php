<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Event;
use Atrium\Errors\Reporters\Sentry\Dsn;
use Atrium\Errors\Reporters\Sentry\HttpTransport;
use Atrium\Errors\Reporters\Sentry\Transport;
use Atrium\Errors\Reporters\SentryReporter;
use Atrium\Errors\Support\Options;
use Atrium\Errors\Tests\TestCase;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The bytes that go to Sentry, the wait for it, and when nothing goes.
 */
final class SentryDetailsTest extends TestCase
{
    public function test_the_reporter_does_nothing_without_a_dsn(): void
    {
        $transport = $this->createMock(Transport::class);
        $transport->expects($this->never())->method('send');
        config(['atrium-errors.sentry.dsn' => null]);

        (new SentryReporter($transport, $this->app->make(Options::class)))->report($this->event());

        $this->addToAssertionCount(1);
    }

    public function test_the_reporter_hands_the_parsed_dsn_and_the_payload_to_the_transport(): void
    {
        $event = $this->event();
        $transport = $this->createMock(Transport::class);
        $transport->expects($this->once())->method('send')->with(
            $this->callback(static fn (Dsn $dsn): bool => $dsn->envelopeUrl() === 'https://sentry.example.test/api/9/envelope/'),
            SentryReporter::payload($event),
        );
        config(['atrium-errors.sentry.dsn' => ' https://key@sentry.example.test/9 ']);

        (new SentryReporter($transport, $this->app->make(Options::class)))->report($event);
    }

    public function test_an_envelope_is_a_header_an_item_header_and_the_event_each_on_a_line_with_a_last_newline(): void
    {
        $dsn = Dsn::parse('https://key@sentry.example.test/9');
        $event = ['event_id' => 'evt-1', 'message' => 'Нет /места'];

        $envelope = HttpTransport::envelope($dsn, $event);

        $this->assertStringEndsWith("\n", $envelope);
        $this->assertStringStartsNotWith("\n", $envelope);
        $lines = explode("\n", $envelope);
        $this->assertCount(4, $lines);
        $this->assertSame('', $lines[3]);
        $header = json_decode($lines[0], true);
        $this->assertSame(['event_id', 'dsn', 'sent_at'], array_keys($header));
        $this->assertSame('evt-1', $header['event_id']);
        $this->assertSame('https://key@sentry.example.test/9', $header['dsn']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $header['sent_at']);
        $this->assertSame('{"event_id":"evt-1","message":"Нет /места"}', $lines[2]);
        $this->assertSame('{"type":"event","length":'.strlen($lines[2]).'}', $lines[1]);
        $this->assertStringContainsString('"dsn":"https://key@sentry.example.test/9"', $lines[0]);
    }

    public function test_an_event_without_an_id_has_a_null_one_in_the_header(): void
    {
        $header = json_decode(explode("\n", HttpTransport::envelope(Dsn::parse('https://key@sentry.example.test/9'), ['message' => 'x']))[0], true);

        $this->assertArrayHasKey('event_id', $header);
        $this->assertNull($header['event_id']);
    }

    public function test_an_event_that_cannot_be_encoded_is_still_sent_in_part(): void
    {
        $envelope = HttpTransport::envelope(Dsn::parse('https://key@sentry.example.test/9'), ['event_id' => 'evt-1', 'message' => "Broken \xB1\x31", 'level' => 'error']);

        $lines = explode("\n", $envelope);
        $this->assertSame(['event_id' => 'evt-1', 'message' => null, 'level' => 'error'], json_decode($lines[2], true));
    }

    public function test_the_wait_for_sentry_is_three_seconds_unless_told_otherwise(): void
    {
        $timeouts = [];
        Http::globalMiddleware(static function (callable $handler) use (&$timeouts): callable {
            return static function ($request, array $options) use ($handler, &$timeouts) {
                $timeouts[] = $options['timeout'] ?? null;

                return $handler($request, $options);
            };
        });
        Http::fake(['*' => Http::response(['id' => 'abc'])]);
        $dsn = Dsn::parse('https://key@sentry.example.test/9');
        $factory = $this->app->make(Factory::class);

        (new HttpTransport($factory))->send($dsn, ['event_id' => 'a']);
        (new HttpTransport($factory, 8))->send($dsn, ['event_id' => 'b']);

        $this->assertSame([3, 8], $timeouts);
    }

    public function test_the_container_waits_for_sentry_as_configured_and_three_seconds_by_default(): void
    {
        $timeouts = [];
        Http::globalMiddleware(static function (callable $handler) use (&$timeouts): callable {
            return static function ($request, array $options) use ($handler, &$timeouts) {
                $timeouts[] = $options['timeout'] ?? null;

                return $handler($request, $options);
            };
        });
        Http::fake(['*' => Http::response(['id' => 'abc'])]);
        $dsn = Dsn::parse('https://key@sentry.example.test/9');

        config(['atrium-errors.sentry' => ['dsn' => null]]);
        $this->app->make(Transport::class)->send($dsn, ['event_id' => 'a']);

        config(['atrium-errors.sentry' => ['dsn' => null, 'timeout' => 7]]);
        $this->app->make(Transport::class)->send($dsn, ['event_id' => 'b']);

        $this->assertSame([3, 7], $timeouts);
    }

    #[DataProvider('paddedDsns')]
    public function test_a_dsn_with_spaces_around_it_is_the_dsn_without_them(string $given): void
    {
        $dsn = Dsn::parse($given);

        $this->assertSame('https://key@sentry.example.test/9', (string) $dsn);
        $this->assertSame('https://sentry.example.test/api/9/envelope/', $dsn->envelopeUrl());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function paddedDsns(): array
    {
        return [
            'none' => ['https://key@sentry.example.test/9'],
            'before' => ['  https://key@sentry.example.test/9'],
            'after' => ["https://key@sentry.example.test/9 \n"],
            'both' => ["\t https://key@sentry.example.test/9 \r\n"],
        ];
    }

    public function test_a_dsn_made_only_of_spaces_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dsn::parse('   ');
    }

    private function event(): Event
    {
        return new Event('evt-1', 'php', 'error', 'E', 'm', [], [], [], [], 'production', '2.4.0', [], 'fp-1', CarbonImmutable::parse('2025-11-17 12:07:03 UTC'));
    }
}
