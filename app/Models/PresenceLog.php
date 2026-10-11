<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresenceLog extends Model
{
    public const ACTION_CHECK_IN = 'check_in';
    public const ACTION_CHECK_OUT = 'check_out';
    public const ACTION_DELETED = 'deleted';

    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_REJECTED = 'rejected';

    protected $fillable = [
        'presence_id',
        'employee_id',
        'user_id',
        'action',
        'outcome',
        'reason',
        'ip_address',
        'user_agent',
        'meta',
        'occurred_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function presence(): BelongsTo
    {
        return $this->belongsTo(Presence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
