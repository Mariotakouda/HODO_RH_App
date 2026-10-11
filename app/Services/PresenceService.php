<?php

namespace App\Services;

use App\Enums\PresenceStatus;
use App\Models\Employee;
use App\Models\Presence;
use App\Models\PresenceLog;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PresenceService
{
    public function __construct(private readonly PresenceLogService $logger)
    {
    }

    public function myPresences(Employee $employee)
    {
        return Presence::where('employee_id', $employee->id)
            ->select('id', 'date', 'check_in', 'check_out', 'total_hours')
            ->latest()
            ->limit(15)
            ->get();
    }

    public function monthlyStats(Employee $employee): array
    {
        $stats = Presence::where('employee_id', $employee->id)
            ->whereMonth('date', now()->month)
            ->selectRaw('SUM(total_hours) as total_hours, COUNT(id) as days_present')
            ->first();

        return [
            'total_hours' => round($stats->total_hours ?? 0, 2),
            'days_present' => $stats->days_present ?? 0,
        ];
    }

    /**
     * BUGFIX (voir cahier des charges) : avant, un manager voyait les présences
     * de TOUTE l'entreprise, comme un admin. La supervision globale est une
     * prérogative admin ; un manager ne voit que son propre département.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException si le manager n'a pas de département
     */
    public function listForStaff(\App\Models\User $user, Request $request)
    {
        $query = Presence::with('employee:id,first_name,last_name');

        if (!$user->isAdmin()) {
            $deptId = $user->employee->department_id ?? null;
            if (!$deptId) {
                throw new \Illuminate\Auth\Access\AuthorizationException("Vous n'avez pas de département assigné.");
            }
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        if ($request->filled('month')) {
            $query->whereMonth('date', $request->month);
        }

        return $query->latest()->paginate(20);
    }

    /**
     * Pointage d'arrivée : une seule arrivée par jour. Au-delà de l'heure de début + tolérance
     * (config/hodo.php), le statut passe à « late ». Toute tentative est journalisée.
     *
     * @throws \RuntimeException si la journée est déjà pointée
     */
    public function checkIn(Employee $employee, Request $request): Presence
    {
        $now = now();
        $today = $now->toDateString();

        if ($employee->isArchived()) {
            $this->logger->record(PresenceLog::ACTION_CHECK_IN, PresenceLog::OUTCOME_REJECTED, $employee, $request, null, 'employee_archived');
            throw new \RuntimeException('Ce compte est archivé.');
        }

        $existing = Presence::where('employee_id', $employee->id)->whereDate('date', $today)->first();

        // Ligne « absent » créée automatiquement (check_in vide) : l'arrivée la complète.
        if ($existing && $existing->check_in !== null) {
            $reason = $existing->check_out ? 'already_completed' : 'already_checked_in';
            $this->logger->record(PresenceLog::ACTION_CHECK_IN, PresenceLog::OUTCOME_REJECTED, $employee, $request, $existing, $reason);

            throw new \RuntimeException($existing->check_out
                ? 'Votre journée est déjà pointée (arrivée et sortie enregistrées).'
                : 'Un pointage est déjà en cours.');
        }

        $deadline = Carbon::parse($today . ' ' . config('hodo.work_start_time', '08:00'), config('app.timezone'))
            ->addMinutes((int) config('hodo.late_grace_minutes', 15));
        $status = $now->greaterThan($deadline) ? PresenceStatus::Late : PresenceStatus::Present;

        $attributes = [
            'check_in' => $now,
            'check_out' => null,
            'total_hours' => null,
            'status' => $status,
        ];

        $presence = $existing
            ? tap($existing)->update($attributes)
            : Presence::create($attributes + ['employee_id' => $employee->id, 'date' => $today]);

        $this->logger->record(PresenceLog::ACTION_CHECK_IN, PresenceLog::OUTCOME_SUCCESS, $employee, $request, $presence, null, [
            'status' => $status->value,
            'time' => $now->format('H:i:s'),
        ]);

        return $presence;
    }

    /**
     * Pointage de sortie : seul le propriétaire du pointage peut le clôturer, une seule fois,
     * avec l'heure du serveur (l'heure envoyée par le navigateur n'est plus acceptée).
     *
     * @throws AuthorizationException si le pointage appartient à quelqu'un d'autre
     * @throws \RuntimeException si la sortie est déjà enregistrée
     */
    public function checkOut(Employee $employee, Presence $presence, Request $request): Presence
    {
        if ((int) $presence->employee_id !== (int) $employee->id) {
            $this->logger->record(PresenceLog::ACTION_CHECK_OUT, PresenceLog::OUTCOME_REJECTED, $employee, $request, null, 'not_owner', [
                'presence_id' => $presence->id,
            ]);
            throw new AuthorizationException('Ce pointage ne vous appartient pas.');
        }

        if ($presence->check_in === null) {
            $this->logger->record(PresenceLog::ACTION_CHECK_OUT, PresenceLog::OUTCOME_REJECTED, $employee, $request, $presence, 'no_check_in');
            throw new \RuntimeException("Aucune arrivée enregistrée pour cette journée.");
        }

        if ($presence->check_out !== null) {
            $this->logger->record(PresenceLog::ACTION_CHECK_OUT, PresenceLog::OUTCOME_REJECTED, $employee, $request, $presence, 'already_checked_out');
            throw new \RuntimeException('La sortie est déjà enregistrée.');
        }

        $checkOut = now();
        $checkIn = Carbon::parse($presence->check_in);

        $presence->check_out = $checkOut;
        $presence->total_hours = round(max(0, $checkIn->diffInMinutes($checkOut)) / 60, 2);
        $presence->save();

        $this->logger->record(PresenceLog::ACTION_CHECK_OUT, PresenceLog::OUTCOME_SUCCESS, $employee, $request, $presence, null, [
            'total_hours' => $presence->total_hours,
            'time' => $checkOut->format('H:i:s'),
        ]);

        return $presence;
    }

    public function delete(Presence $presence, ?\App\Models\User $actor = null): void
    {
        if ($presence->employee) {
            $this->logger->record(PresenceLog::ACTION_DELETED, PresenceLog::OUTCOME_SUCCESS, $presence->employee, null, $presence, 'deleted_by_staff', [
                'date' => (string) $presence->date,
                'check_in' => $presence->check_in ? (string) $presence->check_in : null,
                'check_out' => $presence->check_out ? (string) $presence->check_out : null,
                'status' => $presence->status?->value,
            ], $actor);
        }

        $presence->delete();
    }

    /**
     * Journal du pointage pour l'équipe : l'admin voit tout, un manager uniquement son département.
     *
     * @throws AuthorizationException si le manager n'a pas de département
     */
    public function logsForStaff(\App\Models\User $user, Request $request)
    {
        $query = PresenceLog::with(['employee:id,first_name,last_name', 'user:id,name,role']);

        if (!$user->isAdmin()) {
            $deptId = $user->employee->department_id ?? null;
            if (!$deptId) {
                throw new AuthorizationException("Vous n'avez pas de département assigné.");
            }
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('outcome')) {
            $query->where('outcome', $request->outcome);
        }
        if ($request->filled('date')) {
            $query->whereDate('occurred_at', $request->date);
        }

        return $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate(30);
    }

    /**
     * Lignes de la fiche de présence (export PDF), triées par date puis par employé.
     * Plafonné à 5000 lignes ; `truncated` indique si la période en contient davantage.
     */
    public function exportRows(?string $from, ?string $to): array
    {
        $limit = 5000;

        $query = Presence::with(['employee:id,first_name,last_name,department_id', 'employee.department:id,name'])
            ->orderBy('date')
            ->orderBy('id');

        if ($from) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date', '<=', $to);
        }

        $presences = $query->limit($limit + 1)->get();
        $truncated = $presences->count() > $limit;

        $rows = $presences->take($limit)->map(fn (Presence $p) => [
            'employee' => trim(($p->employee->first_name ?? '') . ' ' . ($p->employee->last_name ?? '')),
            'department' => $p->employee?->department?->name,
            'date' => Carbon::parse($p->date)->toDateString(),
            'check_in' => $p->check_in ? Carbon::parse($p->check_in)->format('H:i') : null,
            'check_out' => $p->check_out ? Carbon::parse($p->check_out)->format('H:i') : null,
            'total_hours' => $p->total_hours !== null ? (float) $p->total_hours : null,
            'status' => $p->status?->value ?? 'present',
        ])->values()->all();

        return ['rows' => $rows, 'truncated' => $truncated];
    }

    /**
     * Génère le flux CSV d'export (BOM UTF-8, séparateur ';' pour compatibilité WPS/Excel FR).
     */
    public function exportCsvCallback(): \Closure
    {
        $presences = Presence::with('employee:id,first_name,last_name')->latest()->get();

        return function () use ($presences) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['ID', 'Employé', 'Date', 'Arrivée', 'Sortie', 'Heures Totales', 'Statut'], ';');

            foreach ($presences as $presence) {
                fputcsv($file, [
                    $presence->id,
                    trim(($presence->employee->first_name ?? '') . ' ' . ($presence->employee->last_name ?? '')),
                    $presence->date,
                    $presence->check_in ? date('H:i', strtotime($presence->check_in)) : '--:--',
                    $presence->check_out ? date('H:i', strtotime($presence->check_out)) : '--:--',
                    str_replace('.', ',', (string) ($presence->total_hours ?? '0')),
                    $presence->status?->label() ?? '',
                ], ';');
            }

            fclose($file);
        };
    }
}
