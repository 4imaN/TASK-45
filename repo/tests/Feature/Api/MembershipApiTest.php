<?php
namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\{MembershipTier, EntitlementPackage, EntitlementGrant};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class MembershipApiTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_view_membership(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $response = $this->actingAs($student)->getJson('/api/memberships/me');
        $response->assertOk()->assertJsonStructure(['membership', 'points_balance', 'stored_value_cents']);
    }

    public function test_list_tiers(): void
    {
        MembershipTier::create(['name' => 'Basic', 'description' => 'B', 'max_active_loans' => 2, 'max_loan_days' => 7, 'max_renewals' => 1]);
        $student = $this->createStudent();
        $response = $this->actingAs($student)->getJson('/api/memberships/tiers');
        $response->assertOk();
    }

    public function test_redeem_points(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        // Add points directly
        \App\Models\PointsLedger::create([
            'user_id' => $student->id, 'points' => 100, 'balance_after' => 100,
            'transaction_type' => 'earned', 'description' => 'Test',
        ]);

        $response = $this->actingAs($student)->postJson('/api/memberships/redeem-points', [
            'points' => 50, 'description' => 'Test redeem',
        ], ['X-Idempotency-Key' => 'test-redeem-points-1']);
        $response->assertOk()->assertJsonPath('balance', 50);
    }

    public function test_consume_entitlement(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $package = EntitlementPackage::create([
            'name' => 'Test Package', 'description' => 'Test', 'quantity' => 10,
            'unit' => 'hours', 'validity_days' => 60, 'price_in_cents' => 0,
        ]);
        $grant = EntitlementGrant::create([
            'user_id' => $student->id, 'package_id' => $package->id,
            'remaining_quantity' => 10, 'granted_at' => now(), 'expires_at' => now()->addDays(60),
        ]);

        $response = $this->actingAs($student)->postJson("/api/memberships/entitlements/{$grant->id}/consume", ['quantity' => 2], ['X-Idempotency-Key' => 'test-consume-entitlement-1']);
        $response->assertOk()->assertJsonPath('remaining', 8);
    }
}
