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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('slug')->unique();
            // Localised names (constitution §5.5): {"en-GB": "Airlines"}.
            // Only en-GB is populated at launch, but the column already
            // supports adding a locale without a schema change.
            $table->json('name');
            $table->string('icon')->nullable();
            // FR-002-18/FR-002-22: whether this category (any depth) shows
            // in navigation and category rankings.
            $table->boolean('launched')->default(false);
            // FR-002-30: the richer draft/launched/paused lifecycle, which
            // only applies to a top-level category (an "industry").
            // Null for every non-top-level category.
            $table->string('state')->nullable();
            // FR-002-29: the "Other / Uncategorised" fallback — protected
            // from deletion/merge (T12) and never itself launchable.
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
