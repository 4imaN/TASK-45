<?php
namespace Database\Seeders;

use App\Models\TaxonomyTerm;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['Electronics', 'Lab Equipment', 'Audio/Video', 'Computing', 'Furniture', 'Studio', 'Venue'];
        foreach ($categories as $cat) {
            TaxonomyTerm::firstOrCreate(['type' => 'category', 'value' => $cat]);
        }

        $tags = ['portable', 'fragile', 'high-value', 'calibrated', 'reserved', 'new-arrival', 'popular', 'restricted', 'lab-only', 'outdoor'];
        foreach ($tags as $tag) {
            TaxonomyTerm::firstOrCreate(['type' => 'tag', 'value' => $tag]);
        }
    }
}
