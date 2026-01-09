<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('opened_at');
            $table->index(['user_id', 'symbol_id', 'timeframe', 'closed_at'], 'trades_open_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropIndex('trades_open_lookup');
            $table->dropColumn('closed_at');
        });
    }
};
