<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function signIn(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    protected function withCsrfToken(): static
    {
        $token = Str::random(40);

        return $this
            ->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token);
    }
}
