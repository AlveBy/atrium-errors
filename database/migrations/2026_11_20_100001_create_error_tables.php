<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issues (one per bug, found by its fingerprint) and their events (each time
 * it happened). Nothing personal is kept: an event knows the user id only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_issues', function (Blueprint $table): void {
            $table->id();
            $table->char('fingerprint', 40)->unique();
            $table->string('kind', 16);
            $table->string('level', 16)->index();
            $table->string('class');
            $table->text('message');
            $table->string('culprit')->nullable();
            $table->string('status', 16)->default('new')->index();
            // The release an issue was resolved in when it should only come
            // back in a newer one ("resolve in next release").
            $table->string('resolved_in')->nullable();
            $table->unsignedInteger('times_seen')->default(0);
            $table->unsignedInteger('users_seen')->default(0);
            $table->string('first_release')->nullable();
            $table->string('last_release')->nullable()->index();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('regressed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('error_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->index();
            $table->foreignId('site_id')->nullable()->index();
            $table->string('level', 16);
            $table->string('release')->nullable()->index();
            $table->string('environment', 32)->nullable();
            $table->string('method', 8)->nullable();
            $table->string('path', 512)->nullable();
            $table->string('route')->nullable();
            $table->string('locale', 16)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            // Text, not JSON: MySQL reorders the keys of a JSON column, and the
            // event is shown as it was captured. It is never searched inside.
            $table->longText('payload');
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['issue_id', 'created_at']);
            $table->index(['issue_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_events');
        Schema::dropIfExists('error_issues');
    }
};
