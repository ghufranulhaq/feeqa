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
        // FR-006-14 through FR-006-17: one row per rung of either ladder
        // ever applied. `subject` covers both a Business (FR-006-14) and a
        // User (FR-006-15) with one table, rather than two near-identical
        // ones. `ladder` is stored alongside `step` even though the
        // subject type already implies it, so a query never has to load
        // the subject just to know which sequence a row belongs to.
        Schema::create('enforcement_actions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('ladder');
            $table->string('step');
            $table->string('reason_code');
            $table->foreignId('applied_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('applied_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->text('lift_reason')->nullable();
            $table->foreignId('senior_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'lifted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enforcement_actions');
    }
};
