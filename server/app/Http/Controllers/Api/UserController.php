<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function test(): JsonResponse
    {
        // Mock
        $dummy = new User();
        $dummy->id = 888;
        $dummy->name = 'Vivu Booking';
        $dummy->email = 'vivu@services.com';
        $dummy->created_at = now();

        return $this->success(
            new UserResource($dummy),
            'Success.'
        );
    }

    public function updateProfile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Update profile endpoint'
        ]);
    }
}
