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
        // FR-006-21(e), FR-006-22: one row per quarter. `figures` holds
        // every number FR-006-21(e) lists — nothing here is hand-entered
        // except the one figure `ComputeTransparencyReport` has no source
        // table for yet (legal/government requests), which lives inside
        // `figures` too rather than getting its own column, so the whole
        // report stays one reproducible unit.
        Schema::create('transparency_reports', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('figures');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transparency_reports');
    }
};
