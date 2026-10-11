<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Presence;
use App\Models\PresenceLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Journal du pointage : chaque tentative est écrite dans la table presence_logs
 * (consultable par l'équipe RH) ET dans storage/logs/presence-*.log (trace technique).
 * Un échec de journalisation ne doit jamais bloquer un pointage.
 */
class PresenceLogService
{
    public function record(
        string $action,
        string $outcome,
        Employee $employee,
        ?Request $request = null,
        ?Presence $presence = null,
        ?string $reason = null,
        array $meta = [],
        ?User $actor = null,
    ): void {
        $actor = $actor ?? $request?->user();
        $ip = $request?->ip();
        $userAgent = $request?->userAgent() ? mb_substr($request->userAgent(), 0, 255) : null;

        try {
            PresenceLog::create([
                'presence_id' => $presence?->id,
                'employee_id' => $employee->id,
                'user_id' => $actor?->id,
                'action' => $action,
                'outcome' => $outcome,
                'reason' => $reason,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'meta' => $meta ?: null,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Journal de pointage (base) impossible : ' . $e->getMessage());
        }

        try {
            Log::channel('presence')->info("{$action} {$outcome}", [
                'employee_id' => $employee->id,
                'user_id' => $actor?->id,
                'presence_id' => $presence?->id,
                'reason' => $reason,
                'ip' => $ip,
                'meta' => $meta,
            ]);
        } catch (Throwable $e) {
            Log::error('Journal de pointage (fichier) impossible : ' . $e->getMessage());
        }
    }
}
