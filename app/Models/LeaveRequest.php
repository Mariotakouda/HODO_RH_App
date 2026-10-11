<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'status',
        'message',
        'admin_comment',
    ];

    protected $casts = [
        'status' => LeaveRequestStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Une demande appartient à un employé.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}