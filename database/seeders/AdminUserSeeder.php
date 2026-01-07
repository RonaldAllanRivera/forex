<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'jaeron.rivera@gmail.com')->first();

        if (! $user) {
            User::query()->create([
                'name' => 'Admin',
                'email' => 'jaeron.rivera@gmail.com',
                'password' => 'password',
                'is_admin' => true,
            ]);

            return;
        }

        if (! $user->is_admin) {
            $user->forceFill(['is_admin' => true])->save();
        }
    }
}
