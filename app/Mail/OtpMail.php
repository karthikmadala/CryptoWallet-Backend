<?php

namespace App\Mail;

use App\Enums\Auth\OtpPurpose;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $otp,
        public readonly OtpPurpose $purpose,
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            OtpPurpose::Registration->value  => 'Verify your CryptoVault account',
            OtpPurpose::Login->value         => 'Your CryptoVault login code',
            OtpPurpose::PasswordReset->value => 'Reset your CryptoVault password',
            OtpPurpose::WalletLink->value    => 'Confirm wallet link — CryptoVault',
        ];
        return new Envelope(subject: $subjects[$this->purpose->value] ?? 'Your CryptoVault code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp');
    }
}
