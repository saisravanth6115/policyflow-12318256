<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyStateHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_request_id',
        'from_status',
        'to_status',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
