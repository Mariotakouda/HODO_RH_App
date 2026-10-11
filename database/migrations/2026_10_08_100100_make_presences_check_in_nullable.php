<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La commande app:mark-absences crée des lignes « absent » sans heure d'arrivée,
 * mais presences.check_in était NOT NULL : l'insertion échouait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dateTime('check_in')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dateTime('check_in')->nullable(false)->change();
        });
    }
};
