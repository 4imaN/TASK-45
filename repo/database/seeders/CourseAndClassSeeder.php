<?php
namespace Database\Seeders;

use App\Models\Course;
use App\Models\ClassModel;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\User;
use App\Models\PermissionScope;
use Illuminate\Database\Seeder;

class CourseAndClassSeeder extends Seeder
{
    public function run(): void
    {
        $csDept = Department::where('code', 'CS')->first();
        $eeDept = Department::where('code', 'EE')->first();
        $maDept = Department::where('code', 'MA')->first();

        $cs101 = Course::firstOrCreate(['code' => 'CS101'], ['name' => 'Intro to Computer Science', 'department_id' => $csDept?->id]);
        $ee201 = Course::firstOrCreate(['code' => 'EE201'], ['name' => 'Circuit Analysis', 'department_id' => $eeDept?->id]);
        $ma150 = Course::firstOrCreate(['code' => 'MA150'], ['name' => 'Digital Media Production', 'department_id' => $maDept?->id]);

        $cs101a = ClassModel::firstOrCreate(
            ['course_id' => $cs101->id, 'section' => 'A', 'semester' => 'Fall', 'year' => 2024],
            ['name' => 'CS101-A Fall 2024']
        );

        $ee201a = ClassModel::firstOrCreate(
            ['course_id' => $ee201->id, 'section' => 'A', 'semester' => 'Fall', 'year' => 2024],
            ['name' => 'EE201-A Fall 2024']
        );

        $ma150a = ClassModel::firstOrCreate(
            ['course_id' => $ma150->id, 'section' => 'A', 'semester' => 'Fall', 'year' => 2024],
            ['name' => 'MA150-A Fall 2024']
        );

        Assignment::firstOrCreate(
            ['class_id' => $cs101a->id, 'name' => 'Lab 1: Hardware Basics'],
            ['description' => 'Identify and catalog computer hardware components']
        );

        Assignment::firstOrCreate(
            ['class_id' => $ee201a->id, 'name' => 'Lab 1: Oscilloscope Training'],
            ['description' => 'Learn to use digital oscilloscopes']
        );

        // Scope teacher to courses
        $teacher = User::where('username', 'teacher')->first();
        if ($teacher) {
            foreach ([$cs101, $ee201] as $course) {
                PermissionScope::firstOrCreate([
                    'user_id' => $teacher->id,
                    'course_id' => $course->id,
                    'scope_type' => 'course',
                ]);
            }
        }

        // Scope TA to specific class
        $ta = User::where('username', 'ta')->first();
        if ($ta) {
            PermissionScope::firstOrCreate([
                'user_id' => $ta->id,
                'class_id' => $cs101a->id,
                'scope_type' => 'class',
            ]);
        }

        // Scope student to courses
        $student = User::where('username', 'student')->first();
        if ($student) {
            PermissionScope::firstOrCreate([
                'user_id' => $student->id,
                'course_id' => $cs101->id,
                'scope_type' => 'course',
            ]);
        }
    }
}
