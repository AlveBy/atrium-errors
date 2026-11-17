<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Unit;

use Atrium\Errors\Event;
use Atrium\Errors\Support\FallbackLog;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The last resort: what a line of the file holds, and that lines pile up.
 */
final class FallbackLogTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = sys_get_temp_dir().'/atrium-fallback-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->folder);

        parent::tearDown();
    }

    public function test_an_event_is_written_with_its_time_the_reason_and_everything_it_holds(): void
    {
        $event = $this->event('Disk is full');
        $log = new FallbackLog($this->folder.'/errors.log');

        $log->write($event, new RuntimeException('Connection refused'));

        $line = $this->lines($log)[0];
        $this->assertSame(['time', 'reason', 'event'], array_keys($line));
        $this->assertSame('2026-11-17T12:07:03Z', $line['time']);
        $this->assertSame(RuntimeException::class.': Connection refused', $line['reason']);
        $this->assertSame($event->toArray(), $line['event']);
    }

    public function test_an_event_without_a_reason_is_written_with_a_null_one(): void
    {
        $log = new FallbackLog($this->folder.'/errors.log');

        $log->write($this->event('Queue is full'));

        $line = $this->lines($log)[0];
        $this->assertArrayHasKey('reason', $line);
        $this->assertNull($line['reason']);
    }

    #[DataProvider('reasons')]
    public function test_the_reason_is_cut_to_three_hundred_characters_not_bytes(string $message, string $shown): void
    {
        $log = new FallbackLog($this->folder.'/errors.log');

        $log->write($this->event('x'), new RuntimeException($message));

        $this->assertSame(RuntimeException::class.': '.$shown, $this->lines($log)[0]['reason']);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function reasons(): array
    {
        return [
            'short' => ['Gone', 'Gone'],
            'exactly three hundred' => [str_repeat('a', 300), str_repeat('a', 300)],
            'one over' => [str_repeat('a', 301), str_repeat('a', 300)],
            'cyrillic is counted in characters' => [str_repeat('я', 301), str_repeat('я', 300)],
        ];
    }

    public function test_text_is_written_as_it_is_not_escaped(): void
    {
        $log = new FallbackLog($this->folder.'/errors.log');

        $log->write($this->event('Нет места на диске: /var/www'), new RuntimeException('Сбой /tmp'));

        $raw = (string) file_get_contents($log->path());
        $this->assertStringContainsString('"message":"Нет места на диске: /var/www"', $raw);
        $this->assertStringContainsString('Сбой /tmp', $raw);
        $this->assertStringNotContainsString('\\u', $raw);
        $this->assertStringNotContainsString('\\/', $raw);
    }

    public function test_an_event_that_cannot_be_encoded_is_still_written_in_part(): void
    {
        $log = new FallbackLog($this->folder.'/errors.log');

        $log->write($this->event("Broken \xB1\x31 bytes"));

        $line = $this->lines($log)[0];
        $this->assertSame('2026-11-17T12:07:03Z', $line['time']);
        $this->assertNull($line['event']['message']);
        $this->assertSame('abc', $line['event']['id']);
    }

    public function test_a_note_is_a_time_and_the_message_as_it_is(): void
    {
        $log = new FallbackLog($this->folder.'/errors.log');

        $log->note('Ошибка в /app');

        $raw = (string) file_get_contents($log->path());
        $line = json_decode($raw, true);
        $this->assertSame(['time', 'note'], array_keys($line));
        $this->assertSame('Ошибка в /app', $line['note']);
        $this->assertStringContainsString('"note":"Ошибка в /app"', $raw);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$/', $line['time']);
        $this->assertEqualsWithDelta(time(), strtotime($line['time']), 5);
    }

    #[DataProvider('notes')]
    public function test_a_note_is_cut_to_a_thousand_characters_not_bytes(string $message, string $shown): void
    {
        $log = new FallbackLog($this->folder.'/errors.log');

        $log->note($message);

        $this->assertSame($shown, $this->lines($log)[0]['note']);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function notes(): array
    {
        return [
            'short' => ['Gone', 'Gone'],
            'exactly a thousand' => [str_repeat('a', 1000), str_repeat('a', 1000)],
            'one over' => [str_repeat('a', 1001), str_repeat('a', 1000)],
            'cyrillic is counted in characters' => [str_repeat('я', 1001), str_repeat('я', 1000)],
        ];
    }

    public function test_lines_pile_up_one_per_call_each_ending_in_a_newline(): void
    {
        $log = new FallbackLog($this->folder.'/errors.log');

        $log->note('first');
        $log->write($this->event('second'));
        $log->note('third');

        $raw = (string) file_get_contents($log->path());
        $this->assertStringEndsWith(PHP_EOL, $raw);
        $lines = explode(PHP_EOL, $raw);
        $this->assertCount(4, $lines);
        $this->assertSame('', $lines[3]);
        $this->assertSame('first', json_decode($lines[0], true)['note']);
        $this->assertSame('second', json_decode($lines[1], true)['event']['message']);
        $this->assertSame('third', json_decode($lines[2], true)['note']);
    }

    public function test_the_missing_folders_are_made_open_for_the_group_but_not_for_others_to_write(): void
    {
        $log = new FallbackLog($this->folder.'/storage/logs/errors.log');
        $before = umask(0);

        try {
            $log->note('Hello');
        } finally {
            umask($before);
        }

        $this->assertSame(0775, fileperms($this->folder.'/storage') & 0777);
        $this->assertSame(0775, fileperms($this->folder.'/storage/logs') & 0777);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function lines(FallbackLog $log): array
    {
        return array_map(
            static fn (string $line): array => json_decode($line, true),
            array_values(array_filter(explode(PHP_EOL, (string) file_get_contents($log->path())))),
        );
    }

    private function event(string $message): Event
    {
        return new Event('abc', 'php', 'error', RuntimeException::class, $message, [], [], [], [], 'testing', '2.4.0', [], 'fp', CarbonImmutable::parse('2026-11-17 12:07:03 UTC'));
    }
}
