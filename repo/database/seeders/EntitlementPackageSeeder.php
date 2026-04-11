<?php
namespace Database\Seeders;

use App\Models\EntitlementPackage;
use App\Models\MembershipTier;
use Illuminate\Database\Seeder;

class EntitlementPackageSeeder extends Seeder
{
    public function run(): void
    {
        $plus = MembershipTier::where('name', 'Plus')->first();
        $premium = MembershipTier::where('name', 'Premium')->first();

        EntitlementPackage::firstOrCreate(['name' => '10 Hours Studio Access'], [
            'description' => '10 hours of recording studio access, valid for 60 days',
            'tier_id' => $plus?->id, 'resource_type' => 'venue', 'quantity' => 10,
            'unit' => 'hours', 'validity_days' => 60, 'price_in_cents' => 5000,
        ]);

        EntitlementPackage::firstOrCreate(['name' => '5 Equipment Checkouts'], [
            'description' => '5 additional equipment checkout slots', 'tier_id' => null,
            'resource_type' => 'equipment', 'quantity' => 5, 'unit' => 'uses',
            'validity_days' => 90, 'price_in_cents' => 2500,
        ]);

        EntitlementPackage::firstOrCreate(['name' => 'Unlimited Lab Access'], [
            'description' => 'Unlimited computer lab access for 30 days',
            'tier_id' => $premium?->id, 'resource_type' => 'venue', 'quantity' => 999,
            'unit' => 'hours', 'validity_days' => 30, 'price_in_cents' => 10000,
        ]);
    }
}
