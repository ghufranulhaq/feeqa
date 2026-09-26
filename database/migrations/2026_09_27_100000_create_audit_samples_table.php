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
        // FR-006-20: one row per sampled `Screening`. `correct` is
        // nullable — null is "pending a staff decision", true/false is
        // that decision — rather than a separate status column, since
        // there's nothing else a decided row can be. `screening_id` is
        // unique: `AuditScreeningSample` uses `firstOrCreate` so a
        // screening already sampled (this run or an earlier one) is
        // never sampled twice.
        Schema::create('audit_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screening_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('correct')->nullable();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_samples');
    }
};
