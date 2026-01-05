<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailySignalsDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{symbol:string, timeframe:string, as_of_date:string|null, signal:string|null, confidence:int|null, reason:string|null}>  $rows
     */
    public function __construct(
        public readonly string $date,
        public readonly array $rows,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Forex Signals Digest (%s)', $this->date),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-signals-digest',
            with: [
                'date' => $this->date,
                'rows' => $this->rows,
            ],
        );
    }
}
