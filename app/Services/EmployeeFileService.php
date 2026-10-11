<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\PresenceLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Dossier d'un employé : fiche, pièces (contrat, identité, diplômes...), historique des
 * départements et synthèse d'activité. Les pièces sont stockées sur un disque PRIVÉ.
 */
class EmployeeFileService
{
    private const DISK = 'local';

    public function build(Employee $employee): array
    {
        $employee->load([
            'department:id,name',
            'roles:id,name',
            'user:id,email,role,needs_password_change',
            'manager:id,employee_id,department_id',
        ]);

        $since = now()->subDays(30)->toDateString();
        $presences30 = fn () => $employee->presences()->whereDate('date', '>=', $since);

        return [
            'employee' => $employee,
            'account' => [
                'has_account' => $employee->user !== null,
                'pending_activation' => (bool) ($employee->user?->needs_password_change),
                'is_manager' => $employee->manager !== null,
            ],
            'documents' => $employee->documents()
                ->with('uploader:id,name')
                ->latest()
                ->get(),
            'department_history' => $employee->departmentHistory()
                ->with(['fromDepartment:id,name', 'toDepartment:id,name', 'transferredBy:id,name'])
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->get(),
            'stats' => [
                'tasks' => $employee->tasks()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
                'leaves' => $employee->leaveRequests()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
                'presences_30d' => [
                    'days_present' => $presences30()->whereIn('status', ['present', 'late'])->count(),
                    'days_late' => $presences30()->where('status', 'late')->count(),
                    'days_absent' => $presences30()->where('status', 'absent')->count(),
                    'total_hours' => round((float) $presences30()->sum('total_hours'), 2),
                ],
            ],
            'recent_presence_logs' => PresenceLog::where('employee_id', $employee->id)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'action', 'outcome', 'reason', 'ip_address', 'occurred_at']),
        ];
    }

    public function addDocument(Employee $employee, UploadedFile $file, string $type, ?string $title, ?User $by): EmployeeDocument
    {
        $path = $file->store("employee_documents/{$employee->id}", self::DISK);

        return $employee->documents()->create([
            'type' => $type,
            'title' => $title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => $by?->id,
        ])->load('uploader:id,name');
    }

    public function deleteDocument(EmployeeDocument $document): void
    {
        Storage::disk(self::DISK)->delete($document->file_path);
        $document->delete();
    }

    public function download(EmployeeDocument $document)
    {
        $disk = Storage::disk(self::DISK);

        abort_unless($disk->exists($document->file_path), 404, 'Fichier introuvable.');

        return $disk->download($document->file_path, $document->original_name);
    }
}
