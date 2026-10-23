<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IssuanceAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_request_id',
        'attempt_number',
        'scenario',
        'outcome',
        'provider_reference',
        'request_payload',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }
}
