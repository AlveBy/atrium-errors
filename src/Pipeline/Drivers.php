<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Pipeline;

use Atrium\Errors\Contracts\ErrorReporter;
use Atrium\Errors\Reporters\DatabaseReporter;
use Atrium\Errors\Reporters\LogReporter;
use Atrium\Errors\Reporters\SentryReporter;
use Atrium\Errors\Support\Options;
use Illuminate\Contracts\Container\Container;

/**
 * The drivers by name: the three built in and those of `atrium-errors.reporters`.
 */
final class Drivers
{
    public const BUILT_IN = [
        'database' => DatabaseReporter::class,
        'sentry' => SentryReporter::class,
        'log' => LogReporter::class,
    ];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Every known driver: name => class.
     *
     * @return array<string,class-string>
     */
    public function known(): array
    {
        $custom = array_filter((array) config('atrium-errors.reporters', []), static fn (mixed $class): bool => is_string($class) && is_subclass_of($class, ErrorReporter::class));

        return [...self::BUILT_IN, ...$custom];
    }

    /**
     * The drivers switched on, resolved; unknown names are skipped.
     *
     * @return array<string,ErrorReporter>
     */
    public function active(): array
    {
        $known = $this->known();
        $active = [];

        // Resolved each time: the sender keeps this object for the whole
        // process, the settings live as long as a request or a job.
        foreach ($this->container->make(Options::class)->drivers() as $name) {
            if (isset($known[$name])) {
                $active[$name] = $this->container->make($known[$name]);
            }
        }

        return $active;
    }
}
