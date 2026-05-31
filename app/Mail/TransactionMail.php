<?php

namespace App\Mail;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Transaction $transaction,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->transaction->status === TransactionStatus::CONFIRMED
            ? 'Transaction confirmed — CryptoVault'
            : 'Transaction failed — CryptoVault';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.transaction');
    }
}
