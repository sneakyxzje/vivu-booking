<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Danh sách tài khoản, và cái khóa.
 *
 * Hai điểm cuối này tồn tại từ lâu nhưng chỉ là chỗ trống trả về `[]` kèm chữ "Placeholder" —
 * tức là điều hành không có đường nào để khóa một tài khoản đang quấy phá ngoài việc mở thẳng cơ
 * sở dữ liệu.
 *
 * ## Khóa chứ không xóa
 *
 * `status = blocked` làm token hiện có mất tác dụng ngay (xem `RoleMiddleware`) và chặn đăng nhập
 * mới, nhưng giữ nguyên mọi đơn hàng, đánh giá và chứng từ gắn với người đó. Xóa tài khoản của
 * một người từng đặt tour là cắt đứt lịch sử giao dịch mà công ty có nghĩa vụ giữ.
 *
 * ## Hai điều không cho phép
 *
 * Tự khóa chính mình, và khóa người quản trị cuối cùng còn hoạt động. Cả hai đều dẫn tới cùng một
 * kết cục: không ai vào được khu điều hành nữa, và không có đường nào trong ứng dụng để sửa.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['admin', 'guide', 'customer'])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'blocked'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->when($filters['q'] ?? null, function ($query, string $keyword) {
                $keyword = trim($keyword);
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20);

        /*
         * Số đơn của từng khách, lấy một lượt cho cả trang.
         *
         * Đây là con số quyết định người bấm có dám khóa hay không: khóa một tài khoản có bốn đơn
         * đang chờ khởi hành là chuyện khác hẳn khóa một tài khoản chưa đặt gì.
         *
         * Không dùng `withCount` vì quan hệ `bookings` chưa được khai trên `User`, và khai thêm
         * một quan hệ chỉ để đếm ở đúng một màn hình thì đắt hơn một truy vấn gộp.
         */
        $ids = collect($users->items())->pluck('id');
        $soDon = Booking::query()
            ->whereIn('customer_id', $ids)
            ->selectRaw('customer_id, count(*) as tong')
            ->groupBy('customer_id')
            ->pluck('tong', 'customer_id');

        $users->getCollection()->transform(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
            'avatar' => $user->avatar,
            'bookings_count' => (int) ($soDon[$user->id] ?? 0),
            'created_at' => $user->created_at?->toDateTimeString(),
        ]);

        return $this->success($users->toArray() + [
            'counts' => [
                'admin' => User::query()->where('role', 'admin')->count(),
                'guide' => User::query()->where('role', 'guide')->count(),
                'customer' => User::query()->where('role', 'customer')->count(),
                'blocked' => User::query()->where('status', 'blocked')->count(),
            ],
        ], 'Lấy danh sách tài khoản thành công');
    }

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (!$user) {
            return $this->error('Không tìm thấy tài khoản', 404);
        }

        if ($user->id === $request->user()->id) {
            return $this->error(
                'Không tự khóa tài khoản của chính mình được — khóa xong thì không còn đường nào '
                . 'trong ứng dụng để mở lại.',
                422,
            );
        }

        $dangKhoa = $user->status === 'active';

        if ($dangKhoa && $user->role === 'admin') {
            $conLai = User::query()
                ->where('role', 'admin')
                ->where('status', 'active')
                ->whereKeyNot($user->id)
                ->count();

            if ($conLai === 0) {
                return $this->error(
                    'Đây là tài khoản quản trị đang hoạt động cuối cùng. Khóa nó là không ai vào '
                    . 'được khu điều hành nữa. Tạo hoặc mở lại một tài khoản quản trị khác trước.',
                    422,
                );
            }
        }

        $user->forceFill(['status' => $dangKhoa ? 'blocked' : 'active'])->save();

        /*
         * Khóa thì thu hồi luôn token đang có.
         *
         * `RoleMiddleware` đã chặn tài khoản không `active` ở mọi tuyến có phân vai, nhưng token
         * vẫn nằm trong trình duyệt người đó và vẫn qua được `auth:sanctum` ở các tuyến dùng
         * chung như `/api/me`. Xóa token là cắt dứt điểm thay vì dựa vào việc mọi tuyến đều nhớ
         * kiểm tra trạng thái.
         */
        if ($dangKhoa) {
            $user->tokens()->delete();
        }

        return $this->success([
            'id' => $user->id,
            'status' => $user->status,
        ], $dangKhoa
            ? sprintf('Đã khóa tài khoản %s. Mọi phiên đăng nhập của họ đã bị thu hồi.', $user->email)
            : sprintf('Đã mở lại tài khoản %s.', $user->email));
    }
}
