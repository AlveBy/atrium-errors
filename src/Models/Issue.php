<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Models;

use Atrium\Errors\Support\SiteAccess;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One bug: every event with the same fingerprint.
 *
 * new — nobody has looked yet, or it came back; resolved — fixed (it reopens
 * when it happens again, or only in a newer release when resolved "in the
 * next release"); ignored — known and accepted, still counted, never alerted.
 *
 * @property int $id
 * @property string $fingerprint
 * @property string $kind
 * @property string $level
 * @property string $class
 * @property string $message
 * @property string|null $culprit
 * @property string $status
 * @property string|null $resolved_in
 * @property int $times_seen
 * @property int $users_seen
 * @property string|null $first_release
 * @property string|null $last_release
 * @property CarbonInterface|null $first_seen_at
 * @property CarbonInterface|null $last_seen_at
 * @property CarbonInterface|null $resolved_at
 * @property CarbonInterface|null $regressed_at
 */
final class Issue extends Model
{
    public const NEW = 'new';

    public const RESOLVED = 'resolved';

    public const IGNORED = 'ignored';

    public const STATUSES = [self::NEW, self::RESOLVED, self::IGNORED];

    protected $table = 'error_issues';

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => self::NEW,
        'times_seen' => 0,
        'users_seen' => 0,
    ];

    protected function casts(): array
    {
        return [
            'times_seen' => 'integer',
            'users_seen' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'regressed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Occurrence, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'issue_id');
    }

    /**
     * Only the issues seen on a site the administrator may see.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeVisible(Builder $query): Builder
    {
        $sites = SiteAccess::ids();

        return $sites === null ? $query : $query->whereHas('occurrences', static fn (Builder $occurrences) => $occurrences->whereIn('site_id', $sites));
    }

    /**
     * Whether an event of this release brings a resolved issue back.
     */
    public function reopensIn(string $release): bool
    {
        return $this->status === self::RESOLVED && ($this->resolved_in === null || $this->resolved_in !== $release);
    }
}
