<?php

namespace Tests\Feature\Security;

use App\Models\Hold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;
use Tests\TestCase;

/**
 * Direct coverage for middleware the audit flagged as untested:
 *   - ForcePasswordChange     (app/Http/Middleware/ForcePasswordChange.php)
 *   - CheckHold               (app/Http/Middleware/CheckHold.php)
 *   - RequestFrequencyGuard   (app/Http/Middleware/RequestFrequencyGuard.php)
 *
 * The IdempotencyMiddleware is already covered in IdempotencyTest.php.
 */
class MiddlewareTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // ================================================================
    // ForcePasswordChange
    // ================================================================

    public function test_force_password_change_blocks_normal_routes(): void
    {
        $student = $this->createStudent(['force_password_change' => true]);
        $response = $this->actingAs($student)->getJson('/api/catalog');
        $response->assertForbidden()
            ->assertJsonPath('force_password_change', true);
    }

    public function test_force_password_change_allows_change_password_route(): void
    {
        $student = $this->createStudent([
            'force_password_change' => true,
            'password' => bcrypt('OldPass1!'),
        ]);

        $response = $this->actingAs($student)->postJson(
            '/api/auth/change-password',
            ['current_password' => 'OldPass1!', 'new_password' => 'NewPass1!'],
            ['X-Idempotency-Key' => 'chg-' . uniqid()],
        );
        // The route itself runs (the middleware lets it through); verify we got
        // something other than the 403 gate response.
        $this->assertNotSame(
            ['force_password_change' => true],
            array_intersect_key($response->json() ?? [], ['force_password_change' => true]),
            'ForcePasswordChange middleware should not block /api/auth/change-password',
        );
    }

    public function test_force_password_change_allows_logout(): void
    {
        $student = $this->createStudent(['force_password_change' => true]);

        $response = $this->actingAs($student)->postJson(
            '/api/auth/logout',
            [],
            ['X-Idempotency-Key' => 'lo-' . uniqid()],
        );
        $response->assertOk();
    }

    public function test_force_password_change_inactive_when_flag_false(): void
    {
        $student = $this->createStudent(['force_password_change' => false]);
        $this->actingAs($student)->getJson('/api/catalog')->assertOk();
    }

    // ================================================================
    // CheckHold
    // ================================================================

    public function test_check_hold_blocks_loan_creation_when_user_has_active_hold(): void
    {
        $student = $this->createStudent();
        Hold::create([
            'user_id' => $student->id, 'hold_type' => 'manual',
            'reason' => 'Policy review', 'status' => 'active', 'triggered_at' => now(),
        ]);
        [$resource] = $this->createResourceWithLot();

        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1,
            'idempotency_key' => 'held-' . uniqid(),
        ], ['X-Idempotency-Key' => 'held-' . uniqid()]);

        $response->assertForbidden()
            ->assertJsonPath('error', 'Your account has an active hold. Contact an administrator.');
    }

    public function test_check_hold_does_not_block_when_hold_is_released(): void
    {
        $student = $this->createStudent();
        Hold::create([
            'user_id' => $student->id, 'hold_type' => 'manual',
            'reason' => 'Closed', 'status' => 'released', 'triggered_at' => now(),
        ]);
        // GET endpoints aren't gated by CheckHold in the first place; but we use
        // one that IS (POST /loans) to prove release removes the block.
        [$resource] = $this->createResourceWithLot();
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1,
            'idempotency_key' => 'rel-' . uniqid(),
        ], ['X-Idempotency-Key' => 'rel-' . uniqid()]);
        // Any non-403 is proof the middleware didn't short-circuit; the
        // downstream controller may 201/422 depending on business rules.
        $this->assertNotSame(403, $response->status(), 'CheckHold should let released users through');
    }

    // ================================================================
    // RequestFrequencyGuard — triggers a hold after N state-changing requests
    // ================================================================

    public function test_request_frequency_guard_creates_hold_after_threshold(): void
    {
        $student = $this->createStudent();
        // Seed a membership + a resource the student can lend.
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 20, 'total_quantity' => 20]);

        // The threshold is 5. Fire 5 identical-shape (but unique-key) POSTs.
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($student)->postJson('/api/loans', [
                'resource_id' => $resource->id, 'quantity' => 1,
                'idempotency_key' => 'freq-ok-' . $i,
            ], ['X-Idempotency-Key' => 'freq-ok-' . $i]);
        }

        // The 6th should be rejected by the frequency guard, not the lending
        // controller — and it should create a 'frequency' hold as a side effect.
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1,
            'idempotency_key' => 'freq-blocked-' . uniqid(),
        ], ['X-Idempotency-Key' => 'freq-blocked-' . uniqid()]);

        $response->assertStatus(429);
        $this->assertDatabaseHas('holds', [
            'user_id' => $student->id,
            'hold_type' => 'frequency',
            'status' => 'active',
        ]);
    }

    public function test_request_frequency_guard_does_not_count_gets(): void
    {
        $student = $this->createStudent();

        // 10 GETs should NOT trip the counter.
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($student)->getJson('/api/catalog')->assertOk();
        }

        // No frequency hold created.
        $this->assertDatabaseMissing('holds', [
            'user_id' => $student->id,
            'hold_type' => 'frequency',
        ]);
    }

    public function test_request_frequency_guard_exempts_auth_endpoints(): void
    {
        $student = $this->createStudent(['password' => bcrypt('X1!aaaa')]);

        // Many auth-path hits should NOT contribute to the frequency counter.
        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/auth/login', [
                'username' => $student->username, 'password' => 'X1!aaaa',
            ], ['X-Idempotency-Key' => 'auth-exempt-' . $i]);
        }

        $this->assertDatabaseMissing('holds', [
            'user_id' => $student->id,
            'hold_type' => 'frequency',
        ]);
    }
}
