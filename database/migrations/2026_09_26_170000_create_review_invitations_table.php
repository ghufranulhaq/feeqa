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
        // FR-005-01 through FR-005-12: one invitation, whichever method
        // produced it. Recipient email and reference are stored encrypted
        // (Laravel's `encrypted` cast); the `_hash` columns are keyed HMACs
        // (App\Domain\Verification\TransactionRecordHash) used for lookups
        // an encrypted column can't support: the 30-day recipient rule, the
        // one-per-reference rule, idempotent API replay, and suppression
        // matching.
        Schema::create('review_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('invitation_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('review_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method');
            $table->string('status')->default('queued');
            $table->text('recipient_email')->nullable();
            $table->string('recipient_email_hash')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('locale')->default('en-GB');
            $table->text('reference')->nullable();
            $table->string('reference_hash')->nullable();
            $table->json('product_skus')->nullable();
            $table->string('token')->unique();
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('queued_reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['business_id', 'recipient_email_hash']);
            $table->index(['business_id', 'reference_hash']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_invitations');
    }
};
