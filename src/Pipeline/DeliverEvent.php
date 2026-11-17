<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Pipeline;

use Atrium\Errors\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * An event on its way through the queue. Tried once: a driver that fails is
 * already written to the fallback log, and a retry would count it twice.
 */
final class DeliverEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  array<string,mixed>  $event
     */
    public function __construct(public readonly array $event) {}

    public function handle(Sender $sender): void
    {
        $sender->deliver(Event::fromArray($this->event));
    }
}
