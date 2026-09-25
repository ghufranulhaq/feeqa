<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // FR-001-14. Not modelled through spatie/laravel-permission like
        // business roles (T14): teams mode makes team_foreign_key part of
        // model_has_roles' primary key and NOT NULL, so there is no clean
        // way to record a role assignment with no team — every row needs
        // *some* business_id. Staff roles aren't scoped to a business at
        // all, and a person holds at most one, so a plain nullable column
        // is both simpler and avoids fighting that constraint.
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_role')->nullable()->after('date_of_birth_confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('staff_role');
        });
    }
};
