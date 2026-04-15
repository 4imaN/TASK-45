<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Database\Seeders\BootstrapAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class BootstrapAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    protected string $credFile;

    protected function setUp(): void
    {
        parent::setUp();
        $runtimeDir = storage_path('app/private/runtime');
        if (!is_dir($runtimeDir)) {
            mkdir($runtimeDir, 0755, true);
        }
        $this->credFile = $runtimeDir . '/bootstrap_credentials.json';

        // The credential file may exist from the container's startup seeding.
        // Remove it so each test starts from the "missing file" state it expects.
        if (file_exists($this->credFile)) {
            unlink($this->credFile);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->credFile)) {
            unlink($this->credFile);
        }
        parent::tearDown();
    }

    public function test_missing_file_existing_users_resets_passwords(): void
    {
        // Seed roles
        foreach (['admin', 'teacher', 'ta', 'student'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName], ['description' => ucfirst($roleName)]);
        }

        // Pre-create bootstrap users with known passwords
        $knownPassword = 'OriginalPassword123!';
        foreach (['admin', 'teacher', 'ta', 'student'] as $username) {
            User::create([
                'username' => $username,
                'display_name' => "Test {$username}",
                'password' => Hash::make($knownPassword),
                'force_password_change' => false,
                'account_status' => 'active',
            ]);
        }

        // Credential file does NOT exist, but users do
        $this->assertFalse(file_exists($this->credFile));
        $this->assertEquals(4, User::whereIn('username', ['admin', 'teacher', 'ta', 'student'])->count());

        // Run the seeder — this is the bug scenario
        $seeder = new BootstrapAccountSeeder();
        $seeder->run();

        // File should have been written
        $this->assertTrue(file_exists($this->credFile));
        $credentials = json_decode(file_get_contents($this->credFile), true);
        $this->assertCount(4, $credentials);

        // Each credential in the file must be valid against the actual DB password
        foreach ($credentials as $cred) {
            $user = User::where('username', $cred['username'])->first();
            $this->assertNotNull($user, "User {$cred['username']} should exist");
            $this->assertTrue(
                Hash::check($cred['password'], $user->password),
                "Credential file password for {$cred['username']} must match the database"
            );
            // Password should have been reset, so the original should no longer work
            $this->assertFalse(
                Hash::check($knownPassword, $user->password),
                "Original password for {$cred['username']} should no longer be valid"
            );
            // force_password_change should be re-enabled
            $this->assertTrue($user->force_password_change);
        }
    }
}
