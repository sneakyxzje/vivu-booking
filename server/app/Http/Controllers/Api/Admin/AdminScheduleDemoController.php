<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourSchedule;
use App\Services\DemoClock;
use App\Services\ScheduleDemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminScheduleDemoController extends Controller
{
    public function __construct(private readonly ScheduleDemoService $demo) {}

    public function availability(): JsonResponse
    {
        return $this->success(['enabled' => DemoClock::enabled()]);
    }

    public function show(int $id): JsonResponse
    {
        $this->demo->assertEnabled();
        return $this->success($this->demo->snapshot(TourSchedule::findOrFail($id)));
    }

    public function enable(Request $request, int $id): JsonResponse
    {
        return $this->success($this->demo->enable($id, $request->user()));
    }

    public function advance(Request $request, int $id): JsonResponse
    {
        $this->demo->assertEnabled();
        $data = $request->validate(['milestone' => ['required', 'string', 'max:40']]);
        return $this->success($this->demo->advance($id, $data['milestone'], $request->user()));
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $this->demo->assertEnabled();
        $data = $request->validate(['status' => ['required', 'in:confirmed,in_progress,completed']]);
        return $this->success($this->demo->moveToStatus($id, $data['status'], $request->user()));
    }

    public function milestone(Request $request, int $id): JsonResponse
    {
        $this->demo->assertEnabled();
        $data = $request->validate(['milestone' => ['required', 'string', 'max:40']]);
        return $this->success($this->demo->moveToMilestone($id, $data['milestone'], $request->user()));
    }
}
