<?php
namespace Tests\Unit\Domain;

use Tests\TestCase;
use App\Domain\Membership\PointsService;
use App\Models\PointsLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class PointsServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected PointsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PointsService::class);
    }

    public function test_award_points(): void
    {
        $student = $this->createStudent();
        $entry = $this->service->awardPoints($student, 10, 'Test award');
        $this->assertEquals(10, $entry->balance_after);
        $this->assertEquals(10, $this->service->getBalance($student));
    }

    public function test_spend_points(): void
    {
        $student = $this->createStudent();
        $this->service->awardPoints($student, 50, 'Initial');
        $entry = $this->service->spendPoints($student, 20, 'Spend');
        $this->assertEquals(30, $entry->balance_after);
    }

    public function test_cannot_spend_more_than_balance(): void
    {
        $student = $this->createStudent();
        $this->service->awardPoints($student, 10, 'Initial');
        $this->expectException(\App\Common\Exceptions\BusinessRuleException::class);
        $this->service->spendPoints($student, 20, 'Over-spend');
    }

    public function test_points_multiplier_with_membership(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student, 'Premium');
        // Premium has 2.0x multiplier
        \App\Models\MembershipTier::where('name', 'Premium')->update(['points_multiplier' => 2.00]);
        $entry = $this->service->awardPoints($student->fresh()->load('membership.tier'), 10, 'Test');
        $this->assertEquals(20, $entry->balance_after); // 10 * 2.0
    }
}
