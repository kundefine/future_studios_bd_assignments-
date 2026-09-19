<?php

namespace App\Services;

use App\DTOs\LoginDTO;
use App\DTOs\RegisterDTO;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * @return array{user: User, token: string}
     */
    public function register(RegisterDTO $registerDto): array
    {
        $user = User::query()->create([
            'name' => $registerDto->name,
            'email' => $registerDto->email,
            'password' => $registerDto->password,
        ]);

        return $this->createTokenResponse($user, $registerDto->deviceName);
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(LoginDTO $loginDto): array
    {
        $user = User::query()->where('email', $loginDto->email)->first();

        if ($user === null || ! Hash::check($loginDto->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $this->createTokenResponse($user, $loginDto->deviceName);
    }

    /**
     * @return array{user: User, token: string}
     */
    private function createTokenResponse(User $user, string $deviceName): array
    {
        $user->tokens()->where('name', $deviceName)->delete();

        return [
            'user' => $user,
            'token' => $user->createToken($deviceName)->plainTextToken,
        ];
    }
}
