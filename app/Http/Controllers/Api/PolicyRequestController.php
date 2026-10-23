<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Models\IssuanceAttempt;
use App\Services\PremiumCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class PolicyRequestController extends Controller
{
    public function store(Request $request, PremiumCalculator $calculator)
    {
        $data = $request->validate([
            'client_request_id' => ['required', 'string', 'max:100'],
            'agent_id' => ['required', 'string', 'max:100'],
            'customer_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'destination' => ['required', Rule::in(['DOMESTIC', 'INTERNATIONAL'])],
            'trip_start' => ['required', 'date', 'after_or_equal:today'],
            'trip_end' => ['required', 'date', 'after_or_equal:trip_start'],
            'plan_code' => ['required', Rule::in(['BASIC', 'PLUS', 'PREMIUM'])],
            'sum_insured' => ['required', 'integer', Rule::in([100000, 300000, 500000])],
        ]);

        $existing = PolicyRequest::where('agent_id', $data['agent_id'])
            ->where('client_request_id', $data['client_request_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Duplicate request; existing request returned.',
                'data' => $existing,
            ], 200);
        }

        try {
            $pricing = $calculator->calculate(
                $data['date_of_birth'],
                $data['trip_start'],
                $data['trip_end'],
                $data['plan_code'],
                $data['destination'],
                (int) $data['sum_insured']
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        try {
            $policy = DB::transaction(function () use ($data, $pricing) {
                $policy = PolicyRequest::create(array_merge($data, [
                    'travel_days' => $pricing['travel_days'],
                    'premium' => $pricing['premium'],
                    'status' => 'RECEIVED',
                ]));

                PolicyStateHistory::create([
                    'policy_request_id' => $policy->id,
                    'from_status' => null,
                    'to_status' => 'RECEIVED',
                    'reason' => 'Policy request received.',
                    'metadata' => ['premium_snapshot' => $pricing['premium']],
                ]);

                return $policy;
            });
        } catch (Throwable $e) {
            // Handles simultaneous duplicate submissions safely.
            $existing = PolicyRequest::where('agent_id', $data['agent_id'])
                ->where('client_request_id', $data['client_request_id'])
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Duplicate request; existing request returned.',
                    'data' => $existing,
                ], 200);
            }

            throw $e;
        }

        return response()->json([
            'message' => 'Policy request created.',
            'data' => $policy,
        ], 201);
    }

    public function index(Request $request)
    {
        $query = PolicyRequest::query();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->query('agent_id'));
        }

        if ($request->filled('client_request_id')) {
            $query->where('client_request_id', $request->query('client_request_id'));
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function show(string $id)
    {
        $policy = PolicyRequest::findOrFail($id);

        return response()->json([
            'data' => $policy,
            'attempts' => IssuanceAttempt::where('policy_request_id', $id)
                ->orderBy('attempt_number')->get(),
            'audit_trail' => PolicyStateHistory::where('policy_request_id', $id)
                ->orderBy('id')->get(),
        ]);
    }

    public function submit(Request $request, string $id)
    {
        $policy = PolicyRequest::findOrFail($id);

        if (in_array($policy->status, ['ISSUED', 'PENDING_CONFIRMATION'], true)) {
            return response()->json([
                'message' => 'Request cannot be submitted again in its current state.',
                'data' => $policy,
            ], 409);
        }

        $data = $request->validate([
            'scenario' => [
                'sometimes',
                Rule::in([
                    'SUCCESS',
                    'TEMPORARY_FAILURE',
                    'TIMEOUT_AFTER_ISSUE',
                    'DELAYED_CALLBACK',
                    'DUPLICATE_CALLBACK',
                ]),
            ],
        ]);

        $scenario = $data['scenario'] ?? 'SUCCESS';

        return app(\App\Services\MockInsurerService::class)
            ->submit($policy, $scenario);
    }
}
