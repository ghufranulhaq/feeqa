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
        // FR-006-18, FR-006-19: `appealable` covers `EnforcementAction`,
        // `Flag`, and `Review` — the "enforcement_action or moderation
        // decision" tasks.md T7 names. `appellant_id` is nullable
        // (`nullOnDelete`) rather than cascading, same choice `flags.
        // reporter_id` already made: an appeal's outcome and reason stay
        // on record after the appellant's account is erased.
        Schema::create('appeals', function (Blueprint $table) {
            $table->id();
            $table->morphs('appealable');
            $table->foreignId('appellant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('statement');
            $table->json('evidence_paths')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appeals');
    }
};
