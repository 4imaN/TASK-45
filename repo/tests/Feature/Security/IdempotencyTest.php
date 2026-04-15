<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_state_changing_request_requires_idempotency_key(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();

        // POST without X-Idempotency-Key should be rejected
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id,
            'quantity' => 1,
            'idempotency_key' => 'body-key-1',
        ]);
        $response->assertUnprocessable();
        $this->assertStringContainsString('Idempotency-Key', $response->json('error'));
    }

    public function test_same_key_same_payload_replays_response(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();

        $payload = [
            'resource_id' => $resource->id,
            'quantity' => 1,
            'idempotency_key' => 'replay-test-1',
        ];
        $headers = ['X-Idempotency-Key' => 'replay-test-1'];

        $r1 = $this->actingAs($student)->postJson('/api/loans', $payload, $headers);
        $r1->assertCreated();

        // Second request with same key and same payload should replay
        $r2 = $this->actingAs($student)->postJson('/api/loans', $payload, $headers);
        $r2->assertCreated();

        // Should NOT create a second loan
        $this->assertDatabaseCount('loan_requests', 1);
    }

    public function test_same_key_different_payload_returns_conflict(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$r1, $l1] = $this->createResourceWithLot();
        [$r2, $l2] = $this->createResourceWithLot();

        $headers = ['X-Idempotency-Key' => 'conflict-test-1'];

        $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $r1->id, 'quantity' => 1, 'idempotency_key' => 'conflict-test-1',
        ], $headers)->assertCreated();

        // Same key, different payload
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $r2->id, 'quantity' => 1, 'idempotency_key' => 'conflict-test-1',
        ], $headers);
        $response->assertStatus(409);
    }

    public function test_different_user_same_key_is_isolated(): void
    {
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();
        $this->assignMembership($s1);
        $this->assignMembership($s2);
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 10]);

        // Same X-Idempotency-Key header for both users, but different body idempotency_keys
        // (because loan_requests.idempotency_key has a UNIQUE constraint)
        $headers = ['X-Idempotency-Key' => 'shared-header-key-1'];

        $this->actingAs($s1)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => 'body-key-user1',
        ], $headers)->assertCreated();

        $this->actingAs($s2)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => 'body-key-user2',
        ], $headers)->assertCreated();

        // Each user should have their own loan — header key is scoped by user
        $this->assertDatabaseCount('loan_requests', 2);
    }

    public function test_auth_login_requires_idempotency_key(): void
    {
        // The middleware historically exempted /api/auth/login. As of the prompt
        // compliance pass, ALL state-changing endpoints (including auth) must
        // present an X-Idempotency-Key. This test guards that rule.
        $user = $this->createStudent(['password' => bcrypt('TestPass1!')]);

        // Missing header → 422 (IdempotencyMiddleware rejects).
        $this->postJson('/api/auth/login', [
            'username' => $user->username, 'password' => 'TestPass1!',
        ])->assertUnprocessable();

        // Same request with a header → 200.
        $this->postJson('/api/auth/login', [
            'username' => $user->username, 'password' => 'TestPass1!',
        ], ['X-Idempotency-Key' => 'login-' . uniqid()])->assertOk();
    }

    public function test_auth_response_body_is_not_persisted_in_idempotency_store(): void
    {
        // Auth response bodies contain bearer tokens. The middleware must store a
        // replay marker instead of the raw token so the idempotency table never
        // persists credentials.
        $user = $this->createStudent(['password' => bcrypt('TestPass1!')]);
        $key = 'login-notoken-' . uniqid();

        $first = $this->postJson('/api/auth/login', [
            'username' => $user->username, 'password' => 'TestPass1!',
        ], ['X-Idempotency-Key' => $key])->assertOk();

        $row = \App\Models\IdempotencyKey::where('key', $key)->first();
        $this->assertNotNull($row);
        $body = is_string($row->response_body) ? json_decode($row->response_body, true) : $row->response_body;
        $this->assertArrayHasKey('_replayed', $body, 'Auth route stored raw response instead of replay marker');
        $this->assertArrayNotHasKey('token', $body, 'Bearer token leaked into idempotency_keys.response_body');
    }
}
