<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Event;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Reporters\DatabaseReporter;
use Atrium\Errors\Tests\TestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the database keeps of an event, and how much of each field fits.
 */
final class DatabaseReporterTest extends TestCase
{
    public function test_an_event_is_kept_whole_under_its_issue(): void
    {
        $event = $this->event(
            request: ['site_id' => 3, 'method' => 'POST', 'path' => '/rooms/12', 'route' => 'rooms.update', 'locale' => 'ru', 'user_id' => 7, 'url' => 'https://example.test/rooms/12'],
        );

        [$issue, $outcome] = $this->reporter()->record($event);

        $this->assertSame(DatabaseReporter::OUTCOME_NEW, $outcome);
        $this->assertSame('fp-1', $issue->fingerprint);
        $this->assertSame('job', $issue->kind);
        $this->assertSame('error', $issue->level);
        $this->assertSame('App\\Booking\\RoomUnavailable', $issue->class);
        $this->assertSame('Room 12 is not free', $issue->message);
        $this->assertSame('2.4.0', $issue->first_release);
        $this->assertSame('2.4.0', $issue->last_release);
        $this->assertSame(1, $issue->times_seen);
        $this->assertSame(1, $issue->users_seen);

        $occurrence = Occurrence::query()->sole();
        $this->assertSame($issue->id, $occurrence->issue_id);
        $this->assertSame(3, $occurrence->site_id);
        $this->assertSame('error', $occurrence->level);
        $this->assertSame('2.4.0', $occurrence->release);
        $this->assertSame('production', $occurrence->environment);
        $this->assertSame('POST', $occurrence->method);
        $this->assertSame('/rooms/12', $occurrence->path);
        $this->assertSame('rooms.update', $occurrence->route);
        $this->assertSame('ru', $occurrence->locale);
        $this->assertSame(7, $occurrence->user_id);
        $this->assertSame('2026-11-17 12:07:03', $occurrence->created_at?->format('Y-m-d H:i:s'));
        $this->assertSame([
            'id' => 'evt-1',
            'kind' => 'job',
            'exceptions' => $event->exceptions,
            'request' => $event->request,
            'context' => ['job' => 'Sync'],
            'breadcrumbs' => [['time' => 't', 'category' => 'query', 'message' => 'select 1']],
            'runtime' => ['php' => '8.4.1'],
        ], $occurrence->payload);
    }

    public function test_an_event_without_a_request_has_no_request_columns(): void
    {
        $this->reporter()->record($this->event(request: []));

        $occurrence = Occurrence::query()->sole();
        $this->assertNull($occurrence->site_id);
        $this->assertNull($occurrence->method);
        $this->assertNull($occurrence->path);
        $this->assertNull($occurrence->route);
        $this->assertNull($occurrence->locale);
        $this->assertNull($occurrence->user_id);
    }

    #[DataProvider('widths')]
    public function test_every_text_is_cut_to_the_width_of_its_column_in_characters(string $field, int $width): void
    {
        $long = str_repeat('я', $width + 45);
        $exact = str_repeat('я', $width);
        $frames = [['file' => $field === 'culprit' ? $long : 'app/X.php', 'line' => 1, 'function' => '', 'in_app' => true]];
        $event = $this->event(
            class: $field === 'class' ? $long : 'E',
            environment: $field === 'environment' ? $long : 'production',
            request: [
                'method' => $field === 'method' ? $long : 'GET',
                'locale' => $field === 'locale' ? $long : 'en',
                'route' => $field === 'route' ? $long : 'r',
            ],
            frames: $frames,
        );

        [$issue] = $this->reporter()->record($event);
        $occurrence = Occurrence::query()->sole();

        $shown = match ($field) {
            'class' => $issue->class,
            'culprit' => $issue->culprit,
            'environment' => $occurrence->environment,
            'method' => $occurrence->method,
            'locale' => $occurrence->locale,
            'route' => $occurrence->route,
        };
        $this->assertSame($field === 'culprit' ? mb_substr($long.':1', 0, $width) : $exact, $shown);
    }

    /**
     * @return array<string,array{string,int}>
     */
    public static function widths(): array
    {
        return [
            'the class of the error' => ['class', 255],
            'where it happened' => ['culprit', 255],
            'the environment' => ['environment', 32],
            'the method' => ['method', 8],
            'the locale' => ['locale', 16],
            'the route' => ['route', 255],
        ];
    }

    public function test_a_text_that_fits_is_kept_whole(): void
    {
        $this->reporter()->record($this->event(
            class: str_repeat('c', 255),
            environment: str_repeat('e', 32),
            request: ['method' => str_repeat('m', 8), 'locale' => str_repeat('l', 16), 'route' => str_repeat('r', 255)],
        ));

        $occurrence = Occurrence::query()->sole();
        $this->assertSame(str_repeat('c', 255), Issue::query()->sole()->class);
        $this->assertSame(str_repeat('e', 32), $occurrence->environment);
        $this->assertSame(str_repeat('m', 8), $occurrence->method);
        $this->assertSame(str_repeat('l', 16), $occurrence->locale);
        $this->assertSame(str_repeat('r', 255), $occurrence->route);
    }

    /**
     * @param  array<string,mixed>  $request
     * @param  array<int,array<string,mixed>>  $frames
     */
    private function event(string $class = 'App\\Booking\\RoomUnavailable', string $environment = 'production', array $request = [], array $frames = []): Event
    {
        return new Event(
            'evt-1', 'job', 'error', $class, 'Room 12 is not free',
            [['class' => $class, 'message' => 'Room 12 is not free', 'frames' => $frames]],
            $request, ['job' => 'Sync'], [['time' => 't', 'category' => 'query', 'message' => 'select 1']],
            $environment, '2.4.0', ['php' => '8.4.1'], 'fp-1', CarbonImmutable::parse('2026-11-17 12:07:03 UTC'),
        );
    }

    private function reporter(): DatabaseReporter
    {
        return $this->app->make(DatabaseReporter::class);
    }
}
