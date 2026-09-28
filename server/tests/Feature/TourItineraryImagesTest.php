<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TourItineraryImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $this->mock(CloudinaryService::class, function ($mock) {
            $mock->shouldReceive('uploadImage')->andReturnUsing(
                fn ($file, $folder) => 'https://example.com/'.$file->getClientOriginalName()
            );
        });
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1sAAAAASUVORK5CYII='
        ));
    }

    private function payload(): array
    {
        return [
            'title' => 'Tour có ảnh từng ngày', 'adult_price' => 1000000, 'child_price' => 700000,
            'infant_price' => 0, 'number_of_days' => 2, 'number_of_nights' => 1,
            'start_location' => 'Hà Nội',
            'itineraries' => [
                ['day_number' => 1, 'title' => 'Ngày 1', 'content' => 'Tham quan vịnh.',
                    'image_files' => [$this->image('day-one.jpg')]],
                ['day_number' => 2, 'title' => 'Ngày 2', 'content' => 'Tham quan đảo.',
                    'image_files' => [$this->image('day-two.jpg')]],
            ],
        ];
    }

    private function createTour(): Tour
    {
        $this->post('/api/admin/tours', $this->payload(), ['Accept' => 'application/json'])->assertSuccessful();
        return Tour::with('itineraries')->firstOrFail();
    }

    private function updatePayload(Tour $tour): array
    {
        return [...$this->payload(), 'itineraries' => $tour->itineraries->toArray()];
    }

    public function test_uploads_are_attached_to_the_correct_day_and_returned_publicly(): void
    {
        $tour = $this->createTour();
        $this->assertSame(['https://example.com/day-one.jpg'], $tour->itineraries[0]->images);
        $this->assertSame(['https://example.com/day-two.jpg'], $tour->itineraries[1]->images);
        $response = $this->getJson('/api/tours/'.$tour->id)->assertOk();
        $this->assertStringContainsString('day-one.jpg', $response->getContent());
        $this->assertStringContainsString('day-two.jpg', $response->getContent());
    }

    public function test_edit_can_replace_remove_and_add_images_without_changing_other_days(): void
    {
        $tour = $this->createTour();
        $payload = $this->updatePayload($tour);
        $payload['_method'] = 'PUT';
        unset($payload['itineraries'][0]['images']);
        $payload['itineraries'][0]['replace_images'] = '1';
        $payload['itineraries'][0]['image_files'] = [$this->image('replacement.png')];
        $this->post('/api/admin/tours/'.$tour->id, $payload, ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(['https://example.com/replacement.png'], $tour->itineraries[0]->fresh()->images);
        $this->assertSame(['https://example.com/day-two.jpg'], $tour->itineraries[1]->fresh()->images);

        $payload['itineraries'][0]['image_files'] = [];
        $this->post('/api/admin/tours/'.$tour->id, $payload, ['Accept' => 'application/json'])->assertOk();
        $this->assertSame([], $tour->itineraries[0]->fresh()->images);
    }

    public function test_text_only_update_preserves_images_for_older_clients(): void
    {
        $tour = $this->createTour();
        $payload = $this->updatePayload($tour);
        foreach ($payload['itineraries'] as &$day) unset($day['images']);
        unset($day);
        $payload['itineraries'][0]['content'] = 'Nội dung đã sửa.';
        $this->putJson('/api/admin/tours/'.$tour->id, $payload)->assertOk();
        $this->assertSame(['https://example.com/day-one.jpg'], $tour->itineraries[0]->fresh()->images);
    }

    public function test_cannot_attach_a_saved_image_from_another_day(): void
    {
        $tour = $this->createTour();
        $payload = $this->updatePayload($tour);
        $payload['itineraries'][0]['images'] = $tour->itineraries[1]->images;
        $this->putJson('/api/admin/tours/'.$tour->id, $payload)->assertUnprocessable();
        $this->assertSame(['https://example.com/day-one.jpg'], $tour->itineraries[0]->fresh()->images);
    }

    public function test_image_type_and_size_are_validated_before_saving(): void
    {
        foreach ([UploadedFile::fake()->create('file.txt', 1, 'text/plain'),
            $this->image('large.jpg')->size(5121)] as $file) {
            $payload = $this->payload();
            $payload['itineraries'][0]['image_files'] = [$file];
            $this->post('/api/admin/tours', $payload, ['Accept' => 'application/json'])
                ->assertUnprocessable()->assertJsonValidationErrors('itineraries.0.image_files.0');
        }
        $this->assertDatabaseCount('tours', 0);
    }

    public function test_limit_counts_saved_and_new_images_together(): void
    {
        $tour = $this->createTour();
        $payload = $this->updatePayload($tour);
        $payload['_method'] = 'PUT';
        $payload['itineraries'][0]['image_files'] = array_map(
            fn ($i) => $this->image("new-{$i}.jpg"), range(1, 8)
        );
        $this->post('/api/admin/tours/'.$tour->id, $payload, ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame(['https://example.com/day-one.jpg'], $tour->itineraries[0]->fresh()->images);
    }
}
