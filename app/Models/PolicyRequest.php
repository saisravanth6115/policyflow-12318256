<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_request_id',
        'agent_id',
        'customer_name',
        'date_of_birth',
        'destination',
        'trip_start',
        'trip_end',
        'plan_code',
        'sum_insured',
        'travel_days',
        'premium',
        'status',
        'provider_reference',
        'policy_number',
        'attempt_count',
        'last_provider_response',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            'trip_start' => 'date:Y-m-d',
            'trip_end' => 'date:Y-m-d',
            'sum_insured' => 'integer',
            'travel_days' => 'integer',
            'premium' => 'decimal:2',
            'attempt_count' => 'integer',
            'last_provider_response' => 'array',
        ];
    }
}
