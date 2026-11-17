<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Capture;

use Throwable;

/**
 * The stack trace of an exception as frames worth storing.
 *
 * The frame the error was thrown in comes first. Paths are relative to the
 * application, so the same bug reads the same on every server. Arguments are
 * never taken: they are where passwords and personal data hide. Lines of code
 * around the frame are read for the application's own frames and the first
 * one, the rest only name the place.
 */
final class Frames
{
    public const MAX_FRAMES = 50;

    /** Frames that get the lines of code around them. */
    private const MAX_WITH_CONTEXT = 15;

    public function __construct(
        private readonly string $basePath,
        private readonly int $contextLines = 5,
    ) {}

    /**
     * @return array<int,array{file:string,line:int,function:string,in_app:bool,context?:array{pre:array<int,string>,line:string,post:array<int,string>}}>
     */
    public function of(Throwable $e): array
    {
        $frames = [];
        $file = $e->getFile();
        $line = $e->getLine();

        // getTrace() names, in each entry, the function that was called and
        // the place it was called from; the place of the throw itself has no
        // entry, so the function of entry N belongs to the place of entry N-1.
        foreach ($e->getTrace() as $call) {
            $frames[] = $this->frame($file, $line, $this->function($call));
            $file = $call['file'] ?? '';
            $line = $call['line'] ?? 0;

            if (count($frames) >= self::MAX_FRAMES) {
                break;
            }
        }

        if (count($frames) < self::MAX_FRAMES) {
            $frames[] = $this->frame($file, $line, '');
        }

        return $this->withContext($frames);
    }

    /**
     * Frames of a browser error, parsed from the `stack` string of Chrome,
     * Firefox and Safari.
     *
     * @return array<int,array{file:string,line:int,function:string,in_app:bool}>
     */
    public function ofBrowserStack(string $stack, string $origin): array
    {
        $frames = [];

        foreach (preg_split('/\R/', $stack) ?: [] as $row) {
            // Chrome: "    at fn (https://site/app.js:10:5)" or "    at https://site/app.js:10:5"
            // Firefox, Safari: "fn@https://site/app.js:10:5"
            if (! preg_match('/^\s*(?:at\s+(?:(.+?)\s+\()?|(.*?)@)(\S+?):(\d+)(?::\d+)?\)?\s*$/', $row, $match)) {
                continue;
            }

            $url = $match[3];
            $frames[] = [
                'file' => $this->browserFile($url, $origin),
                'line' => (int) $match[4],
                'function' => trim($match[1] !== '' ? $match[1] : $match[2]),
                'in_app' => $origin !== '' && str_starts_with($url, $origin),
            ];

            if (count($frames) >= self::MAX_FRAMES) {
                break;
            }
        }

        return $frames;
    }

    /**
     * @param  array<string,mixed>  $call
     */
    private function function(array $call): string
    {
        $function = $call['function'] ?? '';

        // PHP 8.3 names a closure `App\{closure}`, 8.4 `{closure:App\X::y():12}`
        // with the line it starts on. One name for both: an issue keeps its
        // fingerprint when PHP is upgraded or the code above it moves.
        if (str_contains($function, '{closure')) {
            $function = '{closure}';
        }

        return isset($call['class']) ? $call['class'].'::'.$function : $function;
    }

    /**
     * @return array{file:string,line:int,function:string,in_app:bool,absolute:string}
     */
    private function frame(string $file, int $line, string $function): array
    {
        $relative = $this->relative($file);

        return [
            'file' => $relative,
            'line' => $line,
            'function' => $function,
            'in_app' => $relative !== '' && ! str_starts_with($relative, 'vendor/') && ! str_contains($relative, '/vendor/') && ! str_starts_with($relative, 'storage/framework/'),
            'absolute' => $file,
        ];
    }

    /**
     * @param  array<int,array{file:string,line:int,function:string,in_app:bool,absolute:string}>  $frames
     * @return array<int,array{file:string,line:int,function:string,in_app:bool,context?:array{pre:array<int,string>,line:string,post:array<int,string>}}>
     */
    private function withContext(array $frames): array
    {
        $read = 0;
        $result = [];

        foreach ($frames as $index => $frame) {
            $absolute = $frame['absolute'];
            unset($frame['absolute']);

            if (($index === 0 || $frame['in_app']) && $read < self::MAX_WITH_CONTEXT && $this->contextLines > 0) {
                $context = $this->context($absolute, $frame['line']);

                if ($context !== null) {
                    $frame['context'] = $context;
                    $read++;
                }
            }

            $result[] = $frame;
        }

        return $result;
    }

    /**
     * @return array{pre:array<int,string>,line:string,post:array<int,string>}|null
     */
    private function context(string $file, int $line): ?array
    {
        if (! is_file($file) || filesize($file) > 2_000_000) {
            return null;
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);

        if (! isset($lines[$line - 1])) {
            return null;
        }

        $from = max(0, $line - 1 - $this->contextLines);
        $cut = static fn (string $code): string => mb_substr($code, 0, 300);

        return [
            'pre' => array_map($cut, array_slice($lines, $from, $line - 1 - $from)),
            'line' => $cut($lines[$line - 1]),
            'post' => array_map($cut, array_slice($lines, $line, $this->contextLines)),
        ];
    }

    private function relative(string $file): string
    {
        $base = rtrim($this->basePath, '/\\').DIRECTORY_SEPARATOR;

        return str_starts_with($file, $base) ? str_replace('\\', '/', substr($file, strlen($base))) : str_replace('\\', '/', $file);
    }

    private function browserFile(string $url, string $origin): string
    {
        $path = $origin !== '' && str_starts_with($url, $origin) ? substr($url, strlen($origin)) : $url;

        // Query strings carry cache busters and, sometimes, tokens.
        return (string) preg_replace('/[?#].*$/', '', $path);
    }
}
