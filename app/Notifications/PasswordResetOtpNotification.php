<?php

namespace App\Notifications;

use App\Models\PasswordResetOtp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails the one-time password reset code.
 *
 * Queued, so a slow mail server never holds up the API response.
 */
class PasswordResetOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $otp)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = PasswordResetOtp::TTL_MINUTES;

        return (new MailMessage)
            ->subject('Your ' . config('app.name') . ' password reset code')
            ->greeting('Password reset')
            ->line('Use this code to reset your password:')
            ->line('**' . $this->otp . '**')
            ->line("The code expires in {$minutes} minutes and can only be used once.")
            ->line('If you did not ask for this, you can safely ignore this email — your password stays as it is.');
    }
}
