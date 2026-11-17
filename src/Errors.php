<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors;

use Atrium\Errors\Capture\EventFactory;
use Atrium\Errors\Pipeline\Sender;
use Atrium\Errors\Support\FallbackLog;
use Atrium\Errors\Support\Limiter;
use Illuminate\Http\Request;
use Throwable;
use WeakMap;

/**
 * The entry point: capture an exception, a browser report or a message.
 *
 * Exceptions Laravel reports, failed queue jobs and failed scheduled tasks
 * arrive here by themselves (see ErrorsServiceProvider); call it directly to
 * report one you caught and handled:
 *
 *   app(Errors::class)->capture($e);
 *   app(Errors::class)->message('Payment gateway answered in 9 s', 'warning');
 *
 * Nothing here may break the page that is being served: every step is
 * guarded, and an error inside the tracker goes to the fallback log, never
 * back to the exception handler.
 */
final class Errors
{
    /** @var WeakMap<Throwable,string> exceptions already captured, and as what: so a job's failure is not counted twice */
    private WeakMap $seen;

    private bool $busy = false;

    public function __construct(
        private readonly EventFactory $factory,
        private readonly Limiter $limiter,
        private readonly Sender $sender,
        private readonly FallbackLog $fallback,
    ) {
        $this->seen = new WeakMap;
    }

    /**
     * @param  string  $kind  php, job or schedule
     * @param  array<string,mixed>  $context
     */
    public function capture(Throwable $e, string $kind = 'php', array $context = []): ?Event
    {
        if (isset($this->seen[$e]) || $this->ignored($e)) {
            return null;
        }

        $this->seen[$e] = $kind;

        return $this->guarded(fn (): Event => $this->factory->fromThrowable($e, $kind, $context));
    }

    /**
     * @param  array{message:string,name?:string,stack?:string,url?:string,line?:int,column?:int,source?:string}  $report
     */
    public function captureBrowser(array $report, Request $request): ?Event
    {
        return $this->guarded(fn (): Event => $this->factory->fromBrowser($report, $request));
    }

    /**
     * @param  array<string,mixed>  $context
     */
    public function message(string $message, string $level = 'info', array $context = []): ?Event
    {
        return $this->guarded(fn (): Event => $this->factory->fromMessage($message, $level, $context));
    }

    public function enabled(): bool
    {
        return (bool) config('atrium-errors.enabled', true);
    }

    /**
     * @param  callable(): Event  $build
     */
    private function guarded(callable $build): ?Event
    {
        // An error raised while an error is handled is not captured again.
        if ($this->busy || ! $this->enabled()) {
            return null;
        }

        $this->busy = true;

        try {
            if (! $this->limiter->allow()) {
                return null;
            }

            $event = $build();
            $this->sender->send($event);

            return $event;
        } catch (Throwable $failure) {
            $this->fallback->note('atrium-errors could not capture an error: '.$failure::class.': '.$failure->getMessage());

            return null;
        } finally {
            $this->busy = false;
        }
    }

    private function ignored(Throwable $e): bool
    {
        foreach ((array) config('atrium-errors.ignore', []) as $class) {
            if (is_string($class) && $e instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
