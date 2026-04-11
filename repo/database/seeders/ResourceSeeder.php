<?php
namespace Database\Seeders;

use App\Models\Resource;
use App\Models\InventoryLot;
use App\Models\Venue;
use App\Models\VenueTimeSlot;
use App\Models\Department;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $cs = Department::where('code', 'CS')->first();
        $ee = Department::where('code', 'EE')->first();
        $ma = Department::where('code', 'MA')->first();
        $lib = Department::where('code', 'LIB')->first();

        // Equipment resources
        $laptop = Resource::firstOrCreate(['name' => 'Dell Latitude 5540'], [
            'description' => '15-inch laptop for student use', 'resource_type' => 'equipment',
            'category' => 'Computing', 'department_id' => $cs?->id, 'vendor' => 'Dell', 'manufacturer' => 'Dell Inc.',
            'model_number' => 'LAT5540', 'status' => 'active', 'tags' => ['portable', 'popular'],
        ]);
        InventoryLot::firstOrCreate(['lot_number' => 'CS-LAP-001'], [
            'resource_id' => $laptop->id, 'total_quantity' => 10, 'serviceable_quantity' => 10,
            'location' => 'CS Lab A', 'condition' => 'good',
        ]);

        $oscilloscope = Resource::firstOrCreate(['name' => 'Keysight DSOX1204G Oscilloscope'], [
            'description' => '4-channel digital oscilloscope', 'resource_type' => 'equipment',
            'category' => 'Electronics', 'department_id' => $ee?->id, 'vendor' => 'Keysight', 'manufacturer' => 'Keysight Technologies',
            'model_number' => 'DSOX1204G', 'status' => 'active', 'tags' => ['calibrated', 'lab-only', 'fragile'],
        ]);
        InventoryLot::firstOrCreate(['lot_number' => 'EE-OSC-001'], [
            'resource_id' => $oscilloscope->id, 'total_quantity' => 5, 'serviceable_quantity' => 5,
            'location' => 'EE Lab B', 'condition' => 'good',
        ]);

        $camera = Resource::firstOrCreate(['name' => 'Canon EOS R6 Mark II'], [
            'description' => 'Professional mirrorless camera', 'resource_type' => 'equipment',
            'category' => 'Audio/Video', 'department_id' => $ma?->id, 'vendor' => 'Canon', 'manufacturer' => 'Canon Inc.',
            'model_number' => 'EOSR6M2', 'status' => 'active', 'tags' => ['high-value', 'fragile'],
        ]);
        InventoryLot::firstOrCreate(['lot_number' => 'MA-CAM-001'], [
            'resource_id' => $camera->id, 'total_quantity' => 3, 'serviceable_quantity' => 3,
            'location' => 'Media Center', 'condition' => 'new',
        ]);

        $arduino = Resource::firstOrCreate(['name' => 'Arduino Mega 2560 Kit'], [
            'description' => 'Microcontroller development kit', 'resource_type' => 'equipment',
            'category' => 'Electronics', 'department_id' => $ee?->id, 'vendor' => 'Arduino', 'manufacturer' => 'Arduino S.r.l.',
            'model_number' => 'A000067', 'status' => 'active', 'tags' => ['portable', 'popular'],
        ]);
        InventoryLot::firstOrCreate(['lot_number' => 'EE-ARD-001'], [
            'resource_id' => $arduino->id, 'total_quantity' => 20, 'serviceable_quantity' => 20,
            'location' => 'EE Lab A', 'condition' => 'good',
        ]);

        $projector = Resource::firstOrCreate(['name' => 'Epson PowerLite 990U'], [
            'description' => 'WUXGA 3LCD projector', 'resource_type' => 'equipment',
            'category' => 'Audio/Video', 'department_id' => $lib?->id, 'vendor' => 'Epson', 'manufacturer' => 'Seiko Epson Corporation',
            'model_number' => 'V11H867020', 'status' => 'active', 'tags' => ['portable'],
        ]);
        InventoryLot::firstOrCreate(['lot_number' => 'LIB-PRJ-001'], [
            'resource_id' => $projector->id, 'total_quantity' => 4, 'serviceable_quantity' => 4,
            'location' => 'Library AV Room', 'condition' => 'good',
        ]);

        // Venue resources
        $studioResource = Resource::firstOrCreate(['name' => 'Recording Studio A'], [
            'description' => 'Professional recording studio with sound isolation', 'resource_type' => 'venue',
            'category' => 'Studio', 'department_id' => $ma?->id, 'status' => 'active',
        ]);
        $studio = Venue::firstOrCreate(['resource_id' => $studioResource->id], [
            'capacity' => 6, 'location' => 'Media Arts Building, Room 201',
            'building' => 'Media Arts', 'floor' => '2',
            'amenities' => ['soundproofing', 'mixing_console', 'microphones', 'monitors'],
        ]);

        $labResource = Resource::firstOrCreate(['name' => 'Computer Lab C'], [
            'description' => 'Computer lab with 30 workstations', 'resource_type' => 'venue',
            'category' => 'Venue', 'department_id' => $cs?->id, 'status' => 'active',
        ]);
        $lab = Venue::firstOrCreate(['resource_id' => $labResource->id], [
            'capacity' => 30, 'location' => 'CS Building, Room 110',
            'building' => 'Computer Science', 'floor' => '1',
            'amenities' => ['projector', 'whiteboard', 'workstations'],
        ]);

        // Create some time slots for venues
        $dates = [now()->addDays(1)->format('Y-m-d'), now()->addDays(2)->format('Y-m-d'), now()->addDays(3)->format('Y-m-d')];
        $timeSlots = [['09:00:00', '11:00:00'], ['11:00:00', '13:00:00'], ['14:00:00', '16:00:00'], ['16:00:00', '18:00:00']];

        foreach ([$studio, $lab] as $venue) {
            foreach ($dates as $date) {
                foreach ($timeSlots as [$start, $end]) {
                    VenueTimeSlot::firstOrCreate([
                        'venue_id' => $venue->id, 'date' => $date, 'start_time' => $start, 'end_time' => $end,
                    ]);
                }
            }
        }
    }
}
