<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

final class FailingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $reason) {}

    public function handle(): void
    {
        throw new RuntimeException($this->reason);
    }
}
