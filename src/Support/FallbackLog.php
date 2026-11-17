<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Support;

use Atrium\Errors\Event;
use Throwable;

/**
 * The last resort: one JSON line per event in a file of its own, when the
 * database (or the queue) cannot take it. Written with plain PHP, not the
 * log manager, so a broken log channel cannot lose it either.
 */
final class FallbackLog
{
    public function __construct(private readonly string $path) {}

    public function write(Event $event, ?Throwable $reason = null): void
    {
        $line = [
            'time' => $event->timestamp->toIso8601ZuluString(),
            'reason' => $reason !== null ? $reason::class.': '.mb_substr($reason->getMessage(), 0, 300) : null,
            'event' => $event->toArray(),
        ];

        $this->append((string) json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    /**
     * When even building the event failed: the bare minimum.
     */
    public function note(string $message): void
    {
        $this->append((string) json_encode(['time' => gmdate('c'), 'note' => mb_substr($message, 0, 1000)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function path(): string
    {
        return $this->path;
    }

    private function append(string $line): void
    {
        $folder = dirname($this->path);

        if (! is_dir($folder)) {
            @mkdir($folder, 0775, true);
        }

        @file_put_contents($this->path, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
