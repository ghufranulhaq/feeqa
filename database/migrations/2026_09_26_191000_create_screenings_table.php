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
        // FR-006-03, FR-006-04: one immutable record per screened piece of
        // content (a review or a lifecycle update today — see 006 T2's
        // own notes on replies/case messages/media not existing yet),
        // feeding the weekly audit sample (T8) and the transparency report
        // (T9). `screenable` covers every content type this spec screens,
        // present and future, without a new table per type.
        Schema::create('screenings', function (Blueprint $table) {
            $table->id();
            $table->morphs('screenable');
            $table->string('recommendation');
            $table->decimal('risk_score', 3, 2);
            $table->json('triggered_rules');
            $table->json('signals');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screenings');
    }
};
