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
        // FR-005-13, FR-005-14: one editable template per Business per
        // locale. `body` must be checked by GuardNeutralTemplate before
        // this row is written or updated (FR-005-13) — there is no
        // unchecked-draft state.
        Schema::create('invitation_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->default('en-GB');
            $table->string('subject');
            $table->text('body');
            $table->string('sender_name')->nullable();
            $table->string('reply_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_templates');
    }
};
