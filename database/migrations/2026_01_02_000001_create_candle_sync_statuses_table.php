<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candle_sync_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('symbol_id')->constrained()->cascadeOnDelete();
            $table->string('timeframe', 3);
            $table->string('status', 16);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('last_upserted')->nullable();
            $table->json('last_stats')->nullable();
            $table->timestamps();

            $table->unique(['symbol_id', 'timeframe']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candle_sync_statuses');
    }
};
