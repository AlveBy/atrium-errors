<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Capture;

/**
 * What makes two events the same bug: the exception class plus the top frames
 * of the application, by file and function — never by line number, so adding
 * a line above the bug does not open a new issue, and never by message, which
 * carries ids and names.
 *
 * Without frames (a browser error from an inline script) the message is used,
 * with numbers, quoted strings and hex ids blanked out.
 */
final class Fingerprint
{
    /** How many top frames decide. */
    public const FRAMES = 5;

    /**
     * @param  array<int,array<string,mixed>>  $frames  the frame of the throw first
     */
    public function of(string $class, string $message, array $frames): string
    {
        $own = array_filter($frames, static fn (array $frame): bool => (bool) ($frame['in_app'] ?? false));
        $chosen = array_slice($own !== [] ? $own : $frames, 0, self::FRAMES);

        $parts = [$class];
        foreach ($chosen as $frame) {
            $file = $this->file((string) ($frame['file'] ?? ''));
            $function = (string) ($frame['function'] ?? '');
            if ($file !== '' || $function !== '') {
                $parts[] = $file.'|'.$function;
            }
        }

        // Nothing to tell the place by: the message, blanked, decides.
        if (count($parts) === 1) {
            $parts[] = self::normalizeMessage($message);
        }

        return sha1(implode("\n", $parts));
    }

    /**
     * The message with what changes from event to event blanked out.
     */
    public static function normalizeMessage(string $message): string
    {
        $message = mb_substr($message, 0, 500);
        $message = preg_replace('/(["\']).*?\1/s', '<str>', $message) ?? '';
        $message = preg_replace('/\b(?:0x)?[0-9a-f]{8,}\b/i', '<hex>', $message) ?? '';
        $message = preg_replace('/\d+(?:\.\d+)?/', '<n>', $message) ?? '';

        return trim(preg_replace('/\s+/', ' ', $message) ?? '');
    }

    /**
     * Compiled views and bundles carry a hash in the name that changes with
     * each build: keep the name stable.
     */
    private function file(string $file): string
    {
        $file = preg_replace('#^storage/framework/views/[0-9a-f]+\.php$#', 'storage/framework/views/compiled.php', $file) ?? '';

        return (string) preg_replace('/[.-][0-9a-zA-Z_]{8,}(\.(?:m?js|css))$/', '$1', $file);
    }
}
