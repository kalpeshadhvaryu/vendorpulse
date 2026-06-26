<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Resources\Api\V1\OrganizationResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\AuthenticationService;
use App\Services\Auth\PasswordResetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        protected AuthenticationService $authentication,
        protected PasswordResetService $passwordReset,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authentication->register($request->validated());

        return ApiResponse::success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']),
            'organization' => new OrganizationResource($result['organization']),
        ], 'Registered successfully.', Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authentication->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name')
        );

        return ApiResponse::success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']),
        ], 'Authenticated.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authentication->logout($request->user());

        return ApiResponse::success(null, 'Logged out.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordReset->sendResetLink($request->validated('email'));

        return ApiResponse::success(
            null,
            'If an account exists for that email, a password reset link has been sent.'
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordReset->resetPassword(
            $request->validated('email'),
            $request->validated('token'),
            $request->validated('password'),
        );

        if (! $result['ok']) {
            return ApiResponse::error($result['message'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::success(null, 'Password reset successfully. You can sign in with your new password.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['organizations', 'defaultOrganization']);

        return ApiResponse::success(new UserResource($user));
    }
}
