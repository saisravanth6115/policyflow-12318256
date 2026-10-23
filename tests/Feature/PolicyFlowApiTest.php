<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyFlowApiTest extends TestCase
{
    use RefreshDatabase;

    private function requestPayload(string $requestId = 'TEST-001'): array
    {
        return [
            'client_request_id' => $requestId,
            'agent_id' => 'AGENT-TEST',
            'customer_name' => 'Test Customer',
            'date_of_birth' => '1999-05-15',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-12',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];
    }

    public function test_policy_request_is_created_with_expected_premium(): void
    {
        $response = $this->postJson(
            '/api/policy-requests',
            $this->requestPayload()
        );

        $response->assertCreated()
            ->assertJsonPath('data.status', 'RECEIVED')
            ->assertJsonPath('data.travel_days', 3)
            ->assertJsonPath('data.premium', '36.00');

        $this->assertDatabaseHas('policy_requests', [
            'client_request_id' => 'TEST-001',
            'agent_id' => 'AGENT-TEST',
            'status' => 'RECEIVED',
        ]);
    }

    public function test_duplicate_request_returns_existing_record(): void
    {
        $first = $this->postJson(
            '/api/policy-requests',
            $this->requestPayload()
        );

        $first->assertCreated();
        $id = $first->json('data.id');

        $second = $this->postJson(
            '/api/policy-requests',
            $this->requestPayload()
        );

        $second->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->assertDatabaseCount('policy_requests', 1);
    }

    public function test_invalid_trip_dates_are_rejected(): void
    {
        $payload = $this->requestPayload();
        $payload['trip_end'] = '2026-11-09';

        $this->postJson('/api/policy-requests', $payload)
            ->assertUnprocessable();
    }

    public function test_successful_submission_issues_only_one_policy(): void
    {
        $created = $this->postJson(
            '/api/policy-requests',
            $this->requestPayload()
        )->assertCreated();

        $id = $created->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [
            'scenario' => 'SUCCESS',
        ])->assertOk();

        $policy = PolicyRequest::findOrFail($id);

        $this->assertSame('ISSUED', $policy->status);
        $this->assertNotEmpty($policy->policy_number);
        $originalNumber = $policy->policy_number;

        $this->postJson("/api/policy-requests/{$id}/submit", [
            'scenario' => 'SUCCESS',
        ])->assertStatus(409);

        $policy->refresh();

        $this->assertSame($originalNumber, $policy->policy_number);
        $this->assertSame(1, $policy->attempt_count);
    }

    public function test_timeout_can_be_reconciled_with_idempotent_callback(): void
    {
        $created = $this->postJson(
            '/api/policy-requests',
            $this->requestPayload()
        )->assertCreated();

        $id = $created->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [
            'scenario' => 'TIMEOUT_AFTER_ISSUE',
        ])->assertStatus(202);

        $policy = PolicyRequest::findOrFail($id);
        $this->assertSame('PENDING_CONFIRMATION', $policy->status);

        $callback = [
            'event_id' => 'TEST-EVENT-001',
            'provider_reference' => $policy->provider_reference,
            'event_type' => 'ISSUED',
            'policy_number' => 'TP-TEST-001',
        ];

        $this->postJson('/api/mock-insurer/callback', $callback)
            ->assertOk();

        $this->assertSame('ISSUED', $policy->fresh()->status);

        $this->postJson('/api/mock-insurer/callback', $callback)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('provider_events', 1);
        $this->assertSame('TP-TEST-001', $policy->fresh()->policy_number);
    }
}
