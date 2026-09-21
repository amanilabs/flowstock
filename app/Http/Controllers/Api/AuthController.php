<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::with('tenant')->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            $this->logLoginAttempt(null, $request->validated('email'), false);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->tenant_id) {
            $this->logLoginAttempt($user, $user->email, false);

            throw ValidationException::withMessages([
                'email' => ['This account is not associated with a tenant.'],
            ]);
        }

        $this->logLoginAttempt($user, $user->email, true);

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        $token = $user->createToken($request->userAgent() ?? 'api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'tenant_name' => $user->tenant->name,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function logLoginAttempt(?User $user, string $email, bool $succeeded): void
    {
        $logger = activity('auth')->withProperties(['email' => $email, 'succeeded' => $succeeded]);

        if ($user) {
            $logger->causedBy($user)->performedOn($user);
        }

        $activity = $logger->log($succeeded ? 'login succeeded' : 'login failed');

        // The LogsActivity model hook (beforeActivityLogged) only fires for
        // automatic attribute-change logging, not these manual activity()
        // calls — so tenant_id must be stamped explicitly here too.
        if ($activity && $user?->tenant_id) {
            $activity->tenant_id = $user->tenant_id;
            $activity->save();
        }
    }
}
