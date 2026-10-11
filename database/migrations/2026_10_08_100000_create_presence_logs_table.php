<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit du pointage : chaque tentative (réussie ou refusée) est tracée
 * avec l'auteur, l'IP et le navigateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presence_id')->nullable()->constrained('presences')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);            // check_in | check_out | deleted
            $table->string('outcome', 20);           // success | rejected
            $table->string('reason', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('meta')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['employee_id', 'occurred_at']);
            $table->index(['action', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_logs');
    }
};
