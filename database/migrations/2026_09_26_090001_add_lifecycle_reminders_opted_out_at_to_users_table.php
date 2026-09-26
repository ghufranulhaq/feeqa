<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FR-003-19: "unless they have opted out" — same "_at" opt-out
        // convention as users.deletion_requested_at.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('lifecycle_reminders_opted_out_at')->nullable()->after('date_of_birth_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('lifecycle_reminders_opted_out_at');
        });
    }
};
