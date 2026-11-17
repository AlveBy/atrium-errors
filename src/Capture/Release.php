<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Capture;

use Composer\InstalledVersions;

/**
 * The release an event belongs to: the configured one, else the git commit of
 * the checkout (read from .git, no git binary needed), else the version
 * Composer gives the application, else "unknown".
 */
final class Release
{
    private ?string $resolved = null;

    public function __construct(
        private readonly string $basePath,
        private readonly ?string $configured = null,
    ) {}

    public function current(): string
    {
        return $this->resolved ??= $this->resolve();
    }

    private function resolve(): string
    {
        if ($this->configured !== null && trim($this->configured) !== '') {
            return mb_substr(trim($this->configured), 0, 100);
        }

        return $this->gitCommit() ?? $this->composerVersion() ?? 'unknown';
    }

    private function gitCommit(): ?string
    {
        $git = $this->basePath.'/.git';

        // A worktree or submodule has a file pointing at the real folder.
        if (is_file($git) && preg_match('/^gitdir:\s*(.+)/m', (string) file_get_contents($git), $match)) {
            $git = str_starts_with($match[1], '/') ? trim($match[1]) : $this->basePath.'/'.trim($match[1]);
        }

        $head = is_file($git.'/HEAD') ? trim((string) file_get_contents($git.'/HEAD')) : '';

        if (preg_match('/^[0-9a-f]{40}$/', $head)) {
            return substr($head, 0, 12);
        }

        if (! preg_match('#^ref:\s*(refs/\S+)$#', $head, $ref)) {
            return null;
        }

        // A worktree keeps its refs in the common folder.
        $common = is_file($git.'/commondir') ? $git.'/'.trim((string) file_get_contents($git.'/commondir')) : $git;

        foreach ([$git, $common] as $folder) {
            $file = $folder.'/'.$ref[1];

            if (is_file($file)) {
                return substr(trim((string) file_get_contents($file)), 0, 12);
            }

            if (is_file($folder.'/packed-refs') && preg_match('/^([0-9a-f]{12})[0-9a-f]{28} '.preg_quote($ref[1], '/').'$/m', (string) file_get_contents($folder.'/packed-refs'), $packed)) {
                return $packed[1];
            }
        }

        return null;
    }

    private function composerVersion(): ?string
    {
        $version = InstalledVersions::getRootPackage()['pretty_version'];

        return $version !== '' ? $version : null;
    }
}
