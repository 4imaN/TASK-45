<?php
namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\IdempotencyKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class AuthApiTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_login_success(): void
    {
        $user = $this->createStudent(['password' => bcrypt('TestPass123!')]);
        $response = $this->postJson('/api/auth/login', ['username' => $user->username, 'password' => 'TestPass123!'], ['X-Idempotency-Key' => 'login-' . uniqid()]);
        $response->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_login_wrong_password(): void
    {
        $user = $this->createStudent(['password' => bcrypt('correct')]);
        $response = $this->postJson('/api/auth/login', ['username' => $user->username, 'password' => 'wrong'], ['X-Idempotency-Key' => 'login-' . uniqid()]);
        $response->assertUnprocessable();
    }

    public function test_login_suspended_account(): void
    {
        $user = $this->createStudent(['password' => bcrypt('pass'), 'account_status' => 'suspended']);
        $response = $this->postJson('/api/auth/login', ['username' => $user->username, 'password' => 'pass'], ['X-Idempotency-Key' => 'login-' . uniqid()]);
        $response->assertUnprocessable();
    }

    public function test_login_locks_after_failed_attempts(): void
    {
        $user = $this->createStudent(['password' => bcrypt('correct')]);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['username' => $user->username, 'password' => 'wrong'], ['X-Idempotency-Key' => 'login-' . uniqid()]);
        }
        $user->refresh();
        $this->assertNotNull($user->locked_until);
    }

    public function test_logout(): void
    {
        $user = $this->createStudent();
        $this->actingAs($user)->postJson('/api/auth/logout', [], ['X-Idempotency-Key' => 'logout-test-1'])->assertOk();
    }

    public function test_me_endpoint(): void
    {
        $user = $this->createStudent();
        $this->actingAs($user)->getJson('/api/auth/me')->assertOk()->assertJsonPath('username', $user->username);
    }

    public function test_force_password_change_blocks_other_routes(): void
    {
        $user = $this->createStudent(['force_password_change' => true]);
        $this->actingAs($user)->getJson('/api/catalog')->assertStatus(403)->assertJsonPath('force_password_change', true);
    }

    public function test_change_password(): void
    {
        $user = $this->createStudent(['password' => bcrypt('OldPass123!')]);
        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'current_password' => 'OldPass123!',
            'new_password' => 'NewPass456!',
            'new_password_confirmation' => 'NewPass456!',
        ], ['X-Idempotency-Key' => 'test-change-password-1'])->assertOk();
    }

    public function test_force_password_change_flow(): void
    {
        $user = $this->createStudent(['password' => bcrypt('temp'), 'force_password_change' => true]);
        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'current_password' => 'temp',
            'new_password' => 'NewSecure123!',
            'new_password_confirmation' => 'NewSecure123!',
        ], ['X-Idempotency-Key' => 'test-force-change-password-1'])->assertOk();
        $this->assertFalse($user->fresh()->force_password_change);
    }

    public function test_unauthenticated_access_blocked(): void
    {
        $this->getJson('/api/catalog')->assertUnauthorized();
    }

    public function test_login_idempotency_record_does_not_store_bearer_token(): void
    {
        $user = $this->createStudent(['password' => bcrypt('TestPass123!')]);

        $idempotencyKey = 'login-idem-token-check-' . uniqid();
        $response = $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'TestPass123!',
        ], ['X-Idempotency-Key' => $idempotencyKey]);

        $response->assertOk();
        $token = $response->json('token');
        $this->assertNotEmpty($token);

        // Verify the persisted idempotency record does NOT contain the bearer token
        $record = IdempotencyKey::where('key', $idempotencyKey)->first();
        $this->assertNotNull($record, 'Idempotency record should exist');

        $responseBody = $record->response_body;
        $this->assertIsArray($responseBody);
        $this->assertArrayNotHasKey('token', $responseBody);

        // Also verify the raw JSON doesn't contain the token string
        $rawJson = json_encode($responseBody);
        $this->assertStringNotContainsString($token, $rawJson);
    }
}
