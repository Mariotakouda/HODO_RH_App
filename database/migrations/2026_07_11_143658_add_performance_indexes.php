<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les index manquants sur les colonnes de clé étrangère et de filtrage
 * fréquent. Avec SQLite (utilisé en dev/prod ici), foreignId()->constrained()
 * ne crée PAS automatiquement un index comme le fait MySQL/InnoDB : chaque
 * where('employee_id', ...) / where('status', ...) faisait donc un scan complet
 * de la table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index('department_id');
            $table->index('status');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('employee_id');
            $table->index('creator_id');
            $table->index('status');
            $table->index(['employee_id', 'status']);
        });

        Schema::table('presences', function (Blueprint $table) {
            $table->index('employee_id');
            $table->index('date');
            $table->index(['employee_id', 'date']);
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->index('employee_id');
            $table->index('status');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->index('department_id');
            $table->index('employee_id');
            $table->index('is_general');
        });

        Schema::table('employee_role', function (Blueprint $table) {
            $table->index('employee_id');
            $table->index('role_id');
        });

        Schema::table('managers', function (Blueprint $table) {
            $table->index('employee_id');
            $table->index('department_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['department_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['creator_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['employee_id', 'status']);
        });

        Schema::table('presences', function (Blueprint $table) {
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['date']);
            $table->dropIndex(['employee_id', 'date']);
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['department_id']);
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['is_general']);
        });

        Schema::table('employee_role', function (Blueprint $table) {
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['role_id']);
        });

        Schema::table('managers', function (Blueprint $table) {
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['department_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });
    }
};
