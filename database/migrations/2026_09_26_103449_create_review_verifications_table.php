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
        // FR-004-01 through FR-004-11: one verification attempt for one
        // review. `proof_fingerprint` is unique (nullable — Postgres
        // allows many NULLs) so a fingerprint can never be attached to a
        // second row (FR-004-10, FR-004-11's reuse rejection).
        Schema::create('review_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->string('status')->default('pending');
            $table->foreignId('business_verification_request_id')->nullable()
                ->constrained('business_verification_requests')->nullOnDelete();
            $table->string('reference_number')->nullable();
            $table->json('extracted_fields')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('proof_fingerprint')->nullable()->unique();
            $table->json('proof_paths')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason_code')->nullable();
            $table->timestamp('proof_deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_verifications');
    }
};
