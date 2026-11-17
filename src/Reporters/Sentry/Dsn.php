<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Reporters\Sentry;

use InvalidArgumentException;

/**
 * A Sentry DSN: https://{public key}@{host}/{project id}, also with a path
 * before the project (self-hosted under a folder) and a port. GlitchTip and
 * other Sentry-compatible services hand out the same kind of string.
 */
final class Dsn
{
    private function __construct(
        public readonly string $scheme,
        public readonly string $host,
        public readonly ?int $port,
        public readonly string $path,
        public readonly string $publicKey,
        public readonly string $projectId,
        private readonly string $original,
    ) {}

    public static function parse(string $dsn): self
    {
        $parts = parse_url(trim($dsn));

        if ($parts === false || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ($parts['host'] ?? '') === '' || ($parts['user'] ?? '') === '') {
            throw new InvalidArgumentException('Not a Sentry DSN: expected https://key@host/project');
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $slash = strrpos($path, '/');
        $project = $slash === false ? '' : substr($path, $slash + 1);

        if ($project === '' || ! ctype_alnum($project)) {
            throw new InvalidArgumentException('Not a Sentry DSN: the project id is missing');
        }

        return new self(
            scheme: $parts['scheme'],
            host: $parts['host'],
            port: $parts['port'] ?? null,
            path: substr($path, 0, (int) $slash),
            publicKey: rawurldecode($parts['user']),
            projectId: $project,
            original: trim($dsn),
        );
    }

    /**
     * Where envelopes go.
     */
    public function envelopeUrl(): string
    {
        return $this->scheme.'://'.$this->host.($this->port !== null ? ':'.$this->port : '').$this->path.'/api/'.$this->projectId.'/envelope/';
    }

    public function authHeader(string $client): string
    {
        return 'Sentry sentry_version=7, sentry_client='.$client.', sentry_key='.$this->publicKey;
    }

    public function __toString(): string
    {
        return $this->original;
    }
}
