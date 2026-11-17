<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Errors\Capture\Breadcrumbs;
use Atrium\Errors\Capture\EventFactory;
use Atrium\Errors\Capture\Scrubber;
use Atrium\Errors\Errors;
use Atrium\Errors\Models\Occurrence;
use Atrium\Errors\Pipeline\Sender;
use Atrium\Errors\Tests\TestCase;
use Exception;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use RuntimeException;

/**
 * What the capture writes into an event, to the character: the breadcrumbs
 * and the event factory.
 */
final class CaptureDetailsTest extends TestCase
{
    // ---- breadcrumbs ----------------------------------------------------

    public function test_a_breadcrumb_is_the_time_the_category_and_the_message(): void
    {
        $this->travelTo('2026-11-17 10:20:30.123456');
        $crumbs = new Breadcrumbs;

        $crumbs->add('cache', 'miss');

        $this->assertSame([['time' => '10:20:30.123', 'category' => 'cache', 'message' => 'miss']], $crumbs->all());
    }

    public function test_by_default_the_last_thirty_are_kept(): void
    {
        $crumbs = new Breadcrumbs;
        for ($i = 1; $i <= 31; $i++) {
            $crumbs->add('n', (string) $i);
        }

        $messages = array_column($crumbs->all(), 'message');

        $this->assertCount(30, $messages);
        $this->assertSame('2', $messages[0]);
        $this->assertSame('31', $messages[29]);
    }

    public function test_a_ring_of_one_keeps_one_and_a_ring_of_none_keeps_nothing(): void
    {
        $one = new Breadcrumbs(1);
        $one->add('n', 'first');
        $one->add('n', 'second');
        $none = new Breadcrumbs(0);
        $none->add('n', 'first');

        $this->assertSame(['second'], array_column($one->all(), 'message'));
        $this->assertSame([], $none->all());
    }

    public function test_clear_empties_the_ring(): void
    {
        $crumbs = new Breadcrumbs;
        $crumbs->add('n', 'x');

        $crumbs->clear();

        $this->assertSame([], $crumbs->all());
    }

    /**
     * @param  array{float,string}  $case
     */
    #[DataProvider('queries')]
    public function test_a_query_is_its_sql_and_how_long_it_took(string $sql, float $took, string $expected): void
    {
        $this->travelTo('2026-11-17 10:20:30');
        $crumbs = new Breadcrumbs;

        $crumbs->query(new QueryExecuted($sql, ['secret@example.test'], $took, DB::connection()));

        $this->assertSame([['time' => '10:20:30.000', 'category' => 'query', 'message' => $expected]], $crumbs->all());
    }

    /**
     * @return array<string,array{string,float,string}>
     */
    public static function queries(): array
    {
        return [
            'short, rounded to a tenth' => ['select 1', 12.36, 'select 1 [12.4 ms]'],
            'rounded down' => ['select 1', 0.04, 'select 1 [0 ms]'],
            'whole' => ['select 1', 3.0, 'select 1 [3 ms]'],
            'exactly as long as it may be' => [str_repeat('я', 500), 1.5, str_repeat('я', 500).' [1.5 ms]'],
            'one over' => [str_repeat('я', 501), 1.5, str_repeat('я', 500).' [1.5 ms]'],
            'far over' => [str_repeat('я', 900), 1.5, str_repeat('я', 500).' [1.5 ms]'],
        ];
    }

    #[DataProvider('logLines')]
    public function test_a_log_line_is_its_level_and_its_message_cut_to_five_hundred_characters(string $message, string $expected): void
    {
        $this->travelTo('2026-11-17 10:20:30');
        $crumbs = new Breadcrumbs;

        $crumbs->log(new MessageLogged('warning', $message, ['user' => 'secret']));

        $this->assertSame([['time' => '10:20:30.000', 'category' => 'log.warning', 'message' => $expected]], $crumbs->all());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function logLines(): array
    {
        return [
            'short' => ['Disk is almost full', 'Disk is almost full'],
            'exactly as long as it may be' => [str_repeat('я', 500), str_repeat('я', 500)],
            'one over' => [str_repeat('я', 501), str_repeat('я', 500)],
        ];
    }

    // ---- the event factory: the browser ---------------------------------

    public function test_a_browser_error_without_a_stack_takes_its_place_from_the_url_and_line(): void
    {
        $event = $this->factory()->fromBrowser(['message' => 'x', 'url' => 'http://localhost/assets/app.js', 'line' => 7, 'column' => 9], $this->page());

        $this->assertSame(7, $event->exceptions[0]['frames'][0]['line']);
        $this->assertSame('/assets/app.js', $event->exceptions[0]['frames'][0]['file']);
    }

    public function test_a_browser_error_with_a_url_alone_is_on_line_zero(): void
    {
        $event = $this->factory()->fromBrowser(['message' => 'x', 'url' => 'http://localhost/assets/app.js'], $this->page());

        $this->assertSame(0, $event->exceptions[0]['frames'][0]['line']);
        $this->assertSame('/assets/app.js', $event->exceptions[0]['frames'][0]['file']);
    }

    public function test_a_browser_error_keeps_the_stack_when_it_has_one(): void
    {
        $event = $this->factory()->fromBrowser([
            'message' => 'x',
            'stack' => "Error: x\n    at run (http://localhost/assets/app.js:12:3)",
            'url' => 'http://localhost/assets/other.js',
            'line' => 99,
        ], $this->page());

        $this->assertCount(1, $event->exceptions[0]['frames']);
        $this->assertSame(12, $event->exceptions[0]['frames'][0]['line']);
        $this->assertSame('run', $event->exceptions[0]['frames'][0]['function']);
    }

    public function test_a_browser_error_without_url_or_stack_has_no_frames(): void
    {
        $this->assertSame([], $this->factory()->fromBrowser(['message' => 'x', 'url' => ''], $this->page())->exceptions[0]['frames']);
        $this->assertSame([], $this->factory()->fromBrowser(['message' => 'x'], $this->page())->exceptions[0]['frames']);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function names(): array
    {
        return [
            'a plain name' => ['TypeError', 'TypeError'],
            'with a dollar and digits' => ['My_Error$2', 'My_Error$2'],
            'a single letter' => ['E', 'E'],
            'sixty-four characters' => [str_repeat('a', 64), str_repeat('a', 64)],
            'sixty-five characters' => [str_repeat('a', 65), 'Error'],
            'starts with a digit' => ['1TypeError', 'Error'],
            'has a dash' => ['My-Error', 'Error'],
            'has a space in front' => [' TypeError', 'Error'],
            'empty' => ['', 'Error'],
        ];
    }

    #[DataProvider('names')]
    public function test_a_browser_error_is_named_by_the_page_only_when_the_name_is_safe(string $name, string $expected): void
    {
        $this->assertSame($expected, $this->factory()->fromBrowser(['message' => 'x', 'name' => $name], $this->page())->class);
    }

    public function test_a_browser_error_without_a_name_is_just_an_error(): void
    {
        $this->assertSame('Error', $this->factory()->fromBrowser(['message' => 'x'], $this->page())->class);
    }

    #[DataProvider('browserMessages')]
    public function test_a_browser_message_is_cut_to_a_thousand_characters_everywhere(string $message, string $expected): void
    {
        $event = $this->factory()->fromBrowser(['message' => $message], $this->page());

        $this->assertSame($expected, $event->message);
        $this->assertSame($expected, $event->exceptions[0]['message']);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function browserMessages(): array
    {
        return [
            'short' => ['Cannot read properties', 'Cannot read properties'],
            'exactly as long as it may be' => [str_repeat('я', 1000), str_repeat('я', 1000)],
            'one over' => [str_repeat('я', 1001), str_repeat('я', 1000)],
        ];
    }

    public function test_a_browser_error_remembers_the_page_it_happened_on(): void
    {
        $request = $this->page();
        $request->headers->set('referer', 'https://shop.example.test/cart?step=2&session=abc&lang=ru');

        $event = $this->factory()->fromBrowser(['message' => 'x'], $request);

        $this->assertSame('https://shop.example.test/cart?step=2&session=[Filtered]&lang=ru', $event->request['url']);
        $this->assertSame('/cart', $event->request['path']);
        $this->assertNull($event->request['method']);
        $this->assertNull($event->request['route']);
    }

    public function test_a_browser_error_without_a_referer_knows_no_page(): void
    {
        $event = $this->factory()->fromBrowser(['message' => 'x'], $this->page());

        $this->assertNull($event->request['url']);
        $this->assertNull($event->request['path']);
    }

    public function test_a_query_parameter_named_in_the_config_loses_its_value_in_the_page_address(): void
    {
        config(['atrium-errors.scrub_keys' => ['room_code']]);
        $this->app->forgetInstance(Scrubber::class);
        $this->app->forgetInstance(EventFactory::class);
        $request = $this->page();
        $request->headers->set('referer', 'https://shop.example.test/cart?lang=ru&room_code=r-42');

        $url = $this->factory()->fromBrowser(['message' => 'x'], $request)->request['url'];

        $this->assertSame('https://shop.example.test/cart?lang=ru&room_code=[Filtered]', $url);
    }

    public function test_a_page_address_with_an_empty_query_keeps_its_question_mark(): void
    {
        $request = $this->page();
        $request->headers->set('referer', 'https://shop.example.test/cart?');

        $this->assertSame('https://shop.example.test/cart?', $this->factory()->fromBrowser(['message' => 'x'], $request)->request['url']);
    }

    public function test_a_page_address_without_a_query_comes_back_as_it_is(): void
    {
        $request = $this->page();
        $request->headers->set('referer', 'https://shop.example.test/cart');

        $this->assertSame('https://shop.example.test/cart', $this->factory()->fromBrowser(['message' => 'x'], $request)->request['url']);
    }

    #[DataProvider('pathLengths')]
    public function test_the_path_of_a_page_is_cut_to_five_hundred_and_twelve_characters(int $length, int $kept): void
    {
        $request = $this->page();
        $request->headers->set('referer', 'https://shop.example.test/'.str_repeat('б', $length - 1));

        $this->assertSame('/'.str_repeat('б', $kept - 1), $this->factory()->fromBrowser(['message' => 'x'], $request)->request['path']);
    }

    /**
     * @return array<string,array{int,int}>
     */
    public static function pathLengths(): array
    {
        return ['short' => [10, 10], 'exactly' => [512, 512], 'one over' => [513, 512], 'far over' => [900, 512]];
    }

    // ---- the event factory: messages and exceptions ---------------------

    public function test_a_message_is_cut_to_a_thousand_characters(): void
    {
        $factory = $this->factory();

        $this->assertSame(str_repeat('я', 1000), $factory->fromMessage(str_repeat('я', 1000))->message);
        $this->assertSame(str_repeat('я', 1000), $factory->fromMessage(str_repeat('я', 1001))->message);
        $this->assertSame(str_repeat('я', 1000), $factory->fromMessage(str_repeat('я', 1001))->exceptions[0]['message']);
    }

    public function test_an_exception_message_is_cut_to_two_thousand_characters(): void
    {
        $factory = $this->factory();

        $this->assertSame(str_repeat('я', 2000), $factory->fromThrowable(new Exception(str_repeat('я', 2000)))->message);
        $this->assertSame(str_repeat('я', 2000), $factory->fromThrowable(new Exception(str_repeat('я', 2001)))->message);
    }

    public function test_the_level_of_an_exception_can_be_given(): void
    {
        $factory = $this->factory();

        $this->assertSame('warning', $factory->fromThrowable(new Exception('x'), 'php', [], 'warning')->level);
        $this->assertSame('error', $factory->fromThrowable(new Exception('x'))->level);
    }

    public function test_an_event_id_is_thirty_two_hex_digits(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $this->factory()->fromMessage('x')->id);
    }

    #[DataProvider('functionNames')]
    public function test_the_function_of_a_browser_frame_is_cut_to_two_hundred_and_fifty_five_characters(int $length, int $kept): void
    {
        $name = str_repeat('я', $length);

        $event = $this->factory()->fromBrowser(['message' => 'x', 'stack' => 'at '.$name.' (http://localhost/app.js:1:1)'], $this->page());

        $this->assertSame(str_repeat('я', $kept), $event->exceptions[0]['frames'][0]['function']);
    }

    /**
     * @return array<string,array{int,int}>
     */
    public static function functionNames(): array
    {
        return ['short' => [10, 10], 'exactly' => [255, 255], 'one over' => [256, 255], 'far over' => [400, 255]];
    }

    // ---- the event factory: who ----------------------------------------

    public function test_the_user_is_found_on_the_site_guard(): void
    {
        $this->actingAs(new GenericUser(['id' => 41]));

        $this->assertSame(41, $this->factory()->fromMessage('x')->request['user_id']);
    }

    public function test_the_user_is_found_on_the_admin_guard_when_the_site_has_none(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        $this->assertSame($admin->id, $this->factory()->fromMessage('x')->request['user_id']);
    }

    public function test_an_identifier_the_database_hands_out_as_text_is_a_number(): void
    {
        $this->actingAs(new GenericUser(['id' => '41']));

        $this->assertSame(41, $this->factory()->fromMessage('x')->request['user_id']);
    }

    public function test_nobody_signed_in_is_no_user(): void
    {
        $this->assertNull($this->factory()->fromMessage('x')->request['user_id']);
    }

    // ---- the sender -----------------------------------------------------

    public function test_the_end_of_the_response_is_awaited_once_however_many_events_there_are(): void
    {
        (new ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        $this->app->forgetInstance(Sender::class);
        $this->app->forgetInstance(Errors::class);
        $callbacks = new ReflectionProperty($this->app, 'terminatingCallbacks');
        $before = count($callbacks->getValue($this->app));

        $this->errors()->capture(new RuntimeException('One'));
        $this->errors()->capture(new RuntimeException('Two'));
        $this->errors()->capture(new RuntimeException('Three'));

        $this->assertSame($before + 1, count($callbacks->getValue($this->app)));
        $this->app->terminate();
        $this->assertSame(3, Occurrence::query()->count());
    }

    private function factory(): EventFactory
    {
        return $this->app->make(EventFactory::class);
    }

    private function page(): Request
    {
        return Request::create('http://localhost/atrium-errors/browser', 'POST');
    }
}
