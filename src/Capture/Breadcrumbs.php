<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Capture;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Log\Events\MessageLogged;

/**
 * What happened just before an error: the last queries and log lines.
 *
 * A ring of a fixed size, so a long request or a worker never grows it.
 * Queries are kept as SQL with placeholders and without the bindings: those
 * hold the very values (emails, encrypted columns) that must not be stored.
 */
final class Breadcrumbs
{
    /** @var array<int,array{time:string,category:string,message:string}> */
    private array $items = [];

    public function __construct(private readonly int $limit = 30) {}

    public function query(QueryExecuted $query): void
    {
        $this->add('query', mb_substr($query->sql, 0, 500).' ['.round($query->time, 1).' ms]');
    }

    public function log(MessageLogged $log): void
    {
        $this->add('log.'.$log->level, mb_substr($log->message, 0, 500));
    }

    public function add(string $category, string $message): void
    {
        $this->items[] = [
            'time' => now()->format('H:i:s.v'),
            'category' => $category,
            'message' => $message,
        ];

        if (count($this->items) > $this->limit) {
            array_shift($this->items);
        }
    }

    /**
     * @return array<int,array{time:string,category:string,message:string}>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * A queue worker starts every job with a clean ring.
     */
    public function clear(): void
    {
        $this->items = [];
    }
}
