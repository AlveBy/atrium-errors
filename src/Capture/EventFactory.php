<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Capture;

use Atrium\Admin\Support\Sites;
use Atrium\Errors\Event;
use Carbon\CarbonImmutable;
use ErrorException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds an Event from an exception or a browser report, scrubbed.
 *
 * The request part is deliberately thin: the address (with secret query
 * parameters taken out), method, route, site, language and the user id —
 * no headers, no cookies, no body, no IP address.
 */
final class EventFactory
{
    /** Previous exceptions kept after the first one. */
    private const MAX_PREVIOUS = 3;

    public function __construct(
        private readonly Application $app,
        private readonly Frames $frames,
        private readonly Fingerprint $fingerprint,
        private readonly Scrubber $scrubber,
        private readonly Breadcrumbs $breadcrumbs,
        private readonly Release $release,
    ) {}

    /**
     * @param  array<string,mixed>  $context  what the capture knows (the job, the command…)
     */
    public function fromThrowable(Throwable $e, string $kind = 'php', array $context = [], ?string $level = null): Event
    {
        $exceptions = [];
        $current = $e;

        while ($current !== null && count($exceptions) <= self::MAX_PREVIOUS) {
            $exceptions[] = [
                'class' => $current::class,
                'message' => $this->message($current),
                'frames' => $this->scrubFrames($this->frames->of($current)),
            ];
            $current = $current->getPrevious();
        }

        $first = $exceptions[0];

        return $this->make(
            kind: $kind,
            level: $level ?? $this->level($e),
            class: $first['class'],
            message: $first['message'],
            exceptions: $exceptions,
            context: $context,
        );
    }

    /**
     * A JavaScript error reported by the page.
     *
     * @param  array{message:string,name?:string,stack?:string,url?:string,line?:int,column?:int,source?:string}  $report
     */
    public function fromBrowser(array $report, Request $request): Event
    {
        $origin = $request->getSchemeAndHttpHost();
        $frames = $this->frames->ofBrowserStack((string) ($report['stack'] ?? ''), $origin);

        if ($frames === [] && ($report['url'] ?? '') !== '') {
            $frames = $this->frames->ofBrowserStack('at '.$report['url'].':'.($report['line'] ?? 0), $origin);
        }

        $class = preg_match('/^[A-Za-z][A-Za-z0-9_$]{0,63}$/', (string) ($report['name'] ?? '')) ? $report['name'] : 'Error';

        // The page the error happened on, not the address the report came to.
        $page = $request->headers->get('referer') ?? '';

        return $this->make(
            kind: 'browser',
            level: 'error',
            class: $class,
            message: $this->scrubber->text(mb_substr($report['message'], 0, 1000)),
            exceptions: [['class' => $class, 'message' => $this->scrubber->text(mb_substr($report['message'], 0, 1000)), 'frames' => $this->scrubFrames($frames)]],
            context: ['source' => ($report['source'] ?? 'site') === 'admin' ? 'admin' : 'site'],
            page: $page,
        );
    }

    /**
     * A message without an exception: Errors::message('Payment gateway is slow').
     *
     * @param  array<string,mixed>  $context
     */
    public function fromMessage(string $message, string $level = 'info', array $context = []): Event
    {
        $message = $this->scrubber->text(mb_substr($message, 0, 1000));

        return $this->make(
            kind: 'message',
            level: in_array($level, Event::LEVELS, true) ? $level : 'info',
            class: 'Message',
            message: $message,
            exceptions: [['class' => 'Message', 'message' => $message, 'frames' => []]],
            context: $context,
        );
    }

    /**
     * @param  array<int,array{class:string,message:string,frames:array<int,array<string,mixed>>}>  $exceptions
     * @param  array<string,mixed>  $context
     */
    private function make(string $kind, string $level, string $class, string $message, array $exceptions, array $context, ?string $page = null): Event
    {
        return new Event(
            id: str_replace('-', '', (string) Str::uuid()),
            kind: $kind,
            level: $level,
            class: $class,
            message: $message,
            exceptions: $exceptions,
            request: $this->request($page),
            context: (array) $this->scrubber->scrub($context),
            breadcrumbs: (array) $this->scrubber->scrub($this->breadcrumbs->all()),
            environment: (string) $this->app->environment(),
            release: $this->release->current(),
            runtime: [
                'php' => PHP_VERSION,
                'laravel' => $this->app->version(),
                'os' => PHP_OS_FAMILY,
                'sapi' => PHP_SAPI,
            ],
            fingerprint: $this->fingerprint->of($class, $message, $exceptions[0]['frames']),
            timestamp: CarbonImmutable::now(),
        );
    }

    /**
     * A query error tells its SQL with the values filled in: a customer's
     * email, an encrypted column. Keep the SQL with placeholders.
     */
    private function message(Throwable $e): string
    {
        $message = $e instanceof QueryException
            ? Str::before($e->getMessage(), ' (Connection:').' (SQL: '.$e->getSql().')'
            : $e->getMessage();

        return $this->scrubber->text(mb_substr($message, 0, 2000));
    }

    private function level(Throwable $e): string
    {
        if ($e instanceof ErrorException) {
            return in_array($e->getSeverity(), [E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_WARNING, E_USER_NOTICE, E_USER_DEPRECATED, E_CORE_WARNING, E_COMPILE_WARNING], true) ? 'warning' : 'fatal';
        }

        return $e instanceof \Error ? 'fatal' : 'error';
    }

    /**
     * @param  array<int,array<string,mixed>>  $frames
     * @return array<int,array<string,mixed>>
     */
    private function scrubFrames(array $frames): array
    {
        foreach ($frames as &$frame) {
            if (isset($frame['context'])) {
                $frame['context'] = [
                    'pre' => array_map($this->scrubber->text(...), $frame['context']['pre']),
                    'line' => $this->scrubber->text($frame['context']['line']),
                    'post' => array_map($this->scrubber->text(...), $frame['context']['post']),
                ];
            }

            $frame['function'] = mb_substr((string) $frame['function'], 0, 255);
        }

        return $frames;
    }

    /**
     * @param  string|null  $page  for a browser report: the page it came from, '' when unknown
     * @return array<string,mixed>
     */
    private function request(?string $page): array
    {
        $request = $this->app->make('request');
        $browser = $page !== null;

        // A command or a queue worker has no request of its own.
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests() && ! $browser) {
            return [];
        }

        // A browser report without a Referer: the page is unknown, and the
        // address the report was posted to would only mislead.
        $url = $browser ? $page : $request->fullUrl();
        $path = $url !== '' ? '/'.ltrim((string) (parse_url($url, PHP_URL_PATH) ?? '/'), '/') : null;

        return [
            'url' => $url !== '' ? $this->scrubber->text($this->withoutSecrets($url)) : null,
            'path' => $path !== null ? mb_substr($this->scrubber->text($path), 0, 512) : null,
            'method' => $browser ? null : $request->getMethod(),
            'route' => $browser ? null : $request->route()?->getName(),
            'site_id' => $this->siteId(),
            'locale' => $this->app->getLocale(),
            'user_id' => $this->userId(),
        ];
    }

    /**
     * Query parameters with secret names lose their values.
     */
    private function withoutSecrets(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return $url;
        }

        parse_str($query, $params);
        $clean = $this->scrubber->scrub($params);

        return Str::before($url, '?').'?'.urldecode(http_build_query((array) $clean));
    }

    private function siteId(): ?int
    {
        $sites = $this->app->make(Sites::class);

        return $sites->isResolved() ? $sites->current()->id : null;
    }

    private function userId(): ?int
    {
        // The site's guard, then the admin's; one that cannot even be built
        // (no user model) must not hide the other.
        foreach ([null, config('atrium.auth.guard', 'admin')] as $guard) {
            try {
                $id = $this->app->make('auth')->guard($guard)->id();
            } catch (Throwable) {
                continue;
            }

            if (is_numeric($id)) {
                return (int) $id;
            }
        }

        return null;
    }
}
