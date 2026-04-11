<?php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'display_name' => fake()->name(),
            'password' => Hash::make('password'),
            'phone' => fake()->phoneNumber(),
            'force_password_change' => false,
            'account_status' => 'active',
        ];
    }

    public function admin(): static { return $this->state(fn() => ['username' => 'test_admin_' . Str::random(4)]); }
    public function teacher(): static { return $this->state(fn() => ['username' => 'test_teacher_' . Str::random(4)]); }
    public function student(): static { return $this->state(fn() => ['username' => 'test_student_' . Str::random(4)]); }
    public function forcePasswordChange(): static { return $this->state(fn() => ['force_password_change' => true]); }
    public function suspended(): static { return $this->state(fn() => ['account_status' => 'suspended']); }
}
