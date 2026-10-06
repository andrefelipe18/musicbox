<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AuthService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Auth')]
class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, AuthService $auth): JsonResponse
    {
        $credentials = $request->validated();
        $user = $auth->attempt($credentials['email'], $credentials['password'], $request);

        if ($user === null) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return (new UserResource($user))->response()->setStatusCode(200);
    }
}
