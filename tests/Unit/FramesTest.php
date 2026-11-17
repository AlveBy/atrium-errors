<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Unit;

use Atrium\Errors\Capture\Frames;
use Exception;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Stack frames: where they are, whether they are the application's, the code around them.
 */
final class FramesTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = sys_get_temp_dir().'/atrium-frames-'.bin2hex(random_bytes(6));
        mkdir($this->folder.'/app', 0775, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->folder);

        parent::tearDown();
    }

    /**
     * An exception that says it was thrown in this file, on this line.
     */
    private function thrownAt(string $file, int $line): Throwable
    {
        return new class($file, $line) extends Exception
        {
            public function __construct(string $file, int $line)
            {
                parent::__construct('Somewhere');
                $this->file = $file;
                $this->line = $line;
            }
        };
    }

    /**
     * A file of numbered lines: "line 1", "line 2"…
     */
    private function code(int $lines, string $name = 'app/Code.php'): string
    {
        $path = $this->folder.'/'.$name;
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, implode("\n", array_map(static fn (int $n): string => 'line '.$n, range(1, $lines)))."\n");

        return $path;
    }

    /**
     * @return array<string,mixed>
     */
    private function firstFrame(Frames $frames, string $file, int $line): array
    {
        return $frames->of($this->thrownAt($file, $line))[0];
    }

    public function test_each_frame_names_the_function_that_was_running_in_it(): void
    {
        try {
            array_map(static function (): never {
                throw new RuntimeException('Inside a callback');
            }, [1]);
        } catch (Throwable $e) {
        }

        $frames = (new Frames($this->folder))->of($e);

        // The same name on every PHP version, whatever the version calls it.
        $this->assertSame(self::class.'::{closure}', $frames[0]['function']);
        // PHP itself called the callback: that frame has no place of its own.
        $this->assertSame(['file' => '', 'line' => 0, 'function' => 'array_map', 'in_app' => false], array_diff_key($frames[1], ['context' => 1]));
        $this->assertSame(self::class.'::'.__FUNCTION__, $frames[2]['function']);
        $this->assertSame(__FILE__, $frames[2]['file']);
        $this->assertSame('', end($frames)['function']);
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function places(): array
    {
        return [
            'application code' => ['app/Booking/Reserve.php', true],
            'a package of the application' => ['packages/shop/src/Cart.php', true],
            'vendor code' => ['vendor/laravel/framework/src/Router.php', false],
            'vendor code deeper down' => ['packages/shop/vendor/lib/Thing.php', false],
            'a folder that only starts like vendor' => ['vendorish/Thing.php', true],
            'compiled views' => ['storage/framework/views/0123abcd.php', false],
            'a storage folder that is not the framework\'s' => ['storage/app/Export.php', true],
            'views in a nested storage folder' => ['app/storage/framework/Thing.php', true],
            'no file at all' => ['', false],
        ];
    }

    #[DataProvider('places')]
    public function test_only_the_applications_own_code_is_marked_as_such(string $relative, bool $inApp): void
    {
        $frame = $this->firstFrame(new Frames('/srv/app'), $relative === '' ? '' : '/srv/app/'.$relative, 10);

        $this->assertSame($relative, $frame['file']);
        $this->assertSame($inApp, $frame['in_app']);
    }

    public function test_code_outside_the_project_is_the_applications_unless_it_is_vendor_code(): void
    {
        $frames = new Frames('/srv/app');

        $this->assertTrue($this->firstFrame($frames, '/opt/shared/Thing.php', 1)['in_app']);
        $this->assertFalse($this->firstFrame($frames, '/opt/shared/vendor/lib/Thing.php', 1)['in_app']);
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function paths(): array
    {
        return [
            'inside the project' => ['/srv/app', '/srv/app/app/Foo.php', 'app/Foo.php'],
            'the project written with a slash at the end' => ['/srv/app/', '/srv/app/app/Foo.php', 'app/Foo.php'],
            'the project written with slashes at the end' => ['/srv/app//', '/srv/app/app/Foo.php', 'app/Foo.php'],
            'backslashes inside the project' => ['/srv/app', '/srv/app/app\\Sub\\Foo.php', 'app/Sub/Foo.php'],
            'a folder next to the project with the same start' => ['/srv/app', '/srv/application/Foo.php', '/srv/application/Foo.php'],
            'outside the project' => ['/srv/app', '/opt/Foo.php', '/opt/Foo.php'],
            'backslashes outside the project' => ['/srv/app', '/opt\\lib\\Foo.php', '/opt/lib/Foo.php'],
        ];
    }

    #[DataProvider('paths')]
    public function test_a_file_is_named_from_the_project_root_with_forward_slashes(string $base, string $file, string $shown): void
    {
        $this->assertSame($shown, $this->firstFrame(new Frames($base), $file, 3)['file']);
    }

    public function test_the_lines_around_the_throw_are_five_each_way_unless_told_otherwise(): void
    {
        $frame = $this->firstFrame(new Frames($this->folder), $this->code(30), 10);

        $this->assertSame(['line 5', 'line 6', 'line 7', 'line 8', 'line 9'], $frame['context']['pre']);
        $this->assertSame('line 10', $frame['context']['line']);
        $this->assertSame(['line 11', 'line 12', 'line 13', 'line 14', 'line 15'], $frame['context']['post']);
    }

    /**
     * @return array<string,array{int,int,array<int,string>,string,array<int,string>}>
     */
    public static function edges(): array
    {
        return [
            'the first line' => [3, 1, [], 'line 1', ['line 2', 'line 3', 'line 4']],
            'the second line' => [3, 2, ['line 1'], 'line 2', ['line 3', 'line 4', 'line 5']],
            'the line right after the first window' => [2, 4, ['line 2', 'line 3'], 'line 4', ['line 5', 'line 6']],
            'the last line' => [3, 12, ['line 9', 'line 10', 'line 11'], 'line 12', []],
            'the one before the last' => [3, 11, ['line 8', 'line 9', 'line 10'], 'line 11', ['line 12']],
            'one line each way' => [1, 6, ['line 5'], 'line 6', ['line 7']],
        ];
    }

    /**
     * @param  array<int,string>  $pre
     * @param  array<int,string>  $post
     */
    #[DataProvider('edges')]
    public function test_the_window_of_code_is_cut_at_the_ends_of_the_file(int $around, int $line, array $pre, string $shown, array $post): void
    {
        $frame = $this->firstFrame(new Frames($this->folder, $around), $this->code(12), $line);

        $this->assertSame(['pre' => $pre, 'line' => $shown, 'post' => $post], $frame['context']);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function noCode(): array
    {
        return [
            'a line past the end' => ['past'],
            'line zero' => ['zero'],
            'a negative line' => ['negative'],
            'a file that is not there' => ['missing'],
            'a folder' => ['folder'],
            'no file name' => ['unnamed'],
        ];
    }

    #[DataProvider('noCode')]
    public function test_a_frame_without_readable_code_has_none(string $case): void
    {
        $file = $this->code(5);
        [$file, $line] = match ($case) {
            'past' => [$file, 6],
            'zero' => [$file, 0],
            'negative' => [$file, -1],
            'missing' => [$this->folder.'/app/Gone.php', 2],
            'folder' => [$this->folder.'/app', 2],
            'unnamed' => ['', 2],
        };

        $this->assertArrayNotHasKey('context', $this->firstFrame(new Frames($this->folder), $file, $line));
    }

    public function test_long_lines_of_code_are_cut_to_three_hundred_characters(): void
    {
        $path = $this->folder.'/app/Wide.php';
        $wide = static fn (string $letter): string => str_repeat($letter, 400);
        file_put_contents($path, implode("\n", [$wide('я'), $wide('花'), str_repeat('a', 300), $wide('🪑'), $wide('b')])."\n");

        $context = $this->firstFrame(new Frames($this->folder, 2), $path, 3)['context'];

        $this->assertSame([str_repeat('я', 300), str_repeat('花', 300)], $context['pre']);
        $this->assertSame(str_repeat('a', 300), $context['line']);
        $this->assertSame([str_repeat('🪑', 300), str_repeat('b', 300)], $context['post']);
    }

    public function test_a_line_of_exactly_three_hundred_characters_is_kept_whole_and_one_more_is_cut(): void
    {
        $path = $this->folder.'/app/Edge.php';
        file_put_contents($path, str_repeat('x', 300)."\n".str_repeat('y', 301)."\n");

        $this->assertSame(str_repeat('x', 300), $this->firstFrame(new Frames($this->folder), $path, 1)['context']['line']);
        $this->assertSame(str_repeat('y', 300), $this->firstFrame(new Frames($this->folder), $path, 2)['context']['line']);
    }

    /**
     * @return array<string,array{int,bool}>
     */
    public static function sizes(): array
    {
        return [
            'just under two megabytes' => [1_999_999, true],
            'two megabytes' => [2_000_000, true],
            'one byte more' => [2_000_001, false],
        ];
    }

    #[DataProvider('sizes')]
    public function test_a_file_over_two_megabytes_is_not_read(int $bytes, bool $read): void
    {
        $path = $this->folder.'/app/Huge.php';
        $head = "first\nsecond\n";
        file_put_contents($path, $head.str_repeat('z', $bytes - strlen($head)));

        $frame = $this->firstFrame(new Frames($this->folder), $path, 1);

        $this->assertSame($read, isset($frame['context']));
        if ($read) {
            $this->assertSame('first', $frame['context']['line']);
        }
    }

    public function test_the_place_of_the_throw_has_code_even_in_vendor(): void
    {
        $file = $this->code(10, 'vendor/lib/Thing.php');

        $frames = (new Frames($this->folder))->of($this->thrownAt($file, 4));

        $this->assertFalse($frames[0]['in_app']);
        $this->assertSame('line 4', $frames[0]['context']['line']);
    }

    /**
     * @return array<string,array{string,array<string,mixed>}>
     */
    public static function browserStacks(): array
    {
        return [
            'Chrome, a named function' => [
                '    at render (http://localhost/app.js:10:5)',
                ['file' => '/app.js', 'line' => 10, 'function' => 'render', 'in_app' => true],
            ],
            'Chrome, an anonymous function' => [
                '    at http://localhost/app.js:7:1',
                ['file' => '/app.js', 'line' => 7, 'function' => '', 'in_app' => true],
            ],
            'Firefox and Safari' => [
                'render@http://localhost/app.js:3:4',
                ['file' => '/app.js', 'line' => 3, 'function' => 'render', 'in_app' => true],
            ],
            'a function name with spaces around it' => [
                'render @http://localhost/app.js:3:4',
                ['file' => '/app.js', 'line' => 3, 'function' => 'render', 'in_app' => true],
            ],
            'a script of another site' => [
                'at load (https://cdn.example.com/lib.js:99:2)',
                ['file' => 'https://cdn.example.com/lib.js', 'line' => 99, 'function' => 'load', 'in_app' => false],
            ],
            'a query string and no column' => [
                'at load (http://localhost/app.js?v=3#top:12)',
                ['file' => '/app.js', 'line' => 12, 'function' => 'load', 'in_app' => true],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $expected
     */
    #[DataProvider('browserStacks')]
    public function test_a_browser_stack_row_becomes_a_frame(string $row, array $expected): void
    {
        $this->assertSame([$expected], (new Frames($this->folder))->ofBrowserStack($row, 'http://localhost'));
    }

    public function test_rows_that_are_not_frames_are_skipped(): void
    {
        $stack = "TypeError: x is undefined\n    at render (http://localhost/app.js:10:5)\nnot a frame\n\n"
            ."Error: it failed at render (http://localhost/app.js:11:5)\n"
            ."    at render (http://localhost/app.js:12:5) and then some\n";

        $this->assertCount(1, (new Frames($this->folder))->ofBrowserStack($stack, 'http://localhost'));
        $this->assertSame([], (new Frames($this->folder))->ofBrowserStack('', 'http://localhost'));
    }
}
