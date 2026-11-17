<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Models\Issue;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * JavaScript errors of the site and the admin: the endpoint anyone can post
 * to, and the script put into the pages.
 */
final class BrowserTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(static function (): void {
            Route::get('/tours', static fn () => response('<html><head><title>Tours</title></head><body>Tours</body></html>'));
            Route::get('/no-head', static fn () => response('<p>Fragment</p>'));
            Route::get('/data.json', static fn () => response()->json(['ok' => true]));
            Route::get('/brochure', static fn () => response('<html><head></head></html>', 200, ['Content-Type' => 'text/html', 'Content-Disposition' => 'attachment; filename=brochure.html']));
            Route::get('/moved', static fn () => redirect('/tours'));
            Route::get('/admin/help', static fn () => response('<html><head></head><body>Help</body></html>'));
        });
    }

    public function test_a_report_is_stored_with_its_stack_and_page(): void
    {
        $this->settings(['browser_site' => true]);

        $this->withHeader('Referer', 'http://localhost/tours/greece?utm_source=mail&token=abc123')
            ->postJson('/atrium-errors/browser', [
                'message' => "Cannot read properties of undefined (reading 'price')",
                'name' => 'TypeError',
                'stack' => "TypeError: Cannot read properties of undefined\n    at renderPrice (http://localhost/build/app-Bx91kQ2z.js?v=3:10:5)\n    at http://localhost/build/app-Bx91kQ2z.js:20:1\n    at https://cdn.example.test/lib.js:1:1",
                'source' => 'site',
                'line' => 10,
                'column' => 5,
            ])
            ->assertStatus(202);

        $issue = Issue::query()->sole();
        $this->assertSame('browser', $issue->kind);
        $this->assertSame('TypeError', $issue->class);

        $event = Occurrence::query()->sole();
        $this->assertSame('/tours/greece', $event->path);
        $payload = $event->payload;
        $this->assertSame(['source' => 'site'], $payload['context']);
        $this->assertStringNotContainsString('abc123', (string) $payload['request']['url']);

        $frames = $payload['exceptions'][0]['frames'];
        $this->assertSame(['/build/app-Bx91kQ2z.js', '/build/app-Bx91kQ2z.js', 'https://cdn.example.test/lib.js'], array_column($frames, 'file'));
        $this->assertSame(['renderPrice', '', ''], array_column($frames, 'function'));
        $this->assertSame([true, true, false], array_column($frames, 'in_app'));
    }

    public function test_firefox_stacks_and_reports_without_a_stack_are_understood(): void
    {
        $this->settings(['browser_admin' => true]);

        $this->postJson('/atrium-errors/browser', ['message' => 'a is null', 'stack' => "save@http://localhost/admin.js:4:2\n@http://localhost/admin.js:9:1", 'source' => 'admin'])->assertStatus(202);
        $this->postJson('/atrium-errors/browser', ['message' => 'Script error.', 'url' => 'http://localhost/inline.js', 'line' => 3, 'name' => 'bad name!'])->assertStatus(202);

        $first = Occurrence::query()->orderBy('id')->first()?->payload;
        $this->assertSame(['save', ''], array_column($first['exceptions'][0]['frames'], 'function'));
        $this->assertSame(['source' => 'admin'], $first['context']);

        $second = Occurrence::query()->orderByDesc('id')->first()?->payload;
        $this->assertSame('/inline.js', $second['exceptions'][0]['frames'][0]['file']);
        $this->assertSame('Error', $second['exceptions'][0]['class']);
        $this->assertNull(Occurrence::query()->orderByDesc('id')->first()?->path);
    }

    public function test_the_endpoint_is_closed_while_both_switches_are_off(): void
    {
        $this->postJson('/atrium-errors/browser', ['message' => 'x'])->assertNotFound();

        $this->assertSame(0, Occurrence::query()->count());
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function junk(): array
    {
        return [
            'not json' => ['message=hello'],
            'json string' => ['"just a string"'],
            'empty object' => ['{}'],
            'no message' => ['{"stack":"at x"}'],
            'message not text' => ['{"message":["a","b"]}'],
            'line not a number' => ['{"message":"x","line":"ten"}'],
            'negative column' => ['{"message":"x","column":-1}'],
            'unknown source' => ['{"message":"x","source":"mobile-app"}'],
            'message too long' => ['{"message":"'.str_repeat('a', 1001).'"}'],
            'name too long' => ['{"message":"x","name":"'.str_repeat('N', 65).'"}'],
            'broken json' => ['{"message": "x"'],
        ];
    }

    #[DataProvider('junk')]
    public function test_junk_is_refused(string $body): void
    {
        $this->settings(['browser_site' => true]);

        $this->call('POST', '/atrium-errors/browser', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertStatus(422);

        $this->assertSame(0, Occurrence::query()->count());
    }

    public function test_an_oversized_report_is_refused_before_it_is_read(): void
    {
        $this->settings(['browser_site' => true]);

        $big = (string) json_encode(['message' => 'x', 'stack' => str_repeat('at f (http://localhost/a.js:1:1)'."\n", 600)]);
        $this->assertGreaterThan(16384, strlen($big));

        $this->call('POST', '/atrium-errors/browser', [], [], [], ['CONTENT_TYPE' => 'application/json'], $big)->assertStatus(413);
        $this->call('POST', '/atrium-errors/browser', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_CONTENT_LENGTH' => '999999'], '{"message":"x"}')->assertStatus(413);

        $this->assertSame(0, Occurrence::query()->count());
    }

    public function test_a_visitor_is_throttled(): void
    {
        $this->settings(['browser_site' => true]);
        config(['atrium-errors.browser.per_minute' => 3]);

        $statuses = [];
        for ($i = 0; $i < 5; $i++) {
            $statuses[] = $this->postJson('/atrium-errors/browser', ['message' => 'Flood '.$i])->status();
        }

        $this->assertSame([202, 202, 202, 429, 429], $statuses);
    }

    public function test_the_script_is_served(): void
    {
        $response = $this->get('/atrium-errors.js')->assertOk();

        $this->assertStringContainsString('application/javascript', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age=86400', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('sendBeacon', (string) $response->getContent());
    }

    public function test_the_script_goes_into_site_pages_when_switched_on(): void
    {
        $this->get('/tours')->assertDontSee('atrium-errors.js', false);

        $this->settings(['browser_site' => true]);

        $this->get('/tours')
            ->assertSee('<script src="/atrium-errors.js" data-endpoint="/atrium-errors/browser" data-source="site" defer></script></head>', false);
        $this->get('/admin/help')->assertDontSee('atrium-errors.js', false);
    }

    public function test_the_script_goes_into_admin_pages_with_the_csp_nonce(): void
    {
        $this->settings(['browser_admin' => true]);
        Vite::useCspNonce('n0nce');

        $this->get('/admin/help')->assertSee('data-source="admin" defer nonce="n0nce"></script>', false);
        $this->get('/tours')->assertDontSee('atrium-errors.js', false);
    }

    public function test_only_full_html_pages_get_the_script(): void
    {
        $this->settings(['browser_site' => true]);

        $this->get('/no-head')->assertDontSee('atrium-errors.js', false);
        $this->get('/data.json')->assertDontSee('atrium-errors.js', false);
        $this->get('/brochure')->assertDontSee('atrium-errors.js', false);
        $this->get('/moved')->assertRedirect('/tours');
        $this->withHeader('X-Inertia', 'true')->get('/tours')->assertDontSee('atrium-errors.js', false);
    }
}
