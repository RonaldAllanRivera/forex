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
        Schema::create('candles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('symbol_id')->constrained()->cascadeOnDelete();
            $table->string('timeframe', 2);
            $table->unsignedBigInteger('t');
            $table->decimal('o', 16, 6);
            $table->decimal('h', 16, 6);
            $table->decimal('l', 16, 6);
            $table->decimal('c', 16, 6);
            $table->decimal('v', 20, 6)->nullable();
            $table->timestamps();

            $table->unique(['symbol_id', 'timeframe', 't']);
            $table->index(['symbol_id', 'timeframe', 't']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candles');
    }
};
