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
        // FR-006-11: the held-content queue's own assignment column —
        // `flags` and `moderation_incidents` already have theirs, from T4
        // and T3 respectively.
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('durability_signal')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
        });
    }
};
