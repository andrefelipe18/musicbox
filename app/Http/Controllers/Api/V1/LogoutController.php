<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Auth')]
class LogoutController extends Controller
{
    public function __invoke(Request $request, AuthService $auth): JsonResponse
    {
        $auth->logout($request);

        return response()->json(['message' => 'Logged out.']);
    }
}
