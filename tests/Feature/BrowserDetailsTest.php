<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Http\Middleware\AuthorizeErrors;
use Atrium\Errors\Http\Middleware\InjectBrowserScript;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Tests\TestCase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The endpoint's refusals word for word, its size limit to the byte, its
 * throttle, and the exact page the script is put into.
 */
final class BrowserDetailsTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(static function (): void {
            Route::get('/tours', static fn () => response('<html><head><title>Tours</title></head><body>Tours</body></html>'));
            Route::get('/upper', static fn () => response('<HTML><HEAD></HEAD><BODY>x</BODY></HTML>'));
            Route::get('/no-head', static fn () => response('<p>Fragment</p>'));
            Route::get('/multiple', static fn () => response('<html><head></head></html>', 300));
            Route::get('/created', static fn () => response('<html><head></head></html>', 201));
            Route::get('/streamed', static fn () => response()->stream(static function (): void {
                echo '<html><head></head></html>';
            }, 200, ['Content-Type' => 'text/html']));
            Route::get('/admin/help', static fn () => response('<html><head></head><body>Help</body></html>'));
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Far above anything one test sends: the throttle has its own tests.
        config(['atrium-errors.browser.per_minute' => 10000]);
    }

    public function test_a_switched_off_endpoint_says_it_does_not_accept(): void
    {
        $this->postJson('/atrium-errors/browser', ['message' => 'x'])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not accepted']);

        $this->assertSame(0, Occurrence::query()->count());
    }

    public function test_an_endpoint_switched_on_for_the_admin_only_accepts_too(): void
    {
        $this->settings(['browser_admin' => true]);

        $this->postJson('/atrium-errors/browser', ['message' => 'x', 'source' => 'admin'])->assertStatus(202);
    }

    public function test_a_report_that_is_not_valid_is_refused_in_so_many_words(): void
    {
        $this->settings(['browser_site' => true]);

        $this->postJson('/atrium-errors/browser', ['name' => 'TypeError'])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Invalid report']);
        $this->postJson('/atrium-errors/browser', ['message' => 'x', 'source' => 'mobile'])->assertStatus(422);
        $this->call('POST', '/atrium-errors/browser', [], [], [], ['CONTENT_TYPE' => 'application/json'], '[1,2')->assertStatus(422);
        $this->call('POST', '/atrium-errors/browser', [], [], [], ['CONTENT_TYPE' => 'application/json'], '"text"')->assertStatus(422);
    }

    public function test_every_optional_field_may_be_null(): void
    {
        $this->settings(['browser_site' => true]);

        $this->postJson('/atrium-errors/browser', ['message' => 'x', 'name' => null, 'stack' => null, 'url' => null, 'line' => null, 'column' => null, 'source' => null])
            ->assertStatus(202);

        $this->assertSame(['source' => 'site'], Occurrence::query()->sole()->payload['context']);
    }

    #[DataProvider('fieldLimits')]
    public function test_a_field_is_refused_just_over_its_limit_and_taken_on_it(string $field, mixed $fits, mixed $over): void
    {
        $this->settings(['browser_site' => true]);

        $this->postJson('/atrium-errors/browser', ['message' => 'x', $field => $fits])->assertStatus(202);
        $this->postJson('/atrium-errors/browser', ['message' => 'x', $field => $over])->assertStatus(422);
    }

    /**
     * @return array<string,array{string,mixed,mixed}>
     */
    public static function fieldLimits(): array
    {
        return [
            'message' => ['message', str_repeat('a', 1000), str_repeat('a', 1001)],
            'name' => ['name', str_repeat('a', 64), str_repeat('a', 65)],
            'stack' => ['stack', str_repeat('a', 10000), str_repeat('a', 10001)],
            'url' => ['url', str_repeat('a', 2000), str_repeat('a', 2001)],
            'line' => ['line', 10000000, 10000001],
            'column' => ['column', 10000000, 10000001],
        ];
    }

    #[DataProvider('numbersBelowZero')]
    public function test_a_line_or_a_column_below_zero_is_refused(string $field): void
    {
        $this->settings(['browser_site' => true]);

        $this->postJson('/atrium-errors/browser', ['message' => 'x', $field => 0])->assertStatus(202);
        $this->postJson('/atrium-errors/browser', ['message' => 'x', $field => -1])->assertStatus(422);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function numbersBelowZero(): array
    {
        return ['line' => ['line'], 'column' => ['column']];
    }

    #[DataProvider('limits')]
    public function test_the_body_may_be_as_long_as_the_limit_and_not_a_byte_more(?int $configured, int $limit): void
    {
        $this->settings(['browser_site' => true]);
        config(['atrium-errors.browser' => ['per_minute' => 10000] + ($configured === null ? [] : ['max_bytes' => $configured])]);

        $this->postBody($this->body($limit))->assertStatus(202);
        $this->postBody($this->body($limit + 1))->assertStatus(413)->assertExactJson(['message' => 'Too large']);
    }

    /**
     * @return array<string,array{?int,int}>
     */
    public static function limits(): array
    {
        return [
            'the default is sixteen kilobytes' => [null, 16384],
            'a configured limit' => [5000, 5000],
            'a limit under a kilobyte is a kilobyte' => [100, 1024],
            'a limit of zero is a kilobyte too' => [0, 1024],
            'exactly a kilobyte' => [1024, 1024],
            'one over a kilobyte' => [1025, 1025],
        ];
    }

    public function test_a_body_that_says_it_is_too_long_is_refused_before_its_length_is_looked_at(): void
    {
        $this->settings(['browser_site' => true]);
        config(['atrium-errors.browser.max_bytes' => 2000]);

        $this->postBody($this->body(100), ['CONTENT_LENGTH' => 2000])->assertStatus(202);
        $this->postBody($this->body(100), ['CONTENT_LENGTH' => 2001])->assertStatus(413);
        $this->postBody($this->body(100), ['CONTENT_LENGTH' => 99999])->assertStatus(413);
    }

    public function test_the_throttle_counts_per_address_with_ten_a_minute_by_default(): void
    {
        $this->settings(['browser_site' => true]);
        config(['atrium-errors.browser' => []]);

        $codes = [];
        for ($i = 0; $i < 11; $i++) {
            $codes[] = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->postJson('/atrium-errors/browser', ['message' => 'x'])->getStatusCode();
        }

        $this->assertSame([...array_fill(0, 10, 202), 429], $codes);
        $this->assertSame(202, $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->postJson('/atrium-errors/browser', ['message' => 'x'])->getStatusCode());
        $this->assertSame(10, RateLimiter::attempts(md5('atrium-errors-browser'.'atrium-errors|10.0.0.1')));
    }

    #[DataProvider('perMinute')]
    public function test_the_throttle_lets_the_configured_number_through_and_at_least_one(int $configured, int $allowed): void
    {
        $this->settings(['browser_site' => true]);
        config(['atrium-errors.browser.per_minute' => $configured]);

        $codes = [];
        for ($i = 0; $i < $allowed + 1; $i++) {
            $codes[] = $this->postJson('/atrium-errors/browser', ['message' => 'x'])->getStatusCode();
        }

        $this->assertSame([...array_fill(0, $allowed, 202), 429], $codes);
    }

    /**
     * @return array<string,array{int,int}>
     */
    public static function perMinute(): array
    {
        return [
            'one' => [1, 1],
            'two' => [2, 2],
            'three' => [3, 3],
            'zero still lets one through' => [0, 1],
            'below zero too' => [-4, 1],
        ];
    }

    public function test_the_endpoint_takes_no_csrf_token_and_gets_no_script_but_is_throttled(): void
    {
        $route = Route::getRoutes()->getByName('atrium.errors.browser');

        $this->assertNotNull($route);
        $this->assertSame(['web', 'throttle:atrium-errors-browser'], $route->middleware());
        $this->assertSame([ValidateCsrfToken::class, InjectBrowserScript::class], $route->excludedMiddleware());
    }

    public function test_the_admin_screens_are_behind_the_admin_middleware(): void
    {
        $route = Route::getRoutes()->getByName('admin.errors.index');

        $this->assertNotNull($route);
        $this->assertSame(['web', 'atrium.inertia', 'admin.auth', 'atrium.site', AuthorizeErrors::class], $route->middleware());
        $this->assertSame('admin/errors', $route->uri());
    }

    public function test_the_script_is_put_in_front_of_the_end_of_the_head_and_nowhere_else(): void
    {
        $this->settings(['browser_site' => true]);

        $this->assertSame(
            '<html><head><title>Tours</title><script src="/atrium-errors.js" data-endpoint="/atrium-errors/browser" data-source="site" defer></script></head><body>Tours</body></html>',
            $this->get('/tours')->assertOk()->getContent(),
        );
        $this->assertSame(
            '<HTML><HEAD><script src="/atrium-errors.js" data-endpoint="/atrium-errors/browser" data-source="site" defer></script></HEAD><BODY>x</BODY></HTML>',
            $this->get('/upper')->assertOk()->getContent(),
        );
    }

    public function test_a_page_without_a_head_is_left_as_it_is(): void
    {
        $this->settings(['browser_site' => true]);

        $this->assertSame('<p>Fragment</p>', $this->get('/no-head')->assertOk()->getContent());
    }

    public function test_a_streamed_page_is_left_as_it_is(): void
    {
        $this->settings(['browser_site' => true]);

        $response = $this->get('/streamed')->assertOk();

        $this->assertSame('<html><head></head></html>', $response->streamedContent());
    }

    public function test_only_answers_under_three_hundred_get_the_script(): void
    {
        $this->settings(['browser_site' => true]);

        $this->assertStringContainsString('atrium-errors.js', (string) $this->get('/created')->assertStatus(201)->getContent());
        $this->assertSame('<html><head></head></html>', $this->get('/multiple')->assertStatus(300)->getContent());
    }

    #[DataProvider('prefixes')]
    public function test_the_admin_is_told_by_the_prefix_of_the_panel_with_or_without_slashes(string $prefix): void
    {
        $this->settings(['browser_admin' => true]);
        config(['atrium.prefix' => $prefix]);

        $this->assertStringContainsString('data-source="admin"', (string) $this->get('/admin/help')->getContent());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function prefixes(): array
    {
        return [
            'plain' => ['admin'],
            'with a slash in front' => ['/admin'],
            'with a slash behind' => ['admin/'],
            'with both' => ['/admin/'],
        ];
    }

    /**
     * A valid report of exactly this many bytes.
     */
    private function body(int $bytes): string
    {
        $base = (string) json_encode(['message' => 'x', 'pad' => '']);

        return (string) json_encode(['message' => 'x', 'pad' => str_repeat('a', $bytes - strlen($base))]);
    }

    /**
     * @param  array<string,mixed>  $server
     */
    private function postBody(string $body, array $server = []): TestResponse
    {
        return $this->call('POST', '/atrium-errors/browser', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'] + $server, $body);
    }
}
