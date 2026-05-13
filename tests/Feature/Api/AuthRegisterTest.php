<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_bearer_token_and_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Owner User',
            'email' => 'owner@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_name' => 'Ops Co',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'email', 'organizations'],
                    'organization' => ['id', 'name', 'slug'],
                ],
            ]);
    }
}
