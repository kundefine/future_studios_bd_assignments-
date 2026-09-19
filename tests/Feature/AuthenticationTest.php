<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('a user can register and receive a sanctum token', function () {
    $response = $this->postJson(route('auth.register'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'postman',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'jane@example.com')
        ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']]);

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
    expect(Hash::check('password123', $user->password))->toBeTrue();
    expect($user->tokens)->toHaveCount(1);
});

test('a user can log in and retrieve their profile', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson(route('auth.login'), [
        'email' => $user->email,
        'password' => 'password123',
        'device_name' => 'mobile',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id);

    $token = $response->json('data.token');

    $this->withToken($token)
        ->getJson(route('user'))
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

test('invalid credentials are rejected', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $this->postJson(route('auth.login'), [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('data.errors.email.0', 'The provided credentials are incorrect.');
});

test('an authenticated user can log out their current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('postman')->plainTextToken;

    $this->withToken($token)
        ->postJson(route('auth.logout'))
        ->assertNoContent();

    expect($user->fresh()->tokens)->toHaveCount(0);
});

test('a protected API route returns a JSON unauthorized response without a token', function () {
    $this->get(route('orders.index'))
        ->assertUnauthorized()
        ->assertExactJson([
            'success' => false,
            'status' => 401,
            'message' => 'Unauthorized.',
        ]);
});
