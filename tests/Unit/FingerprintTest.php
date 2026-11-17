<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Unit;

use Atrium\Errors\Capture\Fingerprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What makes two events the same bug, spelled out part by part.
 */
final class FingerprintTest extends TestCase
{
    public function test_the_class_and_the_frames_of_the_application_decide(): void
    {
        $frames = [
            ['file' => 'vendor/lib/a.php', 'function' => 'lib', 'in_app' => false],
            ['file' => 'app/Pay.php', 'function' => 'Pay::run', 'in_app' => true],
            ['file' => 'app/Cart.php', 'function' => 'Cart::buy', 'in_app' => true],
        ];

        $this->assertSame(sha1("Boom\napp/Pay.php|Pay::run\napp/Cart.php|Cart::buy"), (new Fingerprint)->of('Boom', 'ignored', $frames));
    }

    public function test_a_frame_that_does_not_say_it_is_the_applications_is_not(): void
    {
        $frames = [
            ['file' => 'one.php', 'function' => 'one'],
            ['file' => 'two.php', 'function' => 'two', 'in_app' => true],
        ];

        $this->assertSame(sha1("Boom\ntwo.php|two"), (new Fingerprint)->of('Boom', '', $frames));
    }

    public function test_the_flag_may_be_any_truthy_or_falsy_value(): void
    {
        $frames = [
            ['file' => 'zero.php', 'function' => 'zero', 'in_app' => 0],
            ['file' => 'one.php', 'function' => 'one', 'in_app' => 1],
            ['file' => 'empty.php', 'function' => 'empty', 'in_app' => ''],
            ['file' => 'text.php', 'function' => 'text', 'in_app' => 'yes'],
        ];

        $this->assertSame(sha1("Boom\none.php|one\ntext.php|text"), (new Fingerprint)->of('Boom', '', $frames));
    }

    public function test_without_any_frame_of_the_application_the_first_frames_are_used(): void
    {
        $frames = array_map(static fn (int $n): array => ['file' => "f{$n}.php", 'function' => "g{$n}", 'in_app' => false], range(1, 7));

        $this->assertSame(sha1("Boom\nf1.php|g1\nf2.php|g2\nf3.php|g3\nf4.php|g4\nf5.php|g5"), (new Fingerprint)->of('Boom', '', $frames));
    }

    public function test_only_the_top_five_frames_of_the_application_decide(): void
    {
        $frames = array_map(static fn (int $n): array => ['file' => "f{$n}.php", 'function' => "g{$n}", 'in_app' => true], range(1, 7));
        $same = array_slice($frames, 0, 5);

        $this->assertSame((new Fingerprint)->of('Boom', '', $same), (new Fingerprint)->of('Boom', '', $frames));
        $this->assertSame(sha1("Boom\nf1.php|g1\nf2.php|g2\nf3.php|g3\nf4.php|g4\nf5.php|g5"), (new Fingerprint)->of('Boom', '', $frames));
    }

    public function test_a_frame_with_a_file_only_or_a_function_only_still_counts_and_an_empty_one_does_not(): void
    {
        $frames = [
            ['file' => 'a.php', 'in_app' => true],
            ['function' => 'g', 'in_app' => true],
            ['in_app' => true],
            ['file' => '', 'function' => '', 'in_app' => true],
            // Whatever a frame holds is read as text: false is as empty as ''.
            ['file' => false, 'function' => false, 'in_app' => true],
        ];

        $this->assertSame(sha1("Boom\na.php|\n|g"), (new Fingerprint)->of('Boom', '', $frames));
    }

    public function test_the_class_alone_tells_nothing_so_the_message_does(): void
    {
        $this->assertSame(sha1("Boom\nUser <n> not found"), (new Fingerprint)->of('Boom', 'User 42 not found', []));
        $this->assertSame(sha1("Boom\nUser <n> not found"), (new Fingerprint)->of('Boom', 'User 42 not found', [['in_app' => true]]));
        $this->assertNotSame((new Fingerprint)->of('Boom', 'User 42 not found', []), (new Fingerprint)->of('Other', 'User 42 not found', []));
    }

    public function test_the_message_is_not_used_when_there_are_frames(): void
    {
        $frames = [['file' => 'a.php', 'function' => 'f', 'in_app' => true]];

        $this->assertSame((new Fingerprint)->of('Boom', 'one', $frames), (new Fingerprint)->of('Boom', 'two', $frames));
    }

    #[DataProvider('files')]
    public function test_a_hash_in_the_name_of_a_file_is_left_out(string $file, string $expected): void
    {
        $frames = [['file' => $file, 'function' => 'f', 'in_app' => true]];

        $this->assertSame(sha1("Boom\n{$expected}|f"), (new Fingerprint)->of('Boom', '', $frames));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function files(): array
    {
        return [
            'a compiled view' => ['storage/framework/views/3f9a0c1b2d.php', 'storage/framework/views/compiled.php'],
            'a view under another folder stays' => ['app/storage/framework/views/3f9a0c1b2d.php', 'app/storage/framework/views/3f9a0c1b2d.php'],
            'a view with letters beyond hex stays' => ['storage/framework/views/zzz.php', 'storage/framework/views/zzz.php'],
            'a bundle with a dash' => ['build/assets/app-4f3a9b2c.js', 'build/assets/app.js'],
            'a bundle with a dot' => ['build/assets/app.4f3a9b2c.css', 'build/assets/app.css'],
            'a module bundle' => ['build/assets/chunk-AbCdEf_12.mjs', 'build/assets/chunk.mjs'],
            'a short suffix stays' => ['build/assets/app-4f3a9b.js', 'build/assets/app-4f3a9b.js'],
            'an ordinary file' => ['app/Http/Kernel.php', 'app/Http/Kernel.php'],
        ];
    }

    #[DataProvider('messages')]
    public function test_the_message_is_blanked_of_what_changes_from_event_to_event(string $message, string $expected): void
    {
        $this->assertSame($expected, Fingerprint::normalizeMessage($message));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function messages(): array
    {
        return [
            'numbers' => ['Order 1234 of 3.5 kg', 'Order <n> of <n> kg'],
            'quotes' => ['No "alice" and \'bob\'', 'No <str> and <str>'],
            'a quote across lines' => ["Said \"a\nb\" twice", 'Said <str> twice'],
            'a hex id' => ['Token deadbeef12 gone', 'Token <hex> gone'],
            'a prefixed hex id' => ['At 0xDEADBEEF now', 'At <hex> now'],
            'a short hex stays but its digits go' => ['Code abc123', 'Code abc<n>'],
            'whitespace folds and the ends go' => ["  a \n\t b   c \n", 'a b c'],
            'a message cut to five hundred' => [str_repeat('x', 501), str_repeat('x', 500)],
            'five hundred stay' => [str_repeat('x', 500), str_repeat('x', 500)],
            'five hundred characters, not bytes' => [str_repeat('я', 501), str_repeat('я', 500)],
            'nothing to blank' => ['Plain words', 'Plain words'],
            'empty' => ['', ''],
        ];
    }
}
