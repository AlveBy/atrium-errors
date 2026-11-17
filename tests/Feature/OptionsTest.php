<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Admin\Modules\Settings;
use Atrium\Errors\Support\Options;
use Atrium\Errors\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The module's settings: what is saved wins, the config answers otherwise.
 */
final class OptionsTest extends TestCase
{
    public function test_a_value_is_read_by_the_name_of_its_setting(): void
    {
        config(['atrium-errors.sample_rate' => 40, 'atrium-errors.alerts.email' => 'ops@example.test']);

        $this->assertSame(40, $this->read()->get('sample_rate'));
        $this->assertSame('ops@example.test', $this->read()->get('alert_email'));
        $this->assertNull($this->read()->get('sentry_dsn'));
    }

    public function test_a_name_that_is_not_a_setting_is_read_from_the_config_as_it_is(): void
    {
        config(['atrium-errors.release' => '9.9.9']);

        $this->assertSame('9.9.9', $this->read()->get('release'));
    }

    public function test_what_is_saved_wins_over_the_config(): void
    {
        config(['atrium-errors.sample_rate' => 40]);
        $this->settings(['sample_rate' => 25]);

        $this->assertSame(25, $this->read()->get('sample_rate'));
    }

    #[DataProvider('emptyValues')]
    public function test_an_empty_saved_value_leaves_the_config_to_answer(mixed $saved): void
    {
        config(['atrium-errors.sample_rate' => 40]);
        $this->settings(['sample_rate' => $saved]);

        $this->assertSame(40, $this->read()->get('sample_rate'));
    }

    /**
     * @return array<string,array{mixed}>
     */
    public static function emptyValues(): array
    {
        return [
            'null' => [null],
            'an empty string' => [''],
            'an empty list' => [[]],
        ];
    }

    #[DataProvider('falsyButSaved')]
    public function test_a_saved_zero_or_false_is_a_value_not_an_empty_one(mixed $saved): void
    {
        config(['atrium-errors.sample_rate' => 40]);
        $this->settings(['sample_rate' => $saved]);

        $this->assertSame($saved, $this->read()->get('sample_rate'));
    }

    /**
     * @return array<string,array{mixed}>
     */
    public static function falsyButSaved(): array
    {
        return [
            'zero' => [0],
            'false' => [false],
            'a zero string' => ['0'],
        ];
    }

    public function test_what_was_read_is_kept_until_it_is_flushed(): void
    {
        $options = $this->read();
        $this->settings(['sample_rate' => 10]);
        $this->assertSame(10, $options->int('sample_rate'));

        $this->app->make(Settings::class)->save('errors', ['sample_rate' => 20]);
        $this->assertSame(10, $options->int('sample_rate'));

        $options->flush();
        $this->assertSame(20, $options->int('sample_rate'));
    }

    #[DataProvider('integers')]
    public function test_a_number_is_read_as_an_integer(mixed $value, int $expected): void
    {
        config(['atrium-errors.per_minute' => $value]);

        $this->assertSame($expected, $this->read()->int('per_minute'));
    }

    /**
     * @return array<string,array{mixed,int}>
     */
    public static function integers(): array
    {
        return [
            'an integer' => [30, 30],
            'a numeric string' => ['45', 45],
            'a string with words' => ['12 per minute', 12],
            'nothing' => [null, 0],
            'a negative' => [-3, -3],
        ];
    }

    #[DataProvider('switches')]
    public function test_a_switch_is_read_as_a_boolean(mixed $value, bool $expected): void
    {
        config(['atrium-errors.alerts.new' => $value]);

        $this->assertSame($expected, $this->read()->bool('alert_new'));
    }

    /**
     * @return array<string,array{mixed,bool}>
     */
    public static function switches(): array
    {
        return [
            'true' => [true, true],
            'on' => ['on', true],
            'yes' => ['yes', true],
            'one' => ['1', true],
            'false' => [false, false],
            'off' => ['off', false],
            'zero' => ['0', false],
            'nothing' => [null, false],
            'words' => ['maybe', false],
        ];
    }

    #[DataProvider('strings')]
    public function test_a_string_is_trimmed_and_only_scalars_are_strings(mixed $value, string $expected): void
    {
        config(['atrium-errors.alerts.telegram_chat' => $value]);

        $this->assertSame($expected, $this->read()->string('telegram_chat'));
    }

    /**
     * @return array<string,array{mixed,string}>
     */
    public static function strings(): array
    {
        return [
            'as it is' => ['@north_ops', '@north_ops'],
            'with spaces around' => ['  @north_ops ', '@north_ops'],
            'with a newline around' => ["\n@north_ops\t", '@north_ops'],
            'a number' => [-1001234, '-1001234'],
            'nothing' => [null, ''],
            'a list' => [['a'], ''],
        ];
    }

    /**
     * @param  array<int,string>  $expected
     */
    #[DataProvider('drivers')]
    public function test_the_drivers_are_names_without_spaces_blanks_and_repeats(mixed $configured, array $expected): void
    {
        config(['atrium-errors.drivers' => $configured]);

        $this->assertSame($expected, $this->read()->drivers());
    }

    /**
     * @return array<string,array{mixed,array<int,string>}>
     */
    public static function drivers(): array
    {
        return [
            'a list' => [['database', 'log'], ['database', 'log']],
            'a comma-separated string' => ['database,log', ['database', 'log']],
            'a string with spaces, blanks and repeats' => [' database , ,log,database,', ['database', 'log']],
            'a list with spaces' => [[' log ', 'database'], ['log', 'database']],
            'a list with blanks and repeats, counted again from zero' => [['', ' log ', 'log', '', 'database'], ['log', 'database']],
            'a list with keys' => [['a' => 'log', 'b' => 'sentry'], ['log', 'sentry']],
            'a number among the names' => [[5, 'log'], ['5', 'log']],
            'nothing' => [null, []],
            'an empty string' => ['', []],
        ];
    }

    private function read(): Options
    {
        return $this->app->make(Options::class);
    }
}
