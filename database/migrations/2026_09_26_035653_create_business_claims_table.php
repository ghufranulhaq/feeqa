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
        // FR-002-11 through FR-002-15. One row per attempt (including a
        // re-claim request on an already-claimed business, FR-002-14 —
        // `status` gets an extra couple of states for that case beyond a
        // plain pending/approved/rejected).
        Schema::create('business_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->string('status')->default('pending');

            // Method (a): email.
            $table->string('target')->nullable();
            $table->string('verification_code')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('code_expires_at')->nullable();

            // Methods (b)/(c): DNS TXT / HTML file or meta tag.
            $table->string('verification_token')->nullable();

            // FR-002-14: an existing Owner gets 7 days to respond.
            $table->timestamp('owner_response_deadline')->nullable();

            // Method (d): manual staff review.
            $table->json('documents')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_claims');
    }
};
