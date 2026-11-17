<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Reporters\Sentry;

use Illuminate\Http\Client\Factory;
use RuntimeException;

/**
 * The envelope endpoint over HTTP, with a short timeout: a slow Sentry must
 * not hold a queue worker or the end of a request.
 */
final class HttpTransport implements Transport
{
    public const CLIENT = 'atrium-errors/0.1';

    public function __construct(
        private readonly Factory $http,
        private readonly int $timeout = 3,
    ) {}

    public function send(Dsn $dsn, array $event): void
    {
        $response = $this->http
            ->timeout($this->timeout)
            ->withHeaders([
                'X-Sentry-Auth' => $dsn->authHeader(self::CLIENT),
                'Content-Type' => 'application/x-sentry-envelope',
            ])
            ->withBody(self::envelope($dsn, $event), 'application/x-sentry-envelope')
            ->post($dsn->envelopeUrl());

        if ($response->failed()) {
            throw new RuntimeException('Sentry answered '.$response->status());
        }
    }

    /**
     * Header, item header and the event, one JSON per line.
     *
     * @param  array<string,mixed>  $event
     */
    public static function envelope(Dsn $dsn, array $event): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_THROW_ON_ERROR;
        $body = json_encode($event, $flags);

        return implode("\n", [
            json_encode(['event_id' => $event['event_id'] ?? null, 'dsn' => (string) $dsn, 'sent_at' => gmdate('Y-m-d\TH:i:s\Z')], $flags),
            json_encode(['type' => 'event', 'length' => strlen($body)], $flags),
            $body,
        ])."\n";
    }
}
