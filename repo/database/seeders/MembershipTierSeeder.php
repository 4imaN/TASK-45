<?php
namespace Database\Seeders;

use App\Models\MembershipTier;
use Illuminate\Database\Seeder;

class MembershipTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['name' => 'Basic', 'description' => 'Basic membership', 'max_active_loans' => 2, 'max_loan_days' => 7, 'max_renewals' => 1, 'points_multiplier' => 1.00],
            ['name' => 'Plus', 'description' => 'Plus membership with extended privileges', 'max_active_loans' => 4, 'max_loan_days' => 14, 'max_renewals' => 2, 'points_multiplier' => 1.50],
            ['name' => 'Premium', 'description' => 'Premium membership with full privileges', 'max_active_loans' => 6, 'max_loan_days' => 21, 'max_renewals' => 3, 'points_multiplier' => 2.00],
        ];
        foreach ($tiers as $tier) {
            MembershipTier::firstOrCreate(['name' => $tier['name']], $tier);
        }
    }
}
