<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained()->cascadeOnDelete();
            $table->date('candle_as_of_date');
            $table->json('review_json')->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_hash', 64)->nullable();
            $table->json('raw_response_json')->nullable();
            $table->timestamps();

            $table->index(['trade_id', 'created_at']);
            $table->index(['candle_as_of_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_reviews');
    }
};
