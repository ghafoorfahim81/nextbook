<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Notification emails used to be sent inline from NotificationService, which put
 * a synchronous SMTP round-trip inside the open database transaction that was
 * creating the record. That held row locks for the length of the mail handshake
 * and delivered mail even when the transaction later rolled back.
 */
class SendNotificationEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly string $email,
        private readonly string $subject,
        private readonly string $body,
    ) {
    }

    public function handle(): void
    {
        Mail::raw($this->body, function ($mail) {
            $mail->to($this->email)->subject($this->subject);
        });
    }
}
