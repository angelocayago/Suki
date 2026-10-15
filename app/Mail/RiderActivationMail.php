<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
class RiderActivationMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public readonly string $activationUrl) {}
    public function build(): self { return $this->subject('Activate your SUKI SHOP Rider account')->view('emails.rider-activation'); }
}
