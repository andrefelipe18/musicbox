<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Auth')]
class CurrentUserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['data' => (new UserResource($request->user()))->resolve()]);
    }
}
