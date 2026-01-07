<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        if ((bool) config('forex.seed_admin_user')) {
            $this->call(AdminUserSeeder::class);
        }

        if ((bool) config('forex.seed_default_symbols') && App::environment(['local', 'testing'])) {
            $this->call(SymbolSeeder::class);
        }

        if (App::environment(['local', 'testing'])) {
            User::query()->firstOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'password' => 'password',
                    'is_admin' => false,
                ]
            );
        }
    }
}
