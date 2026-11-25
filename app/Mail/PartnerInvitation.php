<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PartnerInvitation extends Mailable
{
    use Queueable;

    public function __construct(public string $inviterName, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->inviterName} convidou você para o Nossa Finanças");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.partner-invitation');
    }
}
