<?php

namespace App\Services;

use App\Mail\EmployeeInvitationEmail;
use App\Models\Employee;
use App\Models\EmployeeDepartmentHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class EmployeeService
{
    /**
     * Liste des employés visibles par un manager : uniquement son département,
     * sans lui-même. Les admins passent par listAll().
     */
    public function listForManager(Employee $managerEmployee)
    {
        return Employee::active()->with('department')
            ->where('department_id', $managerEmployee->department_id)
            ->where('id', '!=', $managerEmployee->id)
            ->orderBy('last_name')
            ->get();
    }

    public function listAll()
    {
        return Employee::active()->with('department')->orderBy('last_name')->get();
    }

    /**
     * Anciens employés (démission, renvoi, retraite), les plus récents d'abord.
     */
    public function listArchived()
    {
        return Employee::archived()->with('department')->orderByDesc('archived_at')->get();
    }

    public function create(array $validated): Employee
    {
        [$employee, $user] = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name'     => $validated['first_name'] . ' ' . $validated['last_name'],
                'email'    => $validated['email'],
                // Mot de passe aléatoire que personne ne connaît : l'employé choisit le sien
                // via le lien d'invitation. Aucun mot de passe ne circule par email.
                'password' => Hash::make(Str::random(40)),
                'role'     => 'employee',
                'needs_password_change' => true,
            ]);

            $employee = Employee::create(array_merge($validated, [
                'user_id' => $user->id,
            ]));

            if (!empty($validated['role_ids'])) {
                $employee->roles()->sync($validated['role_ids']);
            }

            return [$employee, $user];
        });

        // Envoi hors transaction et sans file d'attente : un échec SMTP ne doit pas annuler
        // la création, et l'admin est prévenu pour utiliser « Renvoyer l'invitation ».
        $employee->setAttribute('invitation_sent', $this->sendInvitation($user));

        return $employee->load(['department', 'roles']);
    }

    /**
     * Génère un lien d'invitation à usage unique (72 h) et l'envoie par email.
     */
    private function sendInvitation(User $user): bool
    {
        try {
            $token = Password::broker('invitations')->createToken($user);
            $url = config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
            $hours = (int) round(config('auth.passwords.invitations.expire') / 60);

            Mail::to($user->email)->send(new EmployeeInvitationEmail($user->name, $url, $hours));

            return true;
        } catch (Throwable $e) {
            Log::error("Envoi de l'invitation impossible pour {$user->email} : " . $e->getMessage());

            return false;
        }
    }

    /**
     * Renvoie une invitation (lien expiré, email perdu...). L'ancien mot de passe est
     * invalidé, y compris un éventuel mot de passe temporaire envoyé par l'ancienne version.
     *
     * @throws \RuntimeException
     */
    public function resendInvitation(Employee $employee): bool
    {
        $user = $employee->user;

        if (!$user) {
            throw new \RuntimeException('Aucun compte utilisateur lié à cet employé.');
        }
        if ($employee->isArchived()) {
            throw new \RuntimeException('Cet employé est archivé.');
        }
        if (!$user->needs_password_change) {
            throw new \RuntimeException('Cet employé a déjà activé son compte.');
        }

        $user->forceFill(['password' => Hash::make(Str::random(40))])->save();
        $user->tokens()->delete();

        return $this->sendInvitation($user);
    }

    public function update(Employee $employee, array $validated): Employee
    {
        $oldDepartmentId = $employee->department_id;
        $newDepartmentId = array_key_exists('department_id', $validated) ? $validated['department_id'] : $oldDepartmentId;
        $departmentChanged = (int) $newDepartmentId !== (int) $oldDepartmentId;

        if ($departmentChanged && $employee->manager()->exists()) {
            throw new \RuntimeException("Cet employé est manager : modifiez son département depuis la gestion des managers.");
        }

        // Passer le statut à démission / renvoi / retraite = archiver ; repasser à « actif » = réactiver.
        $newStatus = $validated['status'] ?? null;
        $leaving = $newStatus !== null && $newStatus !== 'actif' && !$employee->isArchived();
        $returning = $newStatus === 'actif' && $employee->isArchived();

        if ($leaving && $employee->manager()->exists()) {
            throw new \RuntimeException("Cet employé est manager : retirez-le d'abord de ses fonctions de manager avant de l'archiver.");
        }

        return DB::transaction(function () use ($employee, $validated, $departmentChanged, $oldDepartmentId, $newDepartmentId, $leaving, $returning, $newStatus) {
            $user = $employee->user;
            if ($user) {
                if (isset($validated['email'])) {
                    $user->email = $validated['email'];
                }
                if (isset($validated['first_name']) || isset($validated['last_name'])) {
                    $user->name = ($validated['first_name'] ?? $employee->first_name) . ' ' . ($validated['last_name'] ?? $employee->last_name);
                }
                if ($user->isDirty()) {
                    $user->save();
                }
            }

            $employee->update($validated);

            if ($leaving) {
                $this->archive($employee, ['status' => $newStatus, 'departure_date' => now()->toDateString(), 'departure_reason' => null]);
            } elseif ($returning) {
                $this->restore($employee);
            }

            if ($departmentChanged) {
                $this->recordTransfer($employee, $oldDepartmentId, $newDepartmentId, null, null);
            }

            if (isset($validated['role_ids'])) {
                $employee->roles()->sync($validated['role_ids']);
            }

            return $employee->load(['department', 'roles']);
        });
    }

    /**
     * Affecte un employé à un autre département et garde la trace du mouvement.
     *
     * @throws \RuntimeException
     */
    public function transfer(Employee $employee, int $toDepartmentId, ?string $reason, ?string $effectiveDate): Employee
    {
        if ($employee->isArchived()) {
            throw new \RuntimeException('Impossible de transférer un employé archivé.');
        }
        if ((int) $employee->department_id === $toDepartmentId) {
            throw new \RuntimeException("L'employé appartient déjà à ce département.");
        }
        if ($employee->manager()->exists()) {
            throw new \RuntimeException("Cet employé est manager : retirez-le d'abord de ses fonctions de manager avant de le transférer.");
        }

        $from = $employee->department_id;

        DB::transaction(function () use ($employee, $from, $toDepartmentId, $reason, $effectiveDate) {
            $employee->update(['department_id' => $toDepartmentId]);
            $this->recordTransfer($employee, $from, $toDepartmentId, $reason, $effectiveDate);
        });

        Log::info('Transfert employé', [
            'employee_id' => $employee->id,
            'from' => $from,
            'to' => $toDepartmentId,
            'by' => auth()->id(),
        ]);

        return $employee->load(['department', 'roles']);
    }

    private function recordTransfer(Employee $employee, $from, $to, ?string $reason, ?string $effectiveDate): void
    {
        EmployeeDepartmentHistory::create([
            'employee_id' => $employee->id,
            'from_department_id' => $from ?: null,
            'to_department_id' => $to ?: null,
            'transferred_by' => auth()->id(),
            'reason' => $reason,
            'effective_date' => $effectiveDate ?: now()->toDateString(),
        ]);
    }

    /**
     * Archive un ancien employé : il garde son historique (tâches, présences, congés,
     * dossier) mais ne peut plus se connecter et disparaît des listes courantes.
     *
     * @throws \RuntimeException
     */
    public function archive(Employee $employee, array $data): Employee
    {
        if ($employee->isArchived()) {
            throw new \RuntimeException('Cet employé est déjà archivé.');
        }
        if ($employee->manager()->exists()) {
            throw new \RuntimeException("Cet employé est manager : retirez-le d'abord de ses fonctions de manager avant de l'archiver.");
        }

        DB::transaction(function () use ($employee, $data) {
            $employee->update([
                'status' => $data['status'],
                'archived_at' => now(),
                'archived_by' => auth()->id(),
                'departure_date' => $data['departure_date'],
                'departure_reason' => $data['departure_reason'] ?? null,
            ]);

            // Coupe toutes les sessions ouvertes
            $employee->user?->tokens()->delete();
        });

        Log::info('Employé archivé', ['employee_id' => $employee->id, 'status' => $data['status'], 'by' => auth()->id()]);

        return $employee->fresh(['department', 'roles']);
    }

    /**
     * @throws \RuntimeException
     */
    public function restore(Employee $employee): Employee
    {
        if (!$employee->isArchived()) {
            throw new \RuntimeException("Cet employé n'est pas archivé.");
        }

        $employee->update([
            'status' => 'actif',
            'archived_at' => null,
            'archived_by' => null,
            'departure_date' => null,
            'departure_reason' => null,
        ]);

        Log::info('Employé restauré', ['employee_id' => $employee->id, 'by' => auth()->id()]);

        return $employee->fresh(['department', 'roles']);
    }

    public function delete(Employee $employee): void
    {
        if ($employee->profile_photo) {
            Storage::disk('public')->delete($employee->profile_photo);
        }

        if ($employee->user) {
            $employee->user()->delete(); // cascade sur employees via la contrainte FK
        } else {
            $employee->delete();
        }
    }

    public function uploadProfilePhoto(Employee $employee, \Illuminate\Http\UploadedFile $file): Employee
    {
        if ($employee->profile_photo) {
            Storage::disk('public')->delete($employee->profile_photo);
        }

        $employee->profile_photo = $file->store('profile_photos', 'public');
        $employee->save();

        return $employee->fresh(['department', 'roles']);
    }

    public function deleteProfilePhoto(Employee $employee): Employee
    {
        if ($employee->profile_photo) {
            Storage::disk('public')->delete($employee->profile_photo);
            $employee->profile_photo = null;
            $employee->save();
        }

        return $employee->fresh(['department', 'roles']);
    }

    /**
     * Résumé pour le tableau de bord personnel (5 dernières entrées par module).
     */
    public function dashboardSummary(Employee $employee): array
    {
        $today = now('Africa/Lome')->toDateString();
        $since = now('Africa/Lome')->subDays(30)->toDateString();

        $presences30 = fn () => $employee->presences()->whereDate('date', '>=', $since);

        return [
            // Indicateurs du dashboard moderne (les clés historiques ci-dessous sont inchangées)
            'stats' => [
                'tasks_open' => $employee->tasks()
                    ->whereIn('status', [\App\Enums\TaskStatus::Pending->value, \App\Enums\TaskStatus::InProgress->value])
                    ->count(),
                'tasks_completed' => $employee->tasks()->where('status', \App\Enums\TaskStatus::Completed->value)->count(),
                'leave_pending' => $employee->leaveRequests()->where('status', \App\Enums\LeaveRequestStatus::Pending->value)->count(),
                'days_present_30' => $presences30()->whereIn('status', ['present', 'late'])->count(),
                'days_late_30' => $presences30()->where('status', 'late')->count(),
            ],
            // Pointage du jour (null si l'employé n'a pas encore pointé)
            'today' => $employee->presences()
                ->whereDate('date', $today)
                ->select('id', 'date', 'check_in', 'check_out', 'status')
                ->first(),
            'presences' => $employee->presences()
                ->select('id', 'date', 'status')
                ->latest()
                ->take(5)
                ->get(),
            'tasks' => $employee->tasks()
                ->select('id', 'title', 'due_date', 'status')
                ->latest()
                ->take(5)
                ->get(),
            'leave_requests' => $employee->leaveRequests()
                ->select('id', 'start_date', 'end_date', 'status')
                ->latest()
                ->take(5)
                ->get(),
            'announcements' => \App\Models\Announcement::where('is_general', true)
                ->orWhere('department_id', $employee->department_id)
                ->select('id', 'title', 'created_at', 'is_general')
                ->latest()
                ->take(5)
                ->get(),
        ];
    }
}
