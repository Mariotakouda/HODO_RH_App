<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le modèle Presence, PresenceService::checkIn() et DashboardService lisent/écrivent
 * presences.status, mais la table créée en 2025_10_19 n'a jamais eu cette colonne :
 * le pointage échouait avec "table presences has no column named status".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->string('status')->default('present')->after('total_hours');
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
