<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Models\ProviderEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MockInsurerController extends Controller
{
    public function callback(Request $request)
    {
        $data = $request->validate([
            'event_id' => ['required', 'string', 'max:150'],
            'provider_reference' => ['required', 'string', 'max:150'],
            'event_type' => ['required', 'in:ISSUED,FAILED'],
            'policy_number' => ['required_if:event_type,ISSUED', 'nullable', 'string', 'max:100'],
        ]);

        return DB::transaction(function () use ($data) {
            $existingEvent = ProviderEvent::where('event_id', $data['event_id'])->first();

            if ($existingEvent) {
                return response()->json([
                    'message' => 'Callback already processed.',
                    'duplicate' => true,
                ], 200);
            }

            $policy = PolicyRequest::where(
                'provider_reference',
                $data['provider_reference']
            )->lockForUpdate()->first();

            if (!$policy) {
                throw ValidationException::withMessages([
                    'provider_reference' => ['No matching policy request was found.'],
                ]);
            }

            ProviderEvent::create([
                'event_id' => $data['event_id'],
                'policy_request_id' => $policy->id,
                'event_type' => $data['event_type'],
                'payload' => $data,
            ]);

            // A callback must never downgrade an already issued policy.
            if ($policy->status === 'ISSUED') {
                return response()->json([
                    'message' => 'Policy already issued; callback recorded without changing it.',
                    'data' => $policy,
                ], 200);
            }

            $fromStatus = $policy->status;

            if ($data['event_type'] === 'ISSUED') {
                // Do not replace an existing policy number.
                if ($policy->policy_number !== null) {
                    return response()->json([
                        'message' => 'A policy number already exists; no changes made.',
                        'data' => $policy,
                    ], 200);
                }

                $policy->status = 'ISSUED';
                $policy->policy_number = $data['policy_number'];
            } else {
                $policy->status = 'FAILED';
            }

            $policy->last_provider_response = $data;
            $policy->save();

            IssuanceAttempt::where('policy_request_id', $policy->id)
                ->where('provider_reference', $data['provider_reference'])
                ->latest('attempt_number')
                ->first()
                ?->update([
                    'outcome' => $data['event_type'],
                    'response_payload' => $data,
                ]);

            PolicyStateHistory::create([
                'policy_request_id' => $policy->id,
                'from_status' => $fromStatus,
                'to_status' => $policy->status,
                'reason' => 'Mock insurer callback received.',
                'metadata' => ['event_id' => $data['event_id']],
            ]);

            return response()->json([
                'message' => 'Callback processed.',
                'data' => $policy->fresh(),
            ], 200);
        });
    }
}
