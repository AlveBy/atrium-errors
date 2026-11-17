<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Pipeline;

use Atrium\Errors\Event;
use Atrium\Errors\Support\FallbackLog;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Queue\Factory as Queue;
use Throwable;

/**
 * Gets an event to the drivers without making the visitor wait or fail.
 *
 * With a queue connection set, the event goes to the queue. Otherwise (or
 * when the queue refuses it) it waits in memory and is delivered once the
 * response has been sent; in the console — a queue worker, a scheduled
 * command — there is no response to wait for, so it is delivered at once.
 *
 * Delivery gives the event to each driver in turn. A driver that throws is
 * written down in the fallback log and the next one still gets the event;
 * when the database driver fails, the whole event goes to that log.
 */
final class Sender
{
    /** Events waiting for the end of the request. */
    public const BUFFER = 50;

    /** @var array<int,Event> */
    private array $buffer = [];

    private bool $flushRegistered = false;

    public function __construct(
        private readonly Application $app,
        private readonly Drivers $drivers,
        private readonly FallbackLog $fallback,
    ) {}

    public function send(Event $event): void
    {
        $connection = config('atrium-errors.queue');

        if (is_string($connection) && $connection !== '') {
            try {
                $this->app->make(Queue::class)->connection($connection)->push(new DeliverEvent($event->toArray()));

                return;
            } catch (Throwable) {
                // No queue right now: keep it here.
            }
        }

        if ($this->app->runningInConsole()) {
            $this->deliver($event);

            return;
        }

        if (count($this->buffer) >= self::BUFFER) {
            return;
        }

        $this->buffer[] = $event;

        if (! $this->flushRegistered) {
            $this->flushRegistered = true;
            $this->app->terminating(fn () => $this->flush());
        }
    }

    private function flush(): void
    {
        $events = $this->buffer;
        $this->buffer = [];

        foreach ($events as $event) {
            $this->deliver($event);
        }
    }

    public function deliver(Event $event): void
    {
        try {
            $drivers = $this->drivers->active();
        } catch (Throwable $e) {
            $this->fallback->write($event, $e);
            $drivers = [];
        }

        foreach ($drivers as $name => $driver) {
            try {
                $driver->report($event);
            } catch (Throwable $e) {
                if ($name === 'database') {
                    $this->fallback->write($event, $e);
                } else {
                    $this->fallback->note($name.' driver failed: '.$e::class.': '.$e->getMessage());
                }
            }
        }
    }

    /**
     * Events still waiting: for tests.
     *
     * @return array<int,Event>
     */
    public function pending(): array
    {
        return $this->buffer;
    }
}
