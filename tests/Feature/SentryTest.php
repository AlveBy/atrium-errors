<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Capture\EventFactory;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Reporters\Sentry\Dsn;
use Atrium\Errors\Reporters\Sentry\HttpTransport;
use Atrium\Errors\Reporters\Sentry\Transport;
use Atrium\Errors\Reporters\SentryReporter;
use Atrium\Errors\Tests\TestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * The Sentry driver speaks the Sentry protocol itself, so it works with
 * Sentry, GlitchTip and anything compatible, without the SDK installed.
 */
final class SentryTest extends TestCase
{
    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new FakeTransport;
        $this->app->instance(Transport::class, $this->transport);
        config([
            'atrium-errors.drivers' => ['database', 'sentry'],
            'atrium-errors.sentry.dsn' => 'https://public123@errors.example.test/42',
        ]);
    }

    public function test_an_exception_goes_to_sentry_and_to_the_database(): void
    {
        $event = $this->errors()->capture(new RuntimeException('Booking failed', 0, new LogicException('No rooms left for user@example.com')), 'job', ['job' => 'Sync']);

        $this->assertSame(1, Occurrence::query()->count());
        $this->assertCount(1, $this->transport->sent);
        [$dsn, $payload] = $this->transport->sent[0];

        $this->assertSame('https://errors.example.test/api/42/envelope/', $dsn->envelopeUrl());
        $this->assertSame($event?->id, $payload['event_id']);
        $this->assertSame('php', $payload['platform']);
        $this->assertSame('error', $payload['level']);
        $this->assertSame('2.4.0', $payload['release']);
        $this->assertSame('testing', $payload['environment']);
        $this->assertSame([$event?->fingerprint], $payload['fingerprint']);
        $this->assertSame('job', $payload['tags']['kind']);
        $this->assertSame(['job' => 'Sync'], $payload['extra']);
        $this->assertSame('Laravel', $payload['contexts']['framework']['name']);

        // Innermost first, frames oldest first: as Sentry expects.
        $this->assertSame([LogicException::class, RuntimeException::class], array_column($payload['exception']['values'], 'type'));
        $frames = $payload['exception']['values'][1]['stacktrace']['frames'];
        $last = end($frames);
        $this->assertStringEndsWith('SentryTest.php', $last['filename']);
        $this->assertTrue($last['in_app']);
        $this->assertArrayHasKey('context_line', $last);

        $this->assertStringNotContainsString('user@example.com', (string) json_encode($payload));
    }

    public function test_a_message_and_a_browser_error_are_mapped_as_such(): void
    {
        $this->errors()->message('Low disk space', 'warning');
        $message = $this->transport->sent[0][1];

        $this->assertSame(['formatted' => 'Low disk space'], $message['message']);
        $this->assertArrayNotHasKey('exception', $message);
        $this->assertSame('warning', $message['level']);

        $event = $this->app->make(EventFactory::class)->fromBrowser(['message' => 'x is undefined', 'stack' => 'at render (https://localhost/app.js:3:9)'], request());
        $this->assertSame('javascript', SentryReporter::payload($event)['platform']);
    }

    public function test_the_request_user_and_breadcrumbs_are_mapped(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');
        Log::error('Payment provider timeout');

        $this->errors()->capture(new RuntimeException('After a log line'));
        $payload = $this->transport->sent[0][1];

        $this->assertSame(['id' => (string) $admin->id], $payload['user']);
        $this->assertSame('GET', $payload['request']['method']);
        $levels = array_column($payload['breadcrumbs']['values'], 'level', 'category');
        $this->assertSame('error', $levels['log.error']);
    }

    public function test_without_a_dsn_nothing_is_sent(): void
    {
        config(['atrium-errors.sentry.dsn' => null]);

        $this->errors()->capture(new RuntimeException('Local only'));

        $this->assertSame([], $this->transport->sent);
        $this->assertSame(1, Occurrence::query()->count());
    }

    public function test_a_dsn_saved_in_the_admin_wins_over_the_config(): void
    {
        $this->settings(['sentry_dsn' => 'https://k@glitchtip.example.test/7']);

        $this->errors()->capture(new RuntimeException('To GlitchTip'));

        $this->assertSame('https://glitchtip.example.test/api/7/envelope/', $this->transport->sent[0][0]->envelopeUrl());
    }

    public function test_the_http_transport_posts_an_envelope(): void
    {
        Http::fake(['*' => Http::response(['id' => 'abc'])]);
        $this->app->forgetInstance(Transport::class);
        $this->app->offsetUnset(Transport::class);
        $this->app->bind(Transport::class, fn (): Transport => new HttpTransport($this->app->make(Factory::class), 2));

        $event = $this->errors()->capture(new RuntimeException('Over HTTP'));

        Http::assertSent(static function (Request $request) use ($event): bool {
            [$header, $item, $body] = explode("\n", trim($request->body()));
            $header = json_decode($header, true);
            $item = json_decode($item, true);

            return $request->url() === 'https://errors.example.test/api/42/envelope/'
                && $request->header('X-Sentry-Auth')[0] === 'Sentry sentry_version=7, sentry_client='.HttpTransport::CLIENT.', sentry_key=public123'
                && $header['event_id'] === $event?->id
                && $header['dsn'] === 'https://public123@errors.example.test/42'
                && $item === ['type' => 'event', 'length' => strlen($body)]
                && json_decode($body, true)['event_id'] === $event?->id;
        });
    }

    public function test_a_refusing_sentry_is_noted_and_the_database_still_gets_the_event(): void
    {
        Http::fake(['*' => Http::response('rate limited', 429)]);
        $this->app->offsetUnset(Transport::class);
        $this->app->bind(Transport::class, fn (): Transport => new HttpTransport($this->app->make(Factory::class)));

        $this->errors()->capture(new RuntimeException('Sentry is busy'));

        $this->assertSame(1, Occurrence::query()->count());
        $this->assertSame('sentry driver failed: RuntimeException: Sentry answered 429', $this->fallbackLines()[0]['note']);
    }

    /**
     * @return array<string,array{0:string,1:string,2:string}>
     */
    public static function dsns(): array
    {
        return [
            'sentry.io' => ['https://abc@o123.ingest.sentry.io/456', 'https://o123.ingest.sentry.io/api/456/envelope/', 'abc'],
            'with secret' => ['https://abc:old-secret@sentry.example.test/9', 'https://sentry.example.test/api/9/envelope/', 'abc'],
            'port and path' => ['http://key@localhost:9000/sentry/12/', 'http://localhost:9000/sentry/api/12/envelope/', 'key'],
        ];
    }

    #[DataProvider('dsns')]
    public function test_dsns_are_parsed(string $dsn, string $url, string $key): void
    {
        $parsed = Dsn::parse($dsn);

        $this->assertSame($url, $parsed->envelopeUrl());
        $this->assertSame($key, $parsed->publicKey);
        $this->assertSame(trim($dsn), (string) $parsed);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function badDsns(): array
    {
        return [
            'empty' => [''],
            'no key' => ['https://sentry.example.test/1'],
            'no project' => ['https://key@sentry.example.test/'],
            'ftp' => ['ftp://key@sentry.example.test/1'],
            'odd project' => ['https://key@sentry.example.test/a-b'],
            'garbage' => ['http:///:@'],
        ];
    }

    #[DataProvider('badDsns')]
    public function test_bad_dsns_are_refused(string $dsn): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dsn::parse($dsn);
    }
}

final class FakeTransport implements Transport
{
    /** @var array<int,array{0:Dsn,1:array<string,mixed>}> */
    public array $sent = [];

    public function send(Dsn $dsn, array $event): void
    {
        $this->sent[] = [$dsn, $event];
    }
}
