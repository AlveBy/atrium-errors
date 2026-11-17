<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Unit;

use Atrium\Errors\Event;
use Atrium\Errors\Reporters\SentryReporter;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The event in Sentry's words, key by key.
 */
final class SentryPayloadTest extends TestCase
{
    public function test_an_event_is_mapped_to_everything_sentry_takes(): void
    {
        $event = $this->event(
            exceptions: [
                ['class' => 'App\\Outer', 'message' => 'Outer failed', 'frames' => [
                    ['file' => 'app/Outer.php', 'line' => 10, 'function' => 'Outer::run', 'in_app' => true, 'context' => ['pre' => ['a'], 'line' => 'b', 'post' => ['c']]],
                    ['file' => 'vendor/lib.php', 'line' => 20, 'function' => 'lib', 'in_app' => false],
                ]],
                ['class' => 'App\\Inner', 'message' => 'Inner failed', 'frames' => [
                    ['file' => 'app/Inner.php', 'line' => 3, 'function' => 'Inner::go', 'in_app' => true],
                ]],
            ],
            request: ['url' => 'https://example.test/rooms', 'method' => 'POST', 'route' => 'rooms.store', 'site_id' => 5, 'locale' => 'ru', 'user_id' => 7],
            context: ['job' => 'Sync'],
            breadcrumbs: [
                ['time' => 't', 'category' => 'query', 'message' => 'select 1'],
                ['time' => 't', 'category' => 'log.error', 'message' => 'Gone'],
            ],
            runtime: ['php' => '8.4.1', 'laravel' => '12.0', 'os' => 'Linux'],
        );

        $this->assertSame([
            'event_id' => 'evt-1',
            'timestamp' => 1_763_381_223.25,
            'platform' => 'php',
            'level' => 'error',
            'logger' => 'atrium-errors',
            'release' => '2.4.0',
            'environment' => 'production',
            'exception' => ['values' => [
                ['type' => 'App\\Inner', 'value' => 'Inner failed', 'stacktrace' => ['frames' => [
                    ['filename' => 'app/Inner.php', 'lineno' => 3, 'function' => 'Inner::go', 'in_app' => true],
                ]]],
                ['type' => 'App\\Outer', 'value' => 'Outer failed', 'stacktrace' => ['frames' => [
                    ['filename' => 'vendor/lib.php', 'lineno' => 20, 'function' => 'lib', 'in_app' => false],
                    ['filename' => 'app/Outer.php', 'lineno' => 10, 'function' => 'Outer::run', 'in_app' => true, 'pre_context' => ['a'], 'context_line' => 'b', 'post_context' => ['c']],
                ]]],
            ]],
            'fingerprint' => ['fp-1'],
            'request' => ['url' => 'https://example.test/rooms', 'method' => 'POST'],
            'user' => ['id' => '7'],
            'tags' => ['kind' => 'php', 'route' => 'rooms.store', 'site' => '5', 'locale' => 'ru'],
            'extra' => ['job' => 'Sync'],
            'breadcrumbs' => ['values' => [
                ['category' => 'query', 'message' => 'select 1', 'level' => 'info'],
                ['category' => 'log.error', 'message' => 'Gone', 'level' => 'error'],
            ]],
            'contexts' => [
                'runtime' => ['name' => 'php', 'version' => '8.4.1'],
                'framework' => ['name' => 'Laravel', 'version' => '12.0'],
                'os' => ['name' => 'Linux'],
            ],
        ], SentryReporter::payload($event));
    }

    public function test_an_event_with_nothing_but_the_basics_has_only_those(): void
    {
        $payload = SentryReporter::payload($this->event());

        $this->assertSame([
            'event_id' => 'evt-1',
            'timestamp' => 1_763_381_223.25,
            'platform' => 'php',
            'level' => 'error',
            'logger' => 'atrium-errors',
            'release' => '2.4.0',
            'environment' => 'production',
            'exception' => ['values' => []],
            'fingerprint' => ['fp-1'],
            'tags' => ['kind' => 'php'],
            'contexts' => [
                'runtime' => ['name' => 'php', 'version' => null],
                'framework' => ['name' => 'Laravel', 'version' => null],
                'os' => ['name' => null],
            ],
        ], $payload);
    }

    public function test_the_moment_is_in_seconds_with_the_microseconds_as_a_fraction(): void
    {
        $at = fn (string $moment): float => SentryReporter::payload($this->event(timestamp: CarbonImmutable::parse($moment.' UTC')))['timestamp'];

        $this->assertSame(1_763_381_223.0, $at('2025-11-17 12:07:03'));
        $this->assertEqualsWithDelta(1_763_381_223.000001, $at('2025-11-17 12:07:03.000001'), 1e-9);
        $this->assertEqualsWithDelta(1_763_381_223.999999, $at('2025-11-17 12:07:03.999999'), 1e-9);
        $this->assertEqualsWithDelta(1_763_381_223.5, $at('2025-11-17 12:07:03.5'), 1e-9);
    }

    public function test_a_message_has_its_text_and_no_exception(): void
    {
        $payload = SentryReporter::payload($this->event(kind: 'message', message: 'Low disk space', level: 'warning'));

        $this->assertSame(['formatted' => 'Low disk space'], $payload['message']);
        $this->assertArrayNotHasKey('exception', $payload);
        $this->assertSame('php', $payload['platform']);
        $this->assertSame('warning', $payload['level']);
        $this->assertSame('message', $payload['tags']['kind']);
    }

    public function test_an_event_that_is_not_a_message_has_no_message(): void
    {
        $this->assertArrayNotHasKey('message', SentryReporter::payload($this->event(kind: 'job')));
    }

    public function test_a_browser_error_is_a_javascript_one(): void
    {
        $this->assertSame('javascript', SentryReporter::payload($this->event(kind: 'browser'))['platform']);
    }

    #[DataProvider('requests')]
    public function test_the_request_is_the_url_with_the_method_when_there_is_a_url(array $request, ?array $expected): void
    {
        $payload = SentryReporter::payload($this->event(request: $request));

        if ($expected === null) {
            $this->assertArrayNotHasKey('request', $payload);
        } else {
            $this->assertSame($expected, $payload['request']);
        }
    }

    /**
     * @return array<string,array{array<string,mixed>,array<string,string>|null}>
     */
    public static function requests(): array
    {
        return [
            'a url and a method' => [['url' => 'https://x.test/a', 'method' => 'GET'], ['url' => 'https://x.test/a', 'method' => 'GET']],
            'a url alone' => [['url' => 'https://x.test/a'], ['url' => 'https://x.test/a']],
            'a method alone' => [['method' => 'GET'], null],
            'nothing' => [[], null],
        ];
    }

    #[DataProvider('tags')]
    public function test_a_tag_that_is_empty_or_missing_is_left_out(array $request, array $expected): void
    {
        $this->assertSame($expected, SentryReporter::payload($this->event(request: $request))['tags']);
    }

    /**
     * @return array<string,array{array<string,mixed>,array<string,string>}>
     */
    public static function tags(): array
    {
        return [
            'all four' => [['route' => 'r', 'site_id' => 3, 'locale' => 'en'], ['kind' => 'php', 'route' => 'r', 'site' => '3', 'locale' => 'en']],
            'a site of zero is a site' => [['site_id' => 0], ['kind' => 'php', 'site' => '0']],
            'an empty route' => [['route' => '', 'locale' => 'en'], ['kind' => 'php', 'locale' => 'en']],
            'an empty locale' => [['route' => 'r', 'locale' => ''], ['kind' => 'php', 'route' => 'r']],
            'no site' => [['site_id' => null], ['kind' => 'php']],
        ];
    }

    public function test_a_frame_without_details_has_the_defaults(): void
    {
        $payload = SentryReporter::payload($this->event(exceptions: [['class' => 'E', 'message' => 'm', 'frames' => [[], ['function' => '', 'in_app' => 1, 'line' => 0]]]]));

        $this->assertSame([
            ['filename' => '', 'lineno' => 0, 'in_app' => false],
            ['filename' => '', 'lineno' => 0, 'in_app' => true],
        ], array_reverse($payload['exception']['values'][0]['stacktrace']['frames']));
    }

    public function test_a_frame_that_is_not_the_applications_is_marked_so_even_without_the_flag_being_a_boolean(): void
    {
        $payload = SentryReporter::payload($this->event(exceptions: [['class' => 'E', 'message' => 'm', 'frames' => [['in_app' => 0], ['in_app' => 'yes']]]]));

        $frames = $payload['exception']['values'][0]['stacktrace']['frames'];
        $this->assertFalse($frames[1]['in_app']);
        $this->assertTrue($frames[0]['in_app']);
    }

    /**
     * @param  array<int,array<string,mixed>>  $exceptions
     * @param  array<string,mixed>  $request
     * @param  array<string,mixed>  $context
     * @param  array<int,array{time:string,category:string,message:string}>  $breadcrumbs
     * @param  array<string,string>  $runtime
     */
    private function event(
        string $kind = 'php',
        string $level = 'error',
        string $message = 'm',
        array $exceptions = [],
        array $request = [],
        array $context = [],
        array $breadcrumbs = [],
        array $runtime = [],
        ?CarbonImmutable $timestamp = null,
    ): Event {
        return new Event('evt-1', $kind, $level, 'E', $message, $exceptions, $request, $context, $breadcrumbs, 'production', '2.4.0', $runtime, 'fp-1', $timestamp ?? CarbonImmutable::parse('2025-11-17 12:07:03.25 UTC'));
    }
}
