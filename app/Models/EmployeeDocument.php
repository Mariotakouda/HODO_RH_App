<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'title',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    // Le chemin interne du fichier n'est jamais exposé : le téléchargement
    // passe par un endpoint authentifié (disque privé).
    protected $hidden = ['file_path'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
