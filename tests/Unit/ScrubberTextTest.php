<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Unit;

use Atrium\Errors\Capture\Scrubber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Where free text stops being a number and starts being a phone, a card or a key.
 */
final class ScrubberTextTest extends TestCase
{
    /**
     * @return array<string,array{string,string}>
     */
    public static function numbers(): array
    {
        return [
            'a phone of nine digits' => ['call +12 345 678 9 now', 'call [phone] now'],
            'a phone of ten digits' => ['call +12 345 678 98 now', 'call [phone] now'],
            'a phone of fifteen digits' => ['call +123 456 789 012 345 now', 'call [phone] now'],
            'eight digits are too few for a phone' => ['call +1234 5678 now', 'call +1234 5678 now'],
            'sixteen digits are too many for a phone' => ['call +123 456 789 012 3456 now', 'call +123 456 789 012 3456 now'],
            'a card with spaces' => ['card 5555 5555 5555 4444 ok', 'card [card] ok'],
            'a card with dashes' => ['card 4111-1111-1111-1111 ok', 'card [card] ok'],
            'a card without separators' => ['card 4012888888881881 ok', 'card [card] ok'],
            'a card of fifteen digits' => ['card 378282246310005 ok', 'card [card] ok'],
            'a card of thirteen digits' => ['card 4222222222222 ok', 'card [card] ok'],
            'a card that ends in nine' => ['card 9999999999999995 x', 'card [card] x'],
            'a card with every doubled digit above four' => ['card 5105105105105100 ok', 'card [card] ok'],
            'sixteen digits that are not a card' => ['order 5555 5555 5555 4443 shipped', 'order 5555 5555 5555 4443 shipped'],
            'thirteen digits that are not a card' => ['order 4222222222223 shipped', 'order 4222222222223 shipped'],
            'a long number of digits only' => ['id '.str_repeat('1', 32).' end', 'id '.str_repeat('1', 32).' end'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_numbers_are_hidden_only_when_they_look_like_a_phone_or_a_card(string $text, string $expected): void
    {
        $this->assertSame($expected, (new Scrubber)->text($text));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function keys(): array
    {
        return [
            'letters and digits' => ['key '.str_repeat('a1', 16).' end', 'key [token] end'],
            'letters with one digit at the end' => ['key '.str_repeat('a', 31).'1 end', 'key [token] end'],
            'letters only' => ['word '.str_repeat('a', 32).' end', 'word '.str_repeat('a', 32).' end'],
            'a long name of letters and underscores' => ['name '.str_repeat('Ab', 16).' end', 'name '.str_repeat('Ab', 16).' end'],
            'digits only' => ['id '.str_repeat('7', 40).' end', 'id '.str_repeat('7', 40).' end'],
            'with a double underscore, as the names of code are' => ['x '.str_repeat('a1_', 11).'__a1 y', 'x '.str_repeat('a1_', 11).'__a1 y'],
            'one character short of a key' => ['x '.str_repeat('a1', 15).'a y', 'x '.str_repeat('a1', 15).'a y'],
        ];
    }

    #[DataProvider('keys')]
    public function test_a_long_string_is_a_key_when_it_has_both_letters_and_digits(string $text, string $expected): void
    {
        $this->assertSame($expected, (new Scrubber)->text($text));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function replacements(): array
    {
        return [
            'nothing' => ['', ''],
            'a Laravel encrypted value' => ['value eyJpdiI6'.str_repeat('A1', 40).'= end', 'value [encrypted] end'],
            'a bearer token' => ['Rejected Bearer abcDEF123456789xyz!', 'Rejected Bearer [Filtered]!'],
            'a basic one, whatever the case' => ['proxy basic dXNlcjpwYXNzd29yZA== wrong', 'proxy basic [Filtered] wrong'],
            'a token after the word Token' => ['Token abc12345678 bad', 'Token [Filtered] bad'],
            'a word after Bearer that is too short to be a token' => ['Bearer short', 'Bearer short'],
            'a user and a password in an address' => ['Cannot connect to mysql://shop:Pa55word@db.internal/shop', 'Cannot connect to mysql://[Filtered]@db.internal/shop'],
            'a user without a password in an address' => ['ftp://olga@host/x', 'ftp://[Filtered]@host/x'],
            'a password in a sentence' => ['Login failed: password=Hunter2!', 'Login failed: password=[Filtered]'],
            'a password in JSON' => ['Bad payload {"password":"s3cr3t-pw","name":"x"}', 'Bad payload {"password":[Filtered],"name":"x"}'],
            'a key in a query string' => ['GET /api?api_key=K9x2Lm77Qa&page=2 refused', 'GET /api?api_key=[Filtered]&page=2 refused'],
            'a secret in a PHP array' => ["Config ['secret' => 'q1w2e3r4t5'] rejected", "Config ['secret' => [Filtered]] rejected"],
            'a token with a colon' => ['a token: tk_live_778899 b', 'a token: [Filtered] b'],
            'a placeholder of a query is not a value' => ['x email = ? y', 'x email = ? y'],
        ];
    }

    #[DataProvider('replacements')]
    public function test_each_kind_of_secret_is_replaced_by_its_own_mark(string $text, string $expected): void
    {
        $this->assertSame($expected, (new Scrubber)->text($text));
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function names(): array
    {
        return [
            'a PIN in camel case' => ['userPIN', true],
            'a PIN in capitals' => ['USER_PIN', true],
            'a CVV' => ['myCVV', true],
            'a cookie header' => ['HTTP_COOKIE', true],
            'a harmless name' => ['userName', false],
            'a harmless name in capitals' => ['USER_NAME', false],
        ];
    }

    #[DataProvider('names')]
    public function test_a_name_is_secret_whatever_its_case(string $key, bool $secret): void
    {
        $this->assertSame($secret, (new Scrubber)->isSensitiveKey($key));
    }

    public function test_a_name_of_no_letters_is_not_secret_even_when_a_project_key_is_empty_once_squashed(): void
    {
        $scrubber = new Scrubber(['-', 'x-token-y']);

        $this->assertFalse($scrubber->isSensitiveKey('_'));
        $this->assertFalse($scrubber->isSensitiveKey('***'));
        $this->assertTrue($scrubber->isSensitiveKey('X_Token_Y'));
    }
}
