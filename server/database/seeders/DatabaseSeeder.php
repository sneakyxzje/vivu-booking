<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'customer@gmail.com'],
            [
                'name' => 'Customer User',
                'password' => Hash::make('customer123'),
                'phone' => '0901234567',
                'address' => 'TP. Ho Chi Minh',
                'role' => 'customer',
                'status' => 'active',
            ]
        );

        $this->call([
            SampleTourSeeder::class,
        ]);
    }
}
