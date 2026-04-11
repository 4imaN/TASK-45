<?php
namespace Tests\Unit\Domain;

use Tests\TestCase;
use App\Domain\Membership\StoredValueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class StoredValueServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected StoredValueService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StoredValueService::class);
    }

    public function test_deposit(): void
    {
        $user = $this->createStudent();
        $entry = $this->service->deposit($user, 10000, 'Initial deposit');
        $this->assertEquals(10000, $entry->balance_after_cents);
    }

    public function test_redeem(): void
    {
        $user = $this->createStudent();
        $this->service->deposit($user, 10000, 'Deposit');
        $entry = $this->service->redeem($user, 5000, 'Redeem', uniqid());
        $this->assertEquals(5000, $entry->balance_after_cents);
    }

    public function test_cannot_redeem_more_than_balance(): void
    {
        $user = $this->createStudent();
        $this->service->deposit($user, 5000, 'Deposit');
        $this->expectException(\App\Common\Exceptions\BusinessRuleException::class);
        $this->service->redeem($user, 10000, 'Over-redeem', uniqid());
    }

    public function test_high_value_redemption_triggers_hold(): void
    {
        $user = $this->createStudent();
        $this->service->deposit($user, 50000, 'Large deposit');
        $this->expectException(\App\Common\Exceptions\BusinessRuleException::class);
        $this->expectExceptionMessage('High-value redemption');
        $this->service->redeem($user, 25000, 'Large redemption', uniqid()); // $250 > $200 threshold
    }

    public function test_high_frequency_triggers_hold(): void
    {
        $user = $this->createStudent();
        $this->service->deposit($user, 100000, 'Large deposit');

        // Make 5 quick redemptions
        for ($i = 0; $i < 5; $i++) {
            $this->service->redeem($user, 100, "Redeem $i", uniqid());
        }

        $this->expectException(\App\Common\Exceptions\BusinessRuleException::class);
        $this->expectExceptionMessage('Too many redemptions');
        $this->service->redeem($user, 100, 'One too many', uniqid());
    }

    public function test_cannot_redeem_with_active_hold(): void
    {
        $user = $this->createStudent();
        $this->service->deposit($user, 10000, 'Deposit');
        \App\Models\Hold::create([
            'user_id' => $user->id, 'hold_type' => 'manual', 'reason' => 'Test hold',
            'status' => 'active', 'triggered_at' => now(),
        ]);
        $this->expectException(\App\Common\Exceptions\BusinessRuleException::class);
        $this->expectExceptionMessage('active holds');
        $this->service->redeem($user, 100, 'Test', uniqid());
    }
}
