<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('contact_number', $request->contact_number)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated',
            ], 403);
        }

        if ($user->isTempleUser() && $user->temple && $user->temple->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your temple account is not active',
            ], 403);
        }

        $user->updateLastLogin();

        $token = $user->createToken('auth-token')->plainTextToken;

        // Load role.permissions so getPermissions() works correctly
        $user->load('role.permissions', 'temple');

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'must_reset_password' => $user->must_reset_password,
                'permissions' => $user->getPermissions(),
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(): JsonResponse
    {
        $user = auth()->user()->load('role.permissions', 'temple');

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = auth()->user();

        $user->update([
            'password' => Hash::make($request->password),
            'must_reset_password' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully',
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'address' => 'sometimes|nullable|string|max:500',
        ]);

        $user = auth()->user();
        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => new UserResource($user->fresh('role', 'temple')),
        ]);
    }
}
