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
        // FR-005-15, FR-005-17: `business_id` null means a platform-wide
        // suppression ("from any business"). Permanent until the recipient
        // reverses it — there is no expiry column by design.
        Schema::create('invitation_suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('recipient_email_hash');
            $table->string('reason');
            $table->timestamps();

            $table->unique(['business_id', 'recipient_email_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_suppressions');
    }
};
