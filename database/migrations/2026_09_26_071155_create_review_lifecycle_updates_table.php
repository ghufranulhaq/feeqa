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
        // FR-003-17 to FR-003-22: up to three dated follow-ups per review,
        // one per milestone. Never rewritten — a new update is a new row.
        Schema::create('review_lifecycle_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('milestone');
            $table->string('status');
            $table->unsignedTinyInteger('star_rating');
            $table->text('text');
            $table->json('answers')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->unique(['review_id', 'milestone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_lifecycle_updates');
    }
};
