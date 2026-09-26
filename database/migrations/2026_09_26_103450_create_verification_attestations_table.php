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
        // FR-004-14 through FR-004-17: the signed, publicly-checkable
        // record behind a Verified Experience badge. UUID primary key so
        // the public check page can't enumerate attestations by guessing
        // sequential IDs (edge case: "unknown ID → 404, no signal about
        // whether it ever existed").
        Schema::create('verification_attestations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verification_id')->constrained('review_verifications')->cascadeOnDelete();
            $table->string('method');
            $table->date('experience_month');
            $table->timestamp('decision_time');
            $table->unsignedInteger('methodology_version');
            $table->text('jws');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoked_reason_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_attestations');
    }
};
