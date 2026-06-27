<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TourResource;
use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\Service;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboardData(Request $request): JsonResponse
    {
        $summary = [
            'pending_tours' => Tour::where('status', 'pending')->count(),
            'active_tours' => Tour::where('status', 'active')->count(),
            'inactive_tours' => Tour::where('status', 'inactive')->count(),
            'total_tours' => Tour::count(),
            'featured_tours' => Tour::where('is_featured', true)->count(),
            'new_users_this_week' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'total_customers' => User::where('role', 'customer')->count(),
            'total_guides' => User::where('role', 'guide')->count(),
            'total_categories' => Category::count(),
            'inactive_categories' => Category::where('is_active', false)->count(),
            'total_services' => Service::count(),
            'upcoming_schedules' => TourSchedule::where('start_date', '>=', now()->toDateString())->count(),
            'total_booked_slots' => (int) TourSchedule::sum('booked_people'),
            'canceled_schedules' => TourSchedule::where('status', 'canceled')->count(),
            'completed_schedules' => TourSchedule::where('status', 'completed')->count(),
        ];

        $topSellingTours = Tour::withSum('schedules as total_booked', 'booked_people')
            ->orderByDesc('total_booked')
            ->limit(5)
            ->get();

        if ($topSellingTours->every(fn ($tour) => (int) $tour->total_booked === 0)) {
            $topSellingTours = Tour::where('is_featured', true)
                ->latest()
                ->limit(5)
                ->get();
        }

        $recentPendingTours = Tour::with('host:id,name,email')
            ->where('status', 'pending')
            ->latest()
            ->limit(5)
            ->get();

        $recentUsers = User::latest()->limit(5)->get();

        $topServices = Service::withCount('tours')
            ->orderByDesc('tours_count')
            ->limit(5)
            ->get(['id', 'name', 'icon']);

        $toursByCategory = Category::withCount('tours')
            ->orderByDesc('tours_count')
            ->get(['id', 'name', 'slug', 'is_active']);

        $featuredToursList = Tour::where('is_featured', true)
            ->latest()
            ->limit(5)
            ->get();

        return $this->success([
            'summary' => $summary,
            'top_selling_tours' => TourResource::collection($topSellingTours),
            'recent_pending_tours' => TourResource::collection($recentPendingTours),
            'recent_users' => UserResource::collection($recentUsers),
            'top_services' => $topServices->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'icon' => $service->icon,
                'tours_count' => $service->tours_count,
            ])->values(),
            'tours_by_category' => $toursByCategory->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'is_active' => (bool) $category->is_active,
                'tours_count' => $category->tours_count,
            ])->values(),
            'featured_tours_list' => TourResource::collection($featuredToursList),
        ], 'Lấy dữ liệu dashboard thành công');
    }
}

