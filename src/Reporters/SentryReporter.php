<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Reporters;

use Atrium\Errors\Contracts\ErrorReporter;
use Atrium\Errors\Event;
use Atrium\Errors\Reporters\Sentry\Dsn;
use Atrium\Errors\Reporters\Sentry\Transport;
use Atrium\Errors\Support\Options;

/**
 * Sends events to Sentry, or to GlitchTip, self-hosted Sentry and anything
 * else that takes the Sentry protocol, with no SDK to install: the event is
 * mapped to Sentry's JSON and posted as an envelope.
 *
 * It is the same scrubbed event the database gets, grouped by the same
 * fingerprint, so an issue here and an issue there are the same bug.
 * The DSN comes from the module settings or ATRIUM_ERRORS_SENTRY_DSN /
 * SENTRY_DSN; without one the driver does nothing.
 */
final class SentryReporter implements ErrorReporter
{
    public function __construct(
        private readonly Transport $transport,
        private readonly Options $options,
    ) {}

    public function report(Event $event): void
    {
        $dsn = $this->options->string('sentry_dsn');

        if ($dsn === '') {
            return;
        }

        $this->transport->send(Dsn::parse($dsn), self::payload($event));
    }

    /**
     * The event in the Sentry format.
     *
     * @return array<string,mixed>
     */
    public static function payload(Event $event): array
    {
        $exceptions = [];

        // Sentry lists the chain innermost first and frames oldest first.
        foreach (array_reverse($event->exceptions) as $exception) {
            $exceptions[] = [
                'type' => $exception['class'],
                'value' => $exception['message'],
                'stacktrace' => ['frames' => array_map(self::frame(...), array_reverse($exception['frames']))],
            ];
        }

        $request = $event->request;

        return array_filter([
            'event_id' => $event->id,
            'timestamp' => $event->timestamp->getTimestamp() + $event->timestamp->micro / 1_000_000,
            'platform' => $event->kind === 'browser' ? 'javascript' : 'php',
            'level' => $event->level,
            'logger' => 'atrium-errors',
            'release' => $event->release,
            'environment' => $event->environment,
            'message' => $event->kind === 'message' ? ['formatted' => $event->message] : null,
            'exception' => $event->kind === 'message' ? null : ['values' => $exceptions],
            'fingerprint' => [$event->fingerprint],
            'request' => isset($request['url']) ? array_filter(['url' => $request['url'], 'method' => $request['method'] ?? null]) : null,
            'user' => $event->userId() !== null ? ['id' => (string) $event->userId()] : null,
            'tags' => array_filter([
                'kind' => $event->kind,
                'route' => $request['route'] ?? null,
                'site' => isset($request['site_id']) ? (string) $request['site_id'] : null,
                'locale' => $request['locale'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'extra' => $event->context !== [] ? $event->context : null,
            'breadcrumbs' => $event->breadcrumbs !== [] ? ['values' => array_map(static fn (array $crumb): array => [
                'category' => $crumb['category'],
                'message' => $crumb['message'],
                'level' => str_contains($crumb['category'], 'error') ? 'error' : 'info',
            ], $event->breadcrumbs)] : null,
            'contexts' => [
                'runtime' => ['name' => 'php', 'version' => $event->runtime['php'] ?? null],
                'framework' => ['name' => 'Laravel', 'version' => $event->runtime['laravel'] ?? null],
                'os' => ['name' => $event->runtime['os'] ?? null],
            ],
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string,mixed>  $frame
     * @return array<string,mixed>
     */
    private static function frame(array $frame): array
    {
        $context = $frame['context'] ?? null;

        return array_filter([
            'filename' => $frame['file'] ?? '',
            'lineno' => $frame['line'] ?? 0,
            'function' => ($frame['function'] ?? '') !== '' ? $frame['function'] : null,
            'in_app' => (bool) ($frame['in_app'] ?? false),
            'pre_context' => $context['pre'] ?? null,
            'context_line' => $context['line'] ?? null,
            'post_context' => $context['post'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
