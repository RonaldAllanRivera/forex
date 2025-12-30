<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `candles` MODIFY `timeframe` VARCHAR(3) NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "candles" ALTER COLUMN "timeframe" TYPE varchar(3)');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `candles` MODIFY `timeframe` VARCHAR(2) NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "candles" ALTER COLUMN "timeframe" TYPE varchar(2)');
        }
    }
};
