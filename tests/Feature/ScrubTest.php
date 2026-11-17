<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Capture\Scrubber;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Tests\Fixtures\LeakyCheckout;
use Atrium\Errors\Tests\TestCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

/**
 * No secret and no personal data reaches the database: not in the message,
 * the address, the context, the breadcrumbs or the lines of code.
 */
final class ScrubTest extends TestCase
{
    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function leakyMessages(): array
    {
        return [
            'email' => ['Mail to ivan.kovalenko@example.org bounced', 'ivan.kovalenko@example.org'],
            'email with plus' => ['Unknown user Mary+test@sub.example.co.uk', 'Mary+test@sub.example.co.uk'],
            'phone with plus' => ['SMS to +375 (29) 765-43-21 failed', '765-43-21'],
            'phone with dashes' => ['Callback 8-029-555-12-34 not reached', '555-12-34'],
            'visa card' => ['Charge 4111 1111 1111 1111 declined', '4111 1111 1111 1111'],
            'mastercard no spaces' => ['Card 5555555555554444 expired', '5555555555554444'],
            'password pair' => ['Login failed: password=Hunter2!', 'Hunter2!'],
            'json password' => ['Bad payload {"password":"s3cr3t-pw","name":"x"}', 's3cr3t-pw'],
            'api key in query' => ['GET /api?api_key=K9x2Lm77Qa&page=2 refused', 'K9x2Lm77Qa'],
            'token colon' => ['Rejected token: tk_live_778899', 'tk_live_778899'],
            'bearer' => ['Header Authorization: Bearer abcDEF123456789xyz invalid', 'abcDEF123456789xyz'],
            'basic auth' => ['Proxy said Basic dXNlcjpwYXNzd29yZA== is wrong', 'dXNlcjpwYXNzd29yZA'],
            'url password' => ['Cannot connect to mysql://shop:Pa55word@db.internal/shop', 'Pa55word'],
            // A made-up token, written in parts so secret scanners do not take it for a real one.
            'jwt' => ['Expired eyJhbGciOiJIUzI1NiJ9'.'.eyJzdWIiOiIxMjM0NTY3ODkwIn0'.'.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U', 'dozjgNryP4J3jVmNHl0w5N'],
            'long api key' => ['Key sk9f8e7d6c5b4a3928170fedcba9876543210abcd not valid', 'sk9f8e7d6c5b4a3928170fedcba9876543210abcd'],
            'php array secret' => ["Config ['secret' => 'q1w2e3r4t5'] rejected", 'q1w2e3r4t5'],
            'cookie pair' => ['laravel_session=abcdef0123456789 expired; cookie=zzz111', 'abcdef0123456789'],
        ];
    }

    #[DataProvider('leakyMessages')]
    public function test_secrets_in_a_message_never_reach_the_database(string $message, string $secret): void
    {
        $this->errors()->capture(new RuntimeException($message));

        $this->assertSame(1, Occurrence::query()->count());
        $this->assertStringNotContainsString($secret, $this->stored());
    }

    public function test_a_laravel_encrypted_value_is_not_stored_even_as_cipher(): void
    {
        // Split in the source: the lines around the capture are stored too.
        $cipher = Crypt::encryptString('passport AB'.'1234567');

        $this->errors()->capture(new RuntimeException('Cannot decrypt '.$cipher));

        $this->assertStringNotContainsString(substr($cipher, 0, 40), $this->stored());
        $this->assertStringNotContainsString('AB'.'1234567', $this->stored());
        $this->assertStringContainsString('[encrypted]', $this->stored());
    }

    public function test_context_keys_that_name_secrets_lose_their_values(): void
    {
        $this->errors()->capture(new RuntimeException('Sync failed'), 'php', [
            'order' => 1042,
            'password' => 'correct horse',
            'user' => ['email' => 'nina@example.com', 'name' => 'Nina', 'phone_number' => '80291234567'],
            'headers' => ['Authorization' => 'Basic Zm9vOmJhcg==', 'X-Api-Key' => 'k-778', 'Cookie' => 'sid=1', 'Accept' => 'text/html'],
            'rememberToken' => 'rt-31337',
            'stripe-secret' => 'whsec_9',
            'card_number' => '4000000000000002',
            'nested' => ['deeper' => ['credentials' => ['login' => 'bob', 'pin' => '0000']]],
            'flag' => true,
            'object' => new \stdClass,
        ]);

        $context = Occurrence::query()->sole()->payload['context'];
        $this->assertSame(1042, $context['order']);
        $this->assertSame('[Filtered]', $context['password']);
        $this->assertSame('[Filtered]', $context['user']['email']);
        $this->assertSame('Nina', $context['user']['name']);
        $this->assertSame('[Filtered]', $context['user']['phone_number']);
        $this->assertSame('[Filtered]', $context['headers']['Authorization']);
        $this->assertSame('[Filtered]', $context['headers']['X-Api-Key']);
        $this->assertSame('[Filtered]', $context['headers']['Cookie']);
        $this->assertSame('text/html', $context['headers']['Accept']);
        $this->assertSame('[Filtered]', $context['rememberToken']);
        $this->assertSame('[Filtered]', $context['stripe-secret']);
        $this->assertSame('[Filtered]', $context['card_number']);
        $this->assertSame('[Filtered]', $context['nested']['deeper']['credentials']);
        $this->assertTrue($context['flag']);
        $this->assertSame('[Filtered]', $context['object']);

        foreach (['correct horse', 'nina@example.com', '80291234567', 'Zm9vOmJhcg', 'k-778', 'rt-31337', 'whsec_9', '4000000000000002', '0000'] as $secret) {
            $this->assertStringNotContainsString($secret, $this->stored());
        }
    }

    public function test_lines_of_code_around_the_error_are_scrubbed_too(): void
    {
        try {
            (new LeakyCheckout)->pay();
        } catch (Throwable $e) {
            $this->errors()->capture($e);
        }

        $frame = Occurrence::query()->sole()->payload['exceptions'][0]['frames'][0];
        $this->assertStringContainsString('throw new RuntimeException', $frame['context']['line']);
        $this->assertNotEmpty($frame['context']['pre']);

        foreach (['Tr0ub4dor', 'olga.petrova', '123-45-67'] as $secret) {
            $this->assertStringNotContainsString($secret, $this->stored());
        }
    }

    public function test_the_address_and_the_breadcrumbs_are_scrubbed(): void
    {
        Log::warning('Reset link sent to pavel@example.com, token=reset-998877');

        $this->call('GET', '/missing-page?email=pavel%40example.com&reset_token=rt-5544&page=3');
        $this->errors()->capture(new RuntimeException('After the page'));

        $stored = $this->stored();
        foreach (['pav'.'el', 'rt-'.'5544', 'reset-'.'998877'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }
        $this->assertStringContainsString('page=3', $stored);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function harmlessTexts(): array
    {
        return [
            'order number fails luhn' => ['Order 1234567812345678 not found'],
            'time of day' => ['Lock held since 2026-11-17 10:20:00'],
            'host and port' => ['Connection refused 10.20.30.40:3306'],
            'version' => ['Requires PHP 8.4.12 or newer'],
            'short number' => ['Room 12 on floor 3'],
            'sql placeholder' => ['where email = ? and token = :token'],
            'class name' => ['Class App\\Http\\Controllers\\BookingController not found'],
        ];
    }

    #[DataProvider('harmlessTexts')]
    public function test_harmless_text_stays_as_it_was(string $text): void
    {
        $this->assertSame($text, (new Scrubber)->text($text));
    }

    /**
     * @return array<string,array{0:string|int,1:bool}>
     */
    public static function keys(): array
    {
        return [
            'password' => ['password', true],
            'new_password_confirmation' => ['new_password_confirmation', true],
            'camel token' => ['accessToken', true],
            'kebab' => ['x-auth-token', true],
            'header' => ['Authorization', true],
            'api key' => ['APIKEY', true],
            'email' => ['contact_email', true],
            'tel' => ['tel', true],
            'iban' => ['payout_iban', true],
            'name' => ['name', false],
            'title' => ['title', false],
            'passenger' => ['passengers', false],
            'empty' => ['', false],
            'number' => [7, false],
            'mailbox word' => ['mailbox_size', false],
            'glued password' => ['userpassword', true],
            'glued secret' => ['clientsecretvalue', true],
            'glued token' => ['refreshtokens', true],
            'glued authorization' => ['proxyauthorizationheader', true],
            'glued cookie' => ['sessioncookiejar', true],
            'glued api key' => ['legacyapikeyhash', true],
            'glued harmless' => ['passengercount', false],
        ];
    }

    #[DataProvider('keys')]
    public function test_which_keys_are_secret(string|int $key, bool $secret): void
    {
        $this->assertSame($secret, (new Scrubber)->isSensitiveKey($key));
    }

    public function test_project_keys_are_added_from_the_config(): void
    {
        $scrubber = new Scrubber(['loyalty_no']);

        $this->assertTrue($scrubber->isSensitiveKey('Loyalty-No'));
        $this->assertSame(['loyalty_no' => '[Filtered]', 'city' => 'Minsk'], $scrubber->scrub(['loyalty_no' => 'L-77', 'city' => 'Minsk']));
    }

    public function test_empty_values_and_deep_structures(): void
    {
        $scrubber = new Scrubber;
        $deep = ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => ['g' => ['h' => ['i' => ['j' => 'x']]]]]]]]]];

        $this->assertSame(['password' => '', 'token' => null, 'secret' => false], $scrubber->scrub(['password' => '', 'token' => null, 'secret' => false]));
        $this->assertSame('', $scrubber->text(''));
        $this->assertSame('[Filtered]', $scrubber->scrub($deep)['a']['b']['c']['d']['e']['f']['g']['h']['i']);
    }
}
