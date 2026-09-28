<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationPhoneTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidPhones')]
    public function test_registration_rejects_invalid_phone_without_creating_account(array $phone): void
    {
        $this->postJson('/api/register', [
            'name' => 'Phone Validation Test',
            'email' => 'invalid-phone@example.com',
            'password' => 'password123',
            ...$phone,
        ])->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->assertDatabaseMissing('users', ['email' => 'invalid-phone@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public static function invalidPhones(): array
    {
        return [
            'missing' => [[]],
            'null' => [['phone' => null]],
            'empty' => [['phone' => '']],
            'whitespace' => [['phone' => '   ']],
            'letters' => [['phone' => 'abcdefghi']],
            'mixed text' => [['phone' => '0901234567abc']],
            'too short' => [['phone' => '1234567']],
            'too long' => [['phone' => str_repeat('1', 21)]],
            'misplaced plus' => [['phone' => '090+1234567']],
            'punctuation' => [['phone' => '0901234567!']],
            'array' => [['phone' => ['0901234567']]],
            'number' => [['phone' => 901234567]],
            'boolean' => [['phone' => true]],
        ];
    }

    #[DataProvider('validPhones')]
    public function test_registration_saves_valid_phone_and_returns_it(string $phone): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Phone Validation Test',
            'email' => 'valid-phone@example.com',
            'phone' => $phone,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('user.phone', $phone)->assertJsonPath('user.role', 'customer');

        $this->assertSame($phone, User::where('email', 'valid-phone@example.com')->value('phone'));
        $this->assertNotEmpty($response->json('token'));
    }

    public static function validPhones(): array
    {
        return [
            ['0901234567'],
            ['+84901234567'],
            ['090 123 4567'],
            ['+1-202-555-0123'],
        ];
    }
}
