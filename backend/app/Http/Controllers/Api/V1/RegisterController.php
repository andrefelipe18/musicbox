<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AuthService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Auth')]
class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, AuthService $auth): JsonResponse
    {
        $user = $auth->register($request->validated(), $request);

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
