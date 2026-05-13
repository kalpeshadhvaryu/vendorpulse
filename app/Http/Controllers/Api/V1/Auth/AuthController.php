<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\OrganizationResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\AuthenticationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        protected AuthenticationService $authentication
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

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['organizations', 'defaultOrganization']);

        return ApiResponse::success(new UserResource($user));
    }
}
