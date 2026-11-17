<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Http\Controllers;

use Atrium\Errors\Errors;
use Atrium\Errors\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * The browser side: the script, and the endpoint it reports to.
 *
 * The endpoint is open to anyone (a visitor's page posts to it), so it
 * trusts nothing: it is off unless switched on, throttled per address,
 * refuses bodies over the size limit before reading them, and takes only
 * the fields it knows, in the types it expects.
 */
final class BrowserController
{
    public function store(Request $request, Errors $errors, Options $options): JsonResponse
    {
        if (! $options->bool('browser_site') && ! $options->bool('browser_admin')) {
            return response()->json(['message' => 'Not accepted'], 404);
        }

        $limit = max(1024, (int) config('atrium-errors.browser.max_bytes', 16384));

        if ($request->header('Content-Length', '0') > $limit || strlen((string) $request->getContent()) > $limit) {
            return response()->json(['message' => 'Too large'], 413);
        }

        $data = json_decode((string) $request->getContent(), true);

        $validator = Validator::make(is_array($data) ? $data : [], [
            'message' => ['required', 'string', 'max:1000'],
            'name' => ['nullable', 'string', 'max:64'],
            'stack' => ['nullable', 'string', 'max:10000'],
            'url' => ['nullable', 'string', 'max:2000'],
            'line' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'column' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'source' => ['nullable', 'string', 'in:site,admin'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid report'], 422);
        }

        /** @var array{message:string,name?:string,stack?:string,url?:string,line?:int,column?:int,source?:string} $report */
        $report = $validator->validated();

        $errors->captureBrowser($report, $request);

        return response()->json(null, 202);
    }

    public function script(): Response
    {
        return response((string) file_get_contents(__DIR__.'/../../../resources/js/atrium-errors.js'), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
