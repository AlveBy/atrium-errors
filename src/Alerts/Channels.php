<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Alerts;

use Atrium\Errors\Support\FallbackLog;
use Atrium\Errors\Support\Options;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Where alerts go: email (through the mail module's "errors" account when it
 * is installed, the site's mailer otherwise) and a Telegram chat. One failing
 * channel does not stop the other.
 */
final class Channels
{
    public function __construct(
        private readonly Options $options,
        private readonly Factory $http,
        private readonly FallbackLog $fallback,
    ) {}

    public function any(): bool
    {
        return $this->emails() !== [] || $this->telegram() !== null;
    }

    public function send(Alert $alert): void
    {
        if (($emails = $this->emails()) !== []) {
            try {
                Mail::to($emails)->send(new AlertMail($alert));
            } catch (Throwable $e) {
                $this->fallback->note('alert email failed: '.$e->getMessage());
            }
        }

        if (($telegram = $this->telegram()) !== null) {
            try {
                $this->http->timeout(5)
                    ->post('https://api.telegram.org/bot'.$telegram['token'].'/sendMessage', [
                        'chat_id' => $telegram['chat'],
                        'text' => mb_substr($alert->text(), 0, 4000),
                        'disable_web_page_preview' => true,
                    ])
                    ->throw();
            } catch (Throwable $e) {
                // The token is part of the address: never write the message as it is.
                $this->fallback->note('alert telegram failed: '.str_replace($telegram['token'], '[Filtered]', $e->getMessage()));
            }
        }
    }

    /**
     * @return array<int,string>
     */
    private function emails(): array
    {
        $list = preg_split('/[\s,;]+/', $this->options->string('alert_email')) ?: [];

        return array_filter($list, static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
    }

    /**
     * @return array{token:string,chat:string}|null
     */
    private function telegram(): ?array
    {
        $token = $this->options->string('telegram_token');
        $chat = $this->options->string('telegram_chat');

        return $token !== '' && $chat !== '' ? ['token' => $token, 'chat' => $chat] : null;
    }
}
