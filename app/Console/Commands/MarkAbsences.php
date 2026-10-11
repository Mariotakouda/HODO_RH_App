<?php

namespace App\Console\Commands;

use App\Enums\LeaveRequestStatus;
use App\Enums\PresenceStatus;
use App\Models\Employee;
use App\Models\Presence;
use Illuminate\Console\Command;

class MarkAbsences extends Command
{
    protected $signature = 'app:mark-absences';

    protected $description = "Marque absents les employés actifs sans pointage aujourd'hui (jours ouvrés, hors congés approuvés)";

    public function handle(): int
    {
        $now = now();
        $today = $now->toDateString();

        // Pas d'absence automatique les jours non travaillés (config/hodo.php)
        if (in_array($now->dayOfWeekIso, config('hodo.non_working_days', []), true)) {
            $this->info('Jour non travaillé : aucune absence enregistrée.');
            return self::SUCCESS;
        }

        $count = 0;

        Employee::active()
            ->whereDoesntHave('presences', fn ($q) => $q->whereDate('date', $today))
            ->whereDoesntHave('leaveRequests', fn ($q) => $q
                ->where('status', LeaveRequestStatus::Approved->value)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today))
            ->each(function (Employee $employee) use ($today, &$count) {
                Presence::create([
                    'employee_id' => $employee->id,
                    'date' => $today,
                    'status' => PresenceStatus::Absent,
                    'check_in' => null,
                    'check_out' => null,
                ]);
                $count++;
            });

        $this->info("{$count} absence(s) enregistrée(s).");

        return self::SUCCESS;
    }
}
