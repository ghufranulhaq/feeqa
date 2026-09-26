<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FR-003-19: "one reminder... when a window opens." Tracks which
        // (review, milestone) pairs have already had their reminder sent,
        // so re-running the daily command the same day never double-sends.
        Schema::create('review_lifecycle_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('milestone');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['review_id', 'milestone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_lifecycle_reminders');
    }
};
