<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(public Application $application)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->application->status === 'approved';
        $type = ucfirst($this->application->application_type);

        $message = (new MailMessage)
            ->subject("SUKI SHOP {$type} application decision")
            ->greeting("Hello {$notifiable->name},")
            ->line($approved
                ? "Your {$type} application has been approved."
                : "Your {$type} application has been rejected.");

        if (! $approved && $this->application->rejection_reason) {
            $message->line('Reason: '.$this->application->rejection_reason);
        }

        if ($approved) {
            $message
                ->line('You may now sign in to your approved portal.')
                ->action('Sign in', route('login'));
        }

        return $message->line('Thank you for using SUKI SHOP.');
    }
}
