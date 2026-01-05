<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('symbol_id')->constrained()->cascadeOnDelete();
            $table->string('timeframe', 3);
            $table->date('as_of_date');
            $table->string('signal', 8);
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->text('reason');
            $table->json('levels_json')->nullable();
            $table->json('stoch_json')->nullable();
            $table->string('prompt_hash')->nullable();
            $table->string('model')->nullable();
            $table->json('raw_response_json')->nullable();
            $table->timestamps();

            $table->unique(['symbol_id', 'timeframe', 'as_of_date']);
            $table->index(['symbol_id', 'timeframe', 'as_of_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signals');
    }
};
