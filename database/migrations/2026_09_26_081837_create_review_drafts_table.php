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
        // FR-003-10: server-side draft storage for signed-in users, one
        // draft per user per business (or per user per business+location).
        // No unique index on the (user_id, business_id, location_id) tuple:
        // Postgres treats NULLs as distinct, so it couldn't enforce
        // "one business-only draft" anyway — the save action looks the
        // existing row up itself and updates it in place.
        Schema::create('review_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_drafts');
    }
};
