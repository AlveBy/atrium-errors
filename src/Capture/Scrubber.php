<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Capture;

/**
 * Takes secrets and personal data out of an event before anyone stores it.
 *
 * Two passes. Keys: a value under a name like password, token, cookie,
 * authorization, card, email or phone is replaced whatever it holds. Text:
 * emails, phone numbers, card numbers (checked with Luhn, so an order number
 * stays), bearer and JSON web tokens, Laravel's encrypted strings, the
 * password of a URL and `password=…`-like pairs are cut out of messages,
 * code lines, addresses and log lines.
 *
 * It errs on the side of removing too much: a lost digit in an error message
 * costs less than a customer's phone in a database nobody meant to keep it in.
 */
final class Scrubber
{
    public const FILTERED = '[Filtered]';

    /** Name parts that make a value secret or personal. */
    private const KEYS = [
        'password', 'passwd', 'pwd', 'pass', 'secret', 'token', 'apikey', 'auth', 'authorization',
        'cookie', 'cookies', 'session', 'csrf', 'xsrf', 'card', 'cc', 'cvv', 'cvc', 'pan', 'iban',
        'signature', 'sig', 'dsn', 'email', 'mail', 'phone', 'tel', 'mobile', 'otp', 'pin', 'ssn',
        'credentials', 'credential', 'bearer', 'jwt', 'private',
    ];

    /** Whole names that are secret although no single part is. */
    private const NAMES = ['apikey', 'accesskey', 'secretkey', 'privatekey', 'remembertoken', 'xapikey', 'xauthtoken'];

    /** A digit doubled, and its digits added up: 7 is 14, which is 1 + 4. */
    private const DOUBLED = [0, 2, 4, 6, 8, 1, 3, 5, 7, 9];

    /** @var array<int,string> */
    private array $extraKeys;

    /**
     * @param  array<int,string>  $extraKeys  more names, from `atrium-errors.scrub_keys`
     */
    public function __construct(array $extraKeys = [])
    {
        $this->extraKeys = array_map(static fn (string $key): string => self::squash($key), $extraKeys);
    }

    /**
     * Whether a value under this name must never be stored.
     */
    public function isSensitiveKey(string|int $key): bool
    {
        if (is_int($key)) {
            return false;
        }

        $squashed = self::squash($key);

        if ($squashed === '') {
            return false;
        }

        if (in_array($squashed, self::NAMES, true) || in_array($squashed, $this->extraKeys, true)) {
            return true;
        }

        // camelCase and kebab-case to words: rememberToken → remember token.
        $words = preg_split('/[^a-z0-9]+/', strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $key))) ?: [];

        foreach ($words as $word) {
            if ($word !== '' && in_array($word, self::KEYS, true)) {
                return true;
            }
        }

        foreach (['password', 'secret', 'token', 'authorization', 'cookie', 'apikey'] as $part) {
            if (str_contains($squashed, $part)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A whole structure: sensitive keys emptied, every string scrubbed.
     */
    public function scrub(mixed $value, int $depth = 0): mixed
    {
        if (is_string($value)) {
            return $this->text($value);
        }

        if (! is_array($value)) {
            return is_scalar($value) || $value === null ? $value : self::FILTERED;
        }

        if ($depth > 8) {
            return self::FILTERED;
        }

        $clean = [];

        foreach ($value as $key => $item) {
            $clean[$key] = $this->isSensitiveKey($key) && $item !== null && $item !== '' && ! is_bool($item)
                ? self::FILTERED
                : $this->scrub($item, $depth + 1);
        }

        return $clean;
    }

    /**
     * Free text: a message, a code line, a URL, a log line.
     */
    public function text(string $text): string
    {
        $rules = [
            // Laravel's encrypted strings are base64 of {"iv":…}: never kept, not even as cipher.
            '/eyJpdiI6[A-Za-z0-9+\/=]+/' => '[encrypted]',
            // JSON web tokens.
            '/eyJ[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]*/' => '[token]',
            '/\b(Bearer|Basic|Token)\s+[A-Za-z0-9\-._~+\/]{8,}=*/i' => '$1 '.self::FILTERED,
            // user:password@ in a URL or DSN.
            '#([a-z][a-z0-9+.-]*://)[^\s/@:]+(:[^\s/@]*)?@#i' => '$1'.self::FILTERED.'@',
            // password=…, &token=…, api_key: … in query strings, DSNs and messages;
            // a bound placeholder (`email = ?`, `:email`) is not a value.
            '/((?:^|[?&;,\s"\'$(\[{])[a-z0-9_.-]*(?:pass(?:word|wd)?|pwd|secret|token|api[_-]?key|access[_-]?key|auth|signature|sig|session|cookie|csrf|card|cvv|cvc|email|phone)[a-z0-9_.-]*["\']?\s*(?:=>|=|:)\s*)(?:"[^"\n]*"|\'[^\'\n]*\'|(?![?:]\w*(?:[\s)&,;]|$))[^\s&"\',;)]+)/i' => '$1'.self::FILTERED,
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i' => '[email]',
        ];

        $text = preg_replace(array_keys($rules), $rules, $text) ?? '';

        $text = preg_replace_callback('/(?<![\w.])\d(?:[ -]?\d){12,18}(?![\w.])/', static fn (array $match): string => self::luhn($match[0]) ? '[card]' : $match[0], $text) ?? '';

        // Phone numbers: with a leading + or written with separators, 9–15
        // digits; not next to a colon, so a time or host:port stays.
        $text = preg_replace_callback(
            '/(?<![\w+:])(?:\+\d[\d\s().-]{7,}\d|\(?\d{2,4}\)?[\s.-]\d{2,4}[\s.-]\d{2,4}(?:[\s.-]\d{2,4})?)(?![\w:])/',
            static function (array $match): string {
                $digits = strlen((string) preg_replace('/\D/', '', $match[0]));

                return $digits >= 9 && $digits <= 15 ? '[phone]' : $match[0];
            },
            $text,
        ) ?? '';

        // Long random-looking strings with letters and digits: API keys and the like.
        return (string) preg_replace_callback('/\b[A-Za-z0-9_-]{32,}\b/', static function (array $match): string {
            $value = $match[0];

            return preg_match('/\d/', $value) && preg_match('/[A-Za-z]/', $value) && ! str_contains($value, '__') ? '[token]' : $value;
        }, $text);
    }

    private static function squash(string $key): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));
    }

    private static function luhn(string $number): bool
    {
        $values = [];

        // From the last digit: every second one counts double, as the sum of its digits.
        foreach (array_reverse(str_split(str_replace([' ', '-'], '', $number))) as $position => $digit) {
            $values[] = $position % 2 === 1 ? self::DOUBLED[$digit] : $digit;
        }

        return array_sum($values) % 10 === 0;
    }
}
