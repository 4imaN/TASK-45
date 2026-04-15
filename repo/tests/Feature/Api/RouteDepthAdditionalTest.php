<?php
namespace Tests\Feature\Api;

use App\Models\Allowlist;
use App\Models\AuditLog;
use App\Models\Blacklist;
use App\Models\Hold;
use App\Models\ManufacturerAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;
use Tests\TestCase;

/**
 * Fills the remaining "shallow happy-path only" gaps:
 *   - POST /api/data-quality/manufacturer-aliases happy-path
 *   - GET /api/admin/audit-logs/export filter matrix
 *   - POST /api/admin/reveal-field validation + not-revealable rejection
 *   - POST /api/admin/allowlists + /blacklists validation
 *   - DELETE /api/admin/allowlists/{id} + /blacklists/{id} behaviour and audit
 *   - POST /api/admin/holds/{hold}/release validation
 *   - POST /api/data-quality/import validation/type edges
 */
class RouteDepthAdditionalTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // ------------------------------------------------------------------
    // Data quality — manufacturer alias happy path create
    // ------------------------------------------------------------------

    public function test_manufacturer_alias_create_persists_pending(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->postJson('/api/data-quality/manufacturer-aliases', [
            'alias' => 'Sony Japan',
            'canonical_name' => 'Sony',
        ], ['X-Idempotency-Key' => 'ma-create-' . uniqid()]);

        $response->assertCreated()
            ->assertJsonPath('alias', 'Sony Japan')
            ->assertJsonPath('canonical_name', 'Sony')
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('manufacturer_aliases', [
            'alias' => 'Sony Japan', 'canonical_name' => 'Sony', 'status' => 'pending',
        ]);
    }

    public function test_manufacturer_alias_create_rejects_missing_fields(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin)->postJson(
            '/api/data-quality/manufacturer-aliases',
            ['alias' => 'Only Alias'],
            ['X-Idempotency-Key' => 'ma-bad-' . uniqid()],
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['canonical_name']);
    }

    // ------------------------------------------------------------------
    // Audit log export — filter matrix
    // ------------------------------------------------------------------

    public function test_audit_log_export_filters_by_action(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'login', 'auditable_type' => 'App\\Models\\User', 'auditable_id' => $admin->id]);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'logout', 'auditable_type' => 'App\\Models\\User', 'auditable_id' => $admin->id]);

        $response = $this->actingAs($admin)->getJson('/api/admin/audit-logs/export?action=login');
        $response->assertOk();
        $csv = $response->getContent();
        $this->assertStringContainsString(',login,', $csv);
        $this->assertStringNotContainsString(',logout,', $csv);
    }

    public function test_audit_log_export_filters_by_user_id(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $other = $this->createAdmin();
        AuditLog::create(['user_id' => $admin->id, 'action' => 'login', 'auditable_type' => 'App\\Models\\User', 'auditable_id' => $admin->id]);
        AuditLog::create(['user_id' => $other->id, 'action' => 'login', 'auditable_type' => 'App\\Models\\User', 'auditable_id' => $other->id]);

        $response = $this->actingAs($admin)->getJson('/api/admin/audit-logs/export?user_id=' . $admin->id);
        $csv = $response->getContent();
        // Count only data rows (exclude header)
        $lines = array_filter(explode("\n", $csv), fn($l) => $l !== '' && !str_starts_with($l, 'id,user_id,'));
        $this->assertCount(1, $lines);
        $this->assertStringContainsString(",{$admin->id},", implode("\n", $lines));
    }

    public function test_audit_log_export_filters_by_date_range(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        $old = AuditLog::create(['user_id' => $admin->id, 'action' => 'login', 'auditable_type' => 'App\\Models\\User', 'auditable_id' => $admin->id]);
        $old->created_at = now()->subDays(10);
        $old->save();

        $new = AuditLog::create(['user_id' => $admin->id, 'action' => 'login', 'auditable_type' => 'App\\Models\\User', 'auditable_id' => $admin->id]);

        $from = now()->subDays(1)->toDateString();
        $response = $this->actingAs($admin)->getJson("/api/admin/audit-logs/export?from={$from}");
        $csv = $response->getContent();
        // CSV has header + one data row per match. Each row starts with "id,user_id,...",
        // so the new id is at the start of its row while the old id must not appear at all.
        $this->assertMatchesRegularExpression("/^{$new->id},/m", $csv);
        $this->assertDoesNotMatchRegularExpression("/^{$old->id},/m", $csv);
    }

    // ------------------------------------------------------------------
    // reveal-field — validation + not-revealable rejection
    // ------------------------------------------------------------------

    public function test_reveal_field_requires_all_inputs(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        $this->actingAs($admin)->postJson(
            '/api/admin/reveal-field',
            // missing model_type, model_id, fields, reason
            [],
            ['X-Idempotency-Key' => 'rf-empty-' . uniqid()],
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['model_type', 'model_id', 'fields', 'reason']);
    }

    public function test_reveal_field_requires_long_reason(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent(['phone' => '555-1234']);

        $this->actingAs($admin)->postJson('/api/admin/reveal-field', [
            'model_type' => 'App\\Models\\User',
            'model_id' => $student->id,
            'fields' => ['phone'],
            'reason' => 'too', // under 5 chars
        ], ['X-Idempotency-Key' => 'rf-short-' . uniqid()])
          ->assertUnprocessable()
          ->assertJsonValidationErrors(['reason']);
    }

    public function test_reveal_field_rejects_non_revealable_model(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        $this->actingAs($admin)->postJson('/api/admin/reveal-field', [
            'model_type' => 'App\\Models\\Hold', // not on the allowlist
            'model_id' => 1,
            'fields' => ['reason'],
            'reason' => 'Legitimate reason',
        ], ['X-Idempotency-Key' => 'rf-bad-model-' . uniqid()])
          ->assertStatus(422)
          ->assertJsonPath('error', 'Model type is not revealable.');
    }

    public function test_reveal_field_rejects_non_revealable_fields(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $this->actingAs($admin)->postJson('/api/admin/reveal-field', [
            'model_type' => 'App\\Models\\User',
            'model_id' => $student->id,
            'fields' => ['password', 'remember_token'], // not on the allowlist
            'reason' => 'Investigating leak',
        ], ['X-Idempotency-Key' => 'rf-bad-fields-' . uniqid()])
          ->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Allowlist + blacklist — validation, delete, audit
    // ------------------------------------------------------------------

    public function test_add_allowlist_requires_scope_type_user_and_reason(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        $this->actingAs($admin)->postJson(
            '/api/admin/allowlists',
            [],
            ['X-Idempotency-Key' => 'al-empty-' . uniqid()],
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['scope_type', 'scope_id', 'user_id', 'reason']);
    }

    public function test_add_blacklist_rejects_invalid_scope_type(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $this->actingAs($admin)->postJson('/api/admin/blacklists', [
            'scope_type' => 'galaxy', // not department/global
            'scope_id' => 0,
            'user_id' => $student->id,
            'reason' => 'bad',
        ], ['X-Idempotency-Key' => 'bl-bad-type-' . uniqid()])
          ->assertUnprocessable()
          ->assertJsonValidationErrors(['scope_type']);
    }

    public function test_delete_allowlist_removes_row_and_audits(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();
        $entry = Allowlist::create([
            'scope_type' => 'global', 'scope_id' => 0, 'user_id' => $student->id,
            'reason' => 'Approved beta tester', 'added_by' => $admin->id,
        ]);

        $this->actingAs($admin)->deleteJson(
            "/api/admin/allowlists/{$entry->id}",
            [],
            ['X-Idempotency-Key' => 'al-del-' . uniqid()],
        )->assertOk();

        $this->assertDatabaseMissing('allowlists', ['id' => $entry->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'allowlist_removed',
            'user_id' => $admin->id,
            'auditable_id' => $entry->id,
        ]);
    }

    public function test_delete_blacklist_forbidden_for_non_admin(): void
    {
        $teacher = $this->createTeacher();
        $entry = Blacklist::create([
            'scope_type' => 'global', 'scope_id' => 0, 'user_id' => $teacher->id,
            'reason' => 'Seeded', 'added_by' => $teacher->id,
        ]);

        $student = $this->createStudent();
        $this->actingAs($student)->deleteJson(
            "/api/admin/blacklists/{$entry->id}",
            [],
            ['X-Idempotency-Key' => 'bl-forbid-' . uniqid()],
        )->assertForbidden();

        $this->assertDatabaseHas('blacklists', ['id' => $entry->id]);
    }

    public function test_delete_blacklist_audits_removal(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();
        $entry = Blacklist::create([
            'scope_type' => 'global', 'scope_id' => 0, 'user_id' => $student->id,
            'reason' => 'Policy violation', 'added_by' => $admin->id,
        ]);

        $this->actingAs($admin)->deleteJson(
            "/api/admin/blacklists/{$entry->id}",
            [],
            ['X-Idempotency-Key' => 'bl-del-' . uniqid()],
        )->assertOk();

        $this->assertDatabaseMissing('blacklists', ['id' => $entry->id]);
        $log = AuditLog::where('action', 'blacklist_removed')
            ->where('auditable_id', $entry->id)->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($student->id, $log->old_values['user_id']);
    }

    // ------------------------------------------------------------------
    // Holds release — validation
    // ------------------------------------------------------------------

    public function test_release_hold_requires_reason_of_five_or_more_chars(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();
        $hold = Hold::create([
            'user_id' => $student->id, 'hold_type' => 'manual',
            'reason' => 'Test', 'status' => 'active', 'triggered_at' => now(),
        ]);

        $this->actingAs($admin)->postJson(
            "/api/admin/holds/{$hold->id}/release",
            ['reason' => 'ok'], // too short
            ['X-Idempotency-Key' => 'rl-short-' . uniqid()],
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['reason']);

        // Hold remains active
        $this->assertSame('active', $hold->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Data-quality import — validation edges
    // ------------------------------------------------------------------

    public function test_import_rejects_unsupported_type(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin)->postJson('/api/data-quality/import', [
            'type' => 'users',
            'rows' => [['name' => 'x']],
        ], ['X-Idempotency-Key' => 'imp-bad-type-' . uniqid()])
          ->assertStatus(422)
          ->assertJsonPath('error', "Import type 'users' is not supported. Supported types: resources.");
    }

    public function test_import_rejects_empty_body(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin)->postJson(
            '/api/data-quality/import',
            [],
            ['X-Idempotency-Key' => 'imp-empty-' . uniqid()],
        )->assertStatus(422)
          ->assertJsonPath('error', 'No data provided. Upload a file or provide rows.');
    }

    public function test_import_forbidden_for_student(): void
    {
        $student = $this->createStudent();
        $this->actingAs($student)->postJson(
            '/api/data-quality/import',
            ['rows' => [['name' => 'x']]],
            ['X-Idempotency-Key' => 'imp-forbid-' . uniqid()],
        )->assertForbidden();
    }
}
