<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Unit;

use Atrium\Errors\Capture\Release;
use Composer\InstalledVersions;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The release an error belongs to: what is configured, else the commit, else the version of the app.
 */
final class ReleaseTest extends TestCase
{
    private const SHA = '0123456789abcdef0123456789abcdef01234567';

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = sys_get_temp_dir().'/atrium-release-'.bin2hex(random_bytes(6));
        mkdir($this->folder, 0775, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->folder);

        parent::tearDown();
    }

    /**
     * @param  array<string,string>  $files  path inside the project => content; {base} stands for the project folder
     */
    private function project(array $files): Release
    {
        foreach ($files as $path => $content) {
            @mkdir(dirname($this->folder.'/'.$path), 0775, true);
            file_put_contents($this->folder.'/'.$path, str_replace('{base}', $this->folder, $content));
        }

        return new Release($this->folder);
    }

    private function composerVersion(): string
    {
        return InstalledVersions::getRootPackage()['pretty_version'];
    }

    public function test_the_release_is_worked_out_once(): void
    {
        $release = $this->project(['.git/HEAD' => self::SHA]);

        $this->assertSame('0123456789ab', $release->current());

        file_put_contents($this->folder.'/.git/HEAD', 'f'.substr(self::SHA, 1));

        $this->assertSame('0123456789ab', $release->current());
    }

    /**
     * @return array<string,array{?string,string}>
     */
    public static function configured(): array
    {
        return [
            'as written' => ['v3.0.1', 'v3.0.1'],
            'with spaces around' => ['  v3.0.1 ', 'v3.0.1'],
            'ninety-nine characters' => [str_repeat('a', 99), str_repeat('a', 99)],
            'a hundred characters' => [str_repeat('a', 100), str_repeat('a', 100)],
            'a hundred and one characters' => [str_repeat('a', 101), str_repeat('a', 100)],
            'Cyrillic is cut by characters' => [str_repeat('я', 150), str_repeat('я', 100)],
            'Chinese is cut by characters' => [str_repeat('花', 150), str_repeat('花', 100)],
            'only spaces: not configured' => ['   ', 'composer'],
            'empty: not configured' => ['', 'composer'],
            'null: not configured' => [null, 'composer'],
        ];
    }

    #[DataProvider('configured')]
    public function test_a_configured_release_wins(?string $value, string $expected): void
    {
        $release = new Release($this->folder, $value);

        $this->assertSame($expected === 'composer' ? $this->composerVersion() : $expected, $release->current());
    }

    /**
     * @return array<string,array{array<string,string>,string}>
     */
    public static function repositories(): array
    {
        $short = '0123456789ab';
        $other = 'fedcba987654';
        $otherSha = $other.str_repeat('0', 28);

        return [
            'a detached head' => [['.git/HEAD' => self::SHA."\n"], $short],
            'a detached head written with Windows line ends' => [['.git/HEAD' => self::SHA."\r\n"], $short],
            'a detached head with spaces around' => [['.git/HEAD' => '  '.self::SHA.'  '], $short],
            'forty-one hex digits are not a commit' => [['.git/HEAD' => self::SHA.'0'], 'composer'],
            'text before a commit is not a commit' => [['.git/HEAD' => 'x'.self::SHA], 'composer'],
            'thirty-nine hex digits are not a commit' => [['.git/HEAD' => substr(self::SHA, 1)], 'composer'],
            'a branch' => [['.git/HEAD' => "ref: refs/heads/main\n", '.git/refs/heads/main' => $otherSha."\n"], $other],
            'a branch, the head written with Windows line ends' => [['.git/HEAD' => "ref: refs/heads/main\r\n", '.git/refs/heads/main' => $otherSha], $other],
            'a branch, the head with spaces around' => [['.git/HEAD' => "  ref: refs/heads/main  \n", '.git/refs/heads/main' => $otherSha], $other],
            'a branch whose commit has spaces around it' => [['.git/HEAD' => 'ref: refs/heads/main', '.git/refs/heads/main' => "\n  ".$otherSha."\n"], $other],
            'a head with something after the branch is not a branch' => [['.git/HEAD' => 'ref: refs/heads/main now', '.git/refs/heads/main' => $otherSha], 'composer'],
            'a head with something before the ref is not a ref' => [['.git/HEAD' => 'xref: refs/heads/main', '.git/refs/heads/main' => $otherSha], 'composer'],
            'a branch that is gone' => [['.git/HEAD' => 'ref: refs/heads/gone'], 'composer'],
            'a head that says nothing' => [['.git/HEAD' => 'garbage'], 'composer'],
            'no git at all' => [[], 'composer'],
            'packed refs' => [['.git/HEAD' => 'ref: refs/heads/main', '.git/packed-refs' => "# pack-refs with: peeled\n".$otherSha." refs/heads/main\n".self::SHA." refs/heads/other\n"], $other],
            'packed refs, the branch is not among them' => [['.git/HEAD' => 'ref: refs/heads/main', '.git/packed-refs' => self::SHA." refs/heads/other\n"], 'composer'],
            'packed refs, a longer branch name is not this branch' => [['.git/HEAD' => 'ref: refs/heads/main', '.git/packed-refs' => self::SHA." refs/heads/main-2\n"], 'composer'],
            'a worktree: .git is a file with an absolute path' => [[
                '.git' => "gitdir: {base}/wt\n",
                'wt/HEAD' => 'ref: refs/heads/feature',
                'wt/commondir' => "../common\n",
                'common/refs/heads/feature' => $otherSha,
            ], $other],
            'a worktree whose path is written with Windows line ends' => [[
                '.git' => "gitdir: {base}/wt\r\n",
                'wt/HEAD' => 'ref: refs/heads/feature',
                'wt/commondir' => '../common',
                'common/refs/heads/feature' => $otherSha,
            ], $other],
            'a worktree with a path relative to the project' => [[
                '.git' => "gitdir: wt\n",
                'wt/HEAD' => self::SHA,
            ], $short],
            'a worktree with a relative path written with Windows line ends' => [[
                '.git' => "gitdir: wt \r\n",
                'wt/HEAD' => self::SHA,
            ], $short],
            'a worktree whose gitdir is not on the first line' => [[
                '.git' => "# written by git\ngitdir: wt\n",
                'wt/HEAD' => self::SHA,
            ], $short],
            'a line that only contains gitdir is not a gitdir line' => [[
                '.git' => "see gitdir: wt\n",
                'wt/HEAD' => self::SHA,
            ], 'composer'],
            'a worktree keeps a ref of its own in its own folder' => [[
                '.git' => 'gitdir: {base}/wt',
                'wt/HEAD' => 'ref: refs/bisect/bad',
                'wt/commondir' => '../common',
                'wt/refs/bisect/bad' => $otherSha,
            ], $other],
            'a worktree without commondir keeps its refs in its own folder' => [[
                '.git' => 'gitdir: {base}/wt',
                'wt/HEAD' => 'ref: refs/heads/feature',
                'wt/refs/heads/feature' => $otherSha,
            ], $other],
        ];
    }

    /**
     * @param  array<string,string>  $files
     */
    #[DataProvider('repositories')]
    public function test_without_a_configured_release_the_commit_is_read_from_git(array $files, string $expected): void
    {
        $this->assertSame($expected === 'composer' ? $this->composerVersion() : $expected, $this->project($files)->current());
    }
}
