<?php
namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Computer Science', 'code' => 'CS', 'description' => 'Computer Science Department'],
            ['name' => 'Electrical Engineering', 'code' => 'EE', 'description' => 'Electrical Engineering Department'],
            ['name' => 'Media Arts', 'code' => 'MA', 'description' => 'Media Arts and Design Department'],
            ['name' => 'Library', 'code' => 'LIB', 'description' => 'University Library'],
            ['name' => 'Physics', 'code' => 'PHY', 'description' => 'Physics Department'],
        ];
        foreach ($departments as $dept) {
            Department::firstOrCreate(['code' => $dept['code']], $dept);
        }
    }
}
