<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('symbol_id')->constrained()->cascadeOnDelete();
            $table->string('timeframe');
            $table->string('side');
            $table->decimal('entry_price', 16, 6);
            $table->decimal('stop_loss', 16, 6);
            $table->decimal('take_profit', 16, 6)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['symbol_id', 'timeframe', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
