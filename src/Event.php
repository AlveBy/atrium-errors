<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors;

use Carbon\CarbonImmutable;

/**
 * One captured error, already scrubbed: what every driver receives.
 *
 * Plain data, so it survives the queue as an array and a driver for another
 * service can map it field by field.
 *
 * `exceptions` — the exception first, then its previous ones; each has
 * `class`, `message` and `frames` (the frame the error was thrown in first:
 * `file` relative to the application, `line`, `function`, `in_app` and, for
 * the frames worth reading, `context` with `pre`, `line` and `post` lines).
 *
 * `request` — `url` (query scrubbed), `path`, `method`, `route`, `site_id`,
 * `locale`, `user_id`; empty outside a request.
 */
final class Event
{
    public const KINDS = ['php', 'job', 'schedule', 'browser', 'message'];

    public const LEVELS = ['fatal', 'error', 'warning', 'info'];

    /**
     * @param  array<int,array{class:string,message:string,frames:array<int,array<string,mixed>>}>  $exceptions
     * @param  array<string,mixed>  $request
     * @param  array<string,mixed>  $context
     * @param  array<int,array{time:string,category:string,message:string}>  $breadcrumbs
     * @param  array<string,string>  $runtime
     */
    public function __construct(
        public readonly string $id,
        public readonly string $kind,
        public readonly string $level,
        public readonly string $class,
        public readonly string $message,
        public readonly array $exceptions,
        public readonly array $request,
        public readonly array $context,
        public readonly array $breadcrumbs,
        public readonly string $environment,
        public readonly string $release,
        public readonly array $runtime,
        public readonly string $fingerprint,
        public readonly CarbonImmutable $timestamp,
    ) {}

    /**
     * The frames of the exception itself.
     *
     * @return array<int,array<string,mixed>>
     */
    public function frames(): array
    {
        return $this->exceptions[0]['frames'] ?? [];
    }

    /**
     * Where it happened, for a list: the first frame of the application.
     */
    public function culprit(): ?string
    {
        $frames = $this->frames();

        foreach ($frames as $frame) {
            if ($frame['in_app'] ?? false) {
                return $this->place($frame);
            }
        }

        return isset($frames[0]) ? $this->place($frames[0]) : null;
    }

    public function userId(): ?int
    {
        $id = $this->request['user_id'] ?? null;

        return is_int($id) ? $id : null;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'level' => $this->level,
            'class' => $this->class,
            'message' => $this->message,
            'exceptions' => $this->exceptions,
            'request' => $this->request,
            'context' => $this->context,
            'breadcrumbs' => $this->breadcrumbs,
            'environment' => $this->environment,
            'release' => $this->release,
            'runtime' => $this->runtime,
            'fingerprint' => $this->fingerprint,
            'timestamp' => $this->timestamp->toIso8601ZuluString('microsecond'),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            kind: (string) $data['kind'],
            level: (string) $data['level'],
            class: (string) $data['class'],
            message: (string) $data['message'],
            exceptions: (array) $data['exceptions'],
            request: (array) $data['request'],
            context: (array) $data['context'],
            breadcrumbs: (array) $data['breadcrumbs'],
            environment: (string) $data['environment'],
            release: (string) $data['release'],
            runtime: (array) $data['runtime'],
            fingerprint: (string) $data['fingerprint'],
            timestamp: CarbonImmutable::parse((string) $data['timestamp']),
        );
    }

    /**
     * @param  array<string,mixed>  $frame
     */
    private function place(array $frame): string
    {
        $function = (string) ($frame['function'] ?? '');
        $file = $frame['file'] ?? '';

        return $function !== '' ? $function.' ('.$file.')' : $file.':'.($frame['line'] ?? 0);
    }
}
