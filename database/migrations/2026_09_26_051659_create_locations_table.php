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
        // FR-002-16: a branch of a claimed Business, reviewable and
        // scored separately (score itself is spec 008 — not here yet).
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Unique per business, not globally — the URL is always
            // scoped under the business's own slug.
            $table->string('slug');
            $table->json('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone')->nullable();
            // {"mon": {"open": "09:00", "close": "17:00"}, ...}
            $table->json('hours')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
