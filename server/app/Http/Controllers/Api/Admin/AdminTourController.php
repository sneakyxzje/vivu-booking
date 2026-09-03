<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\GuideAssignmentDecline;
use App\Models\TourImage;
use App\Models\Service;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use App\Models\User;
use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Notifications\Alert;
use App\Services\CloudinaryService;
use App\Services\Notifier;
use App\Services\GuideSuitabilityService;
use App\Services\ScheduleDeadlineService;
use App\Services\ScheduleGuideService;
use App\Services\ScheduleLifecycleService;
use App\Services\TourDeletionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\TourResource;

class AdminTourController extends Controller
{
    public function __construct(
        protected CloudinaryService $cloudinaryService,
        protected ScheduleLifecycleService $scheduleLifecycle,
        protected ScheduleDeadlineService $scheduleDeadline,
        protected ScheduleGuideService $scheduleGuides,
        protected GuideSuitabilityService $guideSuitability,
        protected TourDeletionService $tourDeletion,
        protected Notifier $notifier,
    ) {
    }

    /**
     * K06 - Xem trước việc xóa tour: xóa được không, và những gì vẫn ở lại.
     *
     * Đọc trước khi bấm. Phần "vẫn ở lại" quan trọng ngang phần chặn: người bấm cần thấy rằng
     * đơn hàng và đánh giá của khách không mất đi đâu cả.
     */
    public function deletePreview(int $id): JsonResponse
    {
        $tour = Tour::query()->find($id);

        if (! $tour) {
            return $this->error('Không tìm thấy tour', 404);
        }

        return $this->success($this->tourDeletion->preview($tour), 'Lấy thông tin xóa tour thành công');
    }

    /**
     * Xóa tour. Bên dưới là xóa mềm nên hoàn tác được bằng `restore`.
     */
    public function destroy(int $id): JsonResponse
    {
        $tour = Tour::query()->find($id);

        if (! $tour) {
            return $this->error('Không tìm thấy tour', 404);
        }

        $ten = $tour->title;

        $this->tourDeletion->delete($tour);

        return $this->success(
            null,
            sprintf('Đã xóa "%s". Đơn hàng và đánh giá cũ vẫn còn nguyên; khôi phục lại được.', $ten),
        );
    }

    /** Danh sách tour đã xóa, để khôi phục. */
    public function trashed(): JsonResponse
    {
        $ds = Tour::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->get(['id', 'title', 'start_location', 'status', 'deleted_at'])
            ->map(fn (Tour $tour) => [
                'id' => $tour->id,
                'title' => $tour->title,
                'start_location' => $tour->start_location,
                'deleted_at' => $tour->deleted_at?->format('Y-m-d H:i:s'),
                'bookings_count' => Booking::query()->where('tour_id', $tour->id)->count(),
            ]);

        return $this->success($ds, 'Lấy danh sách tour đã xóa thành công');
    }

    public function restore(int $id): JsonResponse
    {
        $tour = $this->tourDeletion->restore($id);

        return $this->success(
            ['id' => $tour->id, 'title' => $tour->title],
            sprintf('Đã khôi phục "%s".', $tour->title),
        );
    }

    /**
     * Ngừng bán - lối đi cho tour đã có lịch sử, thay cho việc xóa.
     *
     * Không đụng tới chuyến đã chốt: khách đã mua thì chuyến vẫn phải chạy. Ngừng bán chỉ có
     * nghĩa là không nhận khách mới.
     */
    public function retire(Request $request, int $id): JsonResponse
    {
        $tour = Tour::query()->find($id);

        if (! $tour) {
            return $this->error('Không tìm thấy tour', 404);
        }

        $this->tourDeletion->retire($tour, $request->user());

        return $this->success(
            ['id' => $tour->id, 'status' => $tour->status],
            sprintf('Đã chuyển "%s" sang ngừng bán. Chuyến đã chốt vẫn chạy bình thường.', $tour->title),
        );
    }


    public function index(): JsonResponse
    {
        $tours = Tour::with([
            'admin:id,name,email',
            'schedules.guides:id,name,email,phone,status',
            'categories',
            'services',
            'images',
            'itineraries.checkpoints',
            'schedules' => $this->kemSoKhachDaTra(),
        ])
            ->latest()
            ->get();

        return $this->success(TourResource::collection($tours), 'Lấy danh sách tour thành công');
    }

    /**
     * Kèm `paid_people` vào mỗi chuyến: tổng số khách của các đơn ĐÃ THANH TOÁN.
     *
     * Khác `booked_people`, vốn đếm cả chỗ đang giữ mà chưa trả tiền. Lệnh nền
     * `ConfirmReadySchedules` so số khách **đã trả** với `min_people` để quyết chốt chuyến hay
     * không, nên màn hình phải nhìn cùng con số ấy — nếu không thì chuyến giữ 8 chỗ mà mới 2 người
     * trả tiền vẫn trông như đủ khách, và điều hành chỉ biết vào phút cuối.
     *
     * Dùng `withSum` để ra một truy vấn cho cả danh sách, không phải mỗi chuyến một truy vấn.
     *
     * Cộng theo GHẾ, không theo người — đúng đơn vị mà `ConfirmReadySchedules` so với `min_people`
     * và `booked_people` đang đếm. Cộng `guests` ở đây thì màn hình báo một con số còn lệnh nền
     * quyết theo một con số khác, và người dùng không có cách nào biết vì sao chuyến "đủ khách"
     * trên màn hình lại không được chốt.
     *
     * Biểu thức lùi về `guests` khi `seats` bằng 0 là bản SQL của `Booking::seatsTaken()`, giống
     * hệt cách `CheckSeatConsistency` đang làm.
     */
    private function kemSoKhachDaTra(): \Closure
    {
        return fn ($query) => $query->withSum(
            ['bookings as paid_people' => fn ($b) => $b->whereIn('status', BookingStatus::paidValues())],
            DB::raw('CASE WHEN seats > 0 THEN seats ELSE guests END'),
        );
    }

    public function show(int $id): JsonResponse
    {
        $tour = Tour::with([
            'admin:id,name,email',
            'categories',
            'services',
            'images',
            'itineraries.checkpoints',
            'schedules.guides:id,name,email,phone,status',
        ])->find($id);

        if (! $tour) {
            return $this->error('Không tìm thấy tour', 404);
        }

        return $this->success(new TourResource($tour), 'Lấy chi tiết tour thành công');
    }
    /**
     * Mốc kết thúc chuyến.
     *
     * **Điều hành đặt cả ngày lẫn giờ.** Trước đây máy chủ suy trọn gói từ `start_date + (số ngày
     * - 1)`, và hệ quả là phần giờ luôn là bản sao của giờ đi — một con số không mang thông tin
     * nào, tới mức giao diện khách phải giấu nó đi để khỏi nói rằng xe về đúng giờ nó chạy.
     *
     * Số ngày của tour không đủ để suy: cùng một tour ba ngày, chuyến này về chiều ngày thứ ba,
     * chuyến kia đi xe đêm và trả khách lúc 5 giờ sáng. Đó là thỏa thuận với từng nhà xe, không
     * phải thuộc tính của chương trình tour.
     *
     * Không gửi mốc kết thúc thì giữ nguyên nếp suy cũ, để các đường gọi cũ và dữ liệu đã có không
     * bị viết lại âm thầm.
     */
    private function ngayVe(Carbon $batDau, int $soNgay, ?string $ketThuc): Carbon
    {
        return $ketThuc ? Carbon::parse($ketThuc) : $batDau->copy()->addDays(max(0, $soNgay - 1));
    }

    /**
     * Mốc kết thúc phải sau mốc khởi hành.
     *
     * Luật duy nhất còn lại giữa hai mốc, và cố ý chỉ có thế. Không ép khớp với số ngày của tour:
     * một tour ba ngày vẫn có chuyến về sáng sớm hôm thứ ba, và ép khớp sẽ chặn đúng trường hợp
     * thật. Còn kết thúc trước hoặc đúng lúc khởi hành thì không phải một lựa chọn nghiệp vụ, đó
     * là gõ nhầm — và nếu để lọt thì mọi phép tính độ dài chuyến sau đó đều ra số âm.
     */
    private function assertKetThucSauKhoiHanh(Carbon $batDau, Carbon $ketThuc): void
    {
        if ($ketThuc->greaterThan($batDau)) {
            return;
        }

        throw ValidationException::withMessages([
            'schedules' => sprintf(
                'Chuyến khởi hành %s có mốc kết thúc %s không sau mốc khởi hành. Kiểm tra lại ngày giờ về.',
                $batDau->format('d/m/Y H:i'),
                $ketThuc->format('d/m/Y H:i'),
            ),
        ]);
    }

    /**
     * Bốn mốc của một chuyến phải xếp đúng thứ tự đường đi.
     *
     * rời điểm khởi hành → tới điểm đến → rời điểm đến → về tới nơi
     *
     * Hai mốc giữa để trống được, và phần lớn chuyến cũ đang trống. Chỉ kiểm những mốc thật sự
     * được điền, theo từng cặp liền kề — điền một nửa vẫn hợp lệ.
     *
     * Cho phép hai mốc bằng nhau ở khoảng giữa: tour trong ngày có thể tới nơi rồi quay đầu gần
     * như tức thì, và chặn chuyện đó chỉ để cho gọn công thức là chặn một tình huống có thật.
     *
     * @param  array<int, array{0: string, 1: Carbon|null}>  $moc
     */
    private function assertThuTuCacMoc(array $moc): void
    {
        $daDien = array_values(array_filter($moc, fn (array $cap) => $cap[1] !== null));

        for ($i = 1; $i < count($daDien); $i++) {
            [$tenTruoc, $truoc] = $daDien[$i - 1];
            [$tenSau, $sau] = $daDien[$i];

            if ($sau->greaterThanOrEqualTo($truoc)) {
                continue;
            }

            throw ValidationException::withMessages([
                'schedules' => sprintf(
                    'Chuyến khởi hành %s có mốc "%s" (%s) xảy ra trước mốc "%s" (%s).',
                    $daDien[0][1]->format('d/m/Y H:i'),
                    $tenSau,
                    $sau->format('d/m/Y H:i'),
                    $tenTruoc,
                    $truoc->format('d/m/Y H:i'),
                ),
            ]);
        }
    }

    /**
     * Đọc một mốc tùy chọn từ dữ liệu biểu mẫu.
     *
     * Chuỗi rỗng và null đều nghĩa là "không điền": ô ngày giờ để trống gửi lên chuỗi rỗng, và
     * `Carbon::parse('')` trả về thời điểm hiện tại — một mốc bịa ra không ai nhập.
     */
    private function mocTuyChon(array $item, string $khoa): ?Carbon
    {
        $giaTri = $item[$khoa] ?? null;

        return ($giaTri === null || $giaTri === '') ? null : Carbon::parse($giaTri);
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
            /*
             * Trùng lịch hỏi qua ScheduleGuideService, không tự tính ở đây.
             *
             * Chỗ này từng gọi `$this->scheduleOverlaps(...)` — một phương thức không tồn tại ở đâu
             * cả, nên mỗi lần biểu mẫu tour hỏi "ai đang rảnh" là một lần lỗi 500. Đúng cái khuôn
             * mà chú thích của `ScheduleGuideService::lyDoChan()` đã cảnh báo: luật có ở đường ghi
             * mà đường đọc thì tự viết lại một bản riêng.
             *
             * Nay hai phía dùng chung `periodOf()` và `overlaps()`, là đúng hai phép mà
             * `conflictFor()` bên đường ghi dựa vào — thêm luật ở đó thì đường này tự có theo.
             */
            ->filter(function (User $guide) use ($start, $end) {
                return ! $guide->assignedSchedules->contains(
                    fn (TourSchedule $schedule) => ScheduleGuideService::overlaps(
                        $start,
                        $end,
                        ...$this->scheduleGuides->periodOf($schedule),
                    )
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
            /*
             * KHÔNG còn `cancellation_policy_id` ở đây. Cả hệ thống dùng chung một bảng phí hủy;
             * tour không chọn riêng nữa. Cột trên bảng `tours` giữ lại cho dữ liệu cũ, nhưng
             * không đường nào ghi vào nó nữa - xem AdminCancellationPolicyController.
             */
            'itineraries' => ['nullable', 'array'],
            'itineraries.*.day_number' => ['required_with:itineraries', 'integer', 'min:1'],
            'itineraries.*.title' => ['required_with:itineraries', 'string', 'max:255'],
            'itineraries.*.start_point' => ['nullable', 'string', 'max:255'],
            'itineraries.*.end_point' => ['nullable', 'string', 'max:255'],
            'itineraries.*.route_points' => ['nullable', 'string'],
            'itineraries.*.rest_stops' => ['nullable', 'string'],
            'itineraries.*.content' => ['required_with:itineraries', 'string'],
            'itineraries.*.checkpoints' => ['nullable', 'array'],
            'itineraries.*.checkpoints.*.name' => ['required_with:itineraries.*.checkpoints', 'string', 'max:255'],
            'itineraries.*.checkpoints.*.description' => ['nullable', 'string'],
            'itineraries.*.checkpoints.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'itineraries.*.checkpoints.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'itineraries.*.checkpoints.*.sequence' => ['required_with:itineraries.*.checkpoints', 'integer', 'min:1'],
            'itineraries.*.checkpoints.*.is_required_photo' => ['nullable', 'boolean'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.start_date' => ['required_with:schedules', 'date', 'after_or_equal:today'],
            // Mốc kết thúc do điều hành đặt. Bỏ trống thì suy từ số ngày của tour — xem ngayVe().
            'schedules.*.end_date' => ['nullable', 'date'],
            /*
             * Hai mốc giữa: tới điểm đến, và rời điểm đến để về.
             *
             * Giờ áng chừng do điều hành điền, không suy ra được từ đâu. Thứ tự bốn mốc kiểm ở
             * `assertThuTuCacMoc()` chứ không ở đây, để thông báo lỗi gọi được tên từng mốc.
             */
            'schedules.*.arrival_at' => ['nullable', 'date'],
            'schedules.*.return_departure_at' => ['nullable', 'date'],
            'schedules.*.max_people' => ['required_with:schedules', 'integer', 'min:1'],
            'schedules.*.min_people' => ['nullable', 'integer', 'min:1'],
            'schedules.*.booking_deadline' => ['nullable', 'date'],
            'schedules.*.status' => ['nullable', 'string', 'in:open,closed'],
            // Nhiều hướng dẫn viên cho một chuyến. Bao nhiêu người là đủ thì điều hành quyết.
            'schedules.*.guide_ids' => ['nullable', 'array'],
            'schedules.*.guide_ids.*' => ['integer', 'exists:users,id'],
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

        /** @var array<int, array<int, int>> khóa là id chuyến, giá trị là id những người mới */
        $nguoiMoiTheoChuyen = [];

        $tour = DB::transaction(function () use ($request, $validated, $categoryIds, $serviceIds, $itineraries, $schedules, $numberOfDay, &$nguoiMoiTheoChuyen) {
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
                $itinerary = $tour->itineraries()->create([
                    'day_number' => $item['day_number'],
                    'title' => $item['title'],
                    'start_point' => $item['start_point'] ?? null,
                    'end_point' => $item['end_point'] ?? null,
                    'route_points' => $item['route_points'] ?? null,
                    'rest_stops' => $item['rest_stops'] ?? null,
                    'content' => $item['content'],
                ]);

                foreach ($item['checkpoints'] ?? [] as $checkpoint) {
                    $itinerary->checkpoints()->create([
                        'name' => $checkpoint['name'],
                        'description' => $checkpoint['description'] ?? null,
                        'latitude' => $checkpoint['latitude'] ?? null,
                        'longitude' => $checkpoint['longitude'] ?? null,
                        'sequence' => $checkpoint['sequence'],
                        'is_required_photo' => $checkpoint['is_required_photo'] ?? false,
                    ]);
                }
            }

            foreach ($schedules as $item) {
                $startDate = Carbon::parse($item['start_date']);
                $endDate = $this->ngayVe($startDate, $numberOfDay, $item['end_date'] ?? null);
                $this->assertKetThucSauKhoiHanh($startDate, $endDate);

                $toiNoi = $this->mocTuyChon($item, 'arrival_at');
                $roiDiem = $this->mocTuyChon($item, 'return_departure_at');

                $this->assertThuTuCacMoc([
                    ['rời điểm khởi hành', $startDate],
                    ['tới điểm đến', $toiNoi],
                    ['rời điểm đến', $roiDiem],
                    ['về tới nơi', $endDate],
                ]);

                // booking_deadline: không truyền thì lấy mốc mặc định của hệ thống.
                $bookingDeadline = isset($item['booking_deadline'])
                    ? Carbon::parse($item['booking_deadline'])
                    : TourSchedule::hanChotMacDinhTu($startDate);

                $created = $tour->schedules()->create([
                    'start_date'          => $startDate,
                    'end_date'            => $endDate,
                    'arrival_at'          => $toiNoi,
                    'return_departure_at' => $roiDiem,
                    'max_people'       => $item['max_people'],
                    'min_people'       => $item['min_people'] ?? 1,
                    'booking_deadline' => $bookingDeadline,
                    'booked_people'    => 0,
                    'status'           => $item['status'] ?? 'open',
                ]);

                // Phân công đi qua bảng nối. Chồng lịch đã được kiểm ở validateScheduleGuideAssignments
                // ngay đầu giao dịch này, nên ở đây chỉ ghi.
                $nguoiMoiTheoChuyen[$created->id] = $this->phanCongVaTraVeNguoiMoi(
                    $created,
                    $this->guideIdsOf($item),
                );
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

        $this->baoNguoiMoiDuocPhanCong($nguoiMoiTheoChuyen);

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
            /*
             * KHÔNG còn `cancellation_policy_id` ở đây. Cả hệ thống dùng chung một bảng phí hủy;
             * tour không chọn riêng nữa. Cột trên bảng `tours` giữ lại cho dữ liệu cũ, nhưng
             * không đường nào ghi vào nó nữa - xem AdminCancellationPolicyController.
             */
            'itineraries' => ['nullable', 'array'],
            'itineraries.*.id' => ['nullable', 'exists:tour_itineraries,id'],
            'itineraries.*.day_number' => ['required_with:itineraries', 'integer', 'min:1'],
            'itineraries.*.title' => ['required_with:itineraries', 'string', 'max:255'],
            'itineraries.*.start_point' => ['nullable', 'string', 'max:255'],
            'itineraries.*.end_point' => ['nullable', 'string', 'max:255'],
            'itineraries.*.route_points' => ['nullable', 'string'],
            'itineraries.*.rest_stops' => ['nullable', 'string'],
            'itineraries.*.content' => ['required_with:itineraries', 'string'],
            'itineraries.*.checkpoints' => ['nullable', 'array'],
            'itineraries.*.checkpoints.*.id' => ['nullable', 'exists:itinerary_checkpoints,id'],
            'itineraries.*.checkpoints.*.name' => ['required_with:itineraries.*.checkpoints', 'string', 'max:255'],
            'itineraries.*.checkpoints.*.description' => ['nullable', 'string'],
            'itineraries.*.checkpoints.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'itineraries.*.checkpoints.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'itineraries.*.checkpoints.*.sequence' => ['required_with:itineraries.*.checkpoints', 'integer', 'min:1'],
            'itineraries.*.checkpoints.*.is_required_photo' => ['nullable', 'boolean'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.id' => ['nullable', 'exists:tour_schedules,id'],
            'schedules.*.start_date' => ['required_with:schedules', 'date'],
            // Mốc kết thúc do điều hành đặt. Bỏ trống thì suy từ số ngày của tour — xem ngayVe().
            'schedules.*.end_date' => ['nullable', 'date'],
            /*
             * Hai mốc giữa: tới điểm đến, và rời điểm đến để về.
             *
             * Giờ áng chừng do điều hành điền, không suy ra được từ đâu. Thứ tự bốn mốc kiểm ở
             * `assertThuTuCacMoc()` chứ không ở đây, để thông báo lỗi gọi được tên từng mốc.
             */
            'schedules.*.arrival_at' => ['nullable', 'date'],
            'schedules.*.return_departure_at' => ['nullable', 'date'],
            'schedules.*.max_people' => ['required_with:schedules', 'integer', 'min:1'],
            'schedules.*.min_people' => ['nullable', 'integer', 'min:1'],
            'schedules.*.booking_deadline' => ['nullable', 'date'],
            // Lý do dời hạn chốt, không bắt buộc. Có thì được ghi vào nhật ký chuyến.
            'schedules.*.booking_deadline_reason' => ['nullable', 'string', 'max:500'],
            /*
             * Nhận cả sáu trạng thái, không riêng open/closed.
             *
             * Biểu mẫu gửi lại nguyên trạng thái nó đọc được lúc mở form. Tour nào có một chuyến đã
             * chốt hoặc đã đi xong thì lần lưu nào cũng chết ở đây với "The selected
             * schedules.0.status is invalid" — một câu không nói cho người dùng biết họ vừa làm sai
             * cái gì, mà thật ra họ có làm gì đâu.
             *
             * Nhận vào không có nghĩa là ghi xuống: chỗ áp trạng thái bên dưới chỉ lấy open/closed,
             * và chỉ cho chuyến còn đang bán.
             */
            'schedules.*.status' => ['nullable', 'string', Rule::in(ScheduleStatus::values())],
            'schedules.*.guide_ids' => ['nullable', 'array'],
            'schedules.*.guide_ids.*' => ['integer', 'exists:users,id'],
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

        /*
         * Ai vừa được thêm vào chuyến nào, gom trong giao dịch và báo sau khi nó chốt.
         *
         * Thông báo đã gửi thì không gọi về được, còn giao dịch thì vẫn có thể quay lại — cùng lý
         * do đang áp ở `ScheduleCancellationService` và `ScheduleGuideService`.
         *
         * @var array<int, array<int, int>> khóa là id chuyến, giá trị là id những người mới
         */
        $nguoiMoiTheoChuyen = [];

        $tour = DB::transaction(function () use ($request, $tour, $validated, $categoryIds, $serviceIds, $itineraries, $schedules, $numberOfDay, &$nguoiMoiTheoChuyen) {
            /*
             * Luật trùng lịch hướng dẫn viên — kiểm ở ĐÂY, không chỉ ở `store()`.
             *
             * Thiếu dòng này thì form sửa tour là một cửa sau đi vòng qua đúng luật mà đường phân
             * công lẻ (`assignScheduleGuide`) đang chặn: một người được gán cho hai chuyến chồng
             * ngày, và không có gì báo lại. Đúng khuôn lỗi mà chú thích của
             * `ScheduleGuideService::lyDoChan()` đã cảnh báo — luật có ở đường ghi này mà thiếu ở
             * đường ghi kia.
             */
            $this->validateScheduleGuideAssignments($schedules, $numberOfDay);

            if ($request->hasFile('thumbnail_file')) {
                $validated['thumbnail'] = $this->cloudinaryService->uploadImage(
                    $request->file('thumbnail_file')
                );
            }

            unset($validated['thumbnail_file']);
            unset($validated['images'], $validated['category_ids'], $validated['service_ids'], $validated['itineraries'], $validated['schedules']);

            $tour->update([
                ...$validated,
                'slug' => $this->buildUniqueSlug($validated['title'], $tour->id),
            ]);

            $tour->categories()->sync($categoryIds);
            $tour->services()->sync($serviceIds);

            $this->syncItineraries($tour, $itineraries);

            $keptScheduleIds = [];
            foreach ($schedules as $item) {
                $scheduleId = isset($item['id']) ? (int) $item['id'] : null;
                $schedule = $scheduleId
                    ? $tour->schedules()->whereKey($scheduleId)->first()
                    : null;

                // Guard: không cho sửa thông tin vận hành khi chuyến đang chạy/đã kết thúc/đã hủy.
                if ($schedule && $schedule->isOperationallyLocked() && $this->coDoiThongTinVanHanh($schedule, $item)) {
                    throw ValidationException::withMessages([
                        'schedules' => sprintf(
                            'Không thể sửa thông tin chuyến khởi hành %s khi trạng thái là "%s".',
                            $schedule->start_date->format('d/m/Y'),
                            $schedule->status->label(),
                        ),
                    ]);
                }

                $startDate = Carbon::parse($item['start_date']);

                /*
                 * Chuyến đã có khách thì KHÔNG dời được ngày khởi hành qua đường sửa tour.
                 *
                 * Ngày khởi hành không chỉ là một ô ngày tháng. Hạn trả nốt của mọi đơn trên chuyến
                 * suy ra từ nó (`Booking::balanceDueAt()`), nên dời ngày là dời hạn của từng người —
                 * và dời sớm lên thì hạn ấy rơi vào quá khứ, biến cả chuyến thành đơn quá hạn cùng
                 * lúc. Lệnh nhắc gửi thư muộn, lệnh hủy quét theo sau, và khách mất cọc vì một lần
                 * bấm Lưu tour.
                 *
                 * Hệ thống đã có đúng hai đường được phép đổi ngày đi của một đơn — ghép chuyến và
                 * chuyển chuyến — và cả hai đều ghi `booking_transfers`, gửi thư cho khách, miễn phí
                 * hủy vì công ty là bên dời, rồi báo điều hành khi quy trình thu nốt không còn kịp.
                 * Đường này không làm gì trong số đó, nên nó phải đóng lại thay vì học lại từ đầu.
                 *
                 * Chuyến chưa ai đặt thì vẫn sửa thoải mái: lúc ấy ngày khởi hành mới chỉ là dữ liệu.
                 */
                if ($schedule
                    && !$startDate->equalTo($schedule->start_date)
                    && $schedule->bookings()->whereNot('status', BookingStatus::Cancelled->value)->exists()) {
                    throw ValidationException::withMessages([
                        'schedules' => sprintf(
                            'Chuyến khởi hành %s đã có khách đặt nên không đổi được ngày ở đây. '
                                . 'Hạn thanh toán của từng đơn tính theo ngày này, đổi ở đây sẽ làm '
                                . 'khách quá hạn mà không được báo. Dùng chức năng ghép chuyến hoặc '
                                . 'chuyển chuyến để dời khách sang ngày mới.',
                            $schedule->start_date->format('d/m/Y'),
                        ),
                    ]);
                }

                $endDate   = $this->ngayVe($startDate, $numberOfDay, $item['end_date'] ?? null);
                $this->assertKetThucSauKhoiHanh($startDate, $endDate);

                $toiNoi = $this->mocTuyChon($item, 'arrival_at');
                $roiDiem = $this->mocTuyChon($item, 'return_departure_at');

                $this->assertThuTuCacMoc([
                    ['rời điểm khởi hành', $startDate],
                    ['tới điểm đến', $toiNoi],
                    ['rời điểm đến', $roiDiem],
                    ['về tới nơi', $endDate],
                ]);

                /*
                 * Hạn chốt chỉ đụng tới khi biểu mẫu thực sự gửi trường ấy lên.
                 *
                 * Trước đây thiếu trường thì mặc định về "khởi hành trừ ba ngày" rồi ghi đè. Nên
                 * một lần lưu tour chỉ để sửa tiêu đề cũng xóa mất mốc điều hành đã thương lượng
                 * với nhà cung cấp - âm thầm, và cái mốc bị mất ấy điều khiển năm quy tắc khác.
                 */
                $coGuiHanChot = array_key_exists('booking_deadline', $item);
                $hanChotMoi = $coGuiHanChot && $item['booking_deadline'] !== null
                    ? Carbon::parse($item['booking_deadline'])
                    : null;

                $payload = [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'arrival_at'          => $toiNoi,
                    'return_departure_at' => $roiDiem,
                    'max_people' => $item['max_people'],
                    'min_people' => $item['min_people'] ?? ($schedule?->min_people ?? 1),
                ];

                if ($schedule) {
                    if ($schedule->booked_people > (int) $item['max_people']) {
                        throw ValidationException::withMessages([
                            'schedules' => 'Số chỗ tối đa không được nhỏ hơn số khách đã đặt.',
                        ]);
                    }

                    /*
                     * Trạng thái chỉ nhận từ biểu mẫu khi chuyến còn ở giai đoạn bán.
                     *
                     * Biểu mẫu gửi lại nguyên trạng thái nó đọc được lúc mở form. Chuyến đã chốt
                     * chạy mà tin theo con số ấy thì một lần bấm "Lưu tour" lặng lẽ mở bán lại một
                     * chuyến đã chốt danh sách — `isOperationallyLocked()` không đỡ được, vì
                     * `confirmed` không nằm trong nhóm đó.
                     *
                     * Sau khi chốt, vòng đời do nơi khác điều khiển: lệnh nền, nút đổi trạng thái ở
                     * màn quản lý chuyến, và luồng hủy chuyến.
                     */
                    $trangThaiMoi = $item['status'] ?? null;

                    if ($this->conDangBan($schedule) && in_array($trangThaiMoi, ['open', 'closed'], true)) {
                        $payload['status'] = $schedule->booked_people >= (int) $item['max_people']
                            ? 'closed'
                            : $trangThaiMoi;
                    }

                    /*
                     * Hạn chốt không nằm trong payload và đi qua service riêng.
                     *
                     * Dời hạn chốt không phải sửa một con số: nó đổi cùng lúc quyền bán chỗ, sửa
                     * tên hành khách, chuyển chuyến, ghép chuyến, và việc chỗ có về kho khi khách
                     * hủy hay không. Việc đó phải để lại vết. Ghi thẳng vào payload thì giá trị cũ
                     * mất trước khi kịp ghi nhật ký, nên nó phải đứng ngoài.
                     */
                    $schedule->update($payload);

                    // Chuyến đã khóa vận hành thì bỏ qua, không ghi và cũng không báo lỗi: guard
                    // ở trên đã chặn trường hợp người dùng cố ý gửi hạn chốt mới.
                    if ($coGuiHanChot && ! $schedule->isOperationallyLocked()) {
                        $this->scheduleDeadline->change(
                            $schedule,
                            $hanChotMoi,
                            $item['booking_deadline_reason'] ?? null,
                            $request->user(),
                        );
                    }

                    $nguoiMoiTheoChuyen[$schedule->id] = $this->phanCongVaTraVeNguoiMoi(
                        $schedule,
                        $this->guideIdsOf($item),
                    );

                    $keptScheduleIds[] = $schedule->id;
                    continue;
                }

                $created = $tour->schedules()->create([
                    ...$payload,
                    // Chuyến mới dựng thì chưa có gì để giữ, thiếu hạn chốt là lấy mốc mặc định.
                    'booking_deadline' => $hanChotMoi ?? TourSchedule::hanChotMacDinhTu($startDate),
                    'booked_people' => 0,
                    'status'        => $item['status'] ?? 'open',
                ]);

                $nguoiMoiTheoChuyen[$created->id] = $this->phanCongVaTraVeNguoiMoi(
                    $created,
                    $this->guideIdsOf($item),
                );

                $keptScheduleIds[] = $created->id;
            }

            /*
             * Dọn các chuyến bị bỏ khỏi biểu mẫu — nhưng chỉ khi biểu mẫu THỰC SỰ nói về lịch.
             *
             * Trường `schedules` là tùy chọn, nên một yêu cầu chỉ sửa tiêu đề mà không gửi mảng ấy
             * lên sẽ có `$keptScheduleIds` rỗng. Câu lệnh cũ chạy trong trường hợp đó là xóa sạch
             * mọi chuyến chưa có khách của tour, do một thao tác không liên quan gì tới lịch.
             *
             * Điều kiện cũ cũng sai chỗ khác: `booked_people = 0` KHÔNG có nghĩa là chưa ai từng
             * đặt. Một chuyến chỉ còn đơn đã hủy cũng bằng 0, và khóa ngoại `tour_schedule_id` là
             * `nullOnDelete` — xóa chuyến đi thì các đơn cũ mất luôn ngày khởi hành, mất căn cứ
             * hạn chốt, và lịch sử của chúng không dựng lại được nữa.
             *
             * Nên chỉ xóa chuyến chưa từng có đơn nào trỏ tới.
             */
            if ($request->has('schedules')) {
                $tour->schedules()
                    ->whereNotIn('id', $keptScheduleIds)
                    ->whereDoesntHave('bookings')
                    ->delete();
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

            return $tour->fresh(['categories', 'services', 'images', 'itineraries', 'schedules.guides:id,name,email,phone,status']);
        });

        $this->baoNguoiMoiDuocPhanCong($nguoiMoiTheoChuyen);

        return $this->success([
            'tour' => new TourResource($tour),
        ], 'Cập nhật tour thành công');
    }

    /**
     * Ghi danh sách hướng dẫn viên của một chuyến, trả về những ai là NGƯỜI MỚI.
     *
     * Cần biết ai mới vì thông báo chỉ gửi cho họ: điều hành lưu tour vì đủ thứ lý do, và bắn lại
     * thông báo cho những người vốn đã ở trong chuyến là cách nhanh nhất để họ ngừng đọc thông báo.
     *
     * `sync()` với mảng id thuần **không** đụng tới hàng pivot đã có, nên mốc `accepted_at` của
     * người đang ở lại được giữ nguyên.
     *
     * @param  array<int, int>  $idMoi
     * @return array<int, int>
     */
    private function phanCongVaTraVeNguoiMoi(TourSchedule $schedule, array $idMoi): array
    {
        $truocDo = $schedule->guides()->pluck('users.id')->all();

        $schedule->guides()->sync($idMoi);

        return array_values(array_diff($idMoi, $truocDo));
    }

    /**
     * Báo cho những người vừa được thêm vào chuyến qua form sửa tour.
     *
     * Trước đây đường ghi này im lặng hoàn toàn: điều hành gán người ở form tour thì người được
     * gán không biết gì, trong khi gán ở màn phân công lẻ thì có thông báo. Cùng một việc, hai kết
     * quả khác nhau tùy người bấm vào nút nào.
     *
     * @param  array<int, array<int, int>>  $theoChuyen
     */
    private function baoNguoiMoiDuocPhanCong(array $theoChuyen): void
    {
        foreach ($theoChuyen as $scheduleId => $idMoi) {
            if ($idMoi === []) {
                continue;
            }

            $schedule = TourSchedule::query()->with('tour:id,title')->find($scheduleId);

            if (!$schedule) {
                continue;
            }

            foreach (User::query()->whereIn('id', $idMoi)->get() as $guide) {
                $this->notifier->toiNguoiDung(
                    $guide,
                    Alert::PHAN_CONG,
                    sprintf('Bạn được phân công chuyến #%d', $schedule->getKey()),
                    sprintf(
                        '%s · khởi hành %s',
                        $schedule->tour?->title ?? 'Tour',
                        $schedule->start_date?->format('d/m/Y H:i') ?? 'chưa rõ',
                    ),
                    '/guide/assignments',
                );
            }
        }
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
    /**
     * Đồng bộ lịch trình và điểm dừng theo id, không xóa hết rồi tạo lại.
     *
     * Xóa rồi tạo lại là mất dữ liệu thật: itinerary_checkpoints và passenger_checkins đều
     * cascadeOnDelete, nên chỉ sửa mỗi tiêu đề tour cũng kéo theo mất sạch bản ghi điểm danh
     * của các chuyến đã đi. Khách hàng đã đi rồi mà lịch sử điểm danh biến mất thì không còn
     * căn cứ nào đối chiếu khi có khiếu nại.
     *
     * @param  array<int, array<string, mixed>>  $itineraries
     */
    private function syncItineraries(Tour $tour, array $itineraries): void
    {
        $keptIds = [];

        foreach ($itineraries as $item) {
            $payload = [
                'day_number' => $item['day_number'],
                'title' => $item['title'],
                'start_point' => $item['start_point'] ?? null,
                'end_point' => $item['end_point'] ?? null,
                'route_points' => $item['route_points'] ?? null,
                'rest_stops' => $item['rest_stops'] ?? null,
                'content' => $item['content'],
            ];

            // Khớp theo id nếu client gửi. Không có id thì khớp theo số ngày, vì mỗi tour chỉ
            // có một lịch trình cho mỗi ngày nên đó là khóa tự nhiên.
            $itinerary = isset($item['id'])
                ? $tour->itineraries()->whereKey($item['id'])->first()
                : $tour->itineraries()->where('day_number', $item['day_number'])->first();

            if ($itinerary) {
                $itinerary->update($payload);
            } else {
                $itinerary = $tour->itineraries()->create($payload);
            }

            $keptIds[] = $itinerary->id;

            $this->syncCheckpoints($itinerary, $item['checkpoints'] ?? []);
        }

        // Chỉ xóa ngày nào không còn trong payload.
        $tour->itineraries()->whereKeyNot($keptIds)->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $checkpoints
     */
    private function syncCheckpoints(TourItinerary $itinerary, array $checkpoints): void
    {
        $keptIds = [];

        foreach ($checkpoints as $checkpoint) {
            $payload = [
                'name' => $checkpoint['name'],
                'description' => $checkpoint['description'] ?? null,
                'latitude' => $checkpoint['latitude'] ?? null,
                'longitude' => $checkpoint['longitude'] ?? null,
                'sequence' => $checkpoint['sequence'],
                'is_required_photo' => $checkpoint['is_required_photo'] ?? false,
            ];

            $existing = isset($checkpoint['id'])
                ? $itinerary->checkpoints()->whereKey($checkpoint['id'])->first()
                : null;

            if ($existing) {
                $existing->update($payload);
                $keptIds[] = $existing->id;

                continue;
            }

            $keptIds[] = $itinerary->checkpoints()->create($payload)->id;
        }

        $itinerary->checkpoints()->whereKeyNot($keptIds)->delete();
    }

    /** Chuyến còn ở giai đoạn bán, tức trạng thái của nó do biểu mẫu tour quyết được. */
    private function conDangBan(TourSchedule $schedule): bool
    {
        return in_array($schedule->status, [ScheduleStatus::Open, ScheduleStatus::Closed], true);
    }

    /**
     * Biểu mẫu có thực sự đổi thông tin vận hành của chuyến đã khóa này không.
     *
     * Chỉ cần trường CÓ MẶT là chặn thì sai: biểu mẫu gửi lại toàn bộ giá trị nó đọc được lúc mở
     * ra, kể cả của những chuyến người dùng không đụng tới. Sửa một dòng mô tả tour cũng bị từ chối,
     * kèm một thông báo nói về chuyến nào đó đã kết thúc từ tháng trước.
     *
     * @param  array<string, mixed>  $item
     */
    private function coDoiThongTinVanHanh(TourSchedule $schedule, array $item): bool
    {
        $minMoi = $item['min_people'] ?? null;

        if ($minMoi !== null && (int) $minMoi !== (int) $schedule->min_people) {
            return true;
        }

        /*
         * Hai mốc điều khiển vòng đời cũng nằm trong khóa.
         *
         * Trước đây phép này chỉ soi `min_people` và `booking_deadline`, trong khi `$payload` bên
         * dưới ghi cả `start_date` lẫn `end_date` không điều kiện. Nên biểu mẫu tour dời được ngày
         * khởi hành của một chuyến ĐANG CHẠY mà không gì chặn — thông báo khóa nói "không sửa được
         * thông tin chuyến" nhưng phạm vi thi hành hẹp hơn hẳn lời nói.
         *
         * Hai mốc này không phải trường mô tả: `start_date` là mốc mọi phép tính phí hủy đếm
         * ngược tới, còn `end_date` quyết định lúc nào chuyến đóng và đơn được chốt. Sửa chúng sau
         * khi đoàn đã lên đường là viết lại quá khứ.
         *
         * `arrival_at` và `return_departure_at` cố ý ĐỨNG NGOÀI khóa: chúng chỉ là giờ dự kiến cho
         * khách đọc, không dòng nào ra quyết định dựa vào chúng, và đính chính giờ tới nơi giữa
         * chuyến khi xe kẹt đường là việc nên làm chứ không nên chặn.
         */
        if ($this->doiMoc($schedule->start_date, $item['start_date'] ?? null)) {
            return true;
        }

        // Chỉ so khi biểu mẫu thực sự gửi lên: thiếu trường thì mốc kết thúc được suy lại từ số
        // ngày của tour, và so một giá trị suy ra với giá trị đã lưu sẽ báo đổi trong khi không ai đổi.
        if (array_key_exists('end_date', $item) && $this->doiMoc($schedule->end_date, $item['end_date'])) {
            return true;
        }

        if (! array_key_exists('booking_deadline', $item)) {
            return false;
        }

        $hanMoi = $item['booking_deadline'] !== null ? Carbon::parse($item['booking_deadline']) : null;
        $hanCu = $schedule->booking_deadline;

        if ($hanMoi === null || $hanCu === null) {
            return $hanMoi !== $hanCu;
        }

        return ! $hanMoi->equalTo($hanCu);
    }

    /**
     * Một mốc đã lưu có khác thứ biểu mẫu gửi lên không.
     *
     * So bằng `equalTo` chứ không so chuỗi: biểu mẫu gửi "2026-09-09T06:00" còn cột lưu
     * "2026-09-09 06:00:00" — cùng một thời điểm, khác cách viết, và so chuỗi sẽ báo đổi mỗi lần
     * lưu dù không ai chạm vào ô ấy.
     */
    private function doiMoc(?Carbon $cu, mixed $moi): bool
    {
        $mocMoi = ($moi === null || $moi === '') ? null : Carbon::parse($moi);

        if ($cu === null || $mocMoi === null) {
            return ($cu === null) !== ($mocMoi === null);
        }

        return ! $cu->equalTo($mocMoi);
    }

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
        /*
         * Không còn hủy chuyến ở đây.
         *
         * Hủy chuyến không phải một lần đổi trạng thái: nó chạm tới tiền của từng khách và bắt
         * buộc có bước gán phương án cho từng đơn đã thanh toán. Trước đây endpoint này nhận
         * 'cancelled', ghi trạng thái rồi kết thúc - đơn của khách không ai đụng tới, mà màn hình
         * lại trông như đã xử lý xong.
         *
         * Giữ hai đường hủy, một đường xử lý đơn và một đường không, chính là khuôn của phần lớn
         * lỗi đã gặp ở dự án này. Nên đường này đóng hẳn.
         *
         * Xem AdminScheduleCancellationController và docs/nghiep-vu/04-luong-dieu-hanh.md mục 3.
         */
        $allowedForAdmin = [
            ScheduleStatus::Open->value,
            ScheduleStatus::Closed->value,
            ScheduleStatus::Confirmed->value,
        ];

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', $allowedForAdmin)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'status.in' => 'Hủy chuyến phải đi qua màn hủy chuyến riêng, vì mỗi đơn đã thanh toán '
                . 'cần một phương án cụ thể.',
        ]);

        $toStatus = ScheduleStatus::from($validated['status']);

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

    /**
     * Đặt lại danh sách hướng dẫn viên của một chuyến.
     *
     * Nhận cả danh sách chứ không một người: đoàn đông thì cần nhiều người dẫn. Gửi mảng rỗng
     * nghĩa là bỏ hết phân công.
     */
    public function assignScheduleGuide(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'guide_ids' => ['present', 'array'],
            'guide_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $schedule = TourSchedule::with('tour')->find($id);

        if (! $schedule) {
            return $this->error('Không tìm thấy lịch khởi hành', 404);
        }

        $daSua = $this->scheduleGuides->sync($schedule, $validated['guide_ids']);
        $soNguoi = $daSua->guides->count();

        return $this->success(
            $daSua->load('tour:id,title,number_of_days'),
            $soNguoi === 0
                ? 'Đã bỏ phân công hướng dẫn viên'
                : sprintf('Đã phân công %d hướng dẫn viên cho chuyến này.', $soNguoi),
        );
    }

    /**
     * Chấm cả đội ngũ cho một chuyến: ai phù hợp, ai bị chặn, và vì sao.
     *
     * Trả về **cả người bị chặn**. Giấu đi thì điều hành tìm mãi một cái tên đáng lẽ phải có mà
     * không hiểu vì sao mất; hiện ra kèm lý do thì họ biết phải sửa gì - bỏ người đó khỏi chuyến
     * đang vướng, hoặc đổi ngày.
     */
    public function scheduleGuideSuitability(int $id): JsonResponse
    {
        $schedule = TourSchedule::with(['tour:id,title,number_of_days,end_location', 'tour.categories:id,name', 'guides:id'])
            ->find($id);

        if (! $schedule) {
            return $this->error('Không tìm thấy lịch khởi hành', 404);
        }

        return $this->success(
            $this->guideSuitability->danhGia($schedule),
            'Chấm mức phù hợp của hướng dẫn viên thành công',
        );
    }

    /**
     * Những ai đã từ chối chuyến này và vì sao.
     *
     * Từ chối gỡ người ra khỏi danh sách, nên nhìn vào chuyến chỉ thấy "chưa phân công" mà không
     * biết đã có người trả lời rồi. Đọc lúc điều hành mở hộp thoại xếp người: đúng lúc cần biết
     * ai vừa nói không, để khỏi gán lại đúng người ấy.
     */
    public function scheduleGuideDeclines(int $id): JsonResponse
    {
        $ds = GuideAssignmentDecline::query()
            ->where('tour_schedule_id', $id)
            ->with('guide:id,name')
            ->latest('declined_at')
            ->get()
            ->map(fn (GuideAssignmentDecline $tc) => [
                'id' => $tc->id,
                'guide_id' => $tc->guide_id,
                'guide_name' => $tc->guide?->name,
                'reason' => $tc->reason,
                'declined_at' => $tc->declined_at?->toDateTimeString(),
            ]);

        return $this->success($ds, 'Lấy danh sách từ chối thành công');
    }

    /** @param array<string, mixed> $item */
    private function guideIdsOf(array $item): array
    {
        return collect($item['guide_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Kiểm chồng lịch cho cả loạt chuyến của một tour đang lưu.
     *
     * Phải xét hai phía: lịch đã có trong cơ sở dữ liệu, và các chuyến khác trong chính lần lưu
     * này - vì chúng chưa tồn tại nên truy vấn không thấy được nhau.
     */
    private function validateScheduleGuideAssignments(array $schedules, int $numberOfDays): void
    {
        $tatCaGuideIds = collect($schedules)
            ->flatMap(fn (array $item) => $this->guideIdsOf($item))
            ->unique()
            ->values();

        if ($tatCaGuideIds->isEmpty()) {
            return;
        }

        try {
            $guides = $this->scheduleGuides->assertValidGuides($tatCaGuideIds->all());
        } catch (BusinessRuleException $e) {
            throw ValidationException::withMessages(['schedules' => $e->getMessage()]);
        }

        $khoangNhap = [];

        foreach ($schedules as $index => $schedule) {
            $start = Carbon::parse($schedule['start_date'])->startOfDay();
            $end = $start->copy()->addDays($numberOfDays - 1);

            foreach ($this->guideIdsOf($schedule) as $guideId) {
                $boQua = isset($schedule['id']) ? (int) $schedule['id'] : null;
                $vuong = $this->scheduleGuides->conflictFor($guideId, $start, $end, $boQua);

                if ($vuong) {
                    throw ValidationException::withMessages([
                        'schedules.' . $index . '.guide_ids' => $this->scheduleGuides->moTaTrungLich(
                            $guides[$guideId]->name,
                            $vuong,
                        ),
                    ]);
                }

                foreach ($khoangNhap[$guideId] ?? [] as $daNhap) {
                    if (ScheduleGuideService::overlaps($start, $end, $daNhap['start'], $daNhap['end'])) {
                        throw ValidationException::withMessages([
                            'schedules.' . $index . '.guide_ids' => sprintf(
                                '%s bị trùng với một lịch khởi hành khác trong tour đang lưu.',
                                $guides[$guideId]->name,
                            ),
                        ]);
                    }
                }

                $khoangNhap[$guideId][] = ['start' => $start, 'end' => $end];
            }
        }
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



