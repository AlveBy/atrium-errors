<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Unit;

use Atrium\Errors\Event;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Where an event says it happened.
 */
final class EventTest extends TestCase
{
    /**
     * @param  array<int,array<string,mixed>>  $frames
     */
    #[DataProvider('culprits')]
    public function test_the_culprit_is_the_first_frame_of_the_application_else_the_first_frame(array $frames, ?string $expected): void
    {
        $this->assertSame($expected, $this->event($frames)->culprit());
    }

    /**
     * @return array<string,array{array<int,array<string,mixed>>,?string}>
     */
    public static function culprits(): array
    {
        return [
            'no frames' => [[], null],
            'the only frame, of the application' => [[['file' => 'app/A.php', 'line' => 4, 'function' => 'A::run', 'in_app' => true]], 'A::run (app/A.php)'],
            'the first of the application, not the first' => [[
                ['file' => 'vendor/lib.php', 'line' => 1, 'function' => 'lib', 'in_app' => false],
                ['file' => 'app/B.php', 'line' => 2, 'function' => 'B::go', 'in_app' => true],
                ['file' => 'app/C.php', 'line' => 3, 'function' => 'C::go', 'in_app' => true],
            ], 'B::go (app/B.php)'],
            'none of the application: the first frame' => [[
                ['file' => 'vendor/one.php', 'line' => 1, 'function' => 'one', 'in_app' => false],
                ['file' => 'vendor/two.php', 'line' => 2, 'function' => 'two', 'in_app' => false],
            ], 'one (vendor/one.php)'],
            'a frame that does not say is not the application' => [[
                ['file' => 'vendor/one.php', 'line' => 1, 'function' => 'one'],
                ['file' => 'app/B.php', 'line' => 2, 'function' => 'B::go', 'in_app' => true],
            ], 'B::go (app/B.php)'],
            'no frame says, so the first' => [[
                ['file' => 'x.php', 'line' => 1, 'function' => 'x'],
                ['file' => 'y.php', 'line' => 2, 'function' => 'y'],
            ], 'x (x.php)'],
            'without a function: the file and the line' => [[['file' => 'app/A.php', 'line' => 4, 'function' => '', 'in_app' => true]], 'app/A.php:4'],
            'without a function key' => [[['file' => 'app/A.php', 'line' => 4, 'in_app' => true]], 'app/A.php:4'],
            'without a line: line zero' => [[['file' => 'app/A.php', 'function' => '', 'in_app' => true]], 'app/A.php:0'],
            'without anything' => [[[]], ':0'],
            'a function that is false is none' => [[['file' => 'app/A.php', 'line' => 4, 'function' => false, 'in_app' => true]], 'app/A.php:4'],
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $frames
     */
    private function event(array $frames): Event
    {
        return new Event('evt-1', 'php', 'error', 'E', 'm', [['class' => 'E', 'message' => 'm', 'frames' => $frames]], [], [], [], 'production', '2.4.0', [], 'fp-1', CarbonImmutable::parse('2026-11-17 12:07:03 UTC'));
    }
}
