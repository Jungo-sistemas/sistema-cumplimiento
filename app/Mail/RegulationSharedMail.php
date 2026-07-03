<?php

namespace App\Mail;

use App\Models\Regulation;
use App\Models\RegulationShare;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegulationSharedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Regulation $regulation,
        public RegulationShare $share,
        public User $sender,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuevo procedimiento disponible: ' . $this->regulation->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.processes.regulation-shared',
        );
    }
}
