<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\TourImage;
use App\Models\Service;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Enums\ScheduleStatus;
use App\Services\CloudinaryService;
use App\Services\ScheduleLifecycleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\TourResource;

class AdminTourController extends Controller
{
    public function __construct(
        protected CloudinaryService $cloudinaryService,
        protected ScheduleLifecycleService $scheduleLifecycle,
    ) {
    }


    public function index(): JsonResponse
    {
        $tours = Tour::with([
            'admin:id,name,email',
            'schedules.guide:id,name,email,phone,status',
            'categories',
            'services',
            'images',
            'itineraries',
            'schedules',
        ])
            ->latest()
            ->get();

        return $this->success(TourResource::collection($tours), 'Lấy danh sách tour thành công');
    }

    public function show(int $id): JsonResponse
    {
        $tour = Tour::with([
            'admin:id,name,email',
            'categories',
            'services',
            'images',
            'itineraries',
            'schedules.guide:id,name,email,phone,status',
        ])->find($id);

        if (! $tour) {
            return $this->error('Không tìm thấy tour', 404);
        }

        return $this->success(new TourResource($tour), 'Lấy chi tiết tour thành công');
    }
    public function availableGuides(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'number_of_days' => ['required', 'integer', 'min:1'],
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = $start->copy()->addDays((int) $validated['number_of_days'] - 1);

        $guides = User::query()
            ->where('role', 'guide')
            ->where('status', 'active')
            ->with('assignedSchedules.tour:id,number_of_days')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'status'])
            ->filter(function (User $guide) use ($start, $end) {
                return ! $guide->assignedSchedules->contains(
                    fn (TourSchedule $schedule) => $this->scheduleOverlaps($start, $end, $schedule)
                );
            })
            ->values()
            ->map(fn (User $guide) => $guide->only(['id', 'name', 'email', 'phone', 'status']));

        return $this->success($guides, 'Lấy danh sách hướng dẫn viên đang rảnh thành công');
    }
    public function create(): JsonResponse
    {
        return $this->success([
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'services' => Service::orderBy('name')->get(['id', 'name']),
        ], 'Lấy dữ liệu tạo tour thành công');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'adult_price' => ['required', 'numeric', 'min:0'],
            'child_price' => ['required', 'numeric', 'min:0'],
            'infant_price' => ['required', 'numeric', 'min:0'],
            'thumbnail' => ['nullable', 'string'],
            'thumbnail_file' => ['nullable', 'image', 'max:5120'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
            'number_of_days' => ['required', 'integer', 'min:1'],
            'number_of_nights' => ['required', 'integer', 'min:0'],
            'start_location' => ['required', 'string', 'max:255'],
            'end_location' => ['nullable', 'string', 'max:255'],
            'vehicle_info' => ['nullable', 'string', 'max:500'],
            'pickup_location' => ['nullable', 'string', 'max:500'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['exists:services,id'],
            'itineraries' => ['nullable', 'array'],
            'itineraries.*.day_number' => ['required_with:itineraries', 'integer', 'min:1'],
            'itineraries.*.title' => ['required_with:itineraries', 'string', 'max:255'],
            'itineraries.*.start_point' => ['nullable', 'string', 'max:255'],
            'itineraries.*.end_point' => ['nullable', 'string', 'max:255'],
            'itineraries.*.route_points' => ['nullable', 'string'],
            'itineraries.*.rest_stops' => ['nullable', 'string'],
            'itineraries.*.content' => ['required_with:itineraries', 'string'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.start_date' => ['required_with:schedules', 'date', 'after_or_equal:today'],
            'schedules.*.max_people' => ['required_with:schedules', 'integer', 'min:1'],
            'schedules.*.min_people' => ['nullable', 'integer', 'min:1'],
            'schedules.*.booking_deadline' => ['nullable', 'date'],
            'schedules.*.status' => ['nullable', 'string', 'in:open,closed'],
            'schedules.*.guide_id' => ['nullable', 'exists:users,id'],
        ]);

        $numberOfDay = (int) $validated['number_of_days'];
        $numberOfNight = (int) $validated['number_of_nights'];
        if ($numberOfNight > $numberOfDay) {
            return $this->error('Số đêm không được lớn hơn số ngày', 400);
        }
        $categoryIds = $validated['category_ids'] ?? [];
        $serviceIds = $validated['service_ids'] ?? [];
        $itineraries = $validated['itineraries'] ?? [];
        $schedules = $validated['schedules'] ?? [];

        if ($scheduleError = $this->validateScheduleRules($schedules)) {
            return $this->error($scheduleError, 422);
        }

        if (count($itineraries) > $numberOfDay) {
            return $this->error("Lịch trình chỉ được tối đa {$numberOfDay} ngày", 422);
        }

        foreach ($itineraries as $itinerary) {
            if ((int) $itinerary['day_number'] > $numberOfDay) {
                return $this->error("Ngày trong lịch trình không được vượt quá {$numberOfDay}", 422);
            }
        }

        $tour = DB::transaction(function () use ($request, $validated, $categoryIds, $serviceIds, $itineraries, $schedules, $numberOfDay) {
            $this->validateScheduleGuideAssignments($schedules, $numberOfDay);
            if ($request->hasFile('thumbnail_file')) {
                $validated['thumbnail'] = $this->cloudinaryService->uploadImage(
                    $request->file('thumbnail_file')
                );
            }

            unset($validated['thumbnail_file']);
            unset($validated['images'], $validated['category_ids'], $validated['service_ids'], $validated['itineraries'], $validated['schedules']);

            $tour = Tour::create([
                ...$validated,
                'price' => $validated['adult_price'],
                'discount_price' => null,
                'admin_id' => $request->user()->id,
                'status' => 'active',
                'is_featured' => false,
                'slug' => Tour::query()->where('title', $validated['title'])->count()
                    ? Str::slug($validated['title']) . '-' . time()
                    : Str::slug($validated['title']),
            ]);

            if (! empty($categoryIds)) {
                $tour->categories()->sync($categoryIds);
            }

            if (! empty($serviceIds)) {
                $tour->services()->sync($serviceIds);
            }

            foreach ($itineraries as $item) {
                $tour->itineraries()->create([
                    'day_number' => $item['day_number'],
                    'title' => $item['title'],
                    'start_point' => $item['start_point'] ?? null,
                    'end_point' => $item['end_point'] ?? null,
                    'route_points' => $item['route_points'] ?? null,
                    'rest_stops' => $item['rest_stops'] ?? null,
                    'content' => $item['content'],
                ]);
            }

            foreach ($schedules as $item) {
                $startDate = Carbon::parse($item['start_date']);

                // end_date tự tính: start + (number_of_days - 1) ngày
                $endDate = $startDate->copy()->addDays(max(0, $numberOfDay - 1));

                // booking_deadline: nếu không truyền thì mặc định start - 3 ngày
                $bookingDeadline = isset($item['booking_deadline'])
                    ? Carbon::parse($item['booking_deadline'])
                    : $startDate->copy()->subDays(3);

                $tour->schedules()->create([
                    'start_date'       => $startDate,
                    'end_date'         => $endDate,
                    'guide_id'         => $item['guide_id'] ?? null,
                    'max_people'       => $item['max_people'],
                    'min_people'       => $item['min_people'] ?? 1,
                    'booking_deadline' => $bookingDeadline,
                    'booked_people'    => 0,
                    'status'           => $item['status'] ?? 'open',
                ]);
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imagePath = $this->cloudinaryService->uploadImage(
                        $image,
                        'vivu-booking/tour-gallery'
                    );

                    TourImage::create([
                        'tour_id' => $tour->id,
                        'image_path' => $imagePath,
                    ]);
                }
            }

            return $tour->load(['categories', 'services', 'images', 'itineraries', 'schedules']);
        });

        return $this->success([
            'tour' => new TourResource($tour),
        ], 'Tạo tour thành công và đã được kích hoạt');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tour = Tour::with(['itineraries', 'schedules'])->find($id);

        if (! $tour) {
            return $this->error('Không tìm thấy tour', 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'adult_price' => ['required', 'numeric', 'min:0'],
            'child_price' => ['required', 'numeric', 'min:0'],
            'infant_price' => ['required', 'numeric', 'min:0'],
            'thumbnail' => ['nullable', 'string'],
            'thumbnail_file' => ['nullable', 'image', 'max:5120'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
            'number_of_days' => ['required', 'integer', 'min:1'],
            'number_of_nights' => ['required', 'integer', 'min:0'],
            'start_location' => ['required', 'string', 'max:255'],
            'end_location' => ['nullable', 'string', 'max:255'],
            'vehicle_info' => ['nullable', 'string', 'max:500'],
            'pickup_location' => ['nullable', 'string', 'max:500'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['exists:services,id'],
            'itineraries' => ['nullable', 'array'],
            'itineraries.*.id' => ['nullable', 'exists:tour_itineraries,id'],
            'itineraries.*.day_number' => ['required_with:itineraries', 'integer', 'min:1'],
            'itineraries.*.title' => ['required_with:itineraries', 'string', 'max:255'],
            'itineraries.*.start_point' => ['nullable', 'string', 'max:255'],
            'itineraries.*.end_point' => ['nullable', 'string', 'max:255'],
            'itineraries.*.route_points' => ['nullable', 'string'],
            'itineraries.*.rest_stops' => ['nullable', 'string'],
            'itineraries.*.content' => ['required_with:itineraries', 'string'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.id' => ['nullable', 'exists:tour_schedules,id'],
            'schedules.*.start_date' => ['required_with:schedules', 'date'],
            'schedules.*.max_people' => ['required_with:schedules', 'integer', 'min:1'],
            'schedules.*.min_people' => ['nullable', 'integer', 'min:1'],
            'schedules.*.booking_deadline' => ['nullable', 'date'],
            'schedules.*.status' => ['nullable', 'string', 'in:open,closed'],
            'schedules.*.guide_id' => ['nullable', 'exists:users,id'],
        ]);

        $numberOfDay = (int) $validated['number_of_days'];
        $numberOfNight = (int) $validated['number_of_nights'];

        if ($numberOfNight > $numberOfDay) {
            return $this->error('Số đêm không được lớn hơn số ngày', 400);
        }

        $categoryIds = $validated['category_ids'] ?? [];
        $serviceIds = $validated['service_ids'] ?? [];
        $itineraries = $validated['itineraries'] ?? [];
        $schedules = $validated['schedules'] ?? [];

        if ($scheduleError = $this->validateScheduleRules($schedules)) {
            return $this->error($scheduleError, 422);
        }

        if (count($itineraries) > $numberOfDay) {
            return $this->error("Lịch trình chỉ được tối đa {$numberOfDay} ngày", 422);
        }

        foreach ($itineraries as $itinerary) {
            if ((int) $itinerary['day_number'] > $numberOfDay) {
                return $this->error("Ngày trong lịch trình không được vượt quá {$numberOfDay}", 422);
            }
        }

        $tour = DB::transaction(function () use ($request, $tour, $validated, $categoryIds, $serviceIds, $itineraries, $schedules, $numberOfDay) {
            if ($request->hasFile('thumbnail_file')) {
                $validated['thumbnail'] = $this->cloudinaryService->uploadImage(
                    $request->file('thumbnail_file')
                );
            }

            unset($validated['thumbnail_file']);
            unset($validated['images'], $validated['category_ids'], $validated['service_ids'], $validated['itineraries'], $validated['schedules']);

            $tour->update([
                ...$validated,
                'price' => $validated['adult_price'],
                'discount_price' => null,
                'slug' => $this->buildUniqueSlug($validated['title'], $tour->id),
            ]);

            $tour->categories()->sync($categoryIds);
            $tour->services()->sync($serviceIds);

            $tour->itineraries()->delete();
            foreach ($itineraries as $item) {
                $tour->itineraries()->create([
                    'day_number' => $item['day_number'],
                    'title' => $item['title'],
                    'start_point' => $item['start_point'] ?? null,
                    'end_point' => $item['end_point'] ?? null,
                    'route_points' => $item['route_points'] ?? null,
                    'rest_stops' => $item['rest_stops'] ?? null,
                    'content' => $item['content'],
                ]);
            }

            $keptScheduleIds = [];
            foreach ($schedules as $item) {
                $scheduleId = isset($item['id']) ? (int) $item['id'] : null;
                $schedule = $scheduleId
                    ? $tour->schedules()->whereKey($scheduleId)->first()
                    : null;

                // Guard: không cho sửa thông tin vận hành khi chuyến đang chạy/đã kết thúc/đã hủy.
                if ($schedule && $schedule->isOperationallyLocked()) {
                    if (isset($item['min_people']) || isset($item['booking_deadline'])) {
                        throw ValidationException::withMessages([
                            'schedules' => sprintf(
                                'Không thể sửa thông tin chuyến khi trạng thái là "%s".',
                                $schedule->status->label(),
                            ),
                        ]);
                    }
                }

                $startDate = Carbon::parse($item['start_date']);
                $endDate   = $startDate->copy()->addDays(max(0, $numberOfDay - 1));

                $bookingDeadline = isset($item['booking_deadline'])
                    ? Carbon::parse($item['booking_deadline'])
                    : $startDate->copy()->subDays(3);

                $payload = [
                    'start_date'       => $startDate,
                    'end_date'         => $endDate,
                    'guide_id'         => $item['guide_id'] ?? null,
                    'max_people'       => $item['max_people'],
                    'min_people'       => $item['min_people'] ?? ($schedule?->min_people ?? 1),
                    'booking_deadline' => $bookingDeadline,
                ];

                if ($schedule) {
                    if ($schedule->booked_people > (int) $item['max_people']) {
                        throw ValidationException::withMessages([
                            'schedules' => 'Số chỗ tối đa không được nhỏ hơn số khách đã đặt.',
                        ]);
                    }

                    // Chỉ cập nhật status nếu chưa khóa vận hành (open/closed).
                    if (! $schedule->isOperationallyLocked()) {
                        $payload['status'] = $schedule->booked_people >= (int) $item['max_people']
                            ? 'closed'
                            : ($item['status'] ?? 'open');
                    }

                    $schedule->update($payload);
                    $keptScheduleIds[] = $schedule->id;
                    continue;
                }

                $created = $tour->schedules()->create([
                    ...$payload,
                    'booked_people' => 0,
                    'status'        => $item['status'] ?? 'open',
                ]);
                $keptScheduleIds[] = $created->id;
            }

            $tour->schedules()
                ->whereNotIn('id', $keptScheduleIds)
                ->where('booked_people', 0)
                ->delete();

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imagePath = $this->cloudinaryService->uploadImage(
                        $image,
                        'vivu-booking/tour-gallery'
                    );

                    TourImage::create([
                        'tour_id' => $tour->id,
                        'image_path' => $imagePath,
                    ]);
                }
            }

            return $tour->fresh(['categories', 'services', 'images', 'itineraries', 'schedules.guide:id,name,email,phone,status']);
        });

        return $this->success([
            'tour' => new TourResource($tour),
        ], 'Cập nhật tour thành công');
    }

    /**
     * Đổi trạng thái chuyến thủ công (A10).
     *
     * Admin được phép chuyển: open ↔ closed, open/closed → confirmed, open/closed/confirmed → cancelled.
     * Không cho admin chuyển sang in_progress hoặc completed — các trạng thái đó do hệ thống/HDV.
     *
     * PATCH /admin/schedules/{id}/status
     */
    /**
     * Hai ràng buộc không diễn đạt được bằng luật validate của Laravel vì phải so hai trường
     * trong cùng một phần tử mảng.
     *
     * min_people lớn hơn max_people thì chuyến không bao giờ chốt được, tác vụ nền sẽ cảnh báo
     * thiếu khách mãi mãi. booking_deadline sau ngày khởi hành thì hạn chốt vô nghĩa, khách đặt
     * được tới tận lúc xe lăn bánh trong khi phòng và suất ăn đã chốt từ trước.
     *
     * @param  array<int, array<string, mixed>>  $schedules
     */
    private function validateScheduleRules(array $schedules): ?string
    {
        foreach ($schedules as $index => $item) {
            $position = $index + 1;
            $maxPeople = (int) ($item['max_people'] ?? 0);
            $minPeople = (int) ($item['min_people'] ?? 1);

            if ($maxPeople > 0 && $minPeople > $maxPeople) {
                return "Lịch khởi hành thứ {$position}: số khách tối thiểu ({$minPeople}) "
                    . "không được lớn hơn sức chứa ({$maxPeople}).";
            }

            if (empty($item['booking_deadline']) || empty($item['start_date'])) {
                continue;
            }

            $deadline = Carbon::parse($item['booking_deadline']);
            $startDate = Carbon::parse($item['start_date']);

            if ($deadline->gte($startDate)) {
                return "Lịch khởi hành thứ {$position}: hạn chốt danh sách phải trước ngày khởi hành.";
            }
        }

        return null;
    }

    public function updateScheduleStatus(Request $request, int $id): JsonResponse
    {
        $allowedForAdmin = [
            ScheduleStatus::Open->value,
            ScheduleStatus::Closed->value,
            ScheduleStatus::Confirmed->value,
            ScheduleStatus::Cancelled->value,
        ];

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', $allowedForAdmin)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $toStatus = ScheduleStatus::from($validated['status']);

        // Lý do bắt buộc khi hủy chuyến.
        if ($toStatus === ScheduleStatus::Cancelled && empty($validated['reason'])) {
            return $this->error('Lý do hủy chuyến là bắt buộc.', 422);
        }

        $schedule = TourSchedule::find($id);

        if (! $schedule) {
            return $this->error('Không tìm thấy lịch khởi hành.', 404);
        }

        try {
            $schedule = $this->scheduleLifecycle->transitionTo(
                $schedule,
                $toStatus,
                reason: $validated['reason'] ?? null,
                actorId: $request->user()->id,
            );
        } catch (\App\Exceptions\BusinessRuleException $e) {
            return $this->error($e->getMessage(), $e->status());
        }

        return $this->success([
            'id'              => $schedule->id,
            'status'          => $schedule->status instanceof ScheduleStatus
                ? $schedule->status->value
                : $schedule->status,
            'confirmed_at'    => $schedule->confirmed_at?->toIso8601String(),
            'cancelled_at'    => $schedule->cancelled_at?->toIso8601String(),
            'cancelled_reason' => $schedule->cancelled_reason,
        ], 'Đã chuyển trạng thái chuyến sang ' . $toStatus->label());
    }

    public function assignScheduleGuide(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'guide_id' => ['nullable', 'exists:users,id'],
        ]);

        $schedule = TourSchedule::with('tour')->find($id);

        if (! $schedule) {
            return $this->error('Không tìm thấy lịch khởi hành', 404);
        }

        $guideId = $validated['guide_id'] ?? null;

        if ($guideId !== null) {
            $guide = User::find($guideId);

            if ($guide->role !== 'guide' || $guide->status !== 'active') {
                return $this->error('Hướng dẫn viên không hợp lệ hoặc đang ngừng hoạt động', 422);
            }

            $start = Carbon::parse($schedule->start_date)->startOfDay();
            $end = $start->copy()->addDays(max(0, (int) $schedule->tour->number_of_days - 1));

            $conflict = DB::transaction(function () use ($schedule, $guideId, $start, $end) {
                User::whereKey($guideId)->lockForUpdate()->first();

                $conflict = TourSchedule::query()
                    ->with('tour:id,title,number_of_days')
                    ->where('guide_id', $guideId)
                    ->whereKeyNot($schedule->id)
                    ->lockForUpdate()
                    ->get()
                    ->first(function (TourSchedule $assigned) use ($start, $end) {
                        $assignedStart = Carbon::parse($assigned->start_date)->startOfDay();
                        $assignedEnd = $assignedStart->copy()
                            ->addDays(max(0, (int) $assigned->tour->number_of_days - 1));

                        return $start->lte($assignedEnd) && $end->gte($assignedStart);
                    });

                if (! $conflict) {
                    $schedule->update(['guide_id' => $guideId]);
                }

                return $conflict;
            });

            if ($conflict) {
                $conflictStart = Carbon::parse($conflict->start_date);
                $conflictEnd = $conflictStart->copy()
                    ->addDays(max(0, (int) $conflict->tour->number_of_days - 1));

                return $this->error(
                    sprintf(
                        'Hướng dẫn viên đã có chuyến "%s" từ %s đến %s',
                        $conflict->tour->title,
                        $conflictStart->format('d/m/Y'),
                        $conflictEnd->format('d/m/Y')
                    ),
                    422
                );
            }
        }

        if ($guideId === null) {
            $schedule->update(['guide_id' => null]);
        }

        return $this->success(
            $schedule->fresh(['guide:id,name,email,phone,status', 'tour:id,title,number_of_days']),
            $guideId === null ? 'Đã bỏ phân công hướng dẫn viên' : 'Phân công hướng dẫn viên thành công'
        );
    }

    private function validateScheduleGuideAssignments(array $schedules, int $numberOfDays): void
    {
        $selectedGuideIds = collect($schedules)->pluck('guide_id')->filter()->unique()->values();

        if ($selectedGuideIds->isEmpty()) {
            return;
        }

        $guides = User::query()
            ->whereIn('id', $selectedGuideIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $existingSchedules = TourSchedule::query()
            ->with('tour:id,title,number_of_days')
            ->whereIn('guide_id', $selectedGuideIds)
            ->lockForUpdate()
            ->get()
            ->groupBy('guide_id');

        $draftPeriods = [];

        foreach ($schedules as $index => $schedule) {
            $guideId = isset($schedule['guide_id']) ? (int) $schedule['guide_id'] : null;

            if (! $guideId) {
                continue;
            }

            $guide = $guides->get($guideId);

            if (! $guide || $guide->role !== 'guide' || $guide->status !== 'active') {
                throw ValidationException::withMessages([
                    'schedules.' . $index . '.guide_id' => 'Hướng dẫn viên không hợp lệ hoặc đang ngừng hoạt động.',
                ]);
            }

            $start = Carbon::parse($schedule['start_date'])->startOfDay();
            $end = $start->copy()->addDays($numberOfDays - 1);

            $conflict = ($existingSchedules->get($guideId) ?? collect())
                ->first(fn (TourSchedule $assigned) => $this->scheduleOverlaps($start, $end, $assigned));

            if ($conflict) {
                $conflictStart = Carbon::parse($conflict->start_date);
                $conflictEnd = $conflictStart->copy()
                    ->addDays(max(0, (int) $conflict->tour->number_of_days - 1));

                throw ValidationException::withMessages([
                    'schedules.' . $index . '.guide_id' => sprintf(
                        'Hướng dẫn viên đã có chuyến "%s" từ %s đến %s.',
                        $conflict->tour->title,
                        $conflictStart->format('d/m/Y'),
                        $conflictEnd->format('d/m/Y')
                    ),
                ]);
            }

            foreach ($draftPeriods[$guideId] ?? [] as $draft) {
                if ($start->lte($draft['end']) && $end->gte($draft['start'])) {
                    throw ValidationException::withMessages([
                        'schedules.' . $index . '.guide_id' => 'Hướng dẫn viên bị trùng với một lịch khởi hành khác trong tour đang tạo.',
                    ]);
                }
            }

            $draftPeriods[$guideId][] = ['start' => $start, 'end' => $end];
        }
    }

    private function scheduleOverlaps(Carbon $start, Carbon $end, TourSchedule $schedule): bool
    {
        $assignedStart = Carbon::parse($schedule->start_date)->startOfDay();
        $assignedEnd = $assignedStart->copy()
            ->addDays(max(0, (int) $schedule->tour->number_of_days - 1));

        return $start->lte($assignedEnd) && $end->gte($assignedStart);
    }

    private function buildUniqueSlug(string $title, int $ignoreId): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Tour::query()
                ->where('slug', $slug)
                ->whereKeyNot($ignoreId)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }
}



