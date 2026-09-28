<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TourItineraryValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
    }

    private function payload(): array
    {
        return [
            'title' => 'Tour 3 ngày', 'adult_price' => 1000000, 'child_price' => 700000,
            'infant_price' => 0, 'number_of_days' => 3, 'number_of_nights' => 2,
            'start_location' => 'Hà Nội',
            'itineraries' => array_map(fn ($day) => [
                'day_number' => $day, 'title' => "Ngày {$day}", 'content' => 'Tham quan và nghỉ ngơi.',
            ], range(1, 3)),
        ];
    }

    public function test_create_rejects_missing_days_without_writing_a_tour(): void
    {
        $payload = $this->payload();
        $payload['itineraries'] = [$payload['itineraries'][0]];
        $this->postJson('/api/admin/tours', $payload)->assertStatus(422)
            ->assertJsonPath('message', 'Chưa có lịch trình ngày 2, 3.');
        $this->assertDatabaseCount('tours', 0);
    }

    public function test_create_rejects_omitted_null_or_empty_itineraries(): void
    {
        $payload = $this->payload();
        unset($payload['itineraries']);
        $this->postJson('/api/admin/tours', $payload)->assertStatus(422);
        foreach ([null, []] as $items) {
            $this->postJson('/api/admin/tours', [...$payload, 'itineraries' => $items])->assertStatus(422);
        }
        $this->assertDatabaseCount('tours', 0);
    }

    public function test_matching_count_cannot_bypass_day_numbers(): void
    {
        foreach ([[1, 1, 3], [1, 3, 4], [0, 2, 3]] as $numbers) {
            $payload = $this->payload();
            foreach ($numbers as $index => $day) $payload['itineraries'][$index]['day_number'] = $day;
            $this->postJson('/api/admin/tours', $payload)->assertStatus(422);
        }
        $this->assertDatabaseCount('tours', 0);
    }

    public function test_each_day_requires_a_nonblank_title_and_content(): void
    {
        foreach (['title', 'content'] as $field) {
            $payload = $this->payload();
            $payload['itineraries'][1][$field] = "  \n ";
            $this->postJson('/api/admin/tours', $payload)->assertStatus(422)
                ->assertJsonValidationErrors("itineraries.1.{$field}");
        }
    }

    public function test_complete_itineraries_can_be_created_and_updated(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/admin/tours', $payload)->assertSuccessful();
        $tour = Tour::firstOrFail();
        $this->assertCount(3, $tour->itineraries);
        $payload['itineraries'] = $tour->itineraries->toArray();
        $payload['itineraries'][1]['content'] = 'Nội dung ngày 2 đã sửa.';
        $this->putJson("/api/admin/tours/{$tour->id}", $payload)->assertOk();
        $this->assertDatabaseHas('tour_itineraries', [
            'id' => $payload['itineraries'][1]['id'], 'content' => 'Nội dung ngày 2 đã sửa.',
        ]);
    }

    public function test_update_cannot_remove_days_or_increase_duration_without_completing_itinerary(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/admin/tours', $payload)->assertSuccessful();
        $tour = Tour::firstOrFail();
        $url = "/api/admin/tours/{$tour->id}";
        $this->putJson($url, [...$payload, 'itineraries' => [$payload['itineraries'][0]]])->assertStatus(422);
        $this->putJson($url, [...$payload, 'number_of_days' => 4])->assertStatus(422)
            ->assertJsonPath('message', 'Chưa có lịch trình ngày 4.');
        $this->putJson($url, [...$payload, 'number_of_days' => 2])->assertStatus(422);
        unset($payload['itineraries']);
        $this->putJson($url, $payload)->assertStatus(422);
        $this->assertSame(3, $tour->fresh()->number_of_days);
        $this->assertSame(3, $tour->itineraries()->count());
    }

    public function test_one_day_tour_requires_only_one_itinerary(): void
    {
        $payload = $this->payload();
        $payload['number_of_days'] = 1;
        $payload['number_of_nights'] = 0;
        $payload['itineraries'] = [$payload['itineraries'][0]];
        $this->postJson('/api/admin/tours', $payload)->assertSuccessful();
        $this->assertDatabaseCount('tour_itineraries', 1);
    }
}
