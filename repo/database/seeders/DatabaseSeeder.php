<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            MembershipTierSeeder::class,
            DepartmentSeeder::class,
            TaxonomySeeder::class,
            ProhibitedTermSeeder::class,
            BootstrapAccountSeeder::class,
            CourseAndClassSeeder::class,
            ResourceSeeder::class,
            EntitlementPackageSeeder::class,
        ]);
    }
}
