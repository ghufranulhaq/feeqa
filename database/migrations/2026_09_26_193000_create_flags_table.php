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
        // FR-006-07 through FR-006-10: notice-and-action. `flaggable`
        // covers every content type this spec can flag, present (reviews)
        // and future, same shape as `screenings.screenable`. `business_id`
        // isn't in the spec's own column sketch (tasks.md T4) but is added
        // here as a documented, necessary addition: FR-006-10's per-
        // business open-flag cap and reject-rate check need to identify
        // "which business filed this flag" directly, rather than via a
        // fragile polymorphic join through whatever `flaggable` happens to
        // be. It's only ever set when `is_business_flag` is true.
        Schema::create('flags', function (Blueprint $table) {
            $table->id();
            $table->morphs('flaggable');
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_email')->nullable();
            $table->string('reason_code');
            $table->text('details')->nullable();
            $table->json('evidence_paths')->nullable();
            $table->string('status')->default('open');
            $table->boolean('is_business_flag')->default(false);
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flags');
    }
};
