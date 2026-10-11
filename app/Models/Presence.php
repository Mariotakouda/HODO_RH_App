<?php

namespace App\Models;

use App\Enums\PresenceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    protected $fillable = [
        'date',
        'check_in',
        'check_out',
        'total_hours',
        'employee_id',
        // BUGFIX : 'status' n'était pas fillable, donc le statut "present" fixé
        // au check-in n'était jamais réellement enregistré en base.
        'status',
    ];

    protected $casts = [
        'status' => PresenceStatus::class,
    ];

    
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}