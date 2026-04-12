<?php
namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\PermissionScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapAccountSeeder extends Seeder
{
    public function run(): void
    {
        $runtimeDir = storage_path('app/private/runtime');
        if (!is_dir($runtimeDir)) {
            mkdir($runtimeDir, 0755, true);
        }

        $credFile = $runtimeDir . '/bootstrap_credentials.json';

        // Check both file AND database state — regenerate if either is missing
        $dbHasUsers = User::whereIn('username', ['admin', 'teacher', 'ta', 'student'])->count() >= 4;
        if (file_exists($credFile) && $dbHasUsers) {
            return;
        }

        $accounts = [
            ['username' => 'admin', 'display_name' => 'System Administrator', 'role' => 'admin', 'password' => 'Admin123!'],
            ['username' => 'teacher', 'display_name' => 'Demo Teacher', 'role' => 'teacher', 'password' => 'Teacher123!'],
            ['username' => 'ta', 'display_name' => 'Demo Teaching Assistant', 'role' => 'ta', 'password' => 'TA123!'],
            ['username' => 'student', 'display_name' => 'Demo Student', 'role' => 'student', 'password' => 'Student123!'],
        ];

        $credentials = [];

        foreach ($accounts as $account) {
            $password = $account['password'];

            $user = User::firstOrCreate(
                ['username' => $account['username']],
                [
                    'display_name' => $account['display_name'],
                    'password' => Hash::make($password),
                    'force_password_change' => false,
                    'account_status' => 'active',
                ]
            );

            // If user already existed but cred file was missing, reset to match
            if (!$user->wasRecentlyCreated) {
                $user->update([
                    'password' => Hash::make($password),
                    'force_password_change' => true,
                ]);
            }

            $role = Role::where('name', $account['role'])->first();
            if ($role && !$user->roles()->where('role_id', $role->id)->exists()) {
                $user->roles()->attach($role->id);
            }

            // Give admin full scope
            if ($account['role'] === 'admin') {
                PermissionScope::firstOrCreate([
                    'user_id' => $user->id,
                    'scope_type' => 'full',
                ]);
            }

            // Assign Basic tier membership to student
            if ($account['role'] === 'student') {
                $basicTier = MembershipTier::where('name', 'Basic')->first();
                if ($basicTier) {
                    Membership::firstOrCreate(
                        ['user_id' => $user->id],
                        ['tier_id' => $basicTier->id, 'status' => 'active', 'started_at' => now(), 'expires_at' => now()->addYear()]
                    );
                }
            }

            $credentials[] = [
                'username' => $account['username'],
                'password' => $password,
                'role' => $account['role'],
            ];
        }

        file_put_contents($credFile, json_encode($credentials, JSON_PRETTY_PRINT));
        chmod($credFile, 0600);
    }
}
