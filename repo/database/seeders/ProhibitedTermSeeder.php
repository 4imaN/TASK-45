<?php
namespace Database\Seeders;

use App\Models\ProhibitedTerm;
use Illuminate\Database\Seeder;

class ProhibitedTermSeeder extends Seeder
{
    public function run(): void
    {
        $terms = [
            ['term' => 'classified', 'severity' => 'block'],
            ['term' => 'confidential', 'severity' => 'warn'],
            ['term' => 'restricted-export', 'severity' => 'block'],
            ['term' => 'hazardous-untested', 'severity' => 'block'],
        ];
        foreach ($terms as $t) {
            ProhibitedTerm::firstOrCreate(['term' => $t['term']], $t);
        }
    }
}
