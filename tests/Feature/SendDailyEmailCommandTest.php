<?php

namespace Tests\Feature;

use App\Enums\Timeframe;
use App\Mail\DailySignalsDigest;
use App\Models\Signal;
use App\Models\Symbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendDailyEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_daily_digest_to_configured_recipients(): void
    {
        config([
            'forex.email_recipients' => 'one@example.com, two@example.com',
        ]);

        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        Signal::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            'as_of_date' => '2025-01-02',
            'signal' => 'BUY',
            'confidence' => 70,
            'reason' => 'test',
        ]);

        Mail::fake();

        $this->artisan('forex:send-daily-email --date=2025-01-02')
            ->assertExitCode(0);

        Mail::assertSent(DailySignalsDigest::class, function (DailySignalsDigest $mailable): bool {
            return $mailable->date === '2025-01-02' && count($mailable->rows) > 0;
        });
    }

    public function test_it_noops_when_no_recipients_are_configured(): void
    {
        config([
            'forex.email_recipients' => '',
        ]);

        Mail::fake();

        $this->artisan('forex:send-daily-email --date=2025-01-02')
            ->assertExitCode(0);

        Mail::assertNothingSent();
    }
}
