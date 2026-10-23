<?php

namespace App\Services;

use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MockInsurerService
{
    public function submit(PolicyRequest $policy, string $scenario): JsonResponse
    {
        if ($policy->status === 'ISSUED') {
            return response()->json([
                'message' => 'Policy already issued.',
                'data' => $policy,
            ], 200);
        }

        if ($policy->status === 'PENDING_CONFIRMATION') {
            return response()->json([
                'message' => 'Issuance is awaiting reconciliation.',
                'data' => $policy,
            ], 202);
        }

        return DB::transaction(function () use ($policy, $scenario) {
            $policy = PolicyRequest::whereKey($policy->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($policy->status === 'ISSUED') {
                return response()->json(['message' => 'Policy already issued.', 'data' => $policy]);
            }

            $fromStatus = $policy->status;
            $attemptNumber = $policy->attempt_count + 1;
            $reference = 'MOCK-' . Str::upper(Str::random(12));

            $attempt = IssuanceAttempt::create([
                'policy_request_id' => $policy->id,
                'attempt_number' => $attemptNumber,
                'scenario' => $scenario,
                'outcome' => 'STARTED',
                'provider_reference' => $reference,
                'request_payload' => [
                    'customer_name' => $policy->customer_name,
                    'plan_code' => $policy->plan_code,
                    'premium' => $policy->premium,
                ],
            ]);

            $policy->attempt_count = $attemptNumber;
            $policy->provider_reference = $reference;

            if ($scenario === 'SUCCESS' || $scenario === 'DUPLICATE_CALLBACK') {
                $policy->status = 'ISSUED';
                $policy->policy_number = 'TP-' . Str::upper(Str::random(10));
                $attempt->outcome = 'SUCCESS';
                $attempt->response_payload = [
                    'result' => 'ISSUED',
                    'policy_number' => $policy->policy_number,
                ];
            } elseif ($scenario === 'TIMEOUT_AFTER_ISSUE' || $scenario === 'DELAYED_CALLBACK') {
                $policy->status = 'PENDING_CONFIRMATION';
                $attempt->outcome = 'TIMEOUT';
                $attempt->response_payload = [
                    'result' => 'UNKNOWN',
                    'message' => 'Provider confirmation is pending.',
                ];
            } else {
                $policy->status = 'FAILED';
                $attempt->outcome = 'TEMPORARY_FAILURE';
                $attempt->response_payload = [
                    'result' => 'TEMPORARY_FAILURE',
                    'message' => 'Mock insurer temporarily unavailable.',
                ];
            }

            $policy->last_provider_response = $attempt->response_payload;
            $policy->save();
            $attempt->save();

            PolicyStateHistory::create([
                'policy_request_id' => $policy->id,
                'from_status' => $fromStatus,
                'to_status' => $policy->status,
                'reason' => 'Mock insurer scenario: ' . $scenario,
                'metadata' => ['attempt_number' => $attemptNumber],
            ]);

            return response()->json([
                'message' => 'Mock insurer submission processed.',
                'data' => $policy->fresh(),
                'attempt' => $attempt,
            ], $policy->status === 'PENDING_CONFIRMATION' ? 202 : 200);
        });
    }
}
