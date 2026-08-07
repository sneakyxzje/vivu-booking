<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_tra_ve_thong_ke_booking_va_doanh_thu(): void
    {
        $admin = User::create([
            'name' => 'Admin Dashboard',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'booking_summary' => [
                        'total_bookings',
                        'confirmed_bookings',
                        'total_revenue',
                        'revenue_this_month',
                        'occupancy_rate',
                    ],
                    'monthly_performance',
                    'destinations',
                    'recent_bookings',
                ],
            ]);
    }
}
