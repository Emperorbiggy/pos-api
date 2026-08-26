<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CreatePinRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdatePinRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyPinRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

final class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/v1/auth/register',
        operationId: 'authRegister',
        summary: 'Register a user',
        description: 'Create a new API user and return a JWT. Validation rules: name is required string max 255; email is required unique email max 255; terminal_id is required, string max 50, and must not already be assigned to another user; password is required, confirmed, and at least 8 characters.',
        tags: ['Authentication'],
        security: [],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest')),
        responses: [
            new OA\Response(response: 201, description: 'User registered successfully.', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());
        $token = auth('api')->login($user);

        return (new AuthTokenResource($this->tokenPayload($token, $user)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        operationId: 'authLogin',
        summary: 'Login',
        description: 'Authenticate with email and password, then return a JWT for Swagger Authorize. Validation rules: email is required and must be valid; password is required string.',
        tags: ['Authentication'],
        security: [],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Login successful.', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')),
            new OA\Response(response: 401, description: 'Invalid credentials.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function login(LoginRequest $request): AuthTokenResource|JsonResponse
    {
        $credentials = $request->validated();
        $token = auth('api')->attempt($credentials);

        if ($token === false) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /** @var User $user */
        $user = auth('api')->user();

        return new AuthTokenResource($this->tokenPayload($token, $user));
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        operationId: 'authLogout',
        summary: 'Logout',
        description: 'Invalidate the current JWT. Authentication: Bearer token required in the Authorization header.',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out successfully.', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/refresh',
        operationId: 'authRefresh',
        summary: 'Refresh token',
        description: 'Exchange the current JWT for a new one. Authentication: Bearer token required in the Authorization header. The token may already be expired: it stays exchangeable until the refresh window closes (JWT_REFRESH_TTL, measured from when the token was first issued). The old token is blacklisted once refreshed, so each token can only be exchanged once.',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Token refreshed successfully.', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')),
            new OA\Response(response: 401, description: 'Token is missing, already used, past the refresh window, or names an account that no longer exists. The client must log in again.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function refresh(): AuthTokenResource|JsonResponse
    {
        try {
            $token = auth('api')->refresh();
        } catch (JWTException) {
            return response()->json([
                'message' => 'Token is invalid, has already been refreshed, or is past the refresh window. Please log in again.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = auth('api')->setToken($token)->user();

        // A token can carry a valid signature and still name nobody: the account
        // was deleted, or the token was minted against a different database with
        // the same signing key. Refreshing it must not hand back a session.
        if (! $user instanceof User) {
            return response()->json([
                'message' => 'The account this token belongs to no longer exists. Please log in again.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new AuthTokenResource($this->tokenPayload($token, $user));
    }

    #[OA\Get(
        path: '/api/v1/auth/me',
        operationId: 'authMe',
        summary: 'Current user',
        description: 'Return the authenticated user profile. Authentication: Bearer token required in the Authorization header.',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Authenticated user.', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user('api'));
    }

    #[OA\Patch(
        path: '/api/v1/auth/profile',
        operationId: 'authUpdateProfile',
        summary: 'Update profile',
        description: 'Update the authenticated merchant\'s name and/or terminal id. Authentication: Bearer token required in the Authorization header. Both fields are optional, so either can be changed on its own, but neither may be sent blank. Validation rules: name is string max 255; terminal_id is string max 50 and must not already belong to another merchant. Changing the terminal id does not alter payments already recorded, which keep the terminal they were collected on.',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateProfileRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Profile updated successfully.', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error, e.g. the terminal id already belongs to another merchant.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user('api');

        $user->update($request->validated());

        return new UserResource($user->refresh());
    }

    #[OA\Post(
        path: '/api/v1/auth/pin',
        operationId: 'authCreatePin',
        summary: 'Create PIN',
        description: 'Set the merchant\'s first PIN. Separate from registration, so an account exists before it has a PIN. Returns 409 if a PIN is already set: use PUT /api/v1/auth/pin to change one. Authentication: Bearer token required.',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CreatePinRequest')),
        responses: [
            new OA\Response(response: 201, description: 'PIN created.', content: new OA\JsonContent(ref: '#/components/schemas/PinStatusResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'A PIN is already set on this account.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function createPin(CreatePinRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        if ($user->pin !== null) {
            return response()->json([
                'message' => 'A pin is already set on this account. Use PUT /api/v1/auth/pin to change it.',
            ], Response::HTTP_CONFLICT);
        }

        $user->update(['pin' => $request->string('pin')->toString()]);

        return response()->json(['data' => ['has_pin' => true]], Response::HTTP_CREATED);
    }

    #[OA\Put(
        path: '/api/v1/auth/pin',
        operationId: 'authUpdatePin',
        summary: 'Change PIN',
        description: 'Change an existing PIN. Requires current_pin, so knowing the JWT alone is not enough to replace the PIN. Returns 409 when no PIN is set yet: use POST /api/v1/auth/pin to create one. Authentication: Bearer token required.',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdatePinRequest')),
        responses: [
            new OA\Response(response: 200, description: 'PIN changed.', content: new OA\JsonContent(ref: '#/components/schemas/PinStatusResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'No PIN has been set on this account yet.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error, including an incorrect current_pin.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function updatePin(UpdatePinRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        if ($user->pin === null) {
            return response()->json([
                'message' => 'No pin has been set on this account yet. Use POST /api/v1/auth/pin to create one.',
            ], Response::HTTP_CONFLICT);
        }

        // current_pin only proves the caller knows the old PIN; it is never stored.
        $user->update(['pin' => $request->string('pin')->toString()]);

        return response()->json(['data' => ['has_pin' => true]]);
    }

    #[OA\Post(
        path: '/api/v1/auth/verify-pin',
        operationId: 'authVerifyPin',
        summary: 'Verify PIN',
        description: 'Check a PIN against the authenticated merchant\'s stored PIN. Returns 200 with valid true when it matches, and 422 when it does not. Rate limited to 10 attempts per minute per merchant, so a 4 digit PIN cannot be guessed by brute force. Authentication: Bearer token required.',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/VerifyPinRequest')),
        responses: [
            new OA\Response(response: 200, description: 'The PIN is correct.', content: new OA\JsonContent(ref: '#/components/schemas/VerifyPinResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'No PIN has been set on this account yet.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'The PIN is incorrect, or no PIN was supplied.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 429, description: 'Too many attempts. Wait before trying again.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function verifyPin(VerifyPinRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        if ($user->pin === null) {
            return response()->json([
                'message' => 'No pin has been set on this account yet. Set one from your profile first.',
            ], Response::HTTP_CONFLICT);
        }

        if (! Hash::check($request->string('pin')->toString(), $user->pin)) {
            throw ValidationException::withMessages([
                'pin' => 'The pin is incorrect.',
            ]);
        }

        return response()->json(['data' => ['valid' => true]]);
    }

    /**
     * @return array{access_token: string, expires_in: int, user: User}
     */
    private function tokenPayload(string $token, User $user): array
    {
        return [
            'access_token' => $token,
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
            'user' => $user,
        ];
    }
}
