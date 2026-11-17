<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Http\Middleware;

use Atrium\Errors\Support\Options;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the small error-reporting script into the pages of the site and of the
 * admin, when switched on in the settings — no theme or layout to edit.
 *
 * Only full HTML pages get it: not Inertia's JSON visits, not downloads, not
 * redirects.
 */
final class InjectBrowserScript
{
    public function __construct(private readonly Options $options) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $source = $this->isAdmin($request) ? 'admin' : 'site';

        if (! $this->options->bool('browser_'.$source) || ! $this->isHtmlPage($request, $response)) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || ($head = stripos($content, '</head>')) === false) {
            return $response;
        }

        $nonce = Vite::cspNonce();
        $tag = sprintf(
            '<script src="%s" data-endpoint="%s" data-source="%s" defer%s></script>',
            e(route('atrium.errors.script', [], false)),
            e(route('atrium.errors.browser', [], false)),
            $source,
            $nonce !== null ? ' nonce="'.e($nonce).'"' : '',
        );

        $response->setContent(substr($content, 0, $head).$tag.substr($content, $head));

        return $response;
    }

    private function isAdmin(Request $request): bool
    {
        $prefix = trim((string) config('atrium.prefix', 'admin'), '/');

        return $request->is($prefix, $prefix.'/*');
    }

    private function isHtmlPage(Request $request, Response $response): bool
    {
        return $response->getStatusCode() < 300
            && ! $request->headers->has('X-Inertia')
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $response->headers->has('Content-Disposition');
    }
}
